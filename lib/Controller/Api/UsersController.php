<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class UsersController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private IUserManager $userManager,
		private PersonMapper $mapper,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?string $search = null, int $limit = 10): JSONResponse {
		return $this->wrap(function () use ($search, $limit) {
			if (!$this->perm->has('persons.write') && !$this->perm->has('settings.manage')) {
				$this->perm->require('persons.write');
			}
			$q = trim((string)$search);
			if ($q === '') {
				return [];
			}
			$limit = max(1, min($limit, 25));
			$canReadPersons = $this->perm->has('persons.read');
			$out = [];
			foreach ($this->userManager->searchDisplayName($q, $limit) as $user) {
				$linked = null;
				if ($canReadPersons) {
					$p = $this->mapper->findByUserId($user->getUID());
					if ($p !== null) {
						$linked = ['id' => $p->getId(), 'name' => $p->getLastName() . ', ' . $p->getFirstName()];
					}
				}
				$out[] = [
					'id' => $user->getUID(),
					'display_name' => $user->getDisplayName(),
					'email' => $user->getEMailAddress(),
					'linked_person' => $linked,
				];
			}
			return $out;
		});
	}
}
