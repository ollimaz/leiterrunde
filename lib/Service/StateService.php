<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service;

use OCA\Leiterrunde\Db\State;
use OCA\Leiterrunde\Db\StateMapper;

class StateService {
	public function __construct(
		private StateMapper $mapper,
	) {
	}

	public function getState(string $subjectType, int $subjectId, string $checkKey): ?string {
		return $this->mapper->get($subjectType, $subjectId, $checkKey)?->getState();
	}

	/** Persist a new state. Returns previous state (or null if no row existed). */
	public function transition(string $subjectType, int $subjectId, string $checkKey, string $newState): ?string {
		$row = $this->mapper->get($subjectType, $subjectId, $checkKey);
		if ($row === null) {
			$row = new State();
			$row->setSubjectType($subjectType);
			$row->setSubjectId($subjectId);
			$row->setCheckKey($checkKey);
			$row->setState($newState);
			$row->setChangedAt(time());
			$this->mapper->insert($row);
			return null;
		}
		$old = $row->getState();
		if ($old !== $newState) {
			$row->setState($newState);
			$row->setChangedAt(time());
			$this->mapper->update($row);
		}
		return $old;
	}
}
