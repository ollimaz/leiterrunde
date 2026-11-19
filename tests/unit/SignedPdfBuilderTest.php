<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests\Unit;

use OCA\Leiterrunde\Service\SignedPdfBuilder;
use PHPUnit\Framework\TestCase;

class SignedPdfBuilderTest extends TestCase {
	private string $png;

	protected function setUp(): void {
		$img = imagecreatetruecolor(40, 20);
		imagealphablending($img, false);
		imagesavealpha($img, true);
		imagefilledrectangle($img, 0, 0, 40, 20, imagecolorallocatealpha($img, 0, 0, 0, 127));
		imagesetpixel($img, 5, 5, imagecolorallocatealpha($img, 0, 0, 0, 0));
		$this->png = tempnam(sys_get_temp_dir(), 'lr-pdf-test') . '.png';
		imagepng($img, $this->png);
	}

	protected function tearDown(): void {
		@unlink($this->png);
	}

	public function testBuildsValidPdf(): void {
		$pdf = SignedPdfBuilder::build('Titel', "Zeile eins äöüß.\nZweite Zeile", $this->png, 'Max Muster, 18.11.2026');
		$this->assertStringStartsWith('%PDF-1.4', $pdf);
		$this->assertStringEndsWith("%%EOF\n", $pdf);
		$this->assertStringContainsString('/Subtype /Image', $pdf);
		$this->assertStringContainsString('Helvetica', $pdf);
		$this->assertStringContainsString('(Titel)', $pdf);
		$this->assertStringContainsString('(Zeile eins ' . mb_convert_encoding('äöüß.', 'Windows-1252', 'UTF-8') . ')', $pdf);
		$this->assertStringContainsString('(Unterschrift: Max Muster, 18.11.2026)', $pdf);
	}

	public function testXrefOffsetsAreConsistent(): void {
		$pdf = SignedPdfBuilder::build('T', 'body', $this->png, 'x');
		$xrefPos = strpos($pdf, 'xref');
		$this->assertNotFalse($xrefPos);
		$lines = explode("\n", substr($pdf, $xrefPos));
		$count = (int)explode(' ', $lines[1])[1];
		for ($k = 1; $k < $count; $k++) {
			$off = (int)substr($lines[2 + $k], 0, 10);
			$this->assertSame("$k 0 obj", substr($pdf, $off, strlen("$k 0 obj")), "xref offset of object $k");
		}
	}

	public function testImageStreamDecodesToRgb(): void {
		$pdf = SignedPdfBuilder::build('T', 'b', $this->png, 'x');
		$this->assertSame(1, preg_match('/\/Width (\d+) \/Height (\d+).*?stream\n(.*?)endstream/s', $pdf, $m));
		$raw = gzuncompress($m[3]);
		$this->assertSame((int)$m[1] * (int)$m[2] * 3, strlen($raw));
	}

	public function testTransparencyFlattensToWhite(): void {
		$pdf = SignedPdfBuilder::build('T', 'b', $this->png, 'x');
		preg_match('/stream\n(.*?)endstream/s', $pdf, $m);
		$raw = gzuncompress($m[1]);
		$this->assertSame("\xFF\xFF\xFF", substr($raw, 0, 3));
		$px = (5 * 40 + 5) * 3;
		$this->assertSame("\x00\x00\x00", substr($raw, $px, 3));
	}

	public function testLongTextPaginates(): void {
		$pdf = SignedPdfBuilder::build('T', str_repeat("Sehr langer Satz mit Inhalt. \n", 300), $this->png, 'x');
		$this->assertGreaterThanOrEqual(2, substr_count($pdf, '/Type /Page '));
	}

	public function testTypedSignatureRendersNameWithoutImage(): void {
		$pdf = SignedPdfBuilder::buildTyped('Titel', 'Ich akzeptiere.', 'Max Muster', 'Max Muster, 19.11.2026');
		$this->assertStringStartsWith('%PDF-1.4', $pdf);
		$this->assertStringNotContainsString('/Subtype /Image', $pdf);
		$this->assertStringContainsString('Helvetica-Oblique', $pdf);
		$this->assertStringContainsString('(Max Muster)', $pdf);
		$this->assertStringContainsString('(Unterschrift: Max Muster, 19.11.2026)', $pdf);
	}

	public function testTryBuildReturnsNullForNonImage(): void {
		$tmp = tempnam(sys_get_temp_dir(), 'lr-pdf-test') . '.pdf';
		file_put_contents($tmp, "%PDF-1.4 fake\n");
		$this->assertNull(SignedPdfBuilder::tryBuild('T', 'b', $tmp, 'x'));
		unlink($tmp);
	}
}
