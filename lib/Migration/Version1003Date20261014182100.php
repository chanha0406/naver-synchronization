<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1003Date20261014182100 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('naver_sync_objects')) {
			return null;
		}
		$table = $schema->getTable('naver_sync_objects');
		if ($table->hasColumn('local_etag')) {
			return null;
		}
		$table->addColumn('local_etag', Types::STRING, [
			'notnull' => false,
			'length' => 255,
			'default' => null,
		]);
		return $schema;
	}
}
