<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Service;

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
			throw new \InvalidArgumentException('네이버 아이디를 입력하세요.');
		}
		if (str_contains($naverId, ':') || preg_match('/^[A-Za-z0-9._-]+$/', $naverId) !== 1) {
			throw new \InvalidArgumentException('네이버 아이디 형식이 올바르지 않습니다. @naver.com 은 빼고 입력하세요.');
		}

		$this->userConfig->setValueString($userId, Application::APP_ID, 'naver_id', $naverId, lazy: true);
		if ($password !== '') {
			$this->setPassword($userId, $password);
			return;
		}
		if ($this->getPassword($userId) === '') {
			throw new \InvalidArgumentException('암호를 입력하세요. 2단계 인증이면 애플리케이션 비밀번호를 사용합니다.');
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
			throw new \RuntimeException('네이버 계정이 저장되어 있지 않습니다.');
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
