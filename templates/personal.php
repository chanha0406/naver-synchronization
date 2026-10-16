<?php
/** @var array $_ */
$l = \OCP\Util::getL10N('naversynchronization');
$state = json_encode($_['state'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$passwordSet = !empty($_['state']['passwordSet']);
?>

<div id="naver-sync" data-state="<?php p($state); ?>">
	<p class="lead">
		<?php p($l->t('Imports Naver Calendar into Nextcloud. Changes travel only from Naver to Nextcloud, so events created here are not uploaded. Edits here are replaced with the Naver version on the next sync, and events deleted here are created again if they still exist on Naver. Events that exist only here are removed. Tasks are neither uploaded to Naver nor deleted.')); ?>
	</p>
	<p class="lead">
		<?php p($l->t('The server is caldav.calendar.naver.com. The id is your Naver id. If you use two-step verification, enter an')); ?>
		<a href="https://help.naver.com/service/5640/contents/8584" target="_blank" rel="noreferrer"><?php p($l->t('application password')); ?></a><?php p($l->t('. After saving, import runs on each server cron, or when you press Sync now.')); ?>
	</p>

	<section class="block">
		<h3><?php p($l->t('Account')); ?></h3>
		<label class="field" for="naver-id"><?php p($l->t('Naver id')); ?></label>
		<input id="naver-id" type="text" autocomplete="username" spellcheck="false">

		<label class="field" for="naver-password"><?php p($l->t('Password')); ?></label>
		<input id="naver-password" type="password" autocomplete="new-password" placeholder="<?php p($passwordSet ? $l->t('Saved. Enter a new one only to change it') : ''); ?>">
		<p id="naver-password-hint" class="note"><?php p($passwordSet ? $l->t('A password is saved. The field is empty because the password is not shown again. Enter a new one only when you want to change it.') : $l->t('If you use two-step verification, enter an application password.')); ?></p>

		<div class="actions">
			<button id="naver-save" type="button" class="primary"><?php p($l->t('Save account and load list')); ?></button>
		</div>
	</section>

	<section class="block">
		<h3><?php p($l->t('Calendars')); ?></h3>
		<ul id="naver-calendars" class="calendars"></ul>
	</section>
	<div class="actions">
		<button id="naver-save-selection" type="button"><?php p($l->t('Save selection')); ?></button>
		<button id="naver-sync-now" type="button" class="primary"><?php p($l->t('Sync now')); ?></button>
		<button id="naver-refresh" type="button"><?php p($l->t('Reload list')); ?></button>
	</div>
	<p id="naver-status" class="note" role="status"></p>
</div>
