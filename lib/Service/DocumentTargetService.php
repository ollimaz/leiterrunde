<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service;

use OCA\Leiterrunde\Db\Document;
use OCA\Leiterrunde\Db\DocumentMapper;
use OCA\Leiterrunde\Db\DocumentRequest;
use OCA\Leiterrunde\Db\DocumentRequestMapper;
use OCA\Leiterrunde\Db\Person;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

/**
 * Persistent auto-request rules on documents: {all, statuses[], nc_groups[]}.
 * Materialization is additive and idempotent — the unique key on
 * (document_id, person_id) guards duplicates. Requests are never removed
 * when a person leaves a target: once requested, the request stands.
 */
class DocumentTargetService {
	private const MAX_PERSONS = 5000;

	public function __construct(
		private DocumentMapper $docs,
		private DocumentRequestMapper $reqs,
		private PersonMapper $persons,
		private IGroupManager $groupManager,
		private SettingsService $settings,
		private LoggerInterface $logger,
	) {
	}

	/** Validate a submitted rule; returns the normalized spec (may be empty). */
	public function validSpec(mixed $target): array {
		if (!is_array($target)) {
			throw new ApiException('target must be an object', 422);
		}
		$spec = [];
		if (!empty($target['all'])) {
			$spec['all'] = true;
		}
		if (isset($target['statuses'])) {
			if (!is_array($target['statuses'])) {
				throw new ApiException('target.statuses must be a list', 422);
			}
			$allowed = $this->settings->personStatuses();
			$statuses = [];
			foreach ($target['statuses'] as $s) {
				$s = (string)$s;
				if (!in_array($s, $allowed, true)) {
					throw new ApiException('Invalid status: %s', 422, [$s]);
				}
				$statuses[$s] = true;
			}
			if ($statuses !== []) {
				$spec['statuses'] = array_keys($statuses);
			}
		}
		if (isset($target['nc_groups'])) {
			if (!is_array($target['nc_groups'])) {
				throw new ApiException('target.nc_groups must be a list', 422);
			}
			$gids = [];
			foreach ($target['nc_groups'] as $g) {
				$g = (string)$g;
				if ($g === '' || mb_strlen($g) > 64) {
					throw new ApiException('Invalid group id', 422);
				}
				if (!$this->groupManager->groupExists($g)) {
					throw new ApiException('Group does not exist: %s', 422, [$g]);
				}
				$gids[$g] = true;
			}
			if ($gids !== []) {
				$spec['nc_groups'] = array_keys($gids);
			}
		}
		return $spec;
	}

	/** Resolve a rule to concrete person ids. nc_groups only reach linked persons. */
	public function resolvePersonIds(array $spec): array {
		$ids = [];
		if (!empty($spec['all'])) {
			foreach ($this->persons->findAll(null, null, self::MAX_PERSONS) as $p) {
				$ids[$p->getId()] = true;
			}
		}
		foreach ((array)($spec['statuses'] ?? []) as $status) {
			foreach ($this->persons->findByStatuses([$status]) as $p) {
				$ids[$p->getId()] = true;
			}
		}
		foreach ((array)($spec['nc_groups'] ?? []) as $gid) {
			foreach ($this->personIdsInGroup((string)$gid) as $pid) {
				$ids[$pid] = true;
			}
		}
		return array_keys($ids);
	}

	/** @return int[] person ids linked to members of the NC group */
	public function personIdsInGroup(string $gid): array {
		$group = $this->groupManager->get($gid);
		if ($group === null) {
			return [];
		}
		$ids = [];
		foreach ($group->getUsers() as $u) {
			$p = $this->persons->findByUserId($u->getUID());
			if ($p !== null) {
				$ids[] = $p->getId();
			}
		}
		return $ids;
	}

	public function personMatches(Person $p, array $spec): bool {
		if (!empty($spec['all'])) {
			return true;
		}
		if (in_array($p->getStatus(), (array)($spec['statuses'] ?? []), true)) {
			return true;
		}
		$uid = $p->getNcUserId();
		if ($uid !== null) {
			foreach ((array)($spec['nc_groups'] ?? []) as $gid) {
				if ($this->groupManager->isInGroup($uid, (string)$gid)) {
					return true;
				}
			}
		}
		return false;
	}

	/** Ensure pending requests exist for every person the document's rule targets. */
	public function materialize(Document $d): int {
		$spec = $d->targetSpec();
		if ($spec === []) {
			return 0;
		}
		return $this->ensureFor($d->getId(), $this->resolvePersonIds($spec));
	}

	/** Create requests for a person in every document whose rule matches them. */
	public function syncPerson(Person $p): void {
		try {
			foreach ($this->docs->findWithTarget() as $d) {
				if ($this->personMatches($p, $d->targetSpec())) {
					$this->ensureFor($d->getId(), [$p->getId()]);
				}
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Document target sync failed for person ' . $p->getId() . ': ' . $e->getMessage());
		}
	}

	/** Create requests for a user id (NC group join path) — resolves the linked person. */
	public function syncUser(string $uid): void {
		$p = $this->persons->findByUserId($uid);
		if ($p !== null) {
			$this->syncPerson($p);
		}
	}

	/** @return int created count */
	private function ensureFor(int $docId, array $personIds): int {
		$created = 0;
		foreach ($personIds as $pid) {
			try {
				if ($this->reqs->findFor($docId, (int)$pid) !== null) {
					continue;
				}
				$r = new DocumentRequest();
				$r->setDocumentId($docId);
				$r->setPersonId((int)$pid);
				$r->setStatus(DocumentRequest::STATUS_PENDING);
				$r->setCreatedAt(time());
				$this->reqs->insert($r);
				$created++;
			} catch (\Throwable $e) {
				$this->logger->warning('Auto request failed doc ' . $docId . ' person ' . $pid . ': ' . $e->getMessage());
			}
		}
		return $created;
	}
}
