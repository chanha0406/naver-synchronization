<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<SyncedObject>
 */
class SyncedObjectMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'naver_sync_objects', SyncedObject::class);
	}

	/**
	 * @return list<SyncedObject>
	 */
	public function findByCalendar(int $calendarId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('calendar_id', $qb->createNamedParameter($calendarId, IQueryBuilder::PARAM_INT)));
		/** @var list<SyncedObject> $rows */
		$rows = $this->findEntities($qb);
		return $rows;
	}

	public function deleteByCalendar(int $calendarId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('calendar_id', $qb->createNamedParameter($calendarId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
