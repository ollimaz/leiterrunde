<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Db;

use OCP\AppFramework\Db\Entity;

abstract class BaseEntity extends Entity implements \JsonSerializable {
	public function jsonSerialize(): array {
		$out = [];
		foreach (get_object_vars($this) as $prop => $value) {
			if (str_starts_with($prop, '_')) {
				continue;
			}
			$key = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $prop));
			$out[$key] = $value;
		}
		return $out;
	}
}
