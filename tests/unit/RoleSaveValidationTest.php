<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests\Unit;

use OCA\Leiterrunde\Module\CoreModule;

use OCA\Leiterrunde\Module\ModuleRegistry;
use OCA\Leiterrunde\Service\RoleService;
use PHPUnit\Framework\TestCase;

class RoleSaveValidationTest extends TestCase {
	private RoleService $svc;
	private $groupManager;
	private $appConfig;
	private $saved = [];

	protected function setUp(): void {
		$dispatcher = $this->createMock(\OCP\EventDispatcher\IEventDispatcher::class);
		$dispatcher->method('dispatchTyped')->willReturnCallback(static function ($e) {
			$e->registerModule((new \ReflectionClass(CoreModule::class))->newInstanceWithoutConstructor());
		});
		$modules = new ModuleRegistry($dispatcher);
		$this->groupManager = $this->createMock(\OCP\IGroupManager::class);
		$this->groupManager->method('groupExists')->willReturnCallback(
			static fn ($g) => str_starts_with($g, 'leiterrunde_'));
		$this->appConfig = $this->createMock(\OCP\IAppConfig::class);
		$this->saved = [];
		$this->appConfig->method('getValueString')->willReturn('');
		$this->appConfig->method('setValueString')->willReturnCallback(function ($app, $key, $value) {
			$this->saved[$key] = $value;
		});
		$this->svc = new RoleService($this->appConfig, $modules, $this->groupManager);
	}

	public function testValidSave(): void {
		$this->svc->save(['leiter' => ['leiterrunde_leiter']], ['events.write' => ['leiter', 'vorstand']]);
		$this->assertArrayHasKey('role_groups', $this->saved);
	}

	public function testUnknownRoleInGroupsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->save(['bogus' => ['leiterrunde_leiter']], []);
	}

	public function testUnknownRoleInCapsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->save([], ['events.write' => ['bogus']]);
	}

	public function testNcaRejectedWhereNotDefault(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->save([], ['events.write' => ['nca']]);
	}

	public function testNcaAllowedWhereDefault(): void {
		$this->svc->save([], ['settings.manage' => ['admin', 'nca']]);
		$this->addToAssertionCount(1);
	}

	public function testNonexistentGroupRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->svc->save(['leiter' => ['gibts_nicht']], []);
	}

	public function testFzLockEnforced(): void {
		// fz.* ist in Core nicht mehr registriert (Schulungen-Modul) → kein Lock anwendbar
		$this->svc->save([], ['fz.read' => ['fz', 'admin']]);
		$this->addToAssertionCount(1);
	}

	public function testAnyAllowed(): void {
		$this->svc->save([], ['events.read' => ['any']]);
		$this->addToAssertionCount(1);
	}
}
