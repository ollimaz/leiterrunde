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
		['name' => 'Api\\Persons#fields', 'url' => '/api/persons/fields', 'verb' => 'GET'],
		['name' => 'Api\\Persons#options', 'url' => '/api/persons/options', 'verb' => 'GET'],
		['name' => 'Api\\Persons#import', 'url' => '/api/persons/import', 'verb' => 'POST'],
		['name' => 'Api\\Persons#mapAccounts', 'url' => '/api/persons/map-accounts', 'verb' => 'GET'],
		['name' => 'Api\\Persons#saveMapAccounts', 'url' => '/api/persons/map-accounts', 'verb' => 'POST'],
		['name' => 'Api\\Persons#show', 'url' => '/api/persons/{id}', 'verb' => 'GET'],
		['name' => 'Api\\Persons#update', 'url' => '/api/persons/{id}', 'verb' => 'PUT'],
		['name' => 'Api\\Persons#destroy', 'url' => '/api/persons/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Persons#audit', 'url' => '/api/persons/{id}/audit', 'verb' => 'GET'],

		['name' => 'Api\\Documents#index', 'url' => '/api/documents', 'verb' => 'GET'],
		['name' => 'Api\\Documents#mine', 'url' => '/api/documents/mine', 'verb' => 'GET'],
		['name' => 'Api\\Documents#byPerson', 'url' => '/api/persons/{pid}/documents', 'verb' => 'GET'],
		['name' => 'Api\\Documents#targets', 'url' => '/api/documents/targets', 'verb' => 'GET'],
		['name' => 'Api\\Documents#create', 'url' => '/api/documents', 'verb' => 'POST'],
		['name' => 'Api\\Documents#show', 'url' => '/api/documents/{id}', 'verb' => 'GET'],
		['name' => 'Api\\Documents#update', 'url' => '/api/documents/{id}', 'verb' => 'PUT'],
		['name' => 'Api\\Documents#destroy', 'url' => '/api/documents/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Documents#file', 'url' => '/api/documents/{id}/file', 'verb' => 'GET'],
		['name' => 'Api\\Documents#uploadFile', 'url' => '/api/documents/{id}/file', 'verb' => 'POST'],
		['name' => 'Api\\Documents#requestSign', 'url' => '/api/documents/{id}/requests', 'verb' => 'POST'],
		['name' => 'Api\\Documents#updateRequest', 'url' => '/api/documents/{id}/requests/{rid}', 'verb' => 'PUT'],
		['name' => 'Api\\Documents#destroyRequest', 'url' => '/api/documents/{id}/requests/{rid}', 'verb' => 'DELETE'],
		['name' => 'Api\\Documents#uploadRequestFile', 'url' => '/api/documents/{id}/requests/{rid}/file', 'verb' => 'POST'],
		['name' => 'Api\\Documents#requestFile', 'url' => '/api/documents/{id}/requests/{rid}/file', 'verb' => 'GET'],
		['name' => 'Api\\Documents#sign', 'url' => '/api/documents/{id}/sign', 'verb' => 'POST'],
		['name' => 'Api\\Documents#signFile', 'url' => '/api/documents/{id}/sign-file', 'verb' => 'POST'],





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
