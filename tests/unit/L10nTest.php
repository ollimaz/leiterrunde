<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests;

use OCA\Leiterrunde\Module\OverviewTile;
use PHPUnit\Framework\TestCase;

/**
 * i18n spec §5: de.json completeness + derived-file sync + no German literals.
 * The app list is a constant — extend in phase 2 with the module apps.
 */
class L10nTest extends TestCase {
	private const TESTDIR = __DIR__;
	private const APPS = ['leiterrunde', 'leiterrunde_schulungen', 'leiterrunde_finanzen', 'leiterrunde_veranstaltungen'];
	private const BASE = self::TESTDIR . '/../../..';

	private function l10nDir(string $app): string {
		return self::BASE . '/' . $app . '/l10n';
	}

	private function scanKeys(string $app): array {
		$keys = [];
		$dirs = [self::BASE . "/$app/js", self::BASE . "/$app/lib", self::BASE . "/$app/templates"];
		foreach ($dirs as $dir) {
			$dir = realpath($dir) ?: $dir;
			if (!is_dir($dir)) { continue; }
			$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
			foreach ($it as $f) {
				if (!preg_match('/\.(mjs|js|php)$/', $f->getFilename())) { continue; }
				if (str_contains((string)$f, '/tests/')) { continue; }
				$src = file_get_contents((string)$f);
				preg_match_all("/_t\\('((?:[^'\\\\]|\\\\.)*)'/", $src, $m);
				$keys = array_merge($keys, $m[1]);
				preg_match_all("/_n\\('((?:[^'\\\\]|\\\\.)*)',\\s*'((?:[^'\\\\]|\\\\.)*)'/", $src, $m);
				foreach ($m[1] as $i => $one) {
					$keys[] = '_'.$one.'_::_'.$m[2][$i].'_';
				}
				preg_match_all("/->t\\('((?:[^'\\\\]|\\\\.)*)'/", $src, $m);
				$keys = array_merge($keys, $m[1]);
				preg_match_all('/\\$t\\(\'((?:[^\'\\\\]|\\\\.)*)\'/', $src, $m);
				$keys = array_merge($keys, $m[1]);
				preg_match_all('/(?<![\w$>_])t\\(\'((?:[^\'\\\\]|\\\\.)*)\'/', $src, $m);
				$keys = array_merge($keys, $m[1]);
				preg_match_all("/new (?:Api|Forbidden)Exception\\('((?:[^'\\\\]|\\\\.)*)'/", $src, $m);
				$keys = array_merge($keys, $m[1]);
				preg_match_all("/->n\\('((?:[^'\\\\]|\\\\.)*)',\\s*'((?:[^'\\\\]|\\\\.)*)'/", $src, $m);
				foreach ($m[1] as $i => $one) {
					$keys[] = '_'.$one.'_::_'.$m[2][$i].'_';
				}
			}
		}
		return array_values(array_unique($keys));
	}

	private function jsTranslations(string $file): array {
		$js = file_get_contents($file);
		if (!preg_match('/OC\.L10N\.register\("[^"]+",\s*(\{.*\}),\s*"[a-z_+=;n ()!]+"\);?\s*$/s', $js, $m)) {
			// tolerate appended whitespace/parens
			$json = substr($js, strpos($js, '{'), strrpos($js, '}') - strpos($js, '{') + 1);
			return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		}
		return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
	}

	public function testCompletenessAndSync(): void {
		foreach (self::APPS as $app) {
			$dir = $this->l10nDir($app);
			$doc = json_decode(file_get_contents("$dir/de.json"), true, 512, JSON_THROW_ON_ERROR);
			$map = $doc['translations'];
			$keys = $this->scanKeys($app);
			$missing = array_diff($keys, array_keys($map), [$app]);
			$this->assertSame([], array_values($missing), "$app: missing de.json keys");
			$unused = array_diff(array_keys($map), $keys);
			$this->assertSame([], array_values($unused), "$app: unused de.json keys (keys=" . count($keys) . " base=" . self::BASE . ")");
			foreach ($map as $k => $v) {
				$this->assertNotSame('', is_array($v) ? implode('', $v) : $v, "$app: empty translation $k");
			}
			foreach (['de.js', 'de_DE.js'] as $f) {
				$this->assertFileExists("$dir/$f");
				$this->assertEquals($map, $this->jsTranslations("$dir/$f"), "$app: $f out of sync");
			}
			$deDe = json_decode(file_get_contents("$dir/de_DE.json"), true, 512, JSON_THROW_ON_ERROR);
			$this->assertSame($doc, $deDe, "$app: de_DE.json out of sync");
		}
	}

	public function testNoGermanLiterals(): void {
		$allowlist = ['l10n/', 'l10n-tools/', '/tests/',
			'PersonsController.php', // German CSV column headers must stay byte-identical (spec §3)
			'DocumentStorageService.php', // filename-sanitize char class needs umlauts
			'GenericParser.php', 'SparkasseParser.php', 'CsvImporter.php', // German bank-mail keywords are parsing data
		];
		$checked = 0;
		$problems = [];
		foreach (self::APPS as $app) {
			foreach (['js', 'lib', 'templates'] as $dir) {
				$root = self::BASE . "/$app/$dir";
				$root = realpath($root) ?: $root;
				if (!is_dir($root)) { continue; }
				$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
				foreach ($it as $f) {
					$rel = (string)$f;
					$rel = realpath($rel) ?: $rel;
					foreach ($allowlist as $a) { if (str_contains($rel, $a)) { continue 2; } }
					if (!preg_match('/\.(mjs|js|php)$/', $f->getFilename())) { continue; }
					$src = file_get_contents($rel);
					// strip comments before checks
					$code = preg_replace_callback(
						'#("(?:[^"\\\\\n]|\\\\.)*"|\'(?:[^\'\\\\\n]|\\\\.)*\'|`(?:[^`\\\\]|\\\\.)*`)|/\*.*?\*/|(?<![:\\\\])//[^\n]*#s',
						static fn (array $m) => ($m[1] ?? '') !== '' ? $m[1] : '',
						$src,
					);
					// collect user-visible text: quoted string literals + raw HTML outside PHP tags
					$visible = [];
					if (str_ends_with($f->getFilename(), '.php') && str_contains($rel, '/templates/')) {
						$tpl = preg_replace('#<\?php.*?\?>#s', ' ', $code);
						if (trim(strip_tags($tpl)) !== '') { $visible[] = $tpl; }
					}
					preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'|`((?:[^`\\\\]|\\\\.)*)`/s', $code, $mm);
					$visible = array_merge($visible, array_filter([...$mm[1], ...$mm[2], ...$mm[3]], static fn ($s) => $s !== ''));
					$code = implode('\n', $visible);
					foreach (['/[äöüÄÖÜß]/u', '/ und /', '/Keine /', '/\b(Anmeldungen|Mitglied|Ablage|Hinweis|Rolle|Standard|einreichen|beschränkt|lesen|schreiben|Entfernen|Gespeichert|Bearbeiten|Notizen|Ausgestellt|Zahlen|Importieren|Minuten|Pflicht|Kostenpunkt|Vorschau|Dein|Verbindung|Monate|kommagetrennt|Leer|Volle|Verwaltung|Unbekannter|Platzhalter|Trainingsstunden|Einsicht|Finanzen|Schulungen|Veranstaltungen|Personen|Wirklich|Letzter|Abruf)\b|z\. B\./u'] as $re) {
						if (preg_match_all($re, $code, $hits)) {
							foreach (array_unique($hits[0]) as $hit) {
								$problems[] = "$app: German text \"$hit\" in $rel";
							}
						}
					}
					$checked++;
				}
			}
		}
		$this->assertGreaterThan(0, $checked);
		$this->assertSame([], array_values(array_unique($problems)), implode("\n", array_unique($problems)));
	}

	public function testOverviewTileIdSerialization(): void {
		$t = new OverviewTile('core.persons.active', 'Active people', 'info', null, [], '/apps/leiterrunde/');
		$this->assertSame('core.persons.active', $t->id);
		$this->assertSame('Active people', $t->title);
	}
}
