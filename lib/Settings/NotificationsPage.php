<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;

class NotificationsPage implements IDelegatedSettings {
	public function getForm(): TemplateResponse {
		return new TemplateResponse('leiterrunde', 'settings-notifications', []);
	}

	public function getSection(): string {
		return 'leiterrunde';
	}

	public function getPriority(): int {
		return 30;
	}

	public function getName(): ?string {
		return null;
	}

	public function getAuthorizedAppConfig(): array {
		return [];
	}
}
