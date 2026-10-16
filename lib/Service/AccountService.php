<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Service;

use OCA\NaverSynchronization\L10n\T;

use OCA\NaverSynchronization\AppInfo\Application;
use OCP\Config\IUserConfig;
use OCP\Security\ICrypto;

class AccountService {
	public function __construct(
		private IUserConfig $userConfig,
		private ICrypto $crypto,
	) {
	}

	public function save(string $userId, string $naverId, string $password): void {
		$naverId = $this->normalizeId($naverId);
		if ($naverId === '') {
			throw new \InvalidArgumentException(T::t('Enter a Naver id.'));
		}
		if (str_contains($naverId, ':') || preg_match('/^[A-Za-z0-9._-]+$/', $naverId) !== 1) {
			throw new \InvalidArgumentException(T::t('The Naver id format is invalid. Leave off @naver.com.'));
		}

		$this->userConfig->setValueString($userId, Application::APP_ID, 'naver_id', $naverId, lazy: true);
		if ($password !== '') {
			$this->setPassword($userId, $password);
			return;
		}
		if ($this->getPassword($userId) === '') {
			throw new \InvalidArgumentException(T::t('Enter a password. If you use two-step verification, use an application password.'));
		}
	}

	public function hasPassword(string $userId): bool {
		return $this->getPassword($userId) !== '';
	}

	public function getNaverId(string $userId): string {
		return $this->userConfig->getValueString($userId, Application::APP_ID, 'naver_id', '', lazy: true);
	}

	/**
	 * @return array{0: string, 1: string}
	 */
	public function getCredentials(string $userId): array {
		$naverId = $this->getNaverId($userId);
		$password = $this->getPassword($userId);
		if ($naverId === '' || $password === '') {
			throw new \RuntimeException(T::t('No Naver account is saved.'));
		}
		return [$naverId, $password];
	}

	private function setPassword(string $userId, string $password): void {
		$encrypted = $this->crypto->encrypt($password);
		$this->userConfig->setValueString(
			$userId,
			Application::APP_ID,
			'naver_password',
			$encrypted,
			lazy: true,
			flags: IUserConfig::FLAG_SENSITIVE,
		);
	}

	private function getPassword(string $userId): string {
		$stored = $this->userConfig->getValueString($userId, Application::APP_ID, 'naver_password', '', lazy: true);
		if ($stored === '') {
			return '';
		}
		try {
			return $this->crypto->decrypt($stored);
		} catch (\Throwable) {
			return '';
		}
	}

	private function normalizeId(string $naverId): string {
		$naverId = trim($naverId);
		if (preg_match('/^([^@]+)@naver\.com$/i', $naverId, $match) === 1) {
			return $match[1];
		}
		return $naverId;
	}
}
