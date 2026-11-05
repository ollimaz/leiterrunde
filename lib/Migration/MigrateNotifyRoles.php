<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Migration;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Service\RoleService;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Migriert notify_groups_<type> (CSV-Gruppen) nach notify_roles_<type> (JSON-Rollen).
 * Unbekannte Gruppen werden verworfen und geloggt.
 */
class MigrateNotifyRoles implements IRepairStep {
	public function __construct(
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	public function getName(): string {
		return 'Leiterrunde: migrate notification recipients to roles';
	}

	public function run(IOutput $output): void {
		$defaultGroupToRole = array_flip(RoleService::DEFAULT_GROUPS);
		foreach (['training', 'hours', 'finance'] as $type) {
			$oldKey = 'notify_groups_' . $type;
			$newKey = 'notify_roles_' . $type;
			if ($this->appConfig->getValueString(Application::APP_ID, $newKey, '') !== '') {
				continue;
			}
			$old = $this->appConfig->getValueString(Application::APP_ID, $oldKey, '');
			if ($old === '') {
				continue;
			}
			$roles = [];
			foreach (array_filter(array_map('trim', explode(',', $old))) as $gid) {
				if (isset($defaultGroupToRole[$gid])) {
					$roles[] = $defaultGroupToRole[$gid];
				} else {
					$this->logger->warning('Leiterrunde: notify_groups_' . $type . ' group "' . $gid . '" cannot be mapped to a role – dropped');
				}
			}
			if ($roles !== []) {
				$this->appConfig->setValueString(Application::APP_ID, $newKey, json_encode(array_values(array_unique($roles))));
			}
		}
	}
}
