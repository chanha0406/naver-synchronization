<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setCalendarId(int $calendarId)
 * @method int getCalendarId()
 * @method void setRemoteHref(string $remoteHref)
 * @method string getRemoteHref()
 * @method void setHrefHash(string $hrefHash)
 * @method string getHrefHash()
 * @method void setRemoteEtag(string $remoteEtag)
 * @method string|null getRemoteEtag()
 * @method void setLocalUri(string $localUri)
 * @method string|null getLocalUri()
 * @method void setLocalEtag(?string $localEtag)
 * @method string|null getLocalEtag()
 */
class SyncedObject extends Entity {
	protected $calendarId;
	protected $remoteHref;
	protected $hrefHash;
	protected $remoteEtag;
	protected $localUri;
	protected $localEtag;

	public function __construct() {
		$this->addType('calendarId', 'integer');
	}
}
