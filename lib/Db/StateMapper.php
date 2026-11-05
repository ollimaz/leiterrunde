<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @extends QBMapper<State> */
class StateMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'lr_states', State::class);
	}

	public function get(string $subjectType, int $subjectId, string $checkKey): ?State {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('subject_type', $qb->createNamedParameter($subjectType)))
			->andWhere($qb->expr()->eq('subject_id', $qb->createNamedParameter($subjectId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('check_key', $qb->createNamedParameter($checkKey)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return State[] */
	public function getAllForSubject(string $subjectType, int $subjectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('subject_type', $qb->createNamedParameter($subjectType)))
			->andWhere($qb->expr()->eq('subject_id', $qb->createNamedParameter($subjectId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}
}
