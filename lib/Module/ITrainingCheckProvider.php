<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Module;

interface ITrainingCheckProvider {
	/** @return array person_id, date, types, fz, overall (siehe v1) */
	public function betreuerCheck(int $personId, \DateTimeImmutable $at, bool $fzDetails): array;
}
