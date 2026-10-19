<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1004Date20261020020000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('naver_sync_calendars')) {
			return null;
		}
		$table = $schema->getTable('naver_sync_calendars');
		$changed = false;
		if (!$table->hasColumn('applied_name')) {
			$table->addColumn('applied_name', Types::STRING, ['notnull' => false, 'length' => 512, 'default' => null]);
			$changed = true;
		}
		if (!$table->hasColumn('applied_color')) {
			$table->addColumn('applied_color', Types::STRING, ['notnull' => false, 'length' => 16, 'default' => null]);
			$changed = true;
		}
		return $changed ? $schema : null;
	}
}
