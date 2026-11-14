<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service;

use OCA\Leiterrunde\Exception\ApiException;

/**
 * Parses person CSV uploads into canonical field data. Column order does not
 * matter; unknown columns are ignored. Expected header:
 * Vorname;Nachname;Strasse;Hausnummer;PLZ;Ort;Telefon;Mobil;Geburtstag;E-Mail
 * (legacy export columns like ID, Status, Funktion, Notizen,
 * Nextcloud-Benutzer, Geburtsdatum, Geburtsjahr are still recognized).
 */
class PersonCsvParser {
	public const MAX_BYTES = 2 * 1024 * 1024;

	/** Normalized header names (lowercase, alnum only) → canonical key */
	private const COLUMNS = [
		'id' => 'id',
		'vorname' => 'first_name',
		'nachname' => 'last_name',
		'email' => 'email',
		'mail' => 'email',
		'telefon' => 'phone',
		'mobil' => 'mobile',
		'strasse' => 'street',
		'hausnummer' => 'house_number',
		'hausnr' => 'house_number',
		'housenumber' => 'house_number',
		'plz' => 'zip',
		'ort' => 'city',
		'geburtstag' => 'birthdate',
		'geburtsdatum' => 'birthdate',
		'geburtsjahr' => 'birth_year',
		'status' => 'status',
		'funktion' => 'function_label',
		'notizen' => 'notes',
		'nextcloudbenutzer' => 'nc_user_id',
	];

	/** Bytes undefined in Windows-1252 — their presence means the file is likely DOS/OEM (CP850: ü=0x81, ä=0x84, ö=0x94). */
	private const CP1252_UNDEFINED = "/[\x81\x8D\x8F\x90\x9D]/";

	/**
	 * @param array<string,string> $extraColumns normalized header name => person-field key
	 *                                           (custom/plugin columns, e.g. 'kirchort' => 'messdiener_location')
	 * @return list<array{line: int, data: array<string,string>}>
	 * @throws ApiException
	 */
	public static function parse(string $content, array $extraColumns = []): array {
		if (strlen($content) > self::MAX_BYTES) {
			throw new ApiException('File too large', 400);
		}
		if (!mb_check_encoding($content, 'UTF-8')) {
			// Excel exports are commonly Windows-1252; DOS/OEM exports are CP850.
			$from = preg_match(self::CP1252_UNDEFINED, $content) ? 'CP850' : 'Windows-1252';
			$content = mb_convert_encoding($content, 'UTF-8', $from);
		}
		$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
		$firstLine = strtok($content, "\r\n") ?: '';
		$delim = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
		$fp = fopen('php://temp', 'r+');
		fwrite($fp, $content);
		rewind($fp);
		try {
			$header = fgetcsv($fp, 0, $delim, escape: '');
			if ($header === false || $header === [null]) {
				throw new ApiException('CSV header missing', 400);
			}
			$map = [];
			foreach ($header as $i => $name) {
				$norm = self::normalizeHeader((string)$name);
				$key = self::COLUMNS[$norm] ?? $extraColumns[$norm] ?? null;
				if ($key !== null) {
					$map[$key] ??= $i;
				}
			}
			$missing = [];
			foreach (['first_name' => 'Vorname', 'last_name' => 'Nachname'] as $key => $label) {
				if (!isset($map[$key])) {
					$missing[] = $label;
				}
			}
			if ($missing !== []) {
				throw new ApiException('Required columns missing: %s', 400, [implode(', ', $missing)]);
			}
			$rows = [];
			$line = 1;
			while (($row = fgetcsv($fp, 0, $delim, escape: '')) !== false) {
				$line++;
				if ($row === [null]) {
					continue;
				}
				$data = [];
				foreach ($map as $key => $i) {
					$data[$key] = isset($row[$i]) ? trim((string)$row[$i]) : '';
				}
				$rows[] = ['line' => $line, 'data' => $data];
			}
			return $rows;
		} finally {
			fclose($fp);
		}
	}

	public static function normalizeHeader(string $name): string {
		$norm = mb_strtolower(trim($name));
		$norm = str_replace('ß', 'ss', $norm);
		return preg_replace('/[^a-z0-9]+/', '', $norm) ?? '';
	}
}
