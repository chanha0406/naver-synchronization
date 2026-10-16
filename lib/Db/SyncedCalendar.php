<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setRemoteHref(string $remoteHref)
 * @method string getRemoteHref()
 * @method void setHrefHash(string $hrefHash)
 * @method string getHrefHash()
 * @method void setDisplayName(string $displayName)
 * @method string|null getDisplayName()
 * @method void setColor(?string $color)
 * @method string|null getColor()
 * @method void setNcCalendarId(int $ncCalendarId)
 * @method int getNcCalendarId()
 * @method void setEnabled(int $enabled)
 * @method int getEnabled()
 * @method void setLastSync(int $lastSync)
 * @method int getLastSync()
 * @method void setLastError(?string $lastError)
 * @method string|null getLastError()
 * @method void setLastSummary(?string $lastSummary)
 * @method string|null getLastSummary()
 */
class SyncedCalendar extends Entity {
	protected $userId;
	protected $remoteHref;
	protected $hrefHash;
	protected $displayName;
	protected $color;
	protected $ncCalendarId;
	protected $enabled;
	protected $lastSync;
	protected $lastError;
	protected $lastSummary;

	public function __construct() {
		$this->addType('ncCalendarId', 'integer');
		$this->addType('enabled', 'integer');
		$this->addType('lastSync', 'integer');
	}
}
