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

	public function testSniffAcceptsMatchingContent(): void {
		$tmp = tempnam(sys_get_temp_dir(), 'sniff');
		try {
			file_put_contents($tmp, "%PDF-1.7 fake");
			$this->assertTrue(DocumentStorageService::sniff($tmp, 'pdf'));
			file_put_contents($tmp, "\xFF\xD8\xFF\xE0" . str_repeat("\0", 16));
			$this->assertTrue(DocumentStorageService::sniff($tmp, 'jpg'));
			file_put_contents($tmp, "\x89PNG\x0D\x0A\x1A\x0A" . str_repeat("\0", 16));
			$this->assertTrue(DocumentStorageService::sniff($tmp, 'png'));
			file_put_contents($tmp, "\0\0\0\x18ftypheic" . str_repeat("\0", 16));
			$this->assertTrue(DocumentStorageService::sniff($tmp, 'heic'));
		} finally {
			unlink($tmp);
		}
	}

	public function testSniffRejectsMismatchedContent(): void {
		$tmp = tempnam(sys_get_temp_dir(), 'sniff');
		try {
			file_put_contents($tmp, '<?php echo 1;');
			foreach (['pdf', 'jpg', 'png', 'heic'] as $ext) {
				$this->assertFalse(DocumentStorageService::sniff($tmp, $ext), $ext);
			}
			file_put_contents($tmp, '%PDF-1.7 fake');
			$this->assertFalse(DocumentStorageService::sniff($tmp, 'png'));
			$this->assertFalse(DocumentStorageService::sniff($tmp, 'exe'));
		} finally {
			unlink($tmp);
		}
	}
}
