<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Module;

use OCP\EventDispatcher\Event;

class RegisterModulesEvent extends Event {
	/** @var IModule[] */
	private array $modules = [];
	/** @var array<string,object> */
	private array $providers = [];

	public function registerModule(IModule $m): void {
		$this->modules[] = $m;
	}

	public function registerProvider(string $interface, object $impl): void {
		$this->providers[$interface] = $impl;
	}

	/** @internal */
	public function collectModules(): array {
		return $this->modules;
	}

	/** @internal */
	public function collectProviders(): array {
		return $this->providers;
	}
}
