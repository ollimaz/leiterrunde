<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Controller;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;

class PageController extends Controller {
	public function __construct(
		IRequest $request,
		private PermissionService $perm,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse|RedirectResponse {
		if (!$this->perm->has('app.access')) {
			return new RedirectResponse($this->urlGenerator->linkToDefaultPageUrl());
		}
		Util::addScript(Application::APP_ID, 'leiterrunde');
		Util::addStyle(Application::APP_ID, 'leiterrunde');
		return new TemplateResponse(Application::APP_ID, 'main', [], TemplateResponse::RENDER_AS_USER);
	}
}
