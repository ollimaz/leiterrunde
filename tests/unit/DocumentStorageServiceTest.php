<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests\Unit;

use OCA\Leiterrunde\Service\DocumentStorageService;
use PHPUnit\Framework\TestCase;

class DocumentStorageServiceTest extends TestCase {
	public function testSanitizeSegment(): void {
		$this->assertSame('a_b', DocumentStorageService::sanitizeSegment('a/b'));
		$this->assertSame('_', DocumentStorageService::sanitizeSegment('..'));
		$this->assertSame('_', DocumentStorageService::sanitizeSegment('  '));
		$this->assertSame('_', DocumentStorageService::sanitizeSegment(''));
		$this->assertSame('Müller', DocumentStorageService::sanitizeSegment('Müller'));
	}

	private function svc(): DocumentStorageService {
		return (new \ReflectionClass(DocumentStorageService::class))->newInstanceWithoutConstructor();
	}

	public function testRenderPath(): void {
		$tpl = 'Leiterrunde/Belege/{jahr}/{kategorie}';
		$this->assertSame(
			'Leiterrunde/Belege/2026/Material',
			$this->svc()->renderPath($tpl, ['jahr' => '2026', 'kategorie' => 'Material'])
		);
		// böse Werte werden sanitisiert
		$this->assertSame(
			'Leiterrunde/Belege/2026/a_b',
			$this->svc()->renderPath($tpl, ['jahr' => '2026', 'kategorie' => 'a/b'])
		);
		// fehlende Vars -> '_'
		$this->assertSame(
			'Leiterrunde/Belege/2026/_',
			$this->svc()->renderPath($tpl, ['jahr' => '2026'])
		);
	}

	public function testRenderPathRejectsTraversal(): void {
		$this->expectException(\OCA\Leiterrunde\Exception\ApiException::class);
		$this->svc()->renderPath('Leiterrunde/../{jahr}', ['jahr' => '2026']);
	}
}
