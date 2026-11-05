<?php
declare(strict_types=1);
\OCP\Util::addStyle('leiterrunde', 'leiterrunde');
\OCP\Util::addScript('leiterrunde', 'lr-settings');
?>
<div class="section lr" id="lr-settings-notify">
	<h2><?php p($l->t('Leiterrunde – Notifications')); ?></h2>
	<div id="lr-notify-form"></div>
	<button class="lr-btn" id="lr-notify-save"><?php p($l->t('Save')); ?></button>
	<span id="lr-notify-msg"></span>
</div>
