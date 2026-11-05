<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\App\IAppManager;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class MeController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private IAppManager $appManager,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): JSONResponse {
		return $this->wrap(function () {
			$user = $this->perm->requireLoggedIn();
			$this->perm->require('app.access');
			$person = $this->perm->currentPerson();
			return [
				'uid' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'groups' => $this->perm->currentLrGroups(),
				'isNcAdmin' => $this->perm->isNcAdmin(),
				'caps' => $this->perm->caps(),
				'modules' => array_values(array_filter(
					['leiterrunde_schulungen', 'leiterrunde_finanzen', 'leiterrunde_veranstaltungen'],
					fn ($a) => $this->appManager->isEnabledForAnyone($a))),
				'person' => $person?->jsonSerialize(),
			];
		});
	}
}
