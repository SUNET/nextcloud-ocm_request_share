<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 SUNET <kano@sunet.se>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OcmRequestShare\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ShareRequest>
 */
class ShareRequestMapper extends QBMapper {
	public const TABLE_NAME = 'ocm_share_requests';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE_NAME, ShareRequest::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $id): ShareRequest {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE_NAME)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Application-level dedup check for incoming requests — find an existing
	 * pending row for the same (owner, requester, share_ref) tuple before
	 * inserting a duplicate.
	 */
	public function findPendingIncoming(string $ownerOcm, string $requesterOcm, string $shareRef): ?ShareRequest {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE_NAME)
			->where($qb->expr()->eq('direction', $qb->createNamedParameter(ShareRequest::DIRECTION_INCOMING)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(ShareRequest::STATUS_PENDING)))
			->andWhere($qb->expr()->eq('owner_ocm', $qb->createNamedParameter($ownerOcm)))
			->andWhere($qb->expr()->eq('requester_ocm', $qb->createNamedParameter($requesterOcm)))
			->andWhere($qb->expr()->eq('share_ref', $qb->createNamedParameter($shareRef)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Used when an OCM share arrives on /ocm/shares — flip a matching
	 * outgoing pending row to accepted.
	 */
	public function findPendingOutgoing(string $ownerOcm, string $requesterOcm, string $shareRef): ?ShareRequest {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE_NAME)
			->where($qb->expr()->eq('direction', $qb->createNamedParameter(ShareRequest::DIRECTION_OUTGOING)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(ShareRequest::STATUS_PENDING)))
			->andWhere($qb->expr()->eq('owner_ocm', $qb->createNamedParameter($ownerOcm)))
			->andWhere($qb->expr()->eq('requester_ocm', $qb->createNamedParameter($requesterOcm)))
			->andWhere($qb->expr()->eq('share_ref', $qb->createNamedParameter($shareRef)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @return list<ShareRequest>
	 */
	public function findForUser(string $uid, ?string $direction = null, ?string $status = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE_NAME)
			->where($qb->expr()->eq('local_user', $qb->createNamedParameter($uid)));
		if ($direction !== null) {
			$qb->andWhere($qb->expr()->eq('direction', $qb->createNamedParameter($direction)));
		}
		if ($status !== null) {
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
		}
		$qb->orderBy('created_at', 'DESC');
		return $this->findEntities($qb);
	}
}
