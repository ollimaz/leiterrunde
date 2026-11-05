<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Exception;

class ForbiddenException extends ApiException {
	public function __construct(string $message = 'Forbidden') {
		parent::__construct($message, 403);
	}
}
