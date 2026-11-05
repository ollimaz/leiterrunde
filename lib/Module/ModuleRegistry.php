<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Module;

use OCP\EventDispatcher\IEventDispatcher;

class ModuleRegistry {
	/** @var IModule[]|null */
	private ?array $modules = null;
	private ?array $providers = null;

	public function __construct(
		private IEventDispatcher $dispatcher,
	) {
	}

	private function load(): void {
		if ($this->modules !== null) {
			return;
		}
		$event = new RegisterModulesEvent();
		$this->dispatcher->dispatchTyped($event);
		$this->modules = $event->collectModules();
		$this->providers = $event->collectProviders();
	}

	/** @return IModule[] */
	public function modules(): array {
		$this->load();
		return $this->modules;
	}

	public function provider(string $interface): ?object {
		$this->load();
		return $this->providers[$interface] ?? null;
	}

	/** @return CapabilityDefinition[] */
	public function capabilities(): array {
		$out = [];
		foreach ($this->modules() as $m) {
			foreach ($m->getCapabilities() as $c) {
				$out[] = $c;
			}
		}
		return $out;
	}

	/** @return DocumentType[] */
	public function documentTypes(): array {
		$out = [];
		foreach ($this->modules() as $m) {
			foreach ($m->getDocumentTypes() as $d) {
				$out[] = $d;
			}
		}
		return $out;
	}
}
