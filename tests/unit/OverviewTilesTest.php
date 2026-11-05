<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests\Unit;

use OCA\Leiterrunde\Module\CoreModule;
use PHPUnit\Framework\TestCase;

class OverviewTilesTest extends TestCase {
	public function testTileIdsPresent(): void {
		$perm = $this->createMock(\OCA\Leiterrunde\Service\PermissionService::class);
		$perm->method('has')->willReturn(true);
		$pm = $this->createMock(\OCA\Leiterrunde\Db\PersonMapper::class);
		$pm->method('findAll')->willReturn([]);
		$pm->method('findWithBirthdate')->willReturn([]);
		
		$l = $this->createMock(\OCP\IL10N::class);
		$l->method('t')->willReturnCallback(fn($s, $p = []) => $p ? vsprintf($s, $p) : $s);
		$l->method('n')->willReturnCallback(fn($s1, $s2, $n, $p = []) => vsprintf(str_replace('%n', (string)$n, $n === 1 ? $s1 : $s2), $p));
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('u1');
		$m = new CoreModule($pm, $perm, $l);
		$ids = array_map(static fn($t) => $t->id, $m->getOverviewTiles($user));
		foreach (['core.persons.active', 'core.persons.status', 'core.birthdays', 'core.persons.noaccount'] as $id) {
			$this->assertContains($id, $ids);
		}
	}
}
