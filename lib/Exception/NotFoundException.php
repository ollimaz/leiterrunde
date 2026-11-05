<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Exception;

class NotFoundException extends ApiException {
	public function __construct(string $message = 'Nicht gefunden') {
		parent::__construct($message, 404);
	}
}
