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

/** @extends QBMapper<Person> */
class PersonMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'lr_persons', Person::class);
	}

	public function find(int $id): Person {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return Person[] */
	public function findAll(?string $search = null, ?string $status = null, int $limit = 500, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->orderBy('last_name', 'ASC')->addOrderBy('first_name', 'ASC')
			->setMaxResults($limit)->setFirstResult($offset);
		if ($status !== null && $status !== '') {
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
		}
		if ($search !== null && $search !== '') {
			$like = '%' . $this->db->escapeLikeParameter($search) . '%';
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('first_name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('last_name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('email', $qb->createNamedParameter($like)),
			));
		}
		return $this->findEntities($qb);
	}

	public function findByUserId(string $uid): ?Person {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('nc_user_id', $qb->createNamedParameter($uid)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findByEmail(string $email): ?Person {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq(
				$qb->func()->lower('email'),
				$qb->createNamedParameter(mb_strtolower($email))
			))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return Person[] */
	public function findWithBirthdate(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->isNotNull('birthdate'));
		return $this->findEntities($qb);
	}

	/** @return Person[] */
	public function findByStatuses(array $statuses): array {
		if ($statuses === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->in('status', $qb->createNamedParameter($statuses, IQueryBuilder::PARAM_STR_ARRAY)));
		return $this->findEntities($qb);
	}
}
