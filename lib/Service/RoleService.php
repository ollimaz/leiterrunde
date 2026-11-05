<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Module\ModuleRegistry;
use OCP\IAppConfig;

/**
 * Konfigurierbare Rollen: role_groups {roleId:[gid,...]}, role_caps {capId:[roleId,...]}.
 */
class RoleService {
	public const ROLES = [
		'leiter' => 'Leaders',
		'vorstand' => 'Board',
		'kasse' => 'Treasurer',
		'schulung' => 'Training officers',
		'fz' => 'Conduct certificate access',
		'admin' => 'Leiterrunde admin',
	];

	public const DEFAULT_GROUPS = [
		'leiter' => 'leiterrunde_leiter',
		'vorstand' => 'leiterrunde_vorstand',
		'kasse' => 'leiterrunde_kasse',
		'schulung' => 'leiterrunde_schulung',
		'fz' => 'leiterrunde_fz',
		'admin' => 'leiterrunde_admin',
	];

	public function __construct(
		private IAppConfig $appConfig,
		private ModuleRegistry $modules,
		private \OCP\IGroupManager $groupManager,
	) {
	}

	/** @return array<string,string[]> */
	public function roleGroups(): array {
		$raw = $this->appConfig->getValueString(Application::APP_ID, 'role_groups', '');
		$map = $raw !== '' ? (json_decode($raw, true) ?: []) : [];
		foreach (self::DEFAULT_GROUPS as $role => $gid) {
			$map[$role] ??= [$gid];
		}
		return $map;
	}

	/** @return array<string,string[]> capId => roleIds (defaults from CapabilityDefinition) */
	public function capRoles(): array {
		$raw = $this->appConfig->getValueString(Application::APP_ID, 'role_caps', '');
		$saved = $raw !== '' ? (json_decode($raw, true) ?: []) : [];
		$out = [];
		foreach ($this->modules->capabilities() as $cap) {
			$out[$cap->id] = $saved[$cap->id] ?? $cap->defaultRoles;
			if ($cap->lockedToRoles !== null) {
				$out[$cap->id] = $cap->lockedToRoles; // locked
			}
		}
		return $out;
	}

	/** @return string[] uids' role ids for a user (by group membership), excluding pseudo 'any'/'nca' */
	public function userRoles(array $groupIds): array {
		$groups = $this->roleGroups();
		$roles = [];
		foreach ($groups as $role => $gids) {
			if (array_intersect($groupIds, $gids) !== []) {
				$roles[] = $role;
			}
		}
		return $roles;
	}

	public function save(array $roleGroups, array $roleCaps): void {
		foreach (array_keys($roleGroups) as $r) {
			if (!isset(self::ROLES[$r])) {
				throw new \InvalidArgumentException('Unknown role: ' . $r);
			}
			foreach ((array)$roleGroups[$r] as $gid) {
				if (!$this->groupManager->groupExists((string)$gid)) {
					throw new \InvalidArgumentException('Group does not exist: ' . $gid);
				}
			}
		}
		$capDefaults = [];
		foreach ($this->modules->capabilities() as $c) {
			$capDefaults[$c->id] = $c->defaultRoles;
		}
		foreach ($roleCaps as $capId => $roles) {
			foreach ((array)$roles as $r) {
				if ($r === 'any') {
					continue;
				}
				if ($r === 'nca') {
					if (!in_array('nca', $capDefaults[$capId] ?? [], true)) {
						throw new \InvalidArgumentException('Role nca is not allowed for ' . $capId);
					}
					continue;
				}
				if (!isset(self::ROLES[$r])) {
					throw new \InvalidArgumentException('Unknown role: ' . $r);
				}
			}
		}
		$locked = [];
		foreach ($this->modules->capabilities() as $cap) {
			if ($cap->lockedToRoles !== null) {
				$locked[$cap->id] = $cap->lockedToRoles;
			}
		}
		foreach ($roleCaps as $capId => $roles) {
			if (isset($locked[$capId]) && $roles !== $locked[$capId]) {
				throw new \InvalidArgumentException('Permission ' . $capId . ' is locked to the fz role');
			}
		}
		$this->appConfig->setValueString(Application::APP_ID, 'role_groups', json_encode($roleGroups));
		$this->appConfig->setValueString(Application::APP_ID, 'role_caps', json_encode($roleCaps));
	}
}
