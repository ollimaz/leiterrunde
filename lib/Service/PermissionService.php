<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service;

use OCA\Leiterrunde\Db\Person;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ForbiddenException;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

class PermissionService {
	public const G_LEITER = 'leiterrunde_leiter';
	public const G_VORSTAND = 'leiterrunde_vorstand';
	public const G_KASSE = 'leiterrunde_kasse';
	public const G_SCHULUNG = 'leiterrunde_schulung';
	public const G_FZ = 'leiterrunde_fz';
	public const G_ADMIN = 'leiterrunde_admin';

	public function __construct(
		private IUserSession $userSession,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private PersonMapper $personMapper,
		private RoleService $roles,
	) {
	}

	private function currentUid(): ?string {
		return $this->userSession->getUser()?->getUID();
	}

	/** @return string[] group ids of current user in any mapped role group */
	public function currentLrGroups(): array {
		$uid = $this->currentUid();
		if ($uid === null) {
			return [];
		}
		$user = $this->userManager->get($uid);
		if ($user === null) {
			return [];
		}
		$mapped = array_merge(...array_values($this->roles->roleGroups()));
		return array_values(array_intersect($this->groupManager->getUserGroupIds($user), $mapped));
	}

	public function isNcAdmin(?string $uid = null): bool {
		$uid ??= $this->currentUid();
		return $uid !== null && $this->groupManager->isAdmin($uid);
	}

	public function has(string $cap, ?string $uid = null): bool {
		$uid ??= $this->currentUid();
		if ($uid === null) {
			return false;
		}
		$capRoles = $this->roles->capRoles();
		$allowed = $capRoles[$cap] ?? null;
		if ($allowed === null) {
			return false;
		}
		$isAdmin = $this->groupManager->isAdmin($uid);
		$user = $this->userManager->get($uid);
		$userGroups = $user === null ? [] : $this->groupManager->getUserGroupIds($user);
		$userRoles = $this->roles->userRoles($userGroups);
		foreach ($allowed as $r) {
			if ($r === 'nca' && $isAdmin) {
				return true;
			}
			if ($r === 'any' && $userRoles !== []) {
				return true;
			}
			if ($r !== 'nca' && $r !== 'any' && in_array($r, $userRoles, true)) {
				return true;
			}
		}
		return false;
	}

	/** @throws ForbiddenException */
	public function require(string $cap): void {
		if (!$this->has($cap)) {
			throw new ForbiddenException('No permission');
		}
	}

	/** @return array<string,bool> */
	public function caps(?string $uid = null): array {
		$out = [];
		foreach ($this->roles->capRoles() as $cap => $_) {
			$out[$cap] = $this->has($cap, $uid);
		}
		return $out;
	}

	public function currentPerson(?string $uid = null): ?Person {
		$uid ??= $this->currentUid();
		if ($uid === null) {
			return null;
		}
		$person = $this->personMapper->findByUserId($uid);
		if ($person !== null) {
			return $person;
		}
		$user = $this->userManager->get($uid);
		$email = $user?->getEMailAddress();
		if ($email) {
			return $this->personMapper->findByEmail($email);
		}
		return null;
	}

	public function requireLoggedIn(): IUser {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ForbiddenException('Not logged in');
		}
		return $user;
	}

	/** @return string[] member uids of a group */
	public function groupMembers(string $gid): array {
		$group = $this->groupManager->get($gid);
		if ($group === null) {
			return [];
		}
		return array_map(static fn (IUser $u) => $u->getUID(), $group->getUsers());
	}
}
