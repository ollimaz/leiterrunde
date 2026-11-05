<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

\OCP\Util::addStyle('leiterrunde', 'leiterrunde');
\OCP\Util::addScript('leiterrunde', 'lr-settings');
?>
<div class="section lr" id="lr-settings-storage">
	<h2><?php p($l->t('Leiterrunde – Document storage')); ?></h2>
	<p class="settings-hint"><?php p($l->t('Storage user (files land in their files; empty = uploader). Note: share the storage folder with the board manually if needed.')); ?></p>
	<div id="lr-storage-form"></div>
	<button class="lr-btn" id="lr-storage-save"><?php p($l->t('Save')); ?></button>
	<span id="lr-storage-msg"></span>
</div>
