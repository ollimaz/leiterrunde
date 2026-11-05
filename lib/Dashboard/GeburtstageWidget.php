<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Dashboard;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IConditionalWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IWidget;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IURLGenerator;
use OCP\IUserSession;

class GeburtstageWidget implements IAPIWidgetV2, IConditionalWidget, IIconWidget, IWidget {
	public function __construct(
		private IURLGenerator $urlGenerator,
		private IUserSession $userSession,
		private PersonMapper $personMapper,
		private PermissionService $perm,
	) {
	}

	public function getId(): string { return 'leiterrunde-geburtstage'; }
	public function getTitle(): string { return 'Leiterrunde: Geburtstage'; }
	public function getOrder(): int { return 20; }
	public function getIconClass(): string { return 'icon-calendar'; }
	public function getIconUrl(): string { return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')); }
	public function getUrl(): ?string { return $this->urlGenerator->linkToRouteAbsolute('leiterrunde.page.index'); }
	public function load(): void {}
	public function isEnabled(): bool {
		return $this->perm->has('persons.read', $this->userSession->getUser()?->getUID());
	}
	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$today = new \DateTimeImmutable('today');
		$items = [];
		foreach ($this->personMapper->findWithBirthdate() as $p) {
			$bd = \DateTimeImmutable::createFromFormat('!Y-m-d', substr((string)$p->getBirthdate(), 0, 10));
			if ($bd === false) { continue; }
			$next = $bd->setDate((int)$today->format('Y'), (int)$bd->format('m'), (int)$bd->format('d'));
			if ($next < $today) { $next = $next->setDate((int)$today->format('Y') + 1, (int)$bd->format('m'), (int)$bd->format('d')); }
			$diff = (int)$today->diff($next)->days;
			if ($diff <= 14) {
				$items[] = new WidgetItem($p->getFirstName() . ' ' . $p->getLastName(), 'in ' . $diff . ' Tagen');
			}
			if (count($items) >= $limit) { break; }
		}
		return new WidgetItems($items, '', '');
	}
}
