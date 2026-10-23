# 추가 수정 재검토

- 작성일: 2026-10-23
- 근거: 브랜치 `main`. `git rev-parse --short HEAD` = `fe6f8cf` (`git rev-parse HEAD` = `fe6f8cfd33417966f74fecd3fcfe78c2e54afa89`). 앱 PHP는 `lib/` 21개 파일, `appinfo/info.xml`, `appinfo/routes.php`, `templates/personal.php`. 테스트는 `tests/Unit/AppearanceTest.php`, `tests/Unit/CalendarTrashTest.php`, `tests/Unit/MultiStatusParserTest.php`, `tests/bootstrap.php`, `tests/unit-bootstrap.php`, `tests/run-unit.sh`. `vendor`, `node_modules`, `l10n` 내용, 설치본 `/ssd1`은 읽지 않았다. `js/`는 이번 범위 밖이다.
- 상태: 초안

## 판정

추가 수정 없음.

지금 코드에서, 확인된 사실만으로 사용자 데이터가 사라지거나 쓰기·열기가 깨지는 지점은 없다. 고치려면 네이버나 Nextcloud의 응답 형태를 가정해야 하는 후보는 올리지 않았다. 이미 반영했거나 유지하기로 한 동작도 바꾸지 않았다.

## 이미 반영된 것

1.1.1 (`CHANGELOG.md`, `lib/Service/CalendarTrash.php`, `lib/Service/SyncService.php`)에 있다.

- 휴지통은 `{http://nextcloud.com/ns}deleted-at`과 `{http://owncloud.org/ns}deleted-at`을 본다. `deleted-at`이 없으면 연결된 캘린더로 본다. 테스트는 `tests/Unit/CalendarTrashTest.php`에 있다.
- 캘린더를 새로 만들 때와 이름·색을 따라간 뒤, `applied_color`에 빈 문자열을 그대로 저장한다. `null`로 바꾸지 않는다. `Appearance::decide`는 `appliedColor`가 `null`일 때만 기준값을 다시 심는다. 양쪽 색이 비어 있으면 그 기준은 빈 문자열이다. 다음 동기화에서는 `desiredColor`가 비어 색 분기를 타지 않으므로, 같은 행을 다시 쓰지 않는다.

## 유지해서 수정으로 올리지 않은 것

- 단방향 가져오기는 `SyncService`에 둔다. 구글 쪽 `SyncDecision` / `PlanRunner`로 옮기지 않는다.
- `Appearance`는 이 앱의 `lib/Service/Appearance.php`에 둔다.
- `MultiStatusParser`는 루트가 `multistatus`가 아니면 예외를 던진다. `CalDavClient::listObjects`는 `PROPFIND` 결과가 빈 배열이면 예외를 던진다. 그 예외는 `syncUserLocked`에서 그 캘린더의 `lastError`로 남고, `deleteMissing`은 호출되지 않는다. ETag가 없으면 `importItem`이 데이터 해시 `sha256:`를 버전으로 쓴다. `localEtags`는 컴포넌트가 `vevent`가 아니고 이 앱이 기록한 URI도 아닌 객체를 건너뛴다. 할 일 삭제로 이어지지 않는다.
- 휴지통이면 `linkedCalendar`가 `null`이다. 다음 동기화는 그 캘린더에 쓰지 않는다. URI가 남아 `ensureCalendar`가 `naver-import-` 캘린더를 새로 만들 수 있는 것은 알려진 동작이다.

## 데이터 경로에서 확인한 것

- 계정에서 사라진 원격 캘린더는 `enabled`를 끄고 오류만 남긴다. 그 자리에서 로컬 일정을 지우지 않는다.
- 객체 삭제는 `deleteMissing`에만 있다. 잘못된 `multistatus`나 빈 `PROPFIND`는 그 앞에 예외로 빠진다. 이미 연결된 캘린더에서 `ensureCalendar`는 객체 매핑을 지우지 않는다. `deleteByCalendar`는 새 Nextcloud 캘린더를 만든 뒤에만 호출된다.
- 삭제가 50건 이상이거나, 10건 이상이면서 추적 중·로컬 객체 수 대비 5배 이상이면 `allowMassDelete` 없이 삭제를 보류한다.
- 개인 설정 화면은 `Personal::getForm`가 `SyncService::state`로 계정 id, 암호 저장 여부, 캘린더 목록을 넘긴다. 열기 경로에서 일정을 쓰지 않는다.

## 탈락한 후보

- `listObjects`는 `PROPFIND`에서 객체가 하나라도 나오면 `REPORT`를 하지 않는다. 목록이 일부만이면 빠진 일정을 삭제로 볼 수 있다. 네이버가 Depth 1에서 일정 전체를 주는지는 미확인이다.
- `createCalendarObject` / `updateCalendarObject`의 ETag와 `getCalendarObjects`의 ETag 표기가 다르면 매 동기화마다 다시 쓸 수 있다. `CalDavBackend` 구현은 이 저장소에 없고, `vendor`와 `/ssd1`은 읽지 않았다.
- `CalDavClient::absolute`는 `/`로 시작하지 않는 href를 서버 루트 경로로 만든다. 상대 href가 실제 응답에 있는지는 미확인이다.
- `deleteCalendarObject`의 네 번째 인자는 `true`다. 호출 형태는 명확하고, 지우는 대상은 네이버에 없거나 Nextcloud에만 있는 객체다. 제품 설명과 같다.
- `getCalendarObjects`의 `component`가 `VEVENT`이면 이 앱 URI가 아닌 일정은 `localEtags`에서 빠진다. 그 경우 사용자 일정은 삭제에서 남는다. 대소문자는 미확인이고, 데이터가 지워지는 방향이 아니다. 이 앱이 기록한 URI는 `$ours`로 계속 포함한다.
- 할 일 URI가 이 앱 local URI(`href` 해시 앞 32자와 `.ics`)와 같으면 `updateCalendarObject`로 덮일 수 있다. 그런 충돌이 있는지는 미확인이다. 할 일을 남기는 현재 분기는 유지 대상이다.
- 스타일, `Appearance`를 공유 패키지로 빼는 정리, 동기화 흐름을 다른 앱 구조로 옮기는 안은 이번 기준 밖이다.
