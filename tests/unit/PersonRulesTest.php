<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests\Unit;

use OCA\Leiterrunde\Db\Person;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\PersonRules;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class PersonRulesTest extends TestCase {
	private \DateTimeImmutable $today;

	protected function setUp(): void {
		$this->today = new \DateTimeImmutable('2026-11-05');
	}

	public function testBirthdateDeterminesYear(): void {
		$this->assertSame(['2008-03-14', 2008], PersonRules::normalizeBirth('2008-03-14', 1999, $this->today));
	}

	public function testYearOnlyIsKept(): void {
		$this->assertSame([null, 2008], PersonRules::normalizeBirth(null, 2008, $this->today));
	}

	public function testNothingKnown(): void {
		$this->assertSame([null, null], PersonRules::normalizeBirth(null, null, $this->today));
	}

	public function testTodayIsAllowed(): void {
		$this->assertSame(['2026-11-05', 2026], PersonRules::normalizeBirth('2026-11-05', null, $this->today));
	}

	public function testFutureBirthdateRejected(): void {
		$this->expectException(ApiException::class);
		$this->expectExceptionMessage('Date of birth cannot be in the future');
		PersonRules::normalizeBirth('2026-11-06', null, $this->today);
	}

	public function testBirthdateBefore1900Rejected(): void {
		$this->expectException(ApiException::class);
		PersonRules::normalizeBirth('1899-12-31', null, $this->today);
	}

	public function testYearOutOfRangeRejected(): void {
		foreach ([1899, 2027] as $year) {
			try {
				PersonRules::normalizeBirth(null, $year, $this->today);
				$this->fail("year $year accepted");
			} catch (ApiException $e) {
				$this->assertSame('Invalid birth year', $e->getMessage());
			}
		}
	}

	private function person(int $id, string $first = 'Max', string $last = 'Mustermann'): Person {
		$p = new Person();
		$p->setId($id);
		$p->setFirstName($first);
		$p->setLastName($last);
		return $p;
	}

	private function rules(?IUser $user, ?Person $linked): PersonRules {
		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturn($user);
		$mapper = $this->createMock(PersonMapper::class);
		$mapper->method('findByUserId')->willReturn($linked);
		return new PersonRules($users, $mapper);
	}

	public function testUnknownUserRejected(): void {
		$this->expectException(ApiException::class);
		$this->expectExceptionMessage('Nextcloud user does not exist: %s');
		$this->rules(null, null)->assertUserLinkable($this->person(1), 'ghost');
	}

	public function testUserLinkedToOtherPersonRejected(): void {
		try {
			$this->rules($this->createMock(IUser::class), $this->person(2, 'Lena', 'Brandt'))->assertUserLinkable($this->person(1), 'testfz');
			$this->fail('duplicate link accepted');
		} catch (ApiException $e) {
			$this->assertSame('This Nextcloud user is already linked to %s', $e->getMessage());
			$this->assertSame(['Lena Brandt'], $e->getParams());
		}
	}

	public function testUserLinkedToSamePersonAllowed(): void {
		$this->rules($this->createMock(IUser::class), $this->person(1))->assertUserLinkable($this->person(1), 'testfz');
		$this->addToAssertionCount(1);
	}

	public function testFreeUserAllowed(): void {
		$this->rules($this->createMock(IUser::class), null)->assertUserLinkable($this->person(1), 'testfz');
		$this->addToAssertionCount(1);
	}
}
