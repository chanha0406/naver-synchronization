<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\BackgroundJob;

use OCA\NaverSynchronization\Service\SyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class SyncJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private SyncService $sync,
	) {
		parent::__construct($time);
		// Every cron run. Time-sensitive so it is not deferred to the night window.
		$this->setInterval(1);
		$this->setAllowParallelRuns(false);
	}

	#[\Override]
	protected function run($argument): void {
		$this->sync->syncAll();
	}
}
