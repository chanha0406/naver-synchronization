<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Settings;

use OCA\NaverSynchronization\L10n\T;

use OCA\NaverSynchronization\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class PersonalSection implements IIconSection {
	public function __construct(
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return T::t('Naver calendar sync');
	}

	public function getPriority(): int {
		return 85;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
	}
}
