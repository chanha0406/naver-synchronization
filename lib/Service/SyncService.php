<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Service;

use OCA\NaverSynchronization\L10n\T;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\NaverSynchronization\AppInfo\Application;
use OCA\NaverSynchronization\Db\SyncedCalendar;
use OCA\NaverSynchronization\Db\SyncedCalendarMapper;
use OCA\NaverSynchronization\Db\SyncedObject;
use OCA\NaverSynchronization\Db\SyncedObjectMapper;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;
use Sabre\DAV\PropPatch;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class SyncService {
	private const MAX_OBJECT_BYTES = 20971520;

	public function __construct(
		private AccountService $accounts,
		private SyncedCalendarMapper $calendars,
		private SyncedObjectMapper $objects,
		private MultiStatusParser $parser,
		private CalDavBackend $caldav,
		private ILockingProvider $locking,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{naverId: string, passwordSet: bool, calendars: list<array<string, mixed>>}
	 */
	public function state(string $userId): array {
		return [
			'naverId' => $this->accounts->getNaverId($userId),
			'passwordSet' => $this->accounts->hasPassword($userId),
			'calendars' => $this->calendarPayload($userId),
		];
	}

	/**
	 * @return array{naverId: string, passwordSet: bool, calendars: list<array<string, mixed>>}
	 */
	public function refreshCalendars(string $userId): array {
		[$naverId, $password] = $this->accounts->getCredentials($userId);
		$client = new CalDavClient($naverId, $password, $this->parser);
		$remote = $client->listCalendars();

		$byHash = [];
		foreach ($this->calendars->findByUser($userId) as $row) {
			$byHash[(string)$row->getHrefHash()] = $row;
		}

		$seen = [];
		foreach ($remote as $calendar) {
			if ($calendar['href'] === '') {
				continue;
			}
			$hash = hash('sha256', $calendar['href']);
			$seen[$hash] = true;
			$row = $byHash[$hash] ?? new SyncedCalendar();
			if (!isset($byHash[$hash])) {
				$row->setUserId($userId);
				$row->setHrefHash($hash);
				$row->setEnabled(0);
				$row->setNcCalendarId(0);
				$row->setLastSync(0);
			}
			$row->setRemoteHref($calendar['href']);
			$row->setDisplayName(mb_substr($calendar['name'], 0, 255));
			$row->setColor($calendar['color']);
			$row->setLastError(null);
			if ($row->getId() === null) {
				$this->calendars->insert($row);
			} else {
				$this->calendars->update($row);
			}
		}

		foreach ($byHash as $hash => $row) {
			if (isset($seen[$hash])) {
				continue;
			}
			$row->setEnabled(0);
			$row->setLastError(T::t('This calendar was not found in the Naver account.'));
			$this->calendars->update($row);
		}

		return $this->state($userId);
	}

	/**
	 * @param list<array<string, mixed>> $selected
	 */
	public function saveSelection(string $userId, array $selected): void {
		$wanted = [];
		foreach ($selected as $item) {
			if (!is_array($item) || !isset($item['id'])) {
				continue;
			}
			$wanted[(int)$item['id']] = !empty($item['enabled']);
		}
		foreach ($this->calendars->findByUser($userId) as $row) {
			$id = (int)$row->getId();
			if (!array_key_exists($id, $wanted)) {
				continue;
			}
			$row->setEnabled($wanted[$id] ? 1 : 0);
			$this->calendars->update($row);
		}
	}

	public function syncAll(): void {
		foreach ($this->calendars->findUserIdsToSync() as $userId) {
			try {
				$this->syncUser($userId);
			} catch (LockedException) {
				$this->logger->info(T::t('Naver calendar sync is already running.'), [
					'app' => Application::APP_ID,
				]);
			} catch (\Throwable $e) {
				$this->logger->error(T::t('Naver calendar sync failed: %s', [$e->getMessage()]), [
					'app' => Application::APP_ID,
					'exception' => $e,
				]);
			}
		}
	}

	/**
	 * @return array{naverId: string, passwordSet: bool, calendars: list<array<string, mixed>>, message: string}
	 */
	public function syncUser(string $userId, bool $allowMassDelete = false): array {
		T::forUser($userId);
		try {
		$path = 'naversynchronization/' . $userId;
		$this->locking->acquireLock($path, ILockingProvider::LOCK_EXCLUSIVE);
		try {
			return $this->syncUserLocked($userId, $allowMassDelete);
		} finally {
			$this->locking->releaseLock($path, ILockingProvider::LOCK_EXCLUSIVE);
		}
			} finally {
			T::forUser(null);
		}
	}

	/**
	 * @return array{naverId: string, passwordSet: bool, calendars: list<array<string, mixed>>, message: string}
	 */
	private function syncUserLocked(string $userId, bool $allowMassDelete): array {
		@set_time_limit(0);
		[$naverId, $password] = $this->accounts->getCredentials($userId);
		$client = new CalDavClient($naverId, $password, $this->parser);
		$enabled = array_values(array_filter(
			$this->calendars->findByUser($userId),
			static fn (SyncedCalendar $row): bool => (int)$row->getEnabled() === 1,
		));
		if ($enabled === []) {
			$state = $this->state($userId);
			$state['message'] = T::t('No calendar is selected. Choose calendars and save.');
			return $state;
		}

		$parts = [];
		foreach ($enabled as $mapping) {
			$name = (string)$mapping->getDisplayName();
			try {
				$result = $this->syncCalendar($userId, $mapping, $client, $allowMassDelete);
				$mapping->setLastSync(time());
				$mapping->setLastSummary($result['summary']);
				$mapping->setLastError($result['error']);
				$this->calendars->update($mapping);
				$parts[] = $name . ': ' . $result['summary'];
			} catch (\Throwable $e) {
				$this->logger->error(T::t('Could not import the Naver calendar: %s', [$e->getMessage()]), [
					'app' => Application::APP_ID,
					'exception' => $e,
				]);
				$mapping->setLastError($e->getMessage());
				$mapping->setLastSummary(null);
				$this->calendars->update($mapping);
				$parts[] = $name . ': ' . $e->getMessage();
			}
		}

		$state = $this->state($userId);
		$state['message'] = implode("\n", $parts);
		return $state;
	}

	/**
	 * Read-only mirror: local edits are overwritten, local deletes are restored,
	 * and events that exist only in Nextcloud are removed.
	 *
	 * @return array{summary: string, error: ?string}
	 */
	private function syncCalendar(string $userId, SyncedCalendar $mapping, CalDavClient $client, bool $allowMassDelete): array {
		$ncCalendarId = $this->ensureCalendar($userId, $mapping);
		$remote = $client->listObjects((string)$mapping->getRemoteHref());
		$byHash = [];
		foreach ($this->objects->findByCalendar((int)$mapping->getId()) as $row) {
			$byHash[(string)$row->getHrefHash()] = $row;
		}
		$local = $this->localEtags($ncCalendarId);

		$seen = [];
		$touched = [];
		$added = 0;
		$updated = 0;
		$reverted = 0;
		$restored = 0;
		$kept = 0;
		$failed = 0;
		foreach ($remote as $item) {
			if ($item['href'] === '') {
				continue;
			}
			$hash = hash('sha256', $item['href']);
			$seen[$hash] = true;
			$previous = $byHash[$hash] ?? null;
			$etag = $item['etag'];
			if (strlen($etag) > 255) {
				$etag = 'sha256:' . hash('sha256', $etag);
			}
			$localUri = $previous !== null && (string)$previous->getLocalUri() !== ''
				? (string)$previous->getLocalUri()
				: substr($hash, 0, 32) . '.ics';
			$touched[$localUri] = true;
			$storedLocal = $previous !== null ? (string)($previous->getLocalEtag() ?? '') : '';
			$remoteSame = $previous !== null && $etag !== '' && (string)$previous->getRemoteEtag() === $etag;
			$localEtag = $local[$localUri] ?? null;
			if ($remoteSame && $localEtag !== null && $storedLocal !== '' && $localEtag === $storedLocal) {
				$kept++;
				continue;
			}
			if ($previous === null) {
				$kind = 'add';
			} elseif ($localEtag === null) {
				$kind = 'restore';
			} elseif ($remoteSame) {
				$kind = 'revert';
			} else {
				$kind = 'update';
			}

			try {
				$data = $item['data'];
				if (!is_string($data) || $data === '') {
					$data = $client->download($item['href']);
					if ($etag === '') {
						$etag = 'sha256:' . hash('sha256', $data);
					}
				}
				$ics = $this->normalizeIcs($data, $localUri);
				$writtenEtag = $this->writeObject($ncCalendarId, $localUri, $ics);
			} catch (\Throwable $e) {
				$failed++;
				$this->logger->warning(T::t('Skipped one Naver event: %s', [$e->getMessage()]), [
					'app' => Application::APP_ID,
				]);
				continue;
			}

			if ($previous === null) {
				$stored = new SyncedObject();
				$stored->setCalendarId((int)$mapping->getId());
				$stored->setHrefHash($hash);
				$stored->setRemoteHref($item['href']);
				$stored->setRemoteEtag($etag);
				$stored->setLocalUri($localUri);
				$stored->setLocalEtag($writtenEtag);
				$this->objects->insert($stored);
			} else {
				$previous->setRemoteHref($item['href']);
				$previous->setRemoteEtag($etag);
				$previous->setLocalUri($localUri);
				$previous->setLocalEtag($writtenEtag);
				$this->objects->update($previous);
			}
			if ($kind === 'add') {
				$added++;
			} elseif ($kind === 'restore') {
				$restored++;
			} elseif ($kind === 'revert') {
				$reverted++;
			} else {
				$updated++;
			}
		}

		$pending = [];
		foreach ($byHash as $hash => $row) {
			if (isset($seen[$hash])) {
				continue;
			}
			$uri = (string)($row->getLocalUri() ?? '');
			$pending[$uri !== '' ? $uri : 'row:' . $row->getId()] = $row;
		}
		foreach (array_keys($local) as $uri) {
			if (isset($touched[$uri]) || isset($pending[$uri])) {
				continue;
			}
			$pending[$uri] = null;
		}

		$deleted = 0;
		$held = 0;
		$error = null;
		$deletionCount = count($pending);
		if ($deletionCount > 0 && !$allowMassDelete && $this->tooManyDeletions($deletionCount, max(count($byHash), count($local)))) {
			$held = $deletionCount;
			$error = T::t('Refused to delete %1$s items at once. Check them, then continue with occ naversynchronization:sync --allow-mass-delete %2$s.', [$deletionCount, $userId]);
		} else {
			foreach ($pending as $uri => $row) {
				if (!str_starts_with((string)$uri, 'row:') && isset($local[$uri])) {
					$this->caldav->deleteCalendarObject($ncCalendarId, (string)$uri, CalDavBackend::CALENDAR_TYPE_CALENDAR, true);
				}
				if ($row !== null) {
					$this->objects->delete($row);
				}
				$deleted++;
			}
		}

		$summary = T::t('Added %1$s, updated %2$s, reverted %3$s, restored %4$s, deleted %5$s, unchanged %6$s', [$added, $updated, $reverted, $restored, $deleted, $kept]);
		if ($held > 0) {
			$summary .= T::t(', deletion held %s', [$held]);
		}
		if ($failed > 0) {
			$summary .= T::t(', failed %s', [$failed]);
		}
		return ['summary' => $summary, 'error' => $error];
	}

	/**
	 * @return array<string, string> uri => etag
	 */
	private function localEtags(int $ncCalendarId): array {
		$etags = [];
		foreach ($this->caldav->getCalendarObjects($ncCalendarId) as $object) {
			if (($object['component'] ?? '') !== 'vevent') {
				continue;
			}
			$uri = (string)($object['uri'] ?? '');
			if ($uri === '') {
				continue;
			}
			$etags[$uri] = (string)($object['etag'] ?? '');
		}
		return $etags;
	}

	private function writeObject(int $ncCalendarId, string $localUri, string $ics): string {
		$existing = $this->caldav->getCalendarObject($ncCalendarId, $localUri);
		if ($existing === null) {
			return (string)$this->caldav->createCalendarObject($ncCalendarId, $localUri, $ics);
		}
		return (string)$this->caldav->updateCalendarObject($ncCalendarId, $localUri, $ics);
	}

	private function tooManyDeletions(int $deletions, int $total): bool {
		return $deletions >= 50 || ($deletions >= 10 && $deletions * 5 >= max($total, 1));
	}

	private function ensureCalendar(string $userId, SyncedCalendar $mapping): int {
		$principal = 'principals/users/' . $userId;
		$displayName = $this->localName((string)$mapping->getDisplayName());
		$color = $mapping->getColor();
		$existingId = (int)$mapping->getNcCalendarId();
		if ($existingId > 0) {
			$calendar = $this->caldav->getCalendarById($existingId);
			if (is_array($calendar)
				&& !$this->isInTrash($calendar)
				&& ($calendar['principaluri'] ?? '') === $principal) {
				$this->updateCalendarMeta($existingId, $displayName, is_string($color) ? $color : null, $calendar);
				return $existingId;
			}
		}

		$params = [
			'{DAV:}displayname' => $displayName,
			'components' => 'VEVENT',
		];
		if (is_string($color) && $color !== '') {
			$params['{http://apple.com/ns/ical/}calendar-color'] = $color;
		}
		$uri = 'naver-import-' . substr((string)$mapping->getHrefHash(), 0, 32);
		try {
			$created = (int)$this->caldav->createCalendar($principal, $uri, $params);
		} catch (\Throwable) {
			$created = (int)$this->caldav->createCalendar($principal, $uri . '-' . $mapping->getId(), $params);
		}
		$this->objects->deleteByCalendar((int)$mapping->getId());
		$mapping->setNcCalendarId($created);
		$this->calendars->update($mapping);
		return $created;
	}

	/**
	 * @param array<string, mixed> $calendar
	 */
	private function updateCalendarMeta(int $calendarId, string $displayName, ?string $color, array $calendar): void {
		$mutations = [];
		if (($calendar['{DAV:}displayname'] ?? '') !== $displayName) {
			$mutations['{DAV:}displayname'] = $displayName;
		}
		$currentColor = strtoupper(ltrim((string)($calendar['{http://apple.com/ns/ical/}calendar-color'] ?? '')));
		if ($color !== null && $color !== '' && !str_starts_with($currentColor, $color)) {
			$mutations['{http://apple.com/ns/ical/}calendar-color'] = $color;
		}
		if ($mutations === []) {
			return;
		}
		$patch = new PropPatch($mutations);
		$this->caldav->updateCalendar($calendarId, $patch);
		$patch->commit();
	}

	/**
	 * @param array<string, mixed> $calendar
	 */
	private function isInTrash(array $calendar): bool {
		return ($calendar['{http://owncloud.org/ns}deleted-at'] ?? null) !== null;
	}

	private function localName(string $name): string {
		$name = trim($name);
		foreach ([' (네이버)', ' (Naver Calendar)'] as $old) {
			if (str_ends_with($name, $old)) {
				$name = trim(mb_substr($name, 0, mb_strlen($name) - mb_strlen($old)));
			}
		}
		if ($name === '') {
			$name = T::t('Naver calendar');
		}
		$suffix = ' (Naver Calendar)';
		return mb_substr($name, 0, 255 - mb_strlen($suffix)) . $suffix;
	}

	private function normalizeIcs(string $ics, string $localUri): string {
		if (strlen($ics) > self::MAX_OBJECT_BYTES) {
			throw new \RuntimeException(T::t('The event is too large.'));
		}
		$vobject = Reader::read($ics, Reader::OPTION_FORGIVING);
		if (!$vobject instanceof VCalendar) {
			$calendar = new VCalendar();
			$calendar->add($vobject);
			$vobject = $calendar;
		}
		if (isset($vobject->METHOD)) {
			unset($vobject->METHOD);
		}
		$hasComponent = false;
		foreach ($vobject->getComponents() as $component) {
			if ($component->name === 'VTIMEZONE') {
				continue;
			}
			$hasComponent = true;
			if (!isset($component->UID) || trim((string)$component->UID) === '') {
				$component->UID = $localUri;
			}
		}
		if (!$hasComponent) {
			throw new \RuntimeException(T::t('The event has no calendar component.'));
		}
		return $vobject->serialize();
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function calendarPayload(string $userId): array {
		$payload = [];
		foreach ($this->calendars->findByUser($userId) as $row) {
			$payload[] = [
				'id' => (int)$row->getId(),
				'name' => (string)($row->getDisplayName() ?? ''),
				'color' => (string)($row->getColor() ?? ''),
				'enabled' => (int)$row->getEnabled() === 1,
				'lastSync' => (int)$row->getLastSync(),
				'lastError' => (string)($row->getLastError() ?? ''),
				'lastSummary' => (string)($row->getLastSummary() ?? ''),
			];
		}
		return $payload;
	}
}
