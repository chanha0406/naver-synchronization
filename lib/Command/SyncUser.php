<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Command;

use OCA\NaverSynchronization\L10n\T;

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
			->setDescription(T::t('Sync the saved Naver calendars into Nextcloud'))
			->addArgument('user', InputArgument::REQUIRED, T::t('Nextcloud user id'))
			->addOption('allow-mass-delete', null, InputOption::VALUE_NONE, T::t('Continue even if many events would be deleted'));
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = (string)$input->getArgument('user');
		if ($this->users->get($userId) === null) {
			$output->writeln('<error>' . T::t('Unknown user.') . '</error>');
			return 1;
		}
		try {
			$result = $this->sync->syncUser($userId, (bool)$input->getOption('allow-mass-delete'));
		} catch (\Throwable $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}
		$output->writeln((string)($result['message'] ?? T::t('Done')));
		return 0;
	}
}
