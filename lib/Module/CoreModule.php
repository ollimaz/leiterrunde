<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Module;

use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\IUser;
use OCP\IL10N;

class CoreModule implements IModule {
	public function __construct(
		private PersonMapper $personMapper,
		private PermissionService $perm,
		private IL10N $l10n,
	) {
	}

	public function getId(): string {
		return 'leiterrunde';
	}

	public function getLabel(): string {
		return 'Leiterrunde';
	}

	public function getNavUrl(): string {
		return '/apps/leiterrunde/';
	}

	public function getCapabilities(): array {
		return [
			new CapabilityDefinition('app.access', 'App access', 'Core', ['any', 'nca']),
			new CapabilityDefinition('persons.read', 'Read people', 'Core', ['leiter', 'vorstand', 'kasse', 'schulung', 'admin']),
			new CapabilityDefinition('persons.write', 'Write people', 'Core', ['vorstand', 'admin']),
			new CapabilityDefinition('persons.import', 'Import people', 'Core', ['vorstand', 'admin']),
			new CapabilityDefinition('persons.export', 'Export people', 'Core', ['vorstand', 'admin']),
			new CapabilityDefinition('persons.delete', 'Delete people', 'Core', ['vorstand', 'admin']),
			new CapabilityDefinition('audit.read', 'Read change log', 'Core', ['vorstand', 'admin']),
			new CapabilityDefinition('settings.manage', 'Manage settings', 'Core', ['admin', 'nca']),
		];
	}

	public function getDocumentTypes(): array {
		return [];
	}

	public function getOverviewTiles(IUser $user): array {

		$tiles = [];
		$uid = $user->getUID();
		if ($this->perm->has('persons.read', $uid)) {
			$byStatus = [];
			foreach ($this->personMapper->findAll(null, null, 500) as $p) {
				$byStatus[$p->getStatus()] = ($byStatus[$p->getStatus()] ?? 0) + 1;
			}
			$tiles[] = new OverviewTile('core.persons.active', $this->l10n->t('Active people'), 'info', null, [], '/apps/leiterrunde/#/adressen',
				['value' => (string)($byStatus['aktiv'] ?? 0), 'label' => $this->l10n->t('of {n} total') === 'of {n} total' ? str_replace('{n}', (string)array_sum($byStatus), $this->l10n->t('of {n} total')) : str_replace('{n}', (string)array_sum($byStatus), $this->l10n->t('of {n} total'))]);
			$tiles[] = new OverviewTile('core.persons.status', $this->l10n->t('People by status'), 'info', null, [], '/apps/leiterrunde/#/adressen', null,
				['type' => 'stack', 'series' => array_map(static fn ($k, $v) => ['label' => $k, 'key' => $k, 'value' => $v, 'state' => 'info'], array_keys($byStatus), $byStatus)]);
			$items = [];
			$today = new \DateTimeImmutable('today');
			foreach ($this->personMapper->findWithBirthdate() as $p) {
				$bd = \DateTimeImmutable::createFromFormat('!Y-m-d', substr((string)$p->getBirthdate(), 0, 10));
				if ($bd === false) {
					continue;
				}
				$next = $bd->setDate((int)$today->format('Y'), (int)$bd->format('m'), (int)$bd->format('d'));
				if ($next < $today) {
					$next = $next->setDate((int)$today->format('Y') + 1, (int)$bd->format('m'), (int)$bd->format('d'));
				}
				$diff = (int)$today->diff($next)->days;
				if ($diff <= 14) {
					$age = (int)$next->format('Y') - (int)$bd->format('Y');
					$parts = [$next->format('d.m.')];
					if ($age > 0) { $parts[] = $this->l10n->t('turns %s', [(string)$age]); }
					$parts[] = $this->l10n->n('in %n day', 'in %n days', $diff);
					$items[] = ['label' => $p->getFirstName() . ' ' . $p->getLastName(), 'sublabel' => implode(' · ', $parts), 'date' => $next->format('Y-m-d'), 'age' => $age, 'days' => $diff];
				}
			}
			$tiles[] = new OverviewTile('core.birthdays', $this->l10n->t('Birthdays (14 days)'), count($items) > 0 ? 'info' : 'ok', count($items), $items, '/apps/leiterrunde/#/geburtstage');
		}
		if ($this->perm->has('persons.write', $uid)) {
			$items = [];
			foreach ($this->personMapper->findAll(null, null, 500) as $p) {
				if ($p->getNcUserId() === null) {
					$items[] = ['label' => $p->getLastName() . ', ' . $p->getFirstName()];
				}
			}
			$tiles[] = new OverviewTile('core.persons.noaccount', $this->l10n->t('People without account'), count($items) > 0 ? 'warn' : 'ok', count($items), array_slice($items, 0, 5), '/apps/leiterrunde/#/adressen');
		}
		return $tiles;
	}
}
