<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests\Unit;

use OCA\Leiterrunde\Db\Document;
use OCA\Leiterrunde\Db\DocumentMapper;
use OCA\Leiterrunde\Db\DocumentRequestMapper;
use OCA\Leiterrunde\Db\Person;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\DocumentTargetService;
use OCA\Leiterrunde\Service\SettingsService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DocumentTargetServiceTest extends TestCase {
	private function person(int $id, string $status = 'aktiv', ?string $uid = null): Person {
		$p = new Person();
		$p->setId($id);
		$p->setStatus($status);
		$p->setNcUserId($uid);
		return $p;
	}

	private function service(array $opts = []): DocumentTargetService {
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('groupExists')->willReturnCallback(static fn ($g) => in_array($g, $opts['existing'] ?? ['mitglieder'], true));
		$groups->method('isInGroup')->willReturnCallback(static fn ($uid, $gid) => ($opts['memberships'][$uid] ?? []) === [$gid]);
		$groups->method('get')->willReturnCallback(function ($gid) use ($opts) {
			$g = $this->createMock(IGroup::class);
			$users = array_map(function ($uid) {
				$u = $this->createMock(IUser::class);
				$u->method('getUID')->willReturn($uid);
				return $u;
			}, $opts['group_users'][$gid] ?? []);
			$g->method('getUsers')->willReturn($users);
			return $g;
		});
		$settings = $this->createMock(SettingsService::class);
		$settings->method('personStatuses')->willReturn($opts['statuses'] ?? ['aktiv', 'ehemalig']);
		$persons = $this->createMock(PersonMapper::class);
		$persons->method('findAll')->willReturn($opts['persons'] ?? []);
		$persons->method('findByStatuses')->willReturnCallback(static function ($statuses) use ($opts) {
			return array_values(array_filter($opts['persons'] ?? [], static fn ($p) => in_array($p->getStatus(), $statuses, true)));
		});
		$persons->method('findByUserId')->willReturnCallback(static function ($uid) use ($opts) {
			foreach ($opts['persons'] ?? [] as $p) {
				if ($p->getNcUserId() === $uid) {
					return $p;
				}
			}
			return null;
		});
		return new DocumentTargetService(
			$this->createMock(DocumentMapper::class),
			$this->createMock(DocumentRequestMapper::class),
			$persons,
			$groups,
			$settings,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testDocumentTargetSpec(): void {
		$d = new Document();
		$this->assertSame([], $d->targetSpec());
		$d->setTargetRule('{"all":true}');
		$this->assertSame(['all' => true], $d->targetSpec());
		$d->setTargetRule('invalid json {');
		$this->assertSame([], $d->targetSpec());
		$d->setTargetRule('{"all":true}');
		$this->assertSame(['all' => true], $d->jsonSerialize()['target']);
	}

	public function testValidSpecNormalizes(): void {
		$s = $this->service();
		$spec = $s->validSpec(['all' => true, 'statuses' => ['aktiv', 'aktiv'], 'nc_groups' => ['mitglieder']]);
		$this->assertSame(['all' => true, 'statuses' => ['aktiv'], 'nc_groups' => ['mitglieder']], $spec);
		$this->assertSame([], $s->validSpec([]));
	}

	public function testValidSpecRejectsUnknownStatus(): void {
		$this->expectException(ApiException::class);
		$this->service()->validSpec(['statuses' => ['bogus']]);
	}

	public function testValidSpecRejectsUnknownGroup(): void {
		$this->expectException(ApiException::class);
		$this->service()->validSpec(['nc_groups' => ['nonexistent']]);
	}

	public function testValidSpecRejectsNonArray(): void {
		$this->expectException(ApiException::class);
		$this->service()->validSpec('all');
	}

	public function testPersonMatches(): void {
		$s = $this->service(['memberships' => ['alice' => ['mitglieder']]]);
		$this->assertTrue($s->personMatches($this->person(1, 'ehemalig'), ['all' => true]));
		$this->assertTrue($s->personMatches($this->person(2, 'aktiv'), ['statuses' => ['aktiv']]));
		$this->assertFalse($s->personMatches($this->person(3, 'ehemalig'), ['statuses' => ['aktiv']]));
		$this->assertTrue($s->personMatches($this->person(4, 'x', 'alice'), ['nc_groups' => ['mitglieder']]));
		$this->assertFalse($s->personMatches($this->person(5, 'x', 'bob'), ['nc_groups' => ['mitglieder']]));
		$this->assertFalse($s->personMatches($this->person(6, 'x', null), ['nc_groups' => ['mitglieder']]));
	}

	public function testResolvePersonIdsUnions(): void {
		$s = $this->service([
			'persons' => [
				$this->person(1, 'aktiv', 'alice'),
				$this->person(2, 'ehemalig', 'bob'),
				$this->person(3, 'aktiv', null),
			],
			'group_users' => ['mitglieder' => ['alice', 'carol']],
		]);
		$ids = $s->resolvePersonIds(['statuses' => ['ehemalig'], 'nc_groups' => ['mitglieder']]);
		sort($ids);
		$this->assertSame([1, 2], $ids); // bob via status, alice via group; carol has no person
		$this->assertSame([1, 2, 3], $s->resolvePersonIds(['all' => true]));
	}
}
