<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Module\ModuleRegistry;
use OCA\Leiterrunde\Service\DocumentStorageService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\Leiterrunde\Service\RoleService;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCA\Leiterrunde\Service\SettingsService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class SettingsController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private SettingsService $settings,
		private RoleService $roles,
		private ModuleRegistry $modules,
		private DocumentStorageService $docs,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			return $this->settings->exportForUi();
		});
	}

	#[NoAdminRequired]
	public function update(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			$params = $this->request->getParams();
			unset($params['_route'], $params['id']);
			$this->settings->update($params);
			return $this->settings->exportForUi();
		});
	}

	#[NoAdminRequired]
	public function regenerateSecret(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			return ['shared_secret' => $this->settings->regenerateSharedSecret()];
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function roles(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			return [
				'roles' => RoleService::ROLES,
				'role_groups' => $this->roles->roleGroups(),
				'role_caps' => $this->roles->capRoles(),
				'capabilities' => array_map(static fn ($c) => ['id' => $c->id, 'label' => $c->label, 'group' => $c->group, 'locked' => $c->lockedToRoles], $this->modules->capabilities()),
				'warnings' => $this->roleWarnings(),
			];
		});
	}

	#[NoAdminRequired]
	public function saveRoles(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			$p = $this->request->getParams();
			try {
				$this->roles->save($p['role_groups'] ?? [], $p['role_caps'] ?? []);
			} catch (\InvalidArgumentException $e) {
				throw new ApiException($e->getMessage(), 400);
			}
			return ['ok' => true];
		});
	}

	#[NoAdminRequired]
	public function groups(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			$out = [];
			foreach ($this->groupManager->search('') as $g) {
				$out[] = $g->getGID();
			}
			return $out;
		});
	}

	#[NoAdminRequired]
	public function storage(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			$types = [];
			foreach ($this->docs->docTypes() as $d) {
				$types[] = ['id' => $d->id, 'label' => $d->label, 'path' => $this->docs->pathTemplate($d->id), 'defaultPath' => $d->defaultPath, 'placeholders' => $d->placeholders];
			}
			return ['storage_user' => $this->settings->get('storage_user'), 'types' => $types];
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function storageUserCheck(string $uid): JSONResponse {
		return $this->wrap(function () use ($uid) {
			$this->perm->require('settings.manage');
			$u = $this->userManager->get($uid);
			return ['exists' => $u !== null, 'displayName' => $u?->getDisplayName()];
		});
	}

	#[NoAdminRequired]
	public function saveStorage(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('settings.manage');
			$p = $this->request->getParams();
			$uid = trim((string)($p['storage_user'] ?? ''));
			if ($uid !== '' && $this->userManager->get($uid) === null) {
				throw new ApiException('User does not exist: %s', 422, [$uid]);
			}
			$this->settings->set('storage_user', $uid);
			foreach (($p['paths'] ?? []) as $typeId => $tpl) {
				$this->settings->set('doc_path_' . $typeId, (string)$tpl);
			}
			return ['ok' => true];
		});
	}

	private function roleWarnings(): array {
		$warnings = [];
		$roleGroups = $this->roles->roleGroups();
		foreach (RoleService::ROLES as $role => $label) {
			if (($roleGroups[$role] ?? []) === []) {
				$warnings[] = 'Rolle „' . $label . '" hat keine Gruppe zugeordnet.';
			}
		}
		$fzMembers = [];
		foreach ($roleGroups['fz'] ?? [] as $gid) {
			foreach ($this->perm->groupMembers($gid) as $uid) {
				$fzMembers[$uid] = true;
			}
		}
		if (count($fzMembers) > 1) {
			$warnings[] = 'The role "Conduct certificate access" has more than one member — review data privacy.';
		}
		foreach (array_keys($fzMembers) as $uid) {
			if ($this->perm->isNcAdmin($uid)) {
				$warnings[] = '„' . $uid . '" is Nextcloud admin and has conduct certificate access (hint).';
			}
		}
		return $warnings;
	}
}
