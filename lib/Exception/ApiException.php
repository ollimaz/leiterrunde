<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Exception;

class ApiException extends \Exception {
	public function __construct(string $message, private int $status = 400, private array $params = []) {
		parent::__construct($message);
	}

	public function getStatus(): int {
		return $this->status;
	}

	public function getParams(): array {
		return $this->params;
	}
}
