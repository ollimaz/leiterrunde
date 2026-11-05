<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests\Unit;

use OCA\Leiterrunde\Module\CoreModule;

use PHPUnit\Framework\TestCase;

class RoleServiceTest extends TestCase {
	private function caps(): array {
		$out = [];
		foreach ([(new \ReflectionClass(CoreModule::class))->newInstanceWithoutConstructor()] as $m) {
			foreach ($m->getCapabilities() as $c) {
				$out[$c->id] = $c;
			}
		}
		return $out;
	}

	public function testDefaultMatrixEqualsV1(): void {
		$caps = $this->caps();
		$expect = [
			'persons.read' => ['leiter', 'vorstand', 'kasse', 'schulung', 'admin'],
			'persons.write' => ['vorstand', 'admin'],
			'audit.read' => ['vorstand', 'admin'],
			'settings.manage' => ['admin', 'nca'],
		];
		foreach ($expect as $id => $roles) {
			$this->assertArrayHasKey($id, $caps, $id);
			$this->assertSame($roles, $caps[$id]->defaultRoles, $id);
		}
	}

	public function testFzLocked(): void {
		// fz.* Caps liegen im Schulungen-Modul; in Core sind sie nicht registriert
		$this->assertArrayNotHasKey('fz.read', $this->caps());
	}

	public function testNcaOnlySettingsAndAccess(): void {
		foreach ($this->caps() as $id => $c) {
			if (in_array('nca', $c->defaultRoles, true)) {
				$this->assertContains($id, ['app.access', 'settings.manage'], $id);
			}
		}
	}
}
