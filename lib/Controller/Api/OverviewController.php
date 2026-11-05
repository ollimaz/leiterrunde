<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Module\ModuleRegistry;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class OverviewController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private ModuleRegistry $modules,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): JSONResponse {
		return $this->wrap(function () {
			$user = $this->perm->requireLoggedIn();
			$tiles = [];
			foreach ($this->modules->modules() as $module) {
				try {
					foreach ($module->getOverviewTiles($user) as $t) {
						$tiles[] = [
							'module' => $module->getId(),
							'id' => $t->id,
							'title' => $t->title,
							'severity' => $t->severity,
							'count' => $t->count,
							'items' => $t->items,
							'url' => $t->url,
							'kpi' => $t->kpi,
							'chart' => $t->chart,
						];
					}
				} catch (\Throwable $e) {
					$tiles[] = ['module' => $module->getId(), 'title' => $module->getLabel(), 'severity' => 'fail', 'count' => null, 'items' => [['label' => 'Error: ' . $e->getMessage()]], 'url' => null];
				}
			}
			return $tiles;
		});
	}
}
