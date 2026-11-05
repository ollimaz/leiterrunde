<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Generates de.js, de_DE.json and de_DE.js from the hand-maintained l10n/de.json.
// Usage: php l10n-tools/build.php <appid> [<appid> ...]
// Run from an apps-extra directory containing the app folders, or pass app ids
// with an optional base dir: L10N_BASE=/path/to/apps-extra php build.php leiterrunde

$base = getenv('L10N_BASE') ?: dirname(__DIR__, 2);
$apps = array_slice($argv, 1);

foreach ($apps as $appId) {
	$src = $base . '/' . $appId . '/l10n/de.json';
	$doc = json_decode(file_get_contents($src), true, 512, JSON_THROW_ON_ERROR);
	$map = $doc['translations'];
	$plural = $doc['pluralForm'];

	$js = 'OC.L10N.register('
		. json_encode($appId, JSON_UNESCAPED_SLASHES)
		. ', '
		. json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		. ', '
		. json_encode($plural)
		. ");\n";

	file_put_contents(dirname($src) . '/de.js', $js);
	file_put_contents(dirname($src) . '/de_DE.json', json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
	file_put_contents(dirname($src) . '/de_DE.js', $js);
	echo "$appId: generated de.js, de_DE.json, de_DE.js\n";
}
