<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Service\SettingsService;
use OCA\Leiterrunde\Module\ISyncHandler;
use OCA\Leiterrunde\Module\ModuleRegistry;

use OCA\Leiterrunde\Service\Sync\Signer;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class SyncController extends ApiController {
	protected function translateApiErrors(): bool { return false; }
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private SettingsService $settings,
		private ModuleRegistry $modules,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[BruteForceProtection(action: 'leiterrunde_sync')]
	public function index(): JSONResponse {
		$body = (string)file_get_contents('php://input');
		$ts = $this->request->getHeader('X-LR-Timestamp');
		$sig = $this->request->getHeader('X-LR-Signature');
		$secret = $this->settings->get('shared_secret');
		$signer = new Signer($secret);
		if ($secret === '' || !$signer->verify($body, $ts !== '' ? $ts : null, $sig !== '' ? $sig : null, time())) {
			$res = new JSONResponse(['error' => 'invalid signature'], Http::STATUS_UNAUTHORIZED);
			$res->throttle();
			return $res;
		}
		$payload = json_decode($body, true);
		if (!is_array($payload)) {
			return new JSONResponse(['error' => 'invalid JSON'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$handler = $this->modules->provider(ISyncHandler::class);
			if ($handler === null) {
				return new JSONResponse(['error' => 'Veranstaltungen-Modul nicht aktiv'], 503);
			}
			$regId = $handler->handle($payload);
			return new JSONResponse(['ok' => true, 'registration_id' => $regId]);
		} catch (\OCA\Leiterrunde\Exception\ApiException $e) {
			return new JSONResponse(['error' => $e->getMessage()], $e->getStatus());
		} catch (\Throwable $e) {
			$this->logger->error('Leiterrunde Sync: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse(['error' => 'internal error'], 500);
		}
	}
}
