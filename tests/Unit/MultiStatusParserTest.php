<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Tests\Unit;

use OCA\NaverSynchronization\Service\MultiStatusParser;
use PHPUnit\Framework\TestCase;

class MultiStatusParserTest extends TestCase {
	public function testReadsResponsesByLocalName(): void {
		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav" xmlns:A="http://apple.com/ns/ical/">
	<D:response>
		<D:href>/caldav/user/calendar/</D:href>
		<D:propstat>
			<D:prop>
				<D:displayname>Work</D:displayname>
				<D:resourcetype><D:collection/><C:calendar/></D:resourcetype>
				<A:calendar-color>#FF0000</A:calendar-color>
			</D:prop>
			<D:status>HTTP/1.1 200 OK</D:status>
		</D:propstat>
		<D:propstat>
			<D:prop><D:getetag/></D:prop>
			<D:status>HTTP/1.1 404 Not Found</D:status>
		</D:propstat>
	</D:response>
	<D:response>
		<D:href>/caldav/user/calendar/a.ics</D:href>
		<D:propstat>
			<D:prop>
				<D:getetag>"e1"</D:getetag>
				<C:calendar-data>BEGIN:VCALENDAR
END:VCALENDAR</C:calendar-data>
			</D:prop>
			<D:status>HTTP/1.1 200 OK</D:status>
		</D:propstat>
	</D:response>
</D:multistatus>
XML;
		$parsed = (new MultiStatusParser())->parse($xml);
		$this->assertCount(2, $parsed);
		$this->assertSame('/caldav/user/calendar/', $parsed[0]['href']);
		$this->assertSame('Work', $parsed[0]['displayName']);
		$this->assertTrue($parsed[0]['isCalendar']);
		$this->assertNull($parsed[0]['etag']);
		$this->assertSame('"e1"', $parsed[1]['etag']);
		$this->assertStringStartsWith('BEGIN:VCALENDAR', (string)$parsed[1]['calendarData']);
		$this->assertFalse($parsed[1]['isCalendar']);
	}

	public function testEmptyMultistatusIsAnEmptyList(): void {
		$this->assertSame([], (new MultiStatusParser())->parse('<d:multistatus xmlns:d="DAV:"/>'));
	}
}
