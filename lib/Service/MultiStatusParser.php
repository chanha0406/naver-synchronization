<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Service;

use DOMDocument;
use DOMElement;
use OCA\NaverSynchronization\L10n\T;

/**
 * PROPFIND / REPORT multistatus parser.
 *
 * Sabre's property map drops or wraps Naver's calendar properties, so the
 * responses are read by local name instead.
 */
class MultiStatusParser {
	/**
	 * @return list<array{
	 *   href: string,
	 *   displayName: ?string,
	 *   etag: ?string,
	 *   color: ?string,
	 *   homeSet: list<string>,
	 *   isCalendar: bool,
	 *   isInbox: bool,
	 *   calendarData: ?string
	 * }>
	 */
	public function parse(string $xml): array {
		$previous = libxml_use_internal_errors(true);
		$document = new DOMDocument();
		$loaded = $document->loadXML($xml, LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		// An empty list would read as an empty calendar and delete every
		// imported event, so a broken response has to fail the sync.
		if ($loaded === false || $document->documentElement === null
			|| $document->documentElement->localName !== 'multistatus') {
			throw new \RuntimeException(T::t('Naver CalDAV returned a response that is not a valid multistatus.'));
		}

		$parsed = [];
		foreach ($this->elementsByLocalName($document->documentElement, 'response') as $response) {
			$hrefs = $this->elementsByLocalName($response, 'href');
			$href = $hrefs === [] ? '' : trim($hrefs[0]->textContent);
			if ($href === '') {
				continue;
			}

			$displayName = null;
			$etag = null;
			$color = null;
			$homeSet = [];
			$resourceTypes = [];
			$calendarData = null;

			foreach ($this->elementsByLocalName($response, 'propstat') as $propstat) {
				if (!$this->isSuccess($propstat)) {
					continue;
				}
				foreach ($this->elementsByLocalName($propstat, 'prop') as $prop) {
					foreach ($prop->childNodes as $child) {
						if (!$child instanceof DOMElement) {
							continue;
						}
						switch ($child->localName) {
							case 'displayname':
								$displayName = trim($child->textContent);
								break;
							case 'getetag':
								$etag = trim($child->textContent);
								break;
							case 'calendar-color':
								$color = trim($child->textContent);
								break;
							case 'calendar-home-set':
								foreach ($this->elementsByLocalName($child, 'href') as $homeHref) {
									$home = trim($homeHref->textContent);
									if ($home !== '') {
										$homeSet[] = $home;
									}
								}
								break;
							case 'resourcetype':
								foreach ($child->childNodes as $type) {
									if ($type instanceof DOMElement) {
										$resourceTypes[] = $type->localName;
									}
								}
								break;
							case 'calendar-data':
								$calendarData = $child->textContent;
								break;
						}
					}
				}
			}

			$isInbox = in_array('schedule-inbox', $resourceTypes, true)
				|| in_array('schedule-outbox', $resourceTypes, true);
			$parsed[] = [
				'href' => $href,
				'displayName' => $displayName,
				'etag' => $etag,
				'color' => $color,
				'homeSet' => $homeSet,
				'isCalendar' => in_array('calendar', $resourceTypes, true) && !$isInbox,
				'isInbox' => $isInbox,
				'calendarData' => $calendarData,
			];
		}

		return $parsed;
	}

	/**
	 * Direct children with this local name, whatever their namespace prefix.
	 *
	 * @return list<DOMElement>
	 */
	private function elementsByLocalName(DOMElement $element, string $localName): array {
		$found = [];
		foreach ($element->childNodes as $child) {
			if ($child instanceof DOMElement && $child->localName === $localName) {
				$found[] = $child;
			}
		}
		return $found;
	}

	private function isSuccess(DOMElement $propstat): bool {
		foreach ($this->elementsByLocalName($propstat, 'status') as $status) {
			if (preg_match('/\s(\d{3})\s/', ' ' . $status->textContent . ' ', $match) === 1) {
				$code = (int)$match[1];
				return $code >= 200 && $code < 300;
			}
		}
		return false;
	}
}
