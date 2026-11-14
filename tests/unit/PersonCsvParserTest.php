<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests\Unit;

use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\PersonCsvParser;
use PHPUnit\Framework\TestCase;

class PersonCsvParserTest extends TestCase {
	private const HEADER = 'Vorname;Nachname;Straße;Hausnummer;PLZ;Ort;Telefon;Mobil;Geburtstag;E-Mail';

	public function testNewHeaderMapsAllColumns(): void {
		$rows = PersonCsvParser::parse(self::HEADER . "\nMax;Mustermann;Hauptstr.;1a;12345;Musterstadt;01234;56789;14.03.2008;max@example.org\n");
		$this->assertCount(1, $rows);
		$this->assertSame(2, $rows[0]['line']);
		$this->assertSame([
			'first_name' => 'Max',
			'last_name' => 'Mustermann',
			'street' => 'Hauptstr.',
			'house_number' => '1a',
			'zip' => '12345',
			'city' => 'Musterstadt',
			'phone' => '01234',
			'mobile' => '56789',
			'birthdate' => '14.03.2008',
			'email' => 'max@example.org',
		], $rows[0]['data']);
	}

	public function testColumnOrderDoesNotMatter(): void {
		$rows = PersonCsvParser::parse("E-Mail;Nachname;Vorname\na@b.c;Mustermann;Max\n");
		$this->assertSame('Max', $rows[0]['data']['first_name']);
		$this->assertSame('Mustermann', $rows[0]['data']['last_name']);
		$this->assertSame('a@b.c', $rows[0]['data']['email']);
	}

	public function testUnknownColumnsAreIgnored(): void {
		$rows = PersonCsvParser::parse("Vorname;Foo;Nachname\nMax;x;Mustermann\n");
		$this->assertArrayNotHasKey('Foo', $rows[0]['data']);
		$this->assertSame('Mustermann', $rows[0]['data']['last_name']);
	}

	public function testLegacyExportHeaderIsAccepted(): void {
		$csv = "ID;Vorname;Nachname;E-Mail;Telefon;Mobil;Straße;PLZ;Ort;Geburtsdatum;Geburtsjahr;Status;Funktion;Notizen;Nextcloud-Benutzer\n"
			. "7;Max;Mustermann;max@example.org;;01;Hauptstr. 1;12345;Musterstadt;14.03.2008;;aktiv;WL;Notiz;ncmax\n";
		$rows = PersonCsvParser::parse($csv);
		$d = $rows[0]['data'];
		$this->assertSame('7', $d['id']);
		$this->assertSame('14.03.2008', $d['birthdate']);
		$this->assertSame('aktiv', $d['status']);
		$this->assertSame('WL', $d['function_label']);
		$this->assertSame('Notiz', $d['notes']);
		$this->assertSame('ncmax', $d['nc_user_id']);
	}

	public function testMissingRequiredColumnThrows(): void {
		$this->expectException(ApiException::class);
		$this->expectExceptionMessage('Required columns missing: %s');
		PersonCsvParser::parse("Vorname;Ort\nMax;Musterstadt\n");
	}

	public function testEmptyHeaderThrows(): void {
		$this->expectException(ApiException::class);
		$this->expectExceptionMessage('CSV header missing');
		PersonCsvParser::parse('');
	}

	public function testOversizedFileThrows(): void {
		$this->expectException(ApiException::class);
		$this->expectExceptionMessage('File too large');
		PersonCsvParser::parse(str_repeat('a', PersonCsvParser::MAX_BYTES + 1));
	}

	public function testBomIsStripped(): void {
		$rows = PersonCsvParser::parse("\xEF\xBB\xBF" . "Vorname;Nachname\nMax;Mustermann\n");
		$this->assertSame('Max', $rows[0]['data']['first_name']);
	}

	public function testWindows1252IsConverted(): void {
		$content = mb_convert_encoding("Vorname;Nachname;Straße\nBjörn;Müller;Hauptstr. 1\n", 'Windows-1252', 'UTF-8');
		$rows = PersonCsvParser::parse($content);
		$this->assertSame('Björn', $rows[0]['data']['first_name']);
		$this->assertSame('Hauptstr. 1', $rows[0]['data']['street']);
	}

	public function testCp850IsDetectedAndConverted(): void {
		// CP850: ü=0x81, ä=0x84, ö=0x94 — bytes undefined in Windows-1252 trigger CP850.
		$content = mb_convert_encoding("Vorname;Nachname;Ort\nBjörn;Müller;Gütersloh\n", 'CP850', 'UTF-8');
		$rows = PersonCsvParser::parse($content);
		$this->assertSame('Björn', $rows[0]['data']['first_name']);
		$this->assertSame('Müller', $rows[0]['data']['last_name']);
		$this->assertSame('Gütersloh', $rows[0]['data']['city']);
	}

	public function testEMailWithSpacesAndDashIsMapped(): void {
		$rows = PersonCsvParser::parse("Vorname;Nachname;E – Mail\nMax;Mustermann;max@example.org\n");
		$this->assertSame('max@example.org', $rows[0]['data']['email']);
	}

	public function testHausnummerAliasesAreMapped(): void {
		foreach (['Hausnummer', 'Hausnr', 'Haus-Nr.'] as $col) {
			$rows = PersonCsvParser::parse("Vorname;Nachname;$col\nMax;Mustermann;7\n");
			$this->assertSame('7', $rows[0]['data']['house_number'], $col);
		}
	}

	public function testCommaDelimiterIsDetected(): void {
		$rows = PersonCsvParser::parse("Vorname,Nachname\nMax,Mustermann\n");
		$this->assertSame('Mustermann', $rows[0]['data']['last_name']);
	}

	public function testBlankLinesAreSkipped(): void {
		$rows = PersonCsvParser::parse("Vorname;Nachname\nMax;Mustermann\n\n;NurNachname\n");
		$this->assertCount(2, $rows);
		$this->assertSame(4, $rows[1]['line']);
	}

	public function testQuotedFieldsAndCaseInsensitiveHeader(): void {
		$rows = PersonCsvParser::parse("VORNAME;nachname\n\"Max;X\";\"Müller, Anna\"\n");
		$this->assertSame('Max;X', $rows[0]['data']['first_name']);
		$this->assertSame('Müller, Anna', $rows[0]['data']['last_name']);
	}

	public function testExtraColumnsMapToCustomFieldKeys(): void {
		$extra = ['kirchort' => 'messdiener_location', 'defaultkirchort' => 'messdiener_location'];
		$rows = PersonCsvParser::parse("Vorname;Nachname;Kirchort\nMax;Mustermann;Sankt Martin\n", $extra);
		$this->assertSame('Sankt Martin', $rows[0]['data']['messdiener_location']);
	}

	public function testExtraColumnsDoNotOverrideCoreColumns(): void {
		$extra = ['email' => 'custom_mail'];
		$rows = PersonCsvParser::parse("Vorname;Nachname;E-Mail\nMax;Mustermann;max@example.org\n", $extra);
		$this->assertSame('max@example.org', $rows[0]['data']['email']);
		$this->assertArrayNotHasKey('custom_mail', $rows[0]['data']);
	}
}
