<?php
/** @var array $_ */
$state = json_encode($_['state'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>

<div id="naver-sync" data-state="<?php p($state); ?>">
	<p class="lead">
		네이버 캘린더를 Nextcloud로 가져옵니다. 방향은 네이버에서 Nextcloud로만 가고, 여기서 만든 일정은 네이버에 올라가지 않습니다.
		가져온 일정을 여기서 고치면 다음 동기화 때 네이버 내용으로 되돌리고, 여기서 지운 일정은 네이버에 있으면 다시 만듭니다.
		여기서만 만든 일정은 지웁니다. 할 일은 네이버로 올리지 않고, 지우지도 않습니다.
	</p>
	<p class="lead">
		서버는 caldav.calendar.naver.com 입니다. 아이디는 네이버 아이디이고, 2단계 인증을 쓰면
		<a href="https://help.naver.com/service/5640/contents/8584" target="_blank" rel="noreferrer">애플리케이션 비밀번호</a>를 입력하세요.
		저장한 뒤에는 서버 cron이 돌 때마다, 또는 지금 동기화로 가져옵니다.
	</p>

	<section class="block">
		<h3>계정</h3>
		<label class="field" for="naver-id">네이버 아이디</label>
		<input id="naver-id" type="text" autocomplete="username" spellcheck="false">

		<label class="field" for="naver-password">암호</label>
		<input id="naver-password" type="password" autocomplete="new-password" placeholder="<?php p(!empty($_['state']['passwordSet']) ? '저장되어 있습니다. 바꿀 때만 입력' : ''); ?>">
		<p id="naver-password-hint" class="note"><?php p(!empty($_['state']['passwordSet']) ? '암호가 저장되어 있습니다. 칸이 비어 있는 것은 암호를 다시 보여 주지 않기 때문이고, 바꿀 때만 새로 입력하면 됩니다.' : '2단계 인증이면 애플리케이션 비밀번호를 입력하세요.'); ?></p>

		<div class="actions">
			<button id="naver-save" type="button" class="primary">계정 저장하고 목록 가져오기</button>
		</div>
	</section>

	<section class="block">
		<h3>캘린더</h3>
		<ul id="naver-calendars" class="calendars"></ul>
	</section>
	<div class="actions">
		<button id="naver-save-selection" type="button">선택 저장</button>
		<button id="naver-sync-now" type="button" class="primary">지금 동기화</button>
		<button id="naver-refresh" type="button">목록 다시 가져오기</button>
	</div>
	<p id="naver-status" class="note" role="status"></p>
</div>
