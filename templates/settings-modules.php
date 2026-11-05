<?php
declare(strict_types=1);
\OCP\Util::addStyle('leiterrunde', 'leiterrunde');
\OCP\Util::addScript('leiterrunde', 'lr-settings');
?>
<div class="section lr" id="lr-settings-modules">
	<h2><?php p($l->t('Leiterrunde – Modules')); ?></h2>
	<p class="settings-hint"><?php p($l->t('Training rules, IMAP and sync settings (will move to the module apps later).')); ?></p>
	<div id="lr-modules-form"></div>
	<button class="lr-btn" id="lr-modules-save"><?php p($l->t('Save')); ?></button>
	<span id="lr-modules-msg"></span>
</div>
