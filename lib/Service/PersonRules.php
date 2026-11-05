<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service;

use OCA\Leiterrunde\Db\Person;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCP\IUserManager;

/**
 * Validation shared by person create, update and CSV import.
 */
class PersonRules {
	public const MIN_YEAR = 1900;

	public function __construct(
		private IUserManager $userManager,
		private PersonMapper $mapper,
	) {
	}

	/**
	 * A full birthdate always determines the birth year; a year alone is kept when no date is known.
	 *
	 * @return array{0: ?string, 1: ?int} [birthdate Y-m-d, birth year]
	 */
	public static function normalizeBirth(?string $birthdate, ?int $birthYear, \DateTimeImmutable $today): array {
		if ($birthdate !== null) {
			$year = (int)substr($birthdate, 0, 4);
			if ($birthdate > $today->format('Y-m-d')) {
				throw new ApiException('Date of birth cannot be in the future', 422);
			}
			if ($year < self::MIN_YEAR) {
				throw new ApiException('Invalid date of birth', 422);
			}
			return [$birthdate, $year];
		}
		if ($birthYear !== null && ($birthYear < self::MIN_YEAR || $birthYear > (int)$today->format('Y'))) {
			throw new ApiException('Invalid birth year', 422);
		}
		return [null, $birthYear];
	}

	/**
	 * Checks that $uid may be linked to $person: the account must exist and must not belong to another person.
	 */
	public function assertUserLinkable(Person $person, string $uid): void {
		if ($this->userManager->get($uid) === null) {
			throw new ApiException('Nextcloud user does not exist: %s', 422, [$uid]);
		}
		$other = $this->mapper->findByUserId($uid);
		if ($other !== null && $other->getId() !== $person->getId()) {
			throw new ApiException('This Nextcloud user is already linked to %s', 422, [$other->getFirstName() . ' ' . $other->getLastName()]);
		}
	}
}
