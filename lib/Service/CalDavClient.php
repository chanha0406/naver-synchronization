<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Service;

use OCA\NaverSynchronization\L10n\T;

use OCA\NaverSynchronization\AppInfo\Application;
use OCA\NaverSynchronization\Exception\AuthException;
use Sabre\DAV\Client;
use Sabre\HTTP\ClientException;

class CalDavClient {
	private const PROPFIND_BODY = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:a="http://apple.com/ns/ical/">
	<d:prop>
		<d:displayname/>
		<d:resourcetype/>
		<d:getetag/>
		<c:calendar-home-set/>
		<c:calendar-data/>
		<a:calendar-color/>
	</d:prop>
</d:propfind>
XML;

	private const REPORT_BODY = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
	<d:prop>
		<d:getetag/>
		<c:calendar-data/>
	</d:prop>
	<c:filter>
		<c:comp-filter name="VCALENDAR"/>
	</c:filter>
</c:calendar-query>
XML;

	private ?Client $client = null;

	public function __construct(
		private string $username,
		private string $password,
		private MultiStatusParser $parser,
	) {
	}

	/**
	 * @return list<array{href: string, name: string, color: ?string}>
	 */
	public function listCalendars(): array {
		$id = rawurlencode($this->username);
		$paths = [
			'/principals/users/' . $id . '/',
			'/principals/users/' . $id,
			'/principals/' . $id . '/',
			'/caldav/' . $id . '/user/',
		];
		$lastError = T::t('Could not find the calendar URL.');
		foreach ($paths as $path) {
			try {
				$responses = $this->propfind($path, 0);
			} catch (AuthException $e) {
				throw $e;
			} catch (\Throwable $e) {
				$lastError = $e->getMessage();
				continue;
			}

			$homes = [];
			foreach ($responses as $response) {
				foreach ($response['homeSet'] as $home) {
					$homes[] = $this->absolute($home);
				}
			}
			if ($homes === []) {
				continue;
			}

			$calendars = [];
			foreach (array_unique($homes) as $home) {
				$children = $this->propfind($this->pathOf($home), 1);
				foreach ($children as $child) {
					if (!$child['isCalendar']) {
						continue;
					}
					$href = $this->absolute($child['href']);
					if ($this->sameResource($href, $home)) {
						continue;
					}
					$calendars[] = [
						'href' => $href,
						'name' => $child['displayName'] !== null && $child['displayName'] !== ''
							? $child['displayName']
							: T::t('Naver calendar'),
						'color' => $this->normalizeColor($child['color']),
					];
				}
			}
			if ($calendars !== []) {
				return $this->uniqueCalendars($calendars);
			}
			$lastError = T::t('The account is valid, but there is no calendar to import.');
		}

		throw new \RuntimeException($lastError);
	}

	/**
	 * @return list<array{href: string, etag: string, data: ?string}>
	 */
	public function listObjects(string $calendarHref): array {
		$responses = $this->propfind($this->pathOf($calendarHref), 1);
		if ($responses === []) {
			throw new \RuntimeException(T::t('The event list response was empty.'));
		}

		$objects = [];
		foreach ($responses as $response) {
			$href = $this->absolute($response['href']);
			if ($this->sameResource($href, $calendarHref) || $response['isCalendar'] || $response['isInbox']) {
				continue;
			}
			$objects[] = [
				'href' => $href,
				'etag' => $this->normalizeEtag($response['etag'] ?? ''),
				'data' => $response['calendarData'],
			];
		}
		if ($objects !== []) {
			return $objects;
		}

		$reported = $this->report($this->pathOf($calendarHref));
		$objects = [];
		foreach ($reported as $response) {
			$href = $this->absolute($response['href']);
			if ($this->sameResource($href, $calendarHref)) {
				continue;
			}
			$objects[] = [
				'href' => $href,
				'etag' => $this->normalizeEtag($response['etag'] ?? ''),
				'data' => $response['calendarData'],
			];
		}
		return $objects;
	}

	public function download(string $href): string {
		$response = $this->request('GET', $this->pathOf($href), null, [
			'Accept' => 'text/calendar',
		]);
		return $response['body'];
	}

	/**
	 * @return list<array{href: string, displayName: ?string, etag: ?string, color: ?string, homeSet: list<string>, isCalendar: bool, isInbox: bool, calendarData: ?string}>
	 */
	private function propfind(string $path, int $depth): array {
		$response = $this->request('PROPFIND', $path, self::PROPFIND_BODY, [
			'Depth' => (string)$depth,
			'Content-Type' => 'application/xml; charset=utf-8',
		]);
		return $this->parser->parse($response['body']);
	}

	/**
	 * @return list<array{href: string, displayName: ?string, etag: ?string, color: ?string, homeSet: list<string>, isCalendar: bool, isInbox: bool, calendarData: ?string}>
	 */
	private function report(string $path): array {
		$response = $this->request('REPORT', $path, self::REPORT_BODY, [
			'Depth' => '1',
			'Content-Type' => 'application/xml; charset=utf-8',
		]);
		return $this->parser->parse($response['body']);
	}

	/**
	 * @param array<string, string> $headers
	 * @return array{body: string, statusCode: int, headers: array<string, mixed>}
	 */
	private function request(string $method, string $path, ?string $body, array $headers): array {
		try {
			$response = $this->client()->request($method, $path, $body, $headers);
		} catch (ClientException $e) {
			throw new \RuntimeException(T::t('Could not connect to the Naver calendar server.'), 0, $e);
		}

		$code = $response['statusCode'];
		if ($code === 401 || $code === 403) {
			throw new AuthException(T::t('The id or password is wrong. If you use two-step verification, enter an application password.'));
		}
		if ($code < 200 || $code >= 300) {
			throw new \RuntimeException(T::t('Naver CalDAV returned HTTP %s.', [$code]));
		}
		return $response;
	}

	private function client(): Client {
		if ($this->client instanceof Client) {
			return $this->client;
		}
		$this->client = new Client([
			'baseUri' => Application::SERVER . '/',
			'userName' => $this->username,
			'password' => $this->password,
			'authType' => Client::AUTH_BASIC,
		]);
		$this->client->addCurlSetting(CURLOPT_FOLLOWLOCATION, 1);
		$this->client->addCurlSetting(CURLOPT_TIMEOUT, 120);
		$this->client->addCurlSetting(CURLOPT_CONNECTTIMEOUT, 20);
		$this->client->addCurlSetting(CURLOPT_USERAGENT, 'Nextcloud-Naver-Synchronization/1.0');
		return $this->client;
	}

	private function absolute(string $href): string {
		$href = trim($href);
		if (str_starts_with($href, 'https://') || str_starts_with($href, 'http://')) {
			return $href;
		}
		if (!str_starts_with($href, '/')) {
			$href = '/' . $href;
		}
		return Application::SERVER . $href;
	}

	private function pathOf(string $url): string {
		$parts = parse_url($url);
		$path = $parts['path'] ?? '/';
		if (!str_starts_with($path, '/')) {
			$path = '/' . $path;
		}
		if (isset($parts['query']) && $parts['query'] !== '') {
			$path .= '?' . $parts['query'];
		}
		return $path;
	}

	private function sameResource(string $a, string $b): bool {
		$normalize = static function (string $url): string {
			$path = parse_url($url, PHP_URL_PATH);
			$path = is_string($path) ? rawurldecode($path) : $url;
			return rtrim($path, '/');
		};
		return $normalize($a) === $normalize($b);
	}

	private function normalizeColor(?string $color): ?string {
		if ($color === null) {
			return null;
		}
		if (preg_match('/^(#[0-9A-Fa-f]{6})([0-9A-Fa-f]{2})?$/', trim($color), $match) !== 1) {
			return null;
		}
		return strtoupper($match[1]);
	}

	public function normalizeEtag(string $etag): string {
		$etag = trim($etag);
		if (str_starts_with($etag, 'W/')) {
			$etag = trim(substr($etag, 2));
		}
		return trim($etag, '"');
	}

	/**
	 * @param list<array{href: string, name: string, color: ?string}> $calendars
	 * @return list<array{href: string, name: string, color: ?string}>
	 */
	private function uniqueCalendars(array $calendars): array {
		$unique = [];
		foreach ($calendars as $calendar) {
			$unique[$calendar['href']] = $calendar;
		}
		return array_values($unique);
	}
}
