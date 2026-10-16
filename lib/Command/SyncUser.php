<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Command;

use OCA\NaverSynchronization\Service\SyncService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncUser extends Command {
	public function __construct(
		private SyncService $sync,
		private IUserManager $users,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('naversynchronization:sync')
			->setDescription('저장한 네이버 캘린더를 Nextcloud로 동기화합니다')
			->addArgument('user', InputArgument::REQUIRED, 'Nextcloud 사용자 ID')
			->addOption('allow-mass-delete', null, InputOption::VALUE_NONE, '한 번에 많은 일정을 지워도 진행합니다');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = (string)$input->getArgument('user');
		if ($this->users->get($userId) === null) {
			$output->writeln('<error>없는 사용자입니다.</error>');
			return 1;
		}
		try {
			$result = $this->sync->syncUser($userId, (bool)$input->getOption('allow-mass-delete'));
		} catch (\Throwable $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}
		$output->writeln((string)($result['message'] ?? '완료'));
		return 0;
	}
}
