# Naver Synchronization 개요

- 작성일: 2026-10-23
- 근거: `appinfo/info.xml`, `README.md`, `composer.json`, `lib/`, `appinfo/routes.php`. 브랜치 `main`. `git rev-parse --short HEAD` = `fe6f8cf`. `git rev-parse HEAD` = `fe6f8cfd33417966f74fecd3fcfe78c2e54afa89`. `git remote -v`는 아래 저장소 절.
- 상태: 초안

## 목적

Naver Synchronization은 네이버 캘린더를 CalDAV로 Nextcloud에 한 방향 가져오는 Nextcloud 앱이다. 앱 id는 `naversynchronization`, 버전은 1.1.1, 네임스페이스는 `OCA\NaverSynchronization`이다. `info.xml`의 한국어 표시 이름은 네이버 동기화다.

사용자는 네이버 아이디와 암호로 로그인하고, 캘린더가 여러 개면 가져올 항목을 고른다. Nextcloud에서 고친 일정은 다음 동기화 때 네이버 내용으로 되돌아가고, Nextcloud에만 있는 일정은 지워진다. 이 설명은 `README.md`와 `appinfo/info.xml`에 있다.

서버 주소 `https://caldav.calendar.naver.com`은 `lib/AppInfo/Application.php`의 `Application::SERVER`다. Nextcloud 의존 범위는 32 이상 35 이하다. PHP 요구는 `composer.json`의 `^8.1`이다. 라이선스는 AGPL이다.

## 구성

서브모듈은 없다.

| 경로 | 역할 |
| --- | --- |
| `appinfo/info.xml` | 앱 id, 이름, 버전, 백그라운드 작업, occ 명령, 개인 설정 |
| `appinfo/routes.php` | 계정 저장, 캘린더 목록, 선택 저장, 이름·색 맞추기, 즉시 동기화 |
| `lib/Service/` | `SyncService`, `CalDavClient`, `MultiStatusParser`, `Appearance`, `CalendarTrash`, `AccountService` |
| `lib/Db/` | `naver_sync_calendars`, `naver_sync_objects` |
| `lib/Controller/SyncController.php` | 개인 설정용 JSON |
| `lib/BackgroundJob/SyncJob.php` | 주기 동기화 |
| `lib/Command/SyncUser.php` | `naversynchronization:sync` |
| `lib/Settings/` | 개인 설정 섹션 |
| `lib/Migration/` | 위 두 테이블과 `applied_name`, `applied_color`, `local_etag` |
| `templates/personal.php`, `js/personal.js`, `css/personal.css` | 개인 설정 화면 |
| `img/` | `app.svg`, `app-dark.svg` |
| `l10n/` | 번역 파일 위치. 내용은 이 개요에서 열람하지 않음 |
| `tests/Unit/` | `Appearance`, `CalendarTrash`, `MultiStatusParser` 단위 테스트 |
| `vendor-bin/` | 개발 도구용 Composer bin |

## 주요 기능

파일에서 확인한 동작이다.

- 계정 저장. 암호는 `ICrypto`로 암호화해 사용자 설정에 둔다. 빈 암호로 다시 저장하면 기존 암호를 유지한다.
- 원격 캘린더 목록을 가져와 `naver_sync_calendars`에 둔다. 계정에서 사라진 캘린더는 끄고, 로컬 일정은 그 자리에서 지우지 않는다.
- 선택한 캘린더만 가져온다. 로컬 수정은 네이버 내용으로 덮어쓰고, 네이버에서 사라진 객체와 Nextcloud에만 있는 `VEVENT`는 지운다. 이 앱이 만들지 않은 비-`VEVENT`는 `localEtags`에서 빠지므로 삭제 대상이 아니다.
- 한 번에 지울 양이 크면 삭제를 보류한다. 이어서 지우려면 `naversynchronization:sync --allow-mass-delete`가 필요하다.
- CalDAV 응답이 `multistatus`가 아니면 그 캘린더 동기화는 실패로 남는다. ETag가 없으면 데이터 해시를 버전으로 쓴다.
- 이름과 색은 로컬 값이 이 앱이 마지막으로 쓴 값과 같을 때만 네이버를 따라간다. 맞추기 동작은 `matchNaverAppearance`다.
- 휴지통 판정은 `{http://nextcloud.com/ns}deleted-at`과 `{http://owncloud.org/ns}deleted-at`을 본다. 휴지통에 있으면 그 캘린더는 연결되지 않은 것으로 본다.
- `SyncJob` 간격은 1초다. 개인 설정에서 즉시 동기화할 수 있다.

## 저장소

`git remote -v`에서 확인한 원격이다.

| 이름 | URL | 방향 |
| --- | --- | --- |
| `origin` | `git@github.com:chanha0406/naver-synchronization.git` | fetch, push |
| `backup` | `ssh://git@git.chanha.kr:2224/chanha0406/naver-synchronization.git` | fetch, push |

브랜치는 `main`이다. `git rev-parse HEAD`는 `fe6f8cfd33417966f74fecd3fcfe78c2e54afa89`이고, `git rev-parse --short HEAD`는 `fe6f8cf`다.

`info.xml`의 `website`와 `bugs`는 `https://github.com/chanha0406/naver-synchronization`와 `https://github.com/chanha0406/naver-synchronization/issues`다. 이 두 주소는 git remote가 아니라 `info.xml`에서 확인했다.

## 미확인

- 이 앱이 붙어 있는 Nextcloud의 운영 URL. 저장소의 git remote와 `info.xml`에는 없다.
- 네이버 CalDAV가 `PROPFIND` Depth 1에서 일정 전체를 주는지.
- Nextcloud `CalDavBackend`가 돌려주는 ETag 문자열과 `component` 값의 실제 형태. 이번 문서에서는 `vendor`와 설치본을 읽지 않았다.
