<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service\Sync;

class Signer {
	public const MAX_SKEW = 300;

	public function __construct(
		private string $secret,
	) {
	}

	/** Sign "<raw ts>\n<body>" — the timestamp string is used verbatim. */
	public function sign(string $body, string|int $ts): string {
		return hash_hmac('sha256', (string)$ts . "\n" . $body, $this->secret);
	}

	public function verify(string $body, ?string $ts, ?string $sig, int $now): bool {
		if ($ts === null || $sig === null || !ctype_digit($ts)) {
			return false;
		}
		if (abs($now - (int)$ts) > self::MAX_SKEW) {
			return false;
		}
		return hash_equals($this->sign($body, $ts), $sig);
	}
}
