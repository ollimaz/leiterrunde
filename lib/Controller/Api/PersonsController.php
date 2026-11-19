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
use OCA\Leiterrunde\Service\DocumentTargetService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\Leiterrunde\Service\PersonCsvParser;
use OCA\Leiterrunde\Service\PersonRules;
use OCA\Leiterrunde\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class PersonsController extends ApiController {
	private const FIELDS = [
		'first_name' => 'firstName',
		'last_name' => 'lastName',
		'email' => 'email',
		'phone' => 'phone',
		'mobile' => 'mobile',
		'street' => 'street',
		'house_number' => 'houseNumber',
		'zip' => 'zip',
		'city' => 'city',
		'birthdate' => 'birthdate',
		'birth_year' => 'birthYear',
		'status' => 'status',
		'function_label' => 'functionLabel',
		'notes' => 'notes',
		'nc_user_id' => 'ncUserId',
	];

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private PersonMapper $mapper,
		private AuditMapper $auditMapper,
		private AuditService $audit,
		private \OCP\IUserManager $userManager,
		private PersonRules $rules,
		private \OCA\Leiterrunde\Service\PersonFieldsService $personFields,
		private SettingsService $settings,
		private DocumentTargetService $docTargets,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?string $search = null, ?string $status = null, ?string $active = null): JSONResponse {
		return $this->wrap(function () use ($search, $status, $active) {
			$this->perm->require('persons.read');
			return array_map(fn (Person $p) => $this->withUser($p), $this->mapper->findAll($search, $status, 500, 0, $active === '1'));
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
		?string $house_number = null,
		?string $zip = null,
		?string $city = null,
		?string $birthdate = null,
		?int $birth_year = null,
		?string $status = null,
		?string $function_label = null,
		?string $notes = null,
		?string $nc_user_id = null,
		?array $custom_fields = null,
	): JSONResponse {
		return $this->wrap(function () use ($first_name, $last_name, $email, $phone, $mobile, $street, $house_number, $zip, $city, $birthdate, $birth_year, $status, $function_label, $notes, $nc_user_id, $custom_fields) {
			$this->perm->require('persons.write');
			$p = new Person();
			$this->applyFields($p, compact('first_name', 'last_name', 'email', 'phone', 'mobile', 'street', 'house_number', 'zip', 'city', 'birthdate', 'birth_year', 'status', 'function_label', 'notes', 'nc_user_id'));
			$p->setCreatedAt(time());
			if ($custom_fields !== null) {
				$p->setCustomFields($this->personFields->merge($p->getCustomFields(), $custom_fields));
			}
			$p->setUpdatedAt(time());
			$p = $this->mapper->insert($p);
			$this->audit->diff('person', $p->getId(), null, ['_action' => 'created']);
			$this->docTargets->syncPerson($p);
			return new JSONResponse($this->withUser($p), Http::STATUS_CREATED);
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
		?string $house_number = null,
		?string $zip = null,
		?string $city = null,
		?string $birthdate = null,
		?int $birth_year = null,
		?string $status = null,
		?string $function_label = null,
		?string $notes = null,
		?string $nc_user_id = null,
		?array $custom_fields = null,
	): JSONResponse {
		return $this->wrap(function () use ($id, $first_name, $last_name, $email, $phone, $mobile, $street, $house_number, $zip, $city, $birthdate, $birth_year, $status, $function_label, $notes, $nc_user_id, $custom_fields) {
			$this->perm->require('persons.write');
			$p = $this->mapper->find($id);
			$before = $p->jsonSerialize();
			$data = compact('first_name', 'last_name', 'email', 'phone', 'mobile', 'street', 'house_number', 'zip', 'city', 'birthdate', 'birth_year', 'status', 'function_label', 'notes', 'nc_user_id');
			// distinguish "field absent" from "set to null": only update keys present in request
			$params = $this->request->getParams();
			foreach (array_keys($data) as $k) {
				if (!array_key_exists($k, $params)) {
					unset($data[$k]);
				}
			}
			$this->applyFields($p, $data);
			if ($custom_fields !== null) {
				$p->setCustomFields($this->personFields->merge($p->getCustomFields(), $custom_fields));
			}
			$p->setUpdatedAt(time());
			$p = $this->mapper->update($p);
			$this->audit->diff('person', $p->getId(), $before, $p->jsonSerialize());
			$this->docTargets->syncPerson($p);
			return $this->withUser($p);
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
	public function import(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('persons.import');
			$file = $this->request->getUploadedFile('file');
			if (!is_array($file) || empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
				throw new ApiException('No CSV file uploaded', 400);
			}
			if (($file['size'] ?? 0) > PersonCsvParser::MAX_BYTES) {
				throw new ApiException('File too large', 400);
			}
			$content = file_get_contents($file['tmp_name']);
			if ($content === false || $content === '') {
				throw new ApiException('Empty file', 400);
			}
			return $this->importCsv($content);
		});
	}

	public function importCsv(string $content): array {
		$defs = array_column($this->personFields->definitions(), null, 'key');
		$extraColumns = [];
		foreach ($defs as $key => $def) {
			$extraColumns[PersonCsvParser::normalizeHeader((string)$def['label'])] = $key;
			$extraColumns[PersonCsvParser::normalizeHeader($key)] = $key;
			if (str_ends_with(PersonCsvParser::normalizeHeader((string)$def['label']), 'kirchort')) {
				$extraColumns['kirchort'] = $key;
			}
		}
		$created = $updated = $skipped = 0;
		$errors = [];
		$statusDefs = $this->settings->personStatusDefs();
		foreach (PersonCsvParser::parse($content, $extraColumns) as $row) {
			$line = $row['line'];
			$data = $row['data'];
			try {
				if (($data['first_name'] ?? '') === '' && ($data['last_name'] ?? '') === '') {
					$skipped++;
					continue;
				}
				$status = null;
				if (($data['status'] ?? '') !== '') {
					foreach ($statusDefs as $d) {
						if ($data['status'] === $d['key'] || ($d['label'] !== '' && mb_strtolower($data['status']) === mb_strtolower($d['label']))) {
							$status = $d['key'];
							break;
						}
					}
					if ($status === null) {
						throw new ApiException('Invalid status: %s', 422, [$data['status']]);
					}
				}
				$birthdate = $this->parseDateDe($data['birthdate'] ?? '');
				$birthYear = ($data['birth_year'] ?? '') !== '' ? (int)$data['birth_year'] : null;
				$person = null;
				$id = $data['id'] ?? '';
				if ($id !== '' && ctype_digit($id)) {
					try {
						$person = $this->mapper->find((int)$id);
					} catch (\Throwable) {
						$person = null;
					}
				}
				if ($person === null && ($data['email'] ?? '') !== '') {
					$person = $this->mapper->findByEmail($data['email']);
				}
				if ($person === null) {
					$person = $this->mapper->findByName($data['first_name'] ?? '', $data['last_name'] ?? '');
				}
				$fields = [
					'first_name' => $data['first_name'] ?? '',
					'last_name' => $data['last_name'] ?? '',
					'email' => ($data['email'] ?? '') !== '' ? $data['email'] : null,
					'phone' => ($data['phone'] ?? '') !== '' ? $data['phone'] : null,
					'mobile' => ($data['mobile'] ?? '') !== '' ? $data['mobile'] : null,
					'street' => ($data['street'] ?? '') !== '' ? $data['street'] : null,
					'house_number' => ($data['house_number'] ?? '') !== '' ? $data['house_number'] : null,
					'zip' => ($data['zip'] ?? '') !== '' ? $data['zip'] : null,
					'city' => ($data['city'] ?? '') !== '' ? $data['city'] : null,
					'birthdate' => $birthdate,
					'birth_year' => $birthYear,
					'status' => $status,
					'function_label' => ($data['function_label'] ?? '') !== '' ? $data['function_label'] : null,
					'notes' => ($data['notes'] ?? '') !== '' ? $data['notes'] : null,
					'nc_user_id' => ($data['nc_user_id'] ?? '') !== '' ? $data['nc_user_id'] : null,
				];
				$custom = [];
				foreach (array_intersect_key($data, $defs) as $key => $v) {
					if ($v === '') {
						continue;
					}
					if (($defs[$key]['type'] ?? 'text') === 'select') {
						foreach ($defs[$key]['options'] ?? [] as $o) {
							$ov = is_array($o) ? (string)$o['value'] : (string)$o;
							$ol = is_array($o) ? (string)$o['label'] : (string)$o;
							if (mb_strtolower($v) === mb_strtolower($ov) || mb_strtolower($v) === mb_strtolower($ol)) {
								$v = $ov;
								break;
							}
						}
					}
					$custom[$key] = $v;
				}
				if ($person !== null) {
					$before = $person->jsonSerialize();
					$this->applyFields($person, array_filter($fields, static fn ($v) => $v !== null));
					if ($custom !== []) {
						$person->setCustomFields($this->personFields->merge($person->getCustomFields(), $custom));
					}
					$person->setUpdatedAt(time());
					$person = $this->mapper->update($person);
					$this->audit->diff('person', $person->getId(), $before, $person->jsonSerialize());
					$this->docTargets->syncPerson($person);
					$updated++;
				} else {
					$person = new Person();
					$this->applyFields($person, $fields);
					if ($custom !== []) {
						$person->setCustomFields($this->personFields->merge($person->getCustomFields(), $custom));
					}
					$person->setCreatedAt(time());
					$person->setUpdatedAt(time());
					$person = $this->mapper->insert($person);
					$this->audit->diff('person', $person->getId(), null, ['_action' => 'created']);
					$this->docTargets->syncPerson($person);
					$created++;
				}
			} catch (\Throwable $e) {
				$errors[] = ['line' => $line, 'message' => $e->getMessage()];
			}
		}
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
					if (!in_array($value, $this->settings->personStatuses(), true)) {
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function fields(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('persons.read');
			return $this->personFields->definitions();
		});
	}

	/** Dry-run: unlinked persons with an email, and matching NC accounts by exact email. */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function mapAccounts(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('persons.write');
			$out = [];
			foreach ($this->mapper->findAll(null, null, 2000) as $p) {
				if ($p->getNcUserId() !== null || !$p->getEmail()) {
					continue;
				}
				$matches = [];
				foreach ($this->userManager->getByEmail($p->getEmail()) as $u) {
					if (mb_strtolower((string)$u->getEMailAddress()) === mb_strtolower($p->getEmail())) {
						$matches[] = ['uid' => $u->getUID(), 'display_name' => $u->getDisplayName()];
					}
				}
				$out[] = [
					'person_id' => $p->getId(),
					'name' => $p->getLastName() . ', ' . $p->getFirstName(),
					'email' => $p->getEmail(),
					'matches' => $matches,
				];
			}
			return $out;
		});
	}

	/** Apply the exact-email mapping; only unambiguous single matches are persisted. */
	#[NoAdminRequired]
	public function saveMapAccounts(?array $pairs = null): JSONResponse {
		return $this->wrap(function () use ($pairs) {
			$this->perm->require('persons.write');
			if (!is_array($pairs) || $pairs === [] || count($pairs) > 2000) {
				throw new ApiException('pairs: 1–2000 entries required', 422);
			}
			$applied = 0;
			$errors = [];
			foreach ($pairs as $pair) {
				$pid = (int)($pair['person_id'] ?? 0);
				$uid = trim((string)($pair['uid'] ?? ''));
				try {
					$p = $this->mapper->find($pid);
					$email = $p->getEmail();
					if ($email === null || $email === '') {
						throw new ApiException('Person has no email', 422);
					}
					$candidate = false;
					foreach ($this->userManager->getByEmail($email) as $u) {
						if ($u->getUID() === $uid && mb_strtolower((string)$u->getEMailAddress()) === mb_strtolower($email)) {
							$candidate = true;
							break;
						}
					}
					if (!$candidate) {
						throw new ApiException('Not an email match for this person', 422);
					}
					$this->rules->assertUserLinkable($p, $uid);
					$before = $p->jsonSerialize();
					$p->setNcUserId($uid);
					$p->setUpdatedAt(time());
					$this->mapper->update($p);
					$this->audit->diff('person', $p->getId(), $before, $p->jsonSerialize());
					$this->docTargets->syncPerson($p);
					$applied++;
				} catch (\OCP\AppFramework\Db\DoesNotExistException) {
					$errors[] = ['person_id' => $pid, 'message' => 'Person not found'];
				} catch (\Throwable $e) {
					$errors[] = ['person_id' => $pid, 'message' => $e->getMessage()];
				}
			}
			return ['applied' => $applied, 'errors' => $errors];
		});
	}

	private function withUser(Person $p): array {
		$j = $p->jsonSerialize();
		$j['custom_fields'] = (object)$this->personFields->visible($p->getCustomFields());
		$u = $p->getNcUserId() !== null ? $this->userManager->get($p->getNcUserId()) : null;
		$j['nc_user_display_name'] = $u?->getDisplayName();
		return $j;
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
