<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20261014130000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		if (!$schema->hasTable('naver_sync_calendars')) {
			$table = $schema->createTable('naver_sync_calendars');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('remote_href', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('href_hash', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('display_name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('color', Types::STRING, [
				'notnull' => false,
				'length' => 7,
				'default' => null,
			]);
			$table->addColumn('nc_calendar_id', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('enabled', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('last_sync', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('last_error', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('last_summary', Types::STRING, [
				'notnull' => false,
				'length' => 512,
				'default' => null,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id', 'href_hash'], 'naver_cal_user_hash');
			$table->addIndex(['enabled'], 'naver_cal_enabled');
			$changed = true;
		}

		if (!$schema->hasTable('naver_sync_objects')) {
			$table = $schema->createTable('naver_sync_objects');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('calendar_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('remote_href', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('href_hash', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('remote_etag', Types::STRING, [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('local_uri', Types::STRING, [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['calendar_id', 'href_hash'], 'naver_obj_cal_hash');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
