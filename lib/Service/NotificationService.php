<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Service;

use OCA\Leiterrunde\AppInfo\Application;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\L10N\IFactory as L10NFactory;
use OCP\Mail\IMailer;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

class NotificationService {
	public function __construct(
		private IManager $notificationManager,
		private IMailer $mailer,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private SettingsService $settings,
		private L10NFactory $l10nFactory,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Notify recipients about a state transition.
	 * @param string[] $groupIds recipient groups
	 * @param ?string $extraUid additional direct recipient (linked user), or null
	 */
	public function notifyState(string $subject, array $params, array $groupIds, ?string $extraUid, string $mailSubject, string $mailBody): void {
		$uids = [];
		foreach ($groupIds as $gid) {
			foreach ($this->groupMemberUids($gid) as $uid) {
				$uids[$uid] = true;
			}
		}
		if ($extraUid !== null) {
			$uids[$extraUid] = true;
		}
		foreach (array_keys($uids) as $uid) {
			$this->notifyUser($uid, $subject, $params, $mailSubject, $mailBody);
		}
	}

	/** @return string[] */
	private function groupMemberUids(string $gid): array {
		$group = $this->groupManager->get($gid);
		if ($group === null) {
			return [];
		}
		return array_map(static fn ($u) => $u->getUID(), $group->getUsers());
	}

	private function notifyUser(string $uid, string $subject, array $params, string $mailSubject, string $mailBody): void {
		$user = $this->userManager->get($uid);
		if ($user === null) {
			return;
		}
		if ($this->settings->getBool('notify_nc')) {
			try {
				$n = $this->notificationManager->createNotification();
				$n->setApp(Application::APP_ID)
					->setUser($uid)
					->setDateTime(new \DateTime())
					->setObject('state', $subject . ':' . ($params['person_id'] ?? '0'))
					->setSubject($subject, $params);
				$this->notificationManager->notify($n);
			} catch (\Throwable $e) {
				$this->logger->warning('Leiterrunde: NC-Benachrichtigung fehlgeschlagen: ' . $e->getMessage());
			}
		}
		if ($this->settings->getBool('notify_mail')) {
			$email = $user->getEMailAddress();
			if ($email) {
				try {
					$l = $this->l10nFactory->get(Application::APP_ID, $this->l10nFactory->getUserLanguage($user));
					$msg = $this->mailer->createMessage();
					$msg->setTo([$email => $user->getDisplayName()]);
					$msg->setSubject('[Leiterrunde] ' . $l->t($mailSubject));
					$msg->setPlainBody($l->t($mailBody));
					$this->mailer->send($msg);
				} catch (\Throwable $e) {
					$this->logger->warning('Leiterrunde: Mailversand fehlgeschlagen: ' . $e->getMessage());
				}
			}
		}
	}
}
