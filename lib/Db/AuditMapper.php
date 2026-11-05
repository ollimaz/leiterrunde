<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @extends QBMapper<AuditEntry> */
class AuditMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'lr_audit', AuditEntry::class);
	}

	/** @return AuditEntry[] */
	public function findFor(string $entityType, int $entityId, int $limit = 200): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('entity_type', $qb->createNamedParameter($entityType)))
			->andWhere($qb->expr()->eq('entity_id', $qb->createNamedParameter($entityId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'DESC')->setMaxResults($limit);
		return $this->findEntities($qb);
	}
}
