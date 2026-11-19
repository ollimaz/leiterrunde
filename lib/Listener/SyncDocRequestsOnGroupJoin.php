<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Listener;

use OCA\Leiterrunde\Service\DocumentTargetService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\UserAddedEvent;
use Psr\Log\LoggerInterface;

/** When a user joins an NC group, create pending requests for documents targeting that group. */
/** @implements IEventListener<UserAddedEvent> */
class SyncDocRequestsOnGroupJoin implements IEventListener {
	public function __construct(
		private DocumentTargetService $targets,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserAddedEvent)) {
			return;
		}
		try {
			$this->targets->syncUser($event->getUser()->getUID());
		} catch (\Throwable $e) {
			$this->logger->warning('Document target sync on group join failed: ' . $e->getMessage());
		}
	}
}
