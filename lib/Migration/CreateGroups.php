<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Migration;

use OCP\IGroupManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class CreateGroups implements IRepairStep {
	public const GROUPS = [
		'leiterrunde_leiter',
		'leiterrunde_vorstand',
		'leiterrunde_kasse',
		'leiterrunde_schulung',
		'leiterrunde_fz',
		'leiterrunde_admin',
	];

	public function __construct(
		private IGroupManager $groupManager,
	) {
	}

	public function getName(): string {
		return 'Leiterrunde-Gruppen anlegen';
	}

	public function run(IOutput $output): void {
		foreach (self::GROUPS as $gid) {
			if (!$this->groupManager->groupExists($gid)) {
				$this->groupManager->createGroup($gid);
				$output->info("Gruppe $gid angelegt");
			}
		}
	}
}
