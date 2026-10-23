# AGENTS.md

## 2-1 워크스페이스

이 저장소는 제품 코드 저장소다. 표시 이름은 Naver Synchronization, 앱 id는 `naversynchronization`이다.

`.gitmodules`가 없다. 서브모듈은 없다. 서브모듈이 생기면 참고용(읽기 전용)으로 두고, 루트에는 커밋 포인터만 기록한다. 명시적 요청 없이 서브모듈 내부를 수정하지 않는다.

하위 저장소에 자체 `AGENTS.md` 또는 `CLAUDE.md`가 있으면, 그 디렉터리 안에서는 그 규칙이 우선한다.

산출물은 루트 `doc/`에 둔다. 개요는 [doc/naver-synchronization-overview.md](doc/naver-synchronization-overview.md).

## 2-3 작업 언어

한국어로 쓴다. 코드 식별자와 기술 용어는 영문을 유지한다.

## 2-4 문서화

| 종류 | 경로 |
| --- | --- |
| 분석·조사 | `doc/analysis/YYYY-MM-DD-<주제>.md` |
| 계획 | `doc/plans/YYYY-MM-DD-<주제>.md` |
| 설계 | `doc/design/YYYY-MM-DD-<주제>.md` |
| 수정사항 | `doc/changes/YYYY-MM-DD-<주제>.md` |
| 아키텍처 | `doc/architecture/<주제>.md` 갱신형 |
| 백로그 | `doc/backlog/YYYY-MM-DD-<주제>.md` |
| 미구현·보류 | `doc/unimplemented/YYYY-MM-DD-<주제>.md` |
| 작업 로그 | `doc/worklog/YYYY-MM-DD.md` |
| 상시 개요 | `doc/` 루트 `naver-synchronization-overview.md` |

하위 폴더는 첫 문서를 넣을 때 만든다. 같은 주제가 있으면 그 문서를 갱신한다. 문서 상단에 작성일, 근거, 상태를 둔다. 확인하지 못한 내용은 미확인으로 적는다.

작업을 끝내면 `doc/worklog/`의 해당 날짜 문서와 `doc/README.md`를 갱신한다. 문서 목록의 단일 출처는 `doc/README.md`다. 목록, 작업 로그, 백로그, 아키텍처, 미구현 목록은 이 파일에 두지 않는다.

## 2-5 용어

용어 규칙은 없다. 구성요소 이름은 저장소 파일시스템에서 확인한 것만 쓴다.

- 외부 표기: ___ / 내부 표기: ___ / 금지 표기: ___

## 2-7 기록 위치

작업 기록은 `doc/worklog/YYYY-MM-DD.md`에 남긴다. 문서 목록은 `doc/README.md`에만 갱신한다. 분석, 계획, 설계, 수정사항, 아키텍처, 백로그, 미구현 문서는 2-4의 경로에 둔다.
