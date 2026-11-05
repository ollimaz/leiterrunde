<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		['name' => 'Api\\Me#index', 'url' => '/api/me', 'verb' => 'GET'],
		['name' => 'Api\\Overview#index', 'url' => '/api/overview', 'verb' => 'GET'],

		['name' => 'Api\\Users#index', 'url' => '/api/users', 'verb' => 'GET'],
		['name' => 'Api\\Persons#index', 'url' => '/api/persons', 'verb' => 'GET'],
		['name' => 'Api\\Persons#create', 'url' => '/api/persons', 'verb' => 'POST'],
		['name' => 'Api\\Persons#birthdays', 'url' => '/api/persons/birthdays', 'verb' => 'GET'],
		['name' => 'Api\\Persons#options', 'url' => '/api/persons/options', 'verb' => 'GET'],
		['name' => 'Api\\Persons#export', 'url' => '/api/persons/export', 'verb' => 'GET'],
		['name' => 'Api\\Persons#import', 'url' => '/api/persons/import', 'verb' => 'POST'],
		['name' => 'Api\\Persons#show', 'url' => '/api/persons/{id}', 'verb' => 'GET'],
		['name' => 'Api\\Persons#update', 'url' => '/api/persons/{id}', 'verb' => 'PUT'],
		['name' => 'Api\\Persons#destroy', 'url' => '/api/persons/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Persons#audit', 'url' => '/api/persons/{id}/audit', 'verb' => 'GET'],





		['name' => 'Api\\Settings#show', 'url' => '/api/settings', 'verb' => 'GET'],
		['name' => 'Api\\Settings#update', 'url' => '/api/settings', 'verb' => 'PUT'],
		['name' => 'Api\\Settings#regenerateSecret', 'url' => '/api/settings/secret/regenerate', 'verb' => 'POST'],
		['name' => 'Api\\Settings#roles', 'url' => '/api/settings/roles', 'verb' => 'GET'],
		['name' => 'Api\\Settings#saveRoles', 'url' => '/api/settings/roles', 'verb' => 'PUT'],
		['name' => 'Api\\Settings#groups', 'url' => '/api/groups', 'verb' => 'GET'],
		['name' => 'Api\\Settings#storage', 'url' => '/api/settings/storage', 'verb' => 'GET'],
		['name' => 'Api\\Settings#saveStorage', 'url' => '/api/settings/storage', 'verb' => 'PUT'],
		['name' => 'Api\\Settings#storageUserCheck', 'url' => '/api/settings/storage/user-check', 'verb' => 'GET'],

		['name' => 'Api\\Audit#index', 'url' => '/api/audit', 'verb' => 'GET'],








		['name' => 'Api\\Sync#index', 'url' => '/api/sync', 'verb' => 'POST'],
	],
];
