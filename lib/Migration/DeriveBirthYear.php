<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Migration;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class DeriveBirthYear implements IRepairStep {
	public function __construct(private IDBConnection $db) {
	}

	public function getName(): string {
		return 'Derive birth_year from birthdate in lr_persons';
	}

	public function run(IOutput $output): void {
		$sel = $this->db->getQueryBuilder();
		$sel->select('id', 'birthdate', 'birth_year')->from('lr_persons')
			->where($sel->expr()->isNotNull('birthdate'));
		$res = $sel->executeQuery();
		$rows = [];
		while ($row = $res->fetch()) {
			$rows[] = $row;
		}
		$res->closeCursor();
		foreach ($rows as $row) {
			$year = (int)substr((string)$row['birthdate'], 0, 4);
			if ($row['birth_year'] === null || (int)$row['birth_year'] !== $year) {
				$u = $this->db->getQueryBuilder();
				$u->update('lr_persons')
					->set('birth_year', $u->createNamedParameter($year, \OCP\DB\Types::INTEGER))
					->where($u->expr()->eq('id', $u->createNamedParameter((int)$row['id'], \OCP\DB\Types::BIGINT)));
				$u->executeStatement();
			}
		}
	}
}
