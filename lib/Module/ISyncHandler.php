<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Module;

interface ISyncHandler {
	/** v1 RegistrationSyncService-Semantik; @return int lokale registration id */
	public function handle(array $payload): int;
}
