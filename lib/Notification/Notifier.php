<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Notification;

use OCA\Leiterrunde\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public function __construct(
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return 'Leiterrunde';
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$params = $notification->getSubjectParameters();
		$person = ($params['first_name'] ?? '') . ' ' . ($params['last_name'] ?? '');
		$label = $params['check_label'] ?? '';
		switch ($notification->getSubject()) {
			case 'training_state':
			case 'hours_state':
			case 'fz_state':
			case 'obligation_overdue':
				$notification->setParsedSubject($l->t('%1$s: %2$s – Status: %3$s', [trim($person), $label, $params['state'] ?? '']))
					->setLink($this->urlGenerator->linkToRouteAbsolute('leiterrunde.page.index'));
				break;
			case 'expense_submitted':
				$notification->setParsedSubject($l->t('New expense submitted by %s', [trim($person)]))
					->setLink($this->urlGenerator->linkToRouteAbsolute('leiterrunde.page.index'));
				break;
			case 'record_submitted':
				$notification->setParsedSubject($l->t('New training record submitted by %s', [trim($person)]))
					->setLink($this->urlGenerator->linkToRouteAbsolute('leiterrunde.page.index'));
				break;
			default:
				throw new UnknownNotificationException();
		}
		return $notification;
	}
}
