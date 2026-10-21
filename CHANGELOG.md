# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-10-21

### Fixed

- The first name and color check no longer overwrites a calendar renamed or recolored in Nextcloud before this app tracked the values.
- Objects this app imported are recognized whatever their component, so a non-event object from Naver is no longer restored on every sync.
- Refreshing the calendar list keeps a held mass deletion error until the next sync.

### Changed

- syncCalendar is split into importItem and deleteMissing; the name/color logic is the Appearance class shared with google-twoway-sync.
- Unit tests in tests/Unit run without a Nextcloud instance (tests/run-unit.sh).

## [1.0.4] - 2026-10-21

### Fixed

- Events without an ETag use a hash of their data as the version on every listing path, so unchanged events are no longer rewritten on each sync.

## [1.0.3] - 2026-10-21

### Fixed

- A broken or non-multistatus CalDAV response now fails the sync. Before, it read as an empty calendar and removed every imported event.

## [1.0.2] - 2026-10-20

### Changed

- Calendar names and colors no longer follow Naver after you change them in Nextcloud. Naver updates apply only while the Nextcloud value still matches the last value this app wrote.
- Personal settings show per-calendar Match Naver name and Match Naver color buttons when the local value differs from Naver.

## [1.0.1]

### Added

- First release
