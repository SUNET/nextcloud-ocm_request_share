<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 SUNET <kano@sunet.se>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OcmRequestShare\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getDirection()
 * @method void setDirection(string $direction)
 * @method string getOwnerOcm()
 * @method void setOwnerOcm(string $ownerOcm)
 * @method string getRequesterOcm()
 * @method void setRequesterOcm(string $requesterOcm)
 * @method string|null getRemoteHost()
 * @method void setRemoteHost(?string $remoteHost)
 * @method string|null getLocalUser()
 * @method void setLocalUser(?string $localUser)
 * @method string getShareRef()
 * @method void setShareRef(string $shareRef)
 * @method int|null getResolvedNodeId()
 * @method void setResolvedNodeId(?int $resolvedNodeId)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getMessage()
 * @method void setMessage(?string $message)
 * @method string|null getErrorMessage()
 * @method void setErrorMessage(?string $errorMessage)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int|null getDecidedAt()
 * @method void setDecidedAt(?int $decidedAt)
 */
class ShareRequest extends Entity {
	public const DIRECTION_INCOMING = 'incoming';
	public const DIRECTION_OUTGOING = 'outgoing';

	public const STATUS_PENDING = 'pending';
	public const STATUS_ACCEPTED = 'accepted';
	public const STATUS_DECLINED = 'declined';
	public const STATUS_FAILED = 'failed';

	protected string $direction = self::DIRECTION_INCOMING;
	protected string $ownerOcm = '';
	protected string $requesterOcm = '';
	protected ?string $remoteHost = null;
	protected ?string $localUser = null;
	protected string $shareRef = '';
	protected ?int $resolvedNodeId = null;
	protected string $status = self::STATUS_PENDING;
	protected ?string $message = null;
	protected ?string $errorMessage = null;
	protected int $createdAt = 0;
	protected ?int $decidedAt = null;

	public function __construct() {
		$this->addType('direction', Types::STRING);
		$this->addType('ownerOcm', Types::STRING);
		$this->addType('requesterOcm', Types::STRING);
		$this->addType('remoteHost', Types::STRING);
		$this->addType('localUser', Types::STRING);
		$this->addType('shareRef', Types::STRING);
		$this->addType('resolvedNodeId', Types::BIGINT);
		$this->addType('status', Types::STRING);
		$this->addType('message', Types::TEXT);
		$this->addType('errorMessage', Types::TEXT);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('decidedAt', Types::BIGINT);
	}
}
