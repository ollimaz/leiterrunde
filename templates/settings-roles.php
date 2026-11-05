<?php
declare(strict_types=1);
\OCP\Util::addStyle('leiterrunde', 'leiterrunde');
\OCP\Util::addScript('leiterrunde', 'lr-settings');
?>
<div class="section lr" id="lr-settings-roles">
	<h2><?php p($l->t('Leiterrunde – Roles & permissions')); ?></h2>
	<p class="settings-hint"><?php p($l->t('Role–group assignment and permission matrix. Certificate-of-conduct permissions are locked to the “Conduct certificate access” role.')); ?></p>
	<div id="lr-roles-warnings"></div>
	<div id="lr-roles-groups"></div>
	<div id="lr-roles-matrix"></div>
	<button class="lr-btn" id="lr-roles-save"><?php p($l->t('Save')); ?></button>
	<span id="lr-roles-msg"></span>
</div>
