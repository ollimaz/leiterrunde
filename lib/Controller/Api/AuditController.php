<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Db\AuditMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class AuditController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private AuditMapper $auditMapper,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?string $entity_type = null, ?int $entity_id = null): JSONResponse {
		return $this->wrap(function () use ($entity_type, $entity_id) {
			$this->perm->require('audit.read');
			if ($entity_type === null || $entity_id === null) {
				throw new ApiException('entity_type and entity_id are required', 400);
			}
			return array_map(
				static fn ($e) => $e->jsonSerialize(),
				$this->auditMapper->findFor($entity_type, $entity_id)
			);
		});
	}
}
