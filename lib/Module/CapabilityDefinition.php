<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Module;

class CapabilityDefinition {
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly string $group,
		public readonly array $defaultRoles,
		public readonly ?array $lockedToRoles = null,
	) {
	}
}
