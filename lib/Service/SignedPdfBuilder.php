<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service;

use OCA\Leiterrunde\Exception\ApiException;

/**
 * Minimal dependency-free PDF writer: renders a consent text plus a signature
 * into one A4 PDF — either an image (drawn/uploaded) or a typed name in
 * oblique script. Uses the built-in Helvetica fonts (WinAnsi encoding).
 */
class SignedPdfBuilder {
	private const PAGE_W = 595.0;
	private const PAGE_H = 842.0;
	private const MARGIN = 56.0;
	private const LINE_H = 14.0;
	private const FONT_SIZE = 10.5;
	private const TITLE_SIZE = 16.0;

	/** Signature block: label + image area, drawn after the body text. */
	private const SIG_BLOCK_H = 130.0;
	private const SIG_IMG_W = 240.0;
	private const SIG_IMG_H = 85.0;

	/**
	 * Signed PDF with an embedded signature image (PNG/JPEG/GIF/WEBP/BMP —
	 * anything GD decodes). Transparent areas are flattened onto white.
	 * @throws ApiException when the image cannot be decoded
	 */
	public static function build(string $title, string $bodyText, string $imgPath, string $signedLine): string {
		return self::compose($title, $bodyText, self::imgToRgb($imgPath), null, $signedLine);
	}

	/** Like build(), but returns null instead of throwing on unreadable images. */
	public static function tryBuild(string $title, string $bodyText, string $imgPath, string $signedLine): ?string {
		try {
			return self::build($title, $bodyText, $imgPath, $signedLine);
		} catch (\Throwable) {
			return null;
		}
	}

	/** Signed PDF without an image — the typed name is rendered as the signature. */
	public static function buildTyped(string $title, string $bodyText, string $typedName, string $signedLine): string {
		return self::compose($title, $bodyText, null, $typedName, $signedLine);
	}

	/**
	 * @param array{w:int,h:int,data:string}|null $img gzipped RGB pixels, or null for a typed signature
	 * @param string|null $signedText typed signature name (used when $img is null)
	 * @param string $signedLine e.g. "Max Muster, 18.11.2026"
	 */
	private static function compose(string $title, string $bodyText, ?array $img, ?string $signedText, string $signedLine): string {
		$lines = self::wrap($bodyText, self::PAGE_W - 2 * self::MARGIN, self::FONT_SIZE);

		// lay out: [title, blank, body lines…] paginated, signature block after the last line
		$pages = [];
		$page = [];
		$y = self::PAGE_H - self::MARGIN;
		$consume = function (float $h) use (&$y, &$page, &$pages): bool {
			if ($y - $h < self::MARGIN) {
				$pages[] = $page;
				$page = [];
				$y = self::PAGE_H - self::MARGIN;
			}
			$y -= $h;
			return true;
		};
		$consume(28.0);
		$page[] = ['kind' => 'title', 'y' => $y, 'text' => $title];
		$consume(10.0);
		foreach ($lines as $line) {
			$consume(self::LINE_H);
			$page[] = ['kind' => 'line', 'y' => $y, 'text' => $line];
		}
		$consume(self::SIG_BLOCK_H);
		$page[] = ['kind' => 'sig', 'y' => $y];
		$pages[] = $page;

		// object map: 1 catalog, 2 pages, 3 font, 4 font-bold, 5 font-oblique, 6 image (optional), then 2/page
		$objs = [];
		$kids = [];
		$n = 7;
		foreach ($pages as $p) {
			$kids[] = $n;
			$n += 2;
		}
		$objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objs[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn ($k) => $k . ' 0 R', $kids)) . '] /Count ' . count($kids) . ' >>';
		$objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$objs[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';
		if ($img !== null) {
			$objs[6] = '<< /Type /XObject /Subtype /Image /Width ' . $img['w'] . ' /Height ' . $img['h']
				. ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length ' . strlen($img['data']) . ' >>'
				. "\nstream\n" . $img['data'] . "\nendstream";
		}

		$i = 0;
		foreach ($pages as $p) {
			$pageNo = $kids[$i++];
			$content = '';
			foreach ($p as $el) {
				if ($el['kind'] === 'title') {
					$content .= self::text(self::MARGIN, $el['y'], $el['text'], 'F2', self::TITLE_SIZE);
				} elseif ($el['kind'] === 'line') {
					$content .= self::text(self::MARGIN, $el['y'], $el['text'], 'F1', self::FONT_SIZE);
				} else {
					$content .= self::text(self::MARGIN, $el['y'] + self::SIG_BLOCK_H - 18.0, 'Unterschrift: ' . $signedLine, 'F1', self::FONT_SIZE);
					if ($img !== null) {
						$fit = self::fit($img['w'], $img['h'], self::SIG_IMG_W, self::SIG_IMG_H);
						$content .= 'q ' . self::num($fit[0]) . ' 0 0 ' . self::num($fit[1]) . ' '
							. self::num(self::MARGIN) . ' ' . self::num($el['y']) . ' cm /Im1 Do Q ';
					} else {
						$baseline = $el['y'] + 30.0;
						$content .= self::text(self::MARGIN + 8.0, $baseline, (string)$signedText, 'F3', 26.0);
						$content .= self::num(self::MARGIN) . ' ' . self::num($baseline - 6.0)
							. ' ' . self::num(self::SIG_IMG_W) . ' 0.7 re f ';
					}
				}
			}
			$objs[$pageNo] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::num(self::PAGE_W) . ' ' . self::num(self::PAGE_H) . ']'
				. ' /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >>' . ($img !== null ? ' /XObject << /Im1 6 0 R >>' : '') . ' >> /Contents ' . ($pageNo + 1) . ' 0 R >>';
			$objs[$pageNo + 1] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
		}

		$out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = [0];
		for ($k = 1; $k <= max(array_keys($objs)); $k++) {
			$offsets[$k] = strlen($out);
			$out .= $k . " 0 obj\n" . ($objs[$k] ?? '<< >>') . "\nendobj\n";
		}
		$xref = strlen($out);
		$count = max(array_keys($objs)) + 1;
		$out .= "xref\n0 $count\n0000000000 65535 f \n";
		for ($k = 1; $k < $count; $k++) {
			$out .= sprintf("%010d 00000 n \n", $offsets[$k]);
		}
		$out .= "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
		return $out;
	}

	private static function text(float $x, float $y, string $s, string $font, float $size): string {
		$win = (string)mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
		$esc = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $win);
		return 'BT /' . $font . ' ' . self::num($size) . ' Tf ' . self::num($x) . ' ' . self::num($y) . ' Td (' . $esc . ") Tj ET\n";
	}

	/** Word-wrap to ~width in pt; average Helvetica glyph width ≈ 0.52 em. */
	private static function wrap(string $text, float $width, float $size): array {
		$max = max(20, (int)($width / ($size * 0.52)));
		$out = [];
		foreach (preg_split('/\R/u', $text) ?: [] as $para) {
			if (trim($para) === '') {
				$out[] = '';
				continue;
			}
			foreach (explode("\n", wordwrap($para, $max, "\n", false)) as $l) {
				$out[] = $l;
			}
		}
		return $out === [] ? [''] : $out;
	}

	private static function fit(float $w, float $h, float $mw, float $mh): array {
		$s = min($mw / $w, $mh / $h);
		return [$w * $s, $h * $s];
	}

	private static function num(float $v): string {
		return rtrim(rtrim(sprintf('%.2F', $v), '0'), '.');
	}

	private const MAX_SIG_PIXELS = 40000000;

	/** @return array{w:int,h:int,data:string} gzipped RGB pixel data */
	private static function imgToRgb(string $path): array {
		$info = @getimagesize($path);
		if ($info === false || $info[0] <= 0 || $info[1] <= 0 || $info[0] * $info[1] > self::MAX_SIG_PIXELS) {
			throw new ApiException('Signature image could not be read', 422);
		}
		$src = @imagecreatefromstring((string)file_get_contents($path));
		if ($src === false) {
			throw new ApiException('Signature image could not be read', 422);
		}
		$w = imagesx($src);
		$h = imagesy($src);
		$flat = imagecreatetruecolor($w, $h);
		$white = imagecolorallocate($flat, 255, 255, 255);
		imagefilledrectangle($flat, 0, 0, $w, $h, $white);
		imagealphablending($flat, true);
		imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);
		$rgb = '';
		for ($y = 0; $y < $h; $y++) {
			for ($x = 0; $x < $w; $x++) {
				$rgb .= chr((imagecolorat($flat, $x, $y) >> 16) & 0xFF)
					. chr((imagecolorat($flat, $x, $y) >> 8) & 0xFF)
					. chr(imagecolorat($flat, $x, $y) & 0xFF);
			}
		}
		return ['w' => $w, 'h' => $h, 'data' => gzcompress($rgb, 6)];
	}
}
