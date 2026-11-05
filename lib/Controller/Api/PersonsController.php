<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Db\AuditMapper;
use OCA\Leiterrunde\Db\Person;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Exception\NotFoundException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\Leiterrunde\Service\PersonRules;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class PersonsController extends ApiController {
	public const STATUSES = ['aktiv', 'nwl', 'inaktiv', 'ehemalig'];

	private const FIELDS = [
		'first_name' => 'firstName',
		'last_name' => 'lastName',
		'email' => 'email',
		'phone' => 'phone',
		'mobile' => 'mobile',
		'street' => 'street',
		'zip' => 'zip',
		'city' => 'city',
		'birthdate' => 'birthdate',
		'birth_year' => 'birthYear',
		'status' => 'status',
		'function_label' => 'functionLabel',
		'notes' => 'notes',
		'nc_user_id' => 'ncUserId',
	];

	private const CSV_HEADER = ['ID', 'Vorname', 'Nachname', 'E-Mail', 'Telefon', 'Mobil', 'Straße', 'PLZ', 'Ort', 'Geburtsdatum', 'Geburtsjahr', 'Status', 'Funktion', 'Notizen', 'Nextcloud-Benutzer'];

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private PersonMapper $mapper,
		private AuditMapper $auditMapper,
		private AuditService $audit,
		private \OCP\IUserManager $userManager,
		private PersonRules $rules,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?string $search = null, ?string $status = null): JSONResponse {
		return $this->wrap(function () use ($search, $status) {
			$this->perm->require('persons.read');
			return array_map(fn (Person $p) => $this->withUser($p), $this->mapper->findAll($search, $status));
		});
	}

	/** Names only, for person pickers of members without persons.read. */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function options(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('app.access');
			return array_map(static fn (Person $p) => [
				'id' => $p->getId(), 'first_name' => $p->getFirstName(), 'last_name' => $p->getLastName(), 'status' => $p->getStatus(),
			], $this->mapper->findAll(null, null));
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('persons.read');
			return $this->withUser($this->mapper->find($id));
		});
	}

	#[NoAdminRequired]
	public function create(
		string $first_name,
		string $last_name,
		?string $email = null,
		?string $phone = null,
		?string $mobile = null,
		?string $street = null,
		?string $zip = null,
		?string $city = null,
		?string $birthdate = null,
		?int $birth_year = null,
		?string $status = null,
		?string $function_label = null,
		?string $notes = null,
		?string $nc_user_id = null,
	): JSONResponse {
		return $this->wrap(function () use ($first_name, $last_name, $email, $phone, $mobile, $street, $zip, $city, $birthdate, $birth_year, $status, $function_label, $notes, $nc_user_id) {
			$this->perm->require('persons.write');
			$p = new Person();
			$this->applyFields($p, compact('first_name', 'last_name', 'email', 'phone', 'mobile', 'street', 'zip', 'city', 'birthdate', 'birth_year', 'status', 'function_label', 'notes', 'nc_user_id'));
			$p->setCreatedAt(time());
			$p->setUpdatedAt(time());
			$p = $this->mapper->insert($p);
			$this->audit->diff('person', $p->getId(), null, ['_action' => 'created']);
			return new JSONResponse($p->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		?string $first_name = null,
		?string $last_name = null,
		?string $email = null,
		?string $phone = null,
		?string $mobile = null,
		?string $street = null,
		?string $zip = null,
		?string $city = null,
		?string $birthdate = null,
		?int $birth_year = null,
		?string $status = null,
		?string $function_label = null,
		?string $notes = null,
		?string $nc_user_id = null,
	): JSONResponse {
		return $this->wrap(function () use ($id, $first_name, $last_name, $email, $phone, $mobile, $street, $zip, $city, $birthdate, $birth_year, $status, $function_label, $notes, $nc_user_id) {
			$this->perm->require('persons.write');
			$p = $this->mapper->find($id);
			$before = $p->jsonSerialize();
			$data = compact('first_name', 'last_name', 'email', 'phone', 'mobile', 'street', 'zip', 'city', 'birthdate', 'birth_year', 'status', 'function_label', 'notes', 'nc_user_id');
			// distinguish "field absent" from "set to null": only update keys present in request
			$params = $this->request->getParams();
			foreach (array_keys($data) as $k) {
				if (!array_key_exists($k, $params)) {
					unset($data[$k]);
				}
			}
			$this->applyFields($p, $data);
			$p->setUpdatedAt(time());
			$p = $this->mapper->update($p);
			$this->audit->diff('person', $p->getId(), $before, $p->jsonSerialize());
			return $p->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('persons.delete');
			$p = $this->mapper->find($id);
			$this->mapper->delete($p);
			$this->audit->diff('person', $id, null, ['_action' => 'deleted']);
			return ['ok' => true];
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function birthdays(int $days = 30): JSONResponse {
		return $this->wrap(function () use ($days) {
			$this->perm->require('persons.read');
			$today = new \DateTimeImmutable('today');
			$out = [];
			foreach ($this->mapper->findWithBirthdate() as $p) {
				$bd = \DateTimeImmutable::createFromFormat('!Y-m-d', substr((string)$p->getBirthdate(), 0, 10));
				if ($bd === false) {
					continue;
				}
				$next = $bd->setDate((int)$today->format('Y'), (int)$bd->format('m'), (int)$bd->format('d'));
				if ($next < $today) {
					$next = $next->setDate((int)$today->format('Y') + 1, (int)$bd->format('m'), (int)$bd->format('d'));
				}
				$diff = (int)$today->diff($next)->days;
				if ($next < $today || $diff > $days) {
					continue;
				}
				$out[] = [
					'person_id' => $p->getId(),
					'first_name' => $p->getFirstName(),
					'last_name' => $p->getLastName(),
					'date' => $next->format('Y-m-d'),
					'age' => (int)$next->format('Y') - (int)$bd->format('Y'),
					'days_until' => $diff,
				];
			}
			usort($out, static fn ($a, $b) => $a['days_until'] <=> $b['days_until']);
			return $out;
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function export(): Response {
		try {
			$this->perm->require('persons.export');
		} catch (\Throwable) {
			return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}
		$rows = [self::CSV_HEADER];
		foreach ($this->mapper->findAll() as $p) {
			$rows[] = [
				$p->getId(),
				$p->getFirstName(),
				$p->getLastName(),
				$p->getEmail() ?? '',
				$p->getPhone() ?? '',
				$p->getMobile() ?? '',
				$p->getStreet() ?? '',
				$p->getZip() ?? '',
				$p->getCity() ?? '',
				$this->formatDateDe($p->getBirthdate()),
				$p->getBirthYear() !== null ? (string)$p->getBirthYear() : '',
				$p->getStatus(),
				$p->getFunctionLabel() ?? '',
				$p->getNotes() ?? '',
				$p->getNcUserId() ?? '',
			];
		}
		$csv = "\xEF\xBB\xBF";
		$fp = fopen('php://temp', 'r+');
		foreach ($rows as $row) {
			fputcsv($fp, $row, ';', escape: '');
		}
		rewind($fp);
		$csv .= stream_get_contents($fp);
		fclose($fp);
		return new DataDownloadResponse($csv, 'adressen.csv', 'text/csv; charset=utf-8');
	}

	#[NoAdminRequired]
	public function import(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('persons.import');
			$file = $this->request->getUploadedFile('file');
			if (!is_array($file) || empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
				throw new ApiException('No CSV file uploaded', 400);
			}
			$content = file_get_contents($file['tmp_name']);
			if ($content === false || $content === '') {
				throw new ApiException('Empty file', 400);
			}
			return $this->importCsv($content);
		});
	}

	public function importCsv(string $content): array {
		$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
		$firstLine = strtok($content, "\r\n") ?: '';
		$delim = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
		$fp = fopen('php://temp', 'r+');
		fwrite($fp, $content);
		rewind($fp);
		$header = fgetcsv($fp, 0, $delim, escape: '');
		if ($header === false) {
			throw new ApiException('CSV header missing', 400);
		}
		$header = array_map(static fn ($h) => trim((string)$h), $header);
		$expected = self::CSV_HEADER;
		$created = $updated = $skipped = 0;
		$errors = [];
		$line = 1;
		while (($row = fgetcsv($fp, 0, $delim, escape: '')) !== false) {
			$line++;
			if ($row === [null] || $row === false) {
				continue;
			}
			$data = [];
			foreach ($expected as $i => $colName) {
				$data[$colName] = isset($row[$i]) ? trim((string)$row[$i]) : '';
			}
			try {
				if ($data['Vorname'] === '' && $data['Nachname'] === '') {
					$skipped++;
					continue;
				}
				$status = $data['Status'] !== '' ? $data['Status'] : 'aktiv';
				if (!in_array($status, self::STATUSES, true)) {
					throw new ApiException('Invalid status: %s', 422, [$status]);
				}
				$birthdate = $this->parseDateDe($data['Geburtsdatum']);
				$birthYear = $data['Geburtsjahr'] !== '' ? (int)$data['Geburtsjahr'] : null;
				$person = null;
				if ($data['ID'] !== '' && ctype_digit($data['ID'])) {
					try {
						$person = $this->mapper->find((int)$data['ID']);
					} catch (\Throwable) {
						$person = null;
					}
				}
				if ($person === null && $data['E-Mail'] !== '') {
					$person = $this->mapper->findByEmail($data['E-Mail']);
				}
				$fields = [
					'first_name' => $data['Vorname'],
					'last_name' => $data['Nachname'],
					'email' => $data['E-Mail'] !== '' ? $data['E-Mail'] : null,
					'phone' => $data['Telefon'] !== '' ? $data['Telefon'] : null,
					'mobile' => $data['Mobil'] !== '' ? $data['Mobil'] : null,
					'street' => $data['Straße'] !== '' ? $data['Straße'] : null,
					'zip' => $data['PLZ'] !== '' ? $data['PLZ'] : null,
					'city' => $data['Ort'] !== '' ? $data['Ort'] : null,
					'birthdate' => $birthdate,
					'birth_year' => $birthYear,
					'status' => $status,
					'function_label' => $data['Funktion'] !== '' ? $data['Funktion'] : null,
					'notes' => $data['Notizen'] !== '' ? $data['Notizen'] : null,
					'nc_user_id' => $data['Nextcloud-Benutzer'] !== '' ? $data['Nextcloud-Benutzer'] : null,
				];
				if ($person !== null) {
					$before = $person->jsonSerialize();
					$this->applyFields($person, array_filter($fields, static fn ($v) => $v !== null));
					$person->setUpdatedAt(time());
					$person = $this->mapper->update($person);
					$this->audit->diff('person', $person->getId(), $before, $person->jsonSerialize());
					$updated++;
				} else {
					$person = new Person();
					$this->applyFields($person, $fields);
					$person->setCreatedAt(time());
					$person->setUpdatedAt(time());
					$person = $this->mapper->insert($person);
					$this->audit->diff('person', $person->getId(), null, ['_action' => 'created']);
					$created++;
				}
			} catch (\Throwable $e) {
				$errors[] = ['line' => $line, 'message' => $e->getMessage()];
			}
		}
		fclose($fp);
		return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function audit(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('audit.read');
			return array_map(static fn ($e) => $e->jsonSerialize(), $this->auditMapper->findFor('person', $id));
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
		private function applyFields(Person $p, array $data): void {
		foreach ($data as $key => $value) {
			$prop = self::FIELDS[$key] ?? null;
			if ($prop === null || ($value === null && in_array($key, ['first_name', 'last_name', 'status'], true))) {
				continue;
			}
			switch ($key) {
				case 'first_name':
				case 'last_name':
					if (trim((string)$value) === '') {
						throw new ApiException('First and last name are required', 422);
					}
					$p->{'set' . ucfirst($prop)}((string)$value);
					break;
				case 'status':
					if (!in_array($value, self::STATUSES, true)) {
						throw new ApiException('Invalid status: %s', 422, [$value]);
					}
					$p->setStatus((string)$value);
					break;
				case 'birthdate':
					$d = $this->parseDateDe((string)$value);
					if ($value !== null && $value !== '' && $d === null) {
						throw new ApiException('Invalid date of birth', 422);
					}
					$p->setBirthdate($d);
					break;
				case 'birth_year':
					$p->setBirthYear($value === null || $value === '' ? null : (int)$value);
					break;
				case 'email':
					$p->setEmail($value === '' ? null : $value);
					break;
				case 'nc_user_id':
					$uid = trim((string)$value);
					if ($uid !== '' && $uid !== $p->getNcUserId()) {
						$this->rules->assertUserLinkable($p, $uid);
					}
					$p->setNcUserId($uid === '' ? null : $uid);
					break;
				default:
					if ($prop !== null) {
						$p->{'set' . ucfirst($prop)}($value === '' ? null : $value);
					}
			}
		}
		if ($p->getFirstName() === '' || $p->getLastName() === '') {
			throw new ApiException('First and last name are required', 422);
		}
		[$birthdate, $birthYear] = PersonRules::normalizeBirth($p->getBirthdate(), $p->getBirthYear(), new \DateTimeImmutable('today'));
		$p->setBirthdate($birthdate);
		$p->setBirthYear($birthYear);
	}

	private function withUser(Person $p): array {
		$j = $p->jsonSerialize();
		$u = $p->getNcUserId() !== null ? $this->userManager->get($p->getNcUserId()) : null;
		$j['nc_user_display_name'] = $u?->getDisplayName();
		return $j;
	}

	private function formatDateDe(?string $date): string {
		if ($date === null || $date === '') {
			return '';
		}
		$d = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
		return $d === false ? '' : $d->format('d.m.Y');
	}

	/** Accepts dd.mm.yyyy or yyyy-mm-dd; returns Y-m-d or null. */
	private function parseDateDe(string $date): ?string {
		$date = trim($date);
		if ($date === '') {
			return null;
		}
		foreach (['!d.m.Y', '!Y-m-d'] as $fmt) {
			$d = \DateTimeImmutable::createFromFormat($fmt, $date);
			if ($d !== false) {
				return $d->format('Y-m-d');
			}
		}
		return null;
	}
}
