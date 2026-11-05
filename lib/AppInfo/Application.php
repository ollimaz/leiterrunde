<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\AppInfo;

use OCA\Leiterrunde\Module\CoreModule;
use OCA\Leiterrunde\Module\LegacyModule;
use OCA\Leiterrunde\Module\RegisterModulesEvent;
use OCA\Leiterrunde\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'leiterrunde';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
		$context->registerDashboardWidget(\OCA\Leiterrunde\Dashboard\GeburtstageWidget::class);
		$context->registerEventListener(RegisterModulesEvent::class, \OCA\Leiterrunde\Listener\RegisterCoreModules::class);
	}

	public function boot(IBootContext $context): void {
	}
}
