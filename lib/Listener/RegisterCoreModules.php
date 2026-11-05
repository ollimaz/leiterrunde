<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Listener;

use OCA\Leiterrunde\Module\CoreModule;
use OCA\Leiterrunde\Module\RegisterModulesEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class RegisterCoreModules implements IEventListener {
	public function __construct(private CoreModule $module) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof RegisterModulesEvent) {
			return;
		}
		$event->registerModule($this->module);
	}
}
