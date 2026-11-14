<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests\Unit;

use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Module\IPersonFieldsProvider;
use OCA\Leiterrunde\Module\ModuleRegistry;
use OCA\Leiterrunde\Service\PersonFieldsService;
use OCA\Leiterrunde\Service\SettingsService;
use PHPUnit\Framework\TestCase;

class PersonFieldsServiceTest extends TestCase {
	private function service(bool $enabled = false): PersonFieldsService {
		$settings = $this->createMock(SettingsService::class);
		$settings->method('get')->willReturn('[{"key":"custom_team","label":"Team","type":"select","options":["A","B"]},{"key":"custom_note","label":"Note","type":"text"}]');
		$modules = $this->createMock(ModuleRegistry::class);
		$provider = $this->createMock(IPersonFieldsProvider::class);
		$provider->method('personFields')->willReturn([['key' => 'messdiener_location', 'type' => 'select', 'options' => [['value' => '4', 'label' => 'Church']]]]);
		$modules->method('provider')->willReturn($enabled ? $provider : null);
		return new PersonFieldsService($settings, $modules);
	}

	public function testDisabledPluginValuesAreHiddenButPreserved(): void {
		$service = $this->service();
		$stored = '{"messdiener_location":"4","custom_team":"A"}';
		$this->assertSame(['custom_team' => 'A'], $service->visible($stored));
		$this->assertSame(['messdiener_location' => '4', 'custom_team' => 'B'], json_decode($service->merge($stored, ['custom_team' => 'B']), true));
	}

	public function testEnabledPluginFieldAcceptsLocation(): void {
		$this->assertSame(['messdiener_location' => '4'], json_decode($this->service(true)->merge(null, ['messdiener_location' => '4']), true));
	}

	public function testClearAndTextValues(): void {
		$this->assertSame(['custom_note' => '<script>text</script>'], json_decode($this->service()->merge('{"custom_team":"A"}', ['custom_team' => null, 'custom_note' => '<script>text</script>']), true));
	}

	public function testInvalidValuesAreRejected(): void {
		foreach ([['unknown' => 'A'], ['messdiener_location' => '4'], ['custom_team' => 'C'], ['custom_note' => []], ['custom_note' => str_repeat('x', 2001)]] as $patch) {
			try {
				$this->service()->merge(null, $patch);
				$this->fail('Invalid field accepted');
			} catch (ApiException $e) {
				$this->assertNotEmpty($e->getMessage());
			}
		}
	}

	public function testStaleLocationCanBeKeptOrCleared(): void {
		$this->assertSame('{"messdiener_location":"8"}', $this->service(true)->merge('{"messdiener_location":"8"}', ['messdiener_location' => '8']));
		$this->assertSame('{}', $this->service(true)->merge('{"messdiener_location":"8"}', ['messdiener_location' => null]));
	}

	public function testDefinitionValidation(): void {
		PersonFieldsService::validateDefinitions([['key' => 'custom_team', 'label' => 'Team', 'type' => 'select', 'options' => ['A']]]);
		$this->expectException(ApiException::class);
		PersonFieldsService::validateDefinitions([['key' => 'messdiener_location', 'label' => 'Override', 'type' => 'text']]);
	}

	public function testDuplicateKeysRejected(): void {
		$f = ['key' => 'custom_team', 'label' => 'Team', 'type' => 'text'];
		$this->expectException(ApiException::class);
		PersonFieldsService::validateDefinitions([$f, $f]);
	}
}
