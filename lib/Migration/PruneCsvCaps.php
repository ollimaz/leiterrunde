<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Migration;

use OCA\Leiterrunde\AppInfo\Application;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Entfernt die geloeschte persons.export-Berechtigung aus gespeicherten
 * role_caps und beschraenkt persons.import auf die admin-Rolle.
 */
class PruneCsvCaps implements IRepairStep {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function getName(): string {
		return 'Leiterrunde: restrict person CSV import to the admin role, drop removed export capability';
	}

	public function run(IOutput $output): void {
		$raw = $this->appConfig->getValueString(Application::APP_ID, 'role_caps', '');
		if ($raw === '') {
			return;
		}
		$caps = json_decode($raw, true);
		if (!is_array($caps)) {
			return;
		}
		$changed = false;
		if (isset($caps['persons.import']) && $caps['persons.import'] !== ['admin']) {
			$caps['persons.import'] = ['admin'];
			$changed = true;
		}
		if (isset($caps['persons.export'])) {
			unset($caps['persons.export']);
			$changed = true;
		}
		if ($changed) {
			$encoded = json_encode($caps);
			if ($encoded !== false) {
				$this->appConfig->setValueString(Application::APP_ID, 'role_caps', $encoded);
			}
		}
	}
}
