<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\IDelegatedSettings;

class RolesPage implements IDelegatedSettings {
	public function __construct(private IConfig $config) {
	}

	public function getForm(): TemplateResponse {
		return new TemplateResponse('leiterrunde', 'settings-roles', []);
	}

	public function getSection(): string {
		return 'leiterrunde';
	}

	public function getPriority(): int {
		return 10;
	}

	public function getName(): ?string {
		return null;
	}

	public function getAuthorizedAppConfig(): array {
		return [];
	}
}
