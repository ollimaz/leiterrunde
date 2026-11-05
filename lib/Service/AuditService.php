<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service;

use OCA\Leiterrunde\Db\AuditEntry;
use OCA\Leiterrunde\Db\AuditMapper;
use OCP\IUserSession;

class AuditService {
	public function __construct(
		private AuditMapper $mapper,
		private IUserSession $userSession,
	) {
	}

	/**
	 * Write audit rows for all fields that differ between $before and $after.
	 * $before may be null for creates. Field "_action" can be set in $after for create/delete markers.
	 */
	public function diff(string $entityType, int $entityId, ?array $before, array $after): void {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$now = time();
		if (isset($after['_action'])) {
			$entry = new AuditEntry();
			$entry->setEntityType($entityType);
			$entry->setEntityId($entityId);
			$entry->setField((string)$after['_action']);
			$entry->setUserId($uid);
			$entry->setCreatedAt($now);
			$this->mapper->insert($entry);
			return;
		}
		foreach ($after as $field => $new) {
			$old = $before[$field] ?? null;
			$nv = $new === null ? null : (string)$new;
			$ov = $old === null ? null : (string)$old;
			if ($nv === $ov) {
				continue;
			}
			$entry = new AuditEntry();
			$entry->setEntityType($entityType);
			$entry->setEntityId($entityId);
			$entry->setField((string)$field);
			$entry->setOldValue($ov);
			$entry->setNewValue($nv);
			$entry->setUserId($uid);
			$entry->setCreatedAt($now);
			$this->mapper->insert($entry);
		}
	}
}
