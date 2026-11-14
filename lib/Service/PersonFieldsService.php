<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service;

use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Module\IPersonFieldsProvider;
use OCA\Leiterrunde\Module\ModuleRegistry;

class PersonFieldsService {
	private ?array $fields = null;

	public function __construct(private SettingsService $settings, private ModuleRegistry $modules) {
	}

	public function definitions(): array {
		if ($this->fields !== null) {
			return $this->fields;
		}
		$fields = json_decode($this->settings->get('person_fields', '[]'), true) ?: [];
		$provider = $this->modules->provider(IPersonFieldsProvider::class);
		return $this->fields = array_merge($fields, $provider?->personFields() ?? []);
	}

	public static function validateDefinitions(array $fields): void {
		if (!array_is_list($fields) || count($fields) > 30) {
			throw new ApiException('Invalid person columns', 422);
		}
		$keys = [];
		foreach ($fields as $f) {
			if (!is_array($f) || !is_string($f['key'] ?? null) || !preg_match('/^custom_[a-z0-9_]{1,40}$/D', $f['key'])
				|| isset($keys[$f['key']]) || !is_string($f['label'] ?? null) || trim($f['label']) === '' || strlen($f['label']) > 100
				|| !in_array($f['type'] ?? null, ['text', 'select'], true) || array_diff(array_keys($f), ['key', 'label', 'type', 'options'])) {
				throw new ApiException('Invalid person column definition', 422);
			}
			$keys[$f['key']] = true;
			if ($f['type'] === 'select' || isset($f['options'])) {
				$options = $f['options'] ?? null;
				if (!is_array($options) || !array_is_list($options) || ($f['type'] === 'select' && count($options) < 1) || count($options) > 100) {
					throw new ApiException('Select columns need options', 422);
				}
				foreach ($options as $option) {
					if (!is_string($option) || trim($option) === '' || strlen($option) > 200) {
						throw new ApiException('Invalid column option', 422);
					}
				}
			}
		}
	}

	public function visible(?string $json): array {
		$values = json_decode($json ?? '{}', true) ?: [];
		return array_intersect_key($values, array_column($this->definitions(), null, 'key'));
	}

	public function merge(?string $json, array $patch): string {
		if (count($patch) > 31) {
			throw new ApiException('Too many person fields', 422);
		}
		$fields = array_column($this->definitions(), null, 'key');
		$values = json_decode($json ?? '{}', true) ?: [];
		foreach ($patch as $key => $value) {
			$f = $fields[$key] ?? null;
			if ($f === null || ($value !== null && (!is_string($value) || strlen($value) > 2000))) {
				throw new ApiException('Invalid person field', 422);
			}
			if ($value === null || $value === '') {
				unset($values[$key]);
				continue;
			}
			if ($f['type'] === 'select' && !in_array($value, array_map(static fn ($o) => is_array($o) ? $o['value'] : $o, $f['options']), true)
				&& ($values[$key] ?? null) !== $value) {
				throw new ApiException('Invalid column option', 422);
			}
			$values[$key] = $value;
		}
		return json_encode((object)$values, JSON_THROW_ON_ERROR);
	}
}
