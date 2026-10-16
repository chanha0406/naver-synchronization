<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Controller;

use OCA\NaverSynchronization\AppInfo\Application;
use OCA\NaverSynchronization\Exception\AuthException;
use OCA\NaverSynchronization\Service\AccountService;
use OCA\NaverSynchronization\Service\SyncService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

class SyncController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private AccountService $accounts,
		private SyncService $sync,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function saveAccount(): JSONResponse {
		if ($this->userId === null) {
			return $this->error('로그인이 필요합니다.', Http::STATUS_UNAUTHORIZED);
		}
		try {
			$this->accounts->save(
				$this->userId,
				(string)$this->request->getParam('naverId', ''),
				(string)$this->request->getParam('password', ''),
			);
			$state = $this->sync->refreshCalendars($this->userId);
		} catch (\InvalidArgumentException $e) {
			return $this->error($e->getMessage(), Http::STATUS_BAD_REQUEST);
		} catch (AuthException $e) {
			return $this->error($e->getMessage(), Http::STATUS_UNAUTHORIZED);
		} catch (\Throwable $e) {
			return $this->loggedError($e);
		}
		$state['message'] = '계정을 저장하고 캘린더 목록을 가져왔습니다. 가져올 캘린더를 고른 뒤 저장하세요.';
		return new JSONResponse($state);
	}

	#[NoAdminRequired]
	public function getCalendars(int $refresh = 0): JSONResponse {
		if ($this->userId === null) {
			return $this->error('로그인이 필요합니다.', Http::STATUS_UNAUTHORIZED);
		}
		try {
			$state = $refresh === 1
				? $this->sync->refreshCalendars($this->userId)
				: $this->sync->state($this->userId);
		} catch (AuthException $e) {
			return $this->error($e->getMessage(), Http::STATUS_UNAUTHORIZED);
		} catch (\RuntimeException $e) {
			return $this->loggedError($e);
		}
		return new JSONResponse($state);
	}

	#[NoAdminRequired]
	public function saveSelection(): JSONResponse {
		if ($this->userId === null) {
			return $this->error('로그인이 필요합니다.', Http::STATUS_UNAUTHORIZED);
		}
		$calendars = $this->request->getParam('calendars');
		if (!is_array($calendars)) {
			return $this->error('캘린더 목록이 없습니다.', Http::STATUS_BAD_REQUEST);
		}
		$this->sync->saveSelection($this->userId, $calendars);
		$state = $this->sync->state($this->userId);
		$state['message'] = '선택한 캘린더를 저장했습니다.';
		return new JSONResponse($state);
	}

	#[NoAdminRequired]
	public function syncNow(): JSONResponse {
		if ($this->userId === null) {
			return $this->error('로그인이 필요합니다.', Http::STATUS_UNAUTHORIZED);
		}
		try {
			return new JSONResponse($this->sync->syncUser($this->userId));
		} catch (LockedException) {
			return $this->error('이미 동기화 중입니다.', Http::STATUS_CONFLICT);
		} catch (AuthException $e) {
			return $this->error($e->getMessage(), Http::STATUS_UNAUTHORIZED);
		} catch (\Throwable $e) {
			return $this->loggedError($e);
		}
	}

	private function loggedError(\Throwable $e): JSONResponse {
		$this->logger->warning($e->getMessage(), [
			'app' => Application::APP_ID,
			'exception' => $e,
		]);
		$message = $e->getMessage() !== '' ? $e->getMessage() : '네이버 캘린더에 연결하지 못했습니다.';
		return $this->error($message, Http::STATUS_BAD_GATEWAY);
	}

	private function error(string $message, int $status): JSONResponse {
		return new JSONResponse(['error' => $message], $status);
	}
}
