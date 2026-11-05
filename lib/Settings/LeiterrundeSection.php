<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Settings;

use OCA\Leiterrunde\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class LeiterrundeSection implements IIconSection {
	public function __construct(private IURLGenerator $urlGenerator, private IL10N $l) {
	}

	public function getID(): string {
		return 'leiterrunde';
	}

	public function getName(): string {
		return 'Leiterrunde';
	}

	public function getPriority(): int {
		return 80;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');
	}
}
