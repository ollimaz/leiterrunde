<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Tests;

use OCA\Leiterrunde\Service\NotificationService;
use OCA\Leiterrunde\Service\SettingsService;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory as L10NFactory;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Notification\IManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MailLanguageTest extends TestCase {
	private function service(IL10N|MockObject $l, L10NFactory|MockObject $factory, IMailer|MockObject $mailer, ?IUser $user): NotificationService {
		$um = $this->createMock(IUserManager::class);
		$um->method('get')->willReturn($user);
		$gm = $this->createMock(IGroupManager::class);
		
		$settings = $this->createMock(SettingsService::class);
		$settings->method('getBool')->willReturnCallback(fn($k) => $k === 'notify_mail');
		$settings->method('notifyGroups')->willReturn([]);
		$nm = $this->createMock(IManager::class);
		$logger = $this->createMock(LoggerInterface::class);
		return new NotificationService($nm, $mailer, $um, $gm, $settings, $factory, $logger);
	}

	public function testGermanRecipientGetsGermanSubject(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('de@example.org');
		$user->method('getDisplayName')->willReturn('DE');
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(fn($s) => 'DE:' . $s);
		$factory = $this->createMock(L10NFactory::class);
		$factory->method('getUserLanguage')->willReturn('de');
		$factory->method('get')->willReturn($l);
		$msg = $this->createMock(IMessage::class);
		$msg->expects($this->once())->method('setSubject')->with('[Leiterrunde] DE:Overdue payment obligation');
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($msg);
		$this->service($l, $factory, $mailer, $user)
			->notifyState('x', [], [], 'u1', 'Overdue payment obligation', 'Body');
	}

	public function testEnglishRecipientGetsEnglishSubject(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('en@example.org');
		$user->method('getDisplayName')->willReturn('EN');
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(fn($s) => $s);
		$factory = $this->createMock(L10NFactory::class);
		$factory->method('getUserLanguage')->willReturn('en');
		$factory->method('get')->willReturn($l);
		$msg = $this->createMock(IMessage::class);
		$msg->expects($this->once())->method('setSubject')->with('[Leiterrunde] Overdue payment obligation');
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($msg);
		$this->service($l, $factory, $mailer, $user)
			->notifyState('x', [], [], 'u1', 'Overdue payment obligation', 'Body');
	}
}
