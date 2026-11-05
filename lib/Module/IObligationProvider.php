<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Module;

interface IObligationProvider {
	public function ensure(string $reference, string $payerName, string $label, string $amountDue, ?string $dueDate, ?int $registrationId, ?int $personId): int;

	public function cancelIfUnpaid(int $id): void;

	/** @return ?array{status:string, remaining:string} */
	public function status(int $id): ?array;
}
