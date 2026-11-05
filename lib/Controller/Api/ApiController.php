<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Controller\Api;

use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Exception\ForbiddenException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\L10N\IFactory;
use OCP\Server;
use Psr\Log\LoggerInterface;

abstract class ApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		protected LoggerInterface $logger,
		protected ?IL10N $l10n = null,
	) {
		parent::__construct($appName, $request);
	}

	/** Machine-facing endpoints (WordPress sync) return untranslated English. */
	protected function translateApiErrors(): bool {
		return true;
	}

	/** Execute $fn, map exceptions to JSON error responses. */
	protected function wrap(callable $fn): JSONResponse {
		try {
			$result = $fn();
			if ($result instanceof JSONResponse) {
				return $result;
			}
			return new JSONResponse($result);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['error' => $this->message($e, $this->coreL10n())], Http::STATUS_FORBIDDEN);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => $this->translateApiErrors() ? $this->coreL10n()->t('Not found') : 'Not found'], Http::STATUS_NOT_FOUND);
		} catch (ApiException $e) {
			return new JSONResponse(['error' => $this->message($e, $this->l10n ?? Server::get(IFactory::class)->get($this->appName))], $e->getStatus());
		} catch (\Throwable $e) {
			$this->logger->error('Leiterrunde API error: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse(['error' => $this->translateApiErrors() ? $this->coreL10n()->t('Internal error') : 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function coreL10n(): IL10N {
		return Server::get(IFactory::class)->get('leiterrunde');
	}

	private function message(ApiException $e, IL10N $l10n): string {
		if ($this->translateApiErrors()) {
			return $l10n->t($e->getMessage(), $e->getParams());
		}
		return $e->getParams() === [] ? $e->getMessage() : vsprintf($e->getMessage(), $e->getParams());
	}
}
