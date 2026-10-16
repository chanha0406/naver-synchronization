<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Settings;

use OCA\NaverSynchronization\AppInfo\Application;
use OCA\NaverSynchronization\Service\SyncService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

class Personal implements ISettings {
	public function __construct(
		private ?string $userId,
		private SyncService $sync,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'personal');
		Util::addStyle(Application::APP_ID, 'personal');
		$state = [
			'naverId' => '',
			'passwordSet' => false,
			'calendars' => [],
		];
		if ($this->userId !== null) {
			$state = $this->sync->state($this->userId);
		}
		return new TemplateResponse(Application::APP_ID, 'personal', ['state' => $state]);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 10;
	}
}
