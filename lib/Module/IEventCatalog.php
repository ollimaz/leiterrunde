<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Module;

interface IEventCatalog {
	public function eventOptions(): array;
	public function requireEvent(int $id): void;
	public function createEventOption(string $title): int;
}
