<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Exception\ApiException;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Security\ICrypto;

class SettingsService {
	public const KEYS = [
		'remote_url', 'shared_secret', 'default_reg_fields',
		'imap_host', 'imap_port', 'imap_tls', 'imap_user', 'imap_password',
		'imap_folder', 'imap_interval', 'imap_enabled', 'imap_last_poll', 'imap_last_status',
		'warn_days', 'hours_required', 'hours_window_months', 'scope_statuses',
		'notify_mail', 'notify_nc', 'notify_person',
		'notify_roles_training', 'notify_roles_hours', 'notify_roles_finance',
		'expense_categories', 'file_folder', 'person_fields', 'person_statuses',
	];

	private const ENCRYPTED = ['shared_secret', 'imap_password'];

	private const DEFAULTS = [
		'remote_url' => '',
		'default_reg_fields' => '[]',
		'imap_host' => '',
		'imap_port' => '993',
		'imap_tls' => 'ssl',
		'imap_user' => '',
		'imap_folder' => 'INBOX',
		'imap_interval' => '15',
		'imap_enabled' => '0',
		'warn_days' => '60',
		'hours_required' => '8',
		'hours_window_months' => '36',
		'scope_statuses' => 'aktiv,nwl',
		'notify_mail' => '1',
		'notify_nc' => '1',
		'notify_person' => '1',
		'notify_roles_training' => '["schulung"]',
		'notify_roles_hours' => '["schulung"]',
		'notify_roles_finance' => '["kasse"]',
		'expense_categories' => 'Material,Fahrtkosten,Verpflegung,Sonstiges',
		'file_folder' => 'Leiterrunde',
		'person_statuses' => 'aktiv,nwl,inaktiv,ehemalig',
	];

	public function __construct(
		private IAppConfig $appConfig,
		private RoleService $roleService,
		private IConfig $config,
		private ICrypto $crypto,
	) {
	}

	public function get(string $key, string $default = ''): string {
		if (in_array($key, self::ENCRYPTED, true)) {
			$raw = $this->appConfig->getValueString(Application::APP_ID, $key, '');
			if ($raw === '') {
				return '';
			}
			try {
				return $this->crypto->decrypt($raw);
			} catch (\Throwable) {
				return '';
			}
		}
		return $this->appConfig->getValueString(Application::APP_ID, $key, self::DEFAULTS[$key] ?? $default);
	}

	public function getInt(string $key, int $default = 0): int {
		return (int)$this->get($key, (string)$default);
	}

	public function getBool(string $key): bool {
		return $this->get($key) === '1' || $this->get($key) === 'true';
	}

	public function set(string $key, string $value): void {
		if (in_array($key, self::ENCRYPTED, true)) {
			$this->appConfig->setValueString(Application::APP_ID, $key, $this->crypto->encrypt($value), false, true);
			return;
		}
		$this->appConfig->setValueString(Application::APP_ID, $key, $value);
	}

	/** @return string[] */
	public function scopeStatuses(): array {
		return array_values(array_filter(array_map('trim', explode(',', $this->get('scope_statuses')))));
	}

	public const STATUS_COLORS = ['ok', 'info', 'warn', 'fail', 'missing'];
	private const STATUS_COLOR_DEFAULTS = ['aktiv' => 'ok', 'nwl' => 'info', 'inaktiv' => 'missing', 'ehemalig' => 'missing'];
	private const REQUIRED_STATUSES = ['aktiv', 'ehemalig'];

	/** Configured person status keys. 'aktiv' (default) and 'ehemalig' (archive) are required. @return string[] */
	public function personStatuses(): array {
		return array_column($this->personStatusDefs(), 'key');
	}

	/** @return list<array{key: string, label: string, color: string}> */
	public function personStatusDefs(): array {
		try {
			return self::normalizeStatusDefs($this->get('person_statuses'));
		} catch (ApiException) {
			return self::normalizeStatusDefs(self::DEFAULTS['person_statuses']);
		}
	}

	/**
	 * Accepts a JSON/array list of {key, label?, color?} or a comma string
	 * (optionally 'key:Label' per entry) and returns normalized status defs.
	 * @return list<array{key: string, label: string, color: string}> @throws ApiException
	 */
	public static function normalizeStatusDefs(mixed $raw): array {
		if (is_array($raw)) {
			$items = $raw;
		} else {
			$dec = json_decode((string)$raw, true);
			$items = is_array($dec) ? $dec : [];
			if (!is_array($dec)) {
				foreach (explode(',', (string)$raw) as $t) {
					if (trim($t) !== '') {
						$parts = explode(':', trim($t), 2);
						$items[] = ['key' => $parts[0], 'label' => $parts[1] ?? ''];
					}
				}
			}
		}
		$out = [];
		foreach ($items as $item) {
			if (is_string($item)) {
				$item = ['key' => $item];
			}
			if (!is_array($item)) {
				throw new ApiException('Invalid status: %s', 422, [json_encode($item)]);
			}
			$k = preg_replace('/\s+/', '_', mb_strtolower(trim((string)($item['key'] ?? ''))));
			if (!preg_match('/^[a-z0-9_-]{1,30}$/', (string)$k) || isset($out[$k])) {
				throw new ApiException('Invalid status: %s', 422, [(string)($item['key'] ?? '')]);
			}
			$label = trim((string)($item['label'] ?? ''));
			if (mb_strlen($label) > 60) {
				throw new ApiException('Status label too long: %s', 422, [$k]);
			}
			$color = (string)($item['color'] ?? '');
			$out[$k] = [
				'key' => $k,
				'label' => $label,
				'color' => in_array($color, self::STATUS_COLORS, true) ? $color : (self::STATUS_COLOR_DEFAULTS[$k] ?? 'info'),
			];
		}
		if (count($out) > 30) {
			throw new ApiException('Too many statuses', 422);
		}
		foreach (self::REQUIRED_STATUSES as $req) {
			if (!isset($out[$req])) {
				throw new ApiException('Required statuses missing: %s', 422, [$req]);
			}
		}
		return array_values($out);
	}

	/** @return string[] */
	public function notifyGroups(string $which): array {
		$roles = json_decode($this->get('notify_roles_' . $which), true);
		if (!is_array($roles)) {
			return [];
		}
		$map = $this->roleService->roleGroups();
		$gids = [];
		foreach ($roles as $r) {
			foreach ($map[$r] ?? [] as $g) {
				$gids[] = $g;
			}
		}
		return array_values(array_unique($gids));
	}

	/** @return string[] role ids configured for a warning type */
	public function notifyRoles(string $which): array {
		$roles = json_decode($this->get('notify_roles_' . $which), true);
		return is_array($roles) ? $roles : [];
	}

	/** Validate and persist a settings payload. Returns normalized values applied. */
	public function update(array $values): array {
		$applied = [];
		foreach ($values as $key => $value) {
			if (!in_array($key, self::KEYS, true)) {
				throw new ApiException('Unknown setting: %s', 400, [$key]);
			}
			switch ($key) {
				case 'remote_url':
					$value = (string)$value;
					if ($value !== '') {
						if (!str_starts_with($value, 'https://')) {
							if (!(str_starts_with($value, 'http://') && $this->config->getSystemValueBool('debug'))) {
								throw new ApiException('Remote URL must start with https://', 422);
							}
						}
						if (filter_var($value, FILTER_VALIDATE_URL) === false) {
							throw new ApiException('Invalid remote URL', 422);
						}
					}
					break;
				case 'person_fields':
					$fields = is_string($value) ? json_decode($value, true) : null;
					if (!is_array($fields)) {
						throw new ApiException('Invalid person columns', 422);
					}
					PersonFieldsService::validateDefinitions($fields);
					break;
				case 'person_statuses':
					$value = json_encode(self::normalizeStatusDefs($value), JSON_THROW_ON_ERROR);
					break;
				case 'default_reg_fields':
					$this->validateRegFields((string)$value);
					break;
				case 'imap_tls':
					if (!in_array($value, ['ssl', 'starttls', 'none'], true)) {
						throw new ApiException('Invalid value for imap_tls', 422);
					}
					break;
				case 'imap_interval':
					if (!in_array((string)$value, ['15', '30', '60'], true)) {
						throw new ApiException('Invalid IMAP interval (15, 30 or 60)', 422);
					}
					break;
				case 'imap_port':
				case 'warn_days':
				case 'hours_required':
				case 'hours_window_months':
				case 'imap_last_poll':
					if (!is_numeric($value) || (int)$value < 0) {
						throw new ApiException('Invalid numeric value for %s', 422, [$key]);
					}
					$value = (string)(int)$value;
					break;
				case 'imap_enabled':
				case 'notify_mail':
				case 'notify_nc':
				case 'notify_person':
					$value = in_array($value, ['1', 'true', 'on', 'yes', true, 1], true) ? '1' : '0';
					break;
				case 'shared_secret':
				case 'imap_password':
					if ($value === '') {
						continue 2; // never overwrite a secret with empty
					}
					break;
			}
			$this->set($key, (string)$value);
			$applied[$key] = true;
		}
		return $applied;
	}

	/** @throws ApiException */
	public function validateRegFields(string $json): void {
		$fields = json_decode($json, true);
		if (!is_array($fields)) {
			throw new ApiException('Registration fields: invalid JSON', 422);
		}
		foreach ($fields as $f) {
			if (!is_array($f)
				|| !isset($f['key'], $f['label'], $f['type'])
				|| !preg_match('/^[a-z0-9_]+$/', (string)$f['key'])
				|| !in_array($f['type'], ['text', 'email', 'number', 'date', 'select'], true)) {
				throw new ApiException('Registration fields: invalid field schema', 422);
			}
			if ($f['type'] === 'select' && (empty($f['options']) || !is_array($f['options']))) {
				throw new ApiException('Registration fields: select fields need options', 422);
			}
		}
	}

	public function getOrCreateSharedSecret(): string {
		$secret = $this->get('shared_secret');
		if ($secret === '') {
			$secret = $this->regenerateSharedSecret();
		}
		return $secret;
	}

	public function regenerateSharedSecret(): string {
		$secret = bin2hex(random_bytes(32));
		$this->set('shared_secret', $secret);
		return $secret;
	}

	/** Settings for the UI: secrets masked (imap_password) or shown (shared_secret is shown to settings cap per spec). */
	public function exportForUi(): array {
		$out = [];
		foreach (self::KEYS as $key) {
			if ($key === 'imap_password') {
				$out[$key] = $this->get($key) !== '' ? '•••gesetzt•••' : '';
				continue;
			}
			$out[$key] = $this->get($key);
		}
		$out['shared_secret'] = $this->getOrCreateSharedSecret();
		return $out;
	}
}
