<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests\Unit;

use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\SettingsService;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase {
	public function testDefaultsProduceFourStatuses(): void {
		$defs = SettingsService::normalizeStatusDefs('aktiv,nwl,inaktiv,ehemalig');
		$this->assertSame(['aktiv', 'nwl', 'inaktiv', 'ehemalig'], array_column($defs, 'key'));
	}

	public function testLegacyCommaStringNormalizesCaseAndSpaces(): void {
		$defs = SettingsService::normalizeStatusDefs(' Aktiv , Messdiener,Leiter, ehemalig ');
		$this->assertSame(['aktiv', 'messdiener', 'leiter', 'ehemalig'], array_column($defs, 'key'));
		$this->assertSame('ok', $defs[0]['color']); // aktiv defaults to ok
		$this->assertSame('info', $defs[1]['color']); // unknown keys default to info
		$this->assertSame('missing', $defs[3]['color']); // ehemalig defaults to missing
	}

	public function testCommaStringSupportsKeyLabel(): void {
		$defs = SettingsService::normalizeStatusDefs('aktiv:Leiterrunde,ehemalig');
		$this->assertSame('Leiterrunde', $defs[0]['label']);
		$this->assertSame('', $defs[1]['label']);
	}

	public function testJsonDefsKeepLabelAndColor(): void {
		$defs = SettingsService::normalizeStatusDefs([
			['key' => 'Aktiv', 'label' => 'Leiterrunde', 'color' => 'ok'],
			['key' => 'leiter', 'label' => 'Leiter', 'color' => 'warn'],
			['key' => 'ehemalig'],
		]);
		$this->assertSame('aktiv', $defs[0]['key']);
		$this->assertSame('Leiterrunde', $defs[0]['label']);
		$this->assertSame('warn', $defs[1]['color']);
		$this->assertSame('missing', $defs[2]['color']);
	}

	public function testUnknownColorFallsBackToDefault(): void {
		$defs = SettingsService::normalizeStatusDefs([['key' => 'aktiv', 'color' => 'purple'], ['key' => 'ehemalig']]);
		$this->assertSame('ok', $defs[0]['color']);
	}

	public function testRequiresAktivAndEhemalig(): void {
		$this->expectException(ApiException::class);
		SettingsService::normalizeStatusDefs('leiter,ehemalig');
	}

	public function testRejectsInvalidKeys(): void {
		foreach (['aktiv,ehemalig,bad;status', 'aktiv,ehemalig,' . str_repeat('x', 31)] as $raw) {
			try {
				SettingsService::normalizeStatusDefs($raw);
				$this->fail("Expected ApiException for $raw");
			} catch (ApiException) {
			}
		}
		$this->addToAssertionCount(2);
	}

	public function testRejectsDuplicatesAndEmpty(): void {
		foreach (['aktiv,leiter,ehemalig,Leiter', ''] as $raw) {
			try {
				SettingsService::normalizeStatusDefs($raw);
				$this->fail("Expected ApiException for $raw");
			} catch (ApiException) {
			}
		}
		$this->addToAssertionCount(2);
	}

	public function testRejectsOverlongLabel(): void {
		$this->expectException(ApiException::class);
		SettingsService::normalizeStatusDefs([['key' => 'aktiv', 'label' => str_repeat('a', 61)], ['key' => 'ehemalig']]);
	}
}
