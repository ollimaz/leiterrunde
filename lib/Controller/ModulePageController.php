<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Controller;

use OCA\Leiterrunde\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

/**
 * Shared index page for module apps: guards on the core app being enabled,
 * loads the shared assets plus the module's own entry script.
 */
abstract class ModulePageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IAppManager $appManager,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		if (!$this->appManager->isEnabledForAnyone(Application::APP_ID)) {
			return new TemplateResponse(Application::APP_ID, 'core-required', [], 'blank');
		}
		Util::addScript(Application::APP_ID, 'lr-common');
		Util::addStyle(Application::APP_ID, 'leiterrunde');
		Util::addScript($this->appName, 'main', Application::APP_ID);
		return new TemplateResponse(Application::APP_ID, 'module-main', []);
	}
}
