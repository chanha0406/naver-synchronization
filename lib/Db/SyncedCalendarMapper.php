<?php

declare(strict_types=1);

namespace OCA\NaverSynchronization\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<SyncedCalendar>
 */
class SyncedCalendarMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'naver_sync_calendars', SyncedCalendar::class);
	}

	/**
	 * @return list<SyncedCalendar>
	 */
	public function findByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('display_name', 'ASC');
		/** @var list<SyncedCalendar> $rows */
		$rows = $this->findEntities($qb);
		return $rows;
	}

	public function findByUserAndHash(string $userId, string $hrefHash): ?SyncedCalendar {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('href_hash', $qb->createNamedParameter($hrefHash)));
		$rows = $this->findEntities($qb);
		return $rows[0] ?? null;
	}

	public function findForUser(string $userId, int $id): ?SyncedCalendar {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$rows = $this->findEntities($qb);
		return $rows[0] ?? null;
	}

	/**
	 * @return list<string>
	 */
	public function findUserIdsToSync(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('user_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$ids = [];
		while (($row = $result->fetchAssociative()) !== false) {
			$ids[] = (string)$row['user_id'];
		}
		$result->closeCursor();
		return $ids;
	}
}
