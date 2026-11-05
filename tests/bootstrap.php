<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// minimale OCP-Stubs für Unit-Tests ohne Nextcloud-Bootstrap
if (!interface_exists(\OCP\IAppConfig::class)) {
	eval('namespace OCP; interface IAppConfig { public function getValueString(string $app, string $key, string $default = "", bool $lazy = false): string; public function setValueString(string $app, string $key, string $value, bool $sensitive = false): void; }');
	eval('namespace OCP; interface IGroupManager { public function groupExists(string $gid): bool; }');
	eval('namespace OCP\\AppFramework; class App {}');
	eval('namespace OCP\\AppFramework\\Bootstrap; interface IBootstrap {} interface IRegistrationContext {} interface IBootContext {}');
	eval('namespace OCP\EventDispatcher; class Event {} interface IEventDispatcher { public function dispatchTyped(object $event): void; }');
}

if (!interface_exists(\OCP\IUser::class)) {
	eval('namespace OCP; interface IUser { public function getUID(): ?string; public function getEMailAddress(): ?string; public function getDisplayName(): string; }');
	eval('namespace OCP; interface IUserManager { public function get(?string $uid): ?\\OCP\\IUser; }');
	eval('namespace OCP; interface IL10N { public function t(string $text, array $parameters = []): string; public function n(string $text_singular, string $text_plural, int $count, array $parameters = []): string; }');
	eval('namespace OCP\\L10N; interface IFactory { public function get(string $app, ?string $lang = null): \\OCP\\IL10N; public function getUserLanguage(\\OCP\\IUser $user): string; }');
	eval('namespace OCP\\Mail; interface IMailer { public function createMessage(): \\OCP\\Mail\\IMessage; public function send(\\OCP\\Mail\\IMessage $message): void; }');
	eval('namespace OCP\\Mail; interface IMessage { public function setTo(array $to): void; public function setSubject(string $subject): void; public function setPlainBody(string $body): void; }');
	eval('namespace OCP\\Notification; interface IManager { public function createNotification(): \\OCP\\Notification\\INotification; public function notify(\\OCP\\Notification\\INotification $notification): void; }');
	eval('namespace OCP\\Notification; interface INotification {}');
	eval('namespace Psr\\Log; interface LoggerInterface { public function warning(string $message, array $context = []): void; }');
}


if (!class_exists(\OCP\AppFramework\Db\QBMapper::class)) {
	eval('namespace OCP\\AppFramework\\Db; abstract class QBMapper {} abstract class Entity {
		public $id;
		public function getId() { return $this->id; }
		public function setId($id): void { $this->id = $id; }
		public function __call(string $m, array $a) {
			$p = lcfirst(substr($m, 3));
			if (str_starts_with($m, "get")) { return $this->$p; }
			if (str_starts_with($m, "set")) { $this->$p = $a[0]; return null; }
			throw new \BadMethodCallException($m);
		}
	}');
}

spl_autoload_register(static function (string $class): void {
	$prefix = 'OCA\\Leiterrunde\\';
	if (str_starts_with($class, $prefix)) {
		$file = __DIR__ . '/../lib/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
		if (is_file($file)) {
			require_once $file;
		}
	}
});
