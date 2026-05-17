<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 SUNET <kano@sunet.se>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OcmRequestShare\Controller;

use OCA\OcmRequestShare\Db\ShareRequest;
use OCA\OcmRequestShare\Db\ShareRequestMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Federation\ICloudIdManager;
use OCP\IRequest;
use OCP\OCM\Exceptions\OCMCapabilityException;
use OCP\OCM\Exceptions\OCMProviderException;
use OCP\OCM\IOCMDiscoveryService;
use Psr\Log\LoggerInterface;

/**
 * @psalm-type OcmShareRequest = array{
 *     id: int,
 *     direction: string,
 *     owner: string,
 *     requester: string,
 *     remote_host: ?string,
 *     share: string,
 *     status: string,
 *     message: ?string,
 *     error_message: ?string,
 *     created_at: int,
 *     decided_at: ?int,
 * }
 */
class ShareRequestController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ShareRequestMapper $mapper,
		private readonly IOCMDiscoveryService $ocmDiscoveryService,
		private readonly ICloudIdManager $cloudIdManager,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
		private readonly ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Send a request-for-share to the remote owner and record an outgoing
	 * pending row. Payload follows the cs3org OCM "Request for a Share"
	 * RFC, with an extension `message` field carrying the requester's
	 * stated reason.
	 *
	 * @param string $owner remote owner's OCM address (user@host)
	 * @param string $share opaque resource hint per the RFC
	 * @param string|null $message extension: stated reason for the request
	 *
	 * @return DataResponse<Http::STATUS_CREATED, array{id: int}, array{}>
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 * @throws OCSException
	 *
	 * 201: Outgoing request recorded and POSTed to the remote
	 */
	#[NoAdminRequired]
	public function create(string $owner, string $share, ?string $message = null): DataResponse {
		if ($this->userId === null) {
			throw new OCSForbiddenException();
		}

		if ($owner === '' || $share === '') {
			throw new OCSBadRequestException('owner and share are required');
		}

		try {
			$remoteCloudId = $this->cloudIdManager->resolveCloudId($owner);
		} catch (\InvalidArgumentException) {
			throw new OCSBadRequestException('owner is not a valid OCM address');
		}
		$remoteHost = $remoteCloudId->getRemote();
		$requesterOcm = $this->cloudIdManager->getCloudId($this->userId, null)->getId();

		$payload = [
			'owner' => $owner,
			'shareWith' => $requesterOcm,
			'share' => $share,
		];
		if ($message !== null && $message !== '') {
			$payload['message'] = $message;
		}

		try {
			// Guzzle's default http_errors=true means 4xx/5xx come back as
			// thrown RequestExceptions, not return values — so a 2xx return
			// is the only success path.
			$this->ocmDiscoveryService->requestRemoteOcmEndpoint(
				'request-share',
				$remoteHost,
				'request-share',
				$payload,
				'post',
			);
		} catch (OCMCapabilityException $e) {
			$this->logger->info('remote does not advertise request-share', [
				'remote' => $remoteHost, 'exception' => $e,
			]);
			throw new OCSException('remote does not support request-share', Http::STATUS_BAD_GATEWAY);
		} catch (OCMProviderException $e) {
			$this->logger->warning('ocm discovery/request failed', [
				'remote' => $remoteHost, 'exception' => $e,
			]);
			throw new OCSException('remote OCM endpoint unreachable', Http::STATUS_BAD_GATEWAY);
		} catch (\Throwable $e) {
			$this->logger->warning('remote rejected request-share', [
				'remote' => $remoteHost, 'exception' => $e,
			]);
			throw new OCSException('remote rejected the request-share', Http::STATUS_BAD_GATEWAY);
		}

		$entity = new ShareRequest();
		$entity->setDirection(ShareRequest::DIRECTION_OUTGOING);
		$entity->setOwnerOcm($owner);
		$entity->setRequesterOcm($requesterOcm);
		$entity->setRemoteHost($remoteHost);
		$entity->setLocalUser($this->userId);
		$entity->setShareRef($share);
		$entity->setStatus(ShareRequest::STATUS_PENDING);
		$entity->setMessage($message);
		$entity->setCreatedAt($this->timeFactory->getTime());
		$entity = $this->mapper->insert($entity);

		return new DataResponse(['id' => $entity->getId()], Http::STATUS_CREATED);
	}

	/**
	 * List the current user's share requests, optionally filtered by
	 * direction and/or status.
	 *
	 * @param string|null $direction one of "incoming", "outgoing"
	 * @param string|null $status one of "pending", "accepted", "declined", "failed"
	 *
	 * @return DataResponse<Http::STATUS_OK, array{requests: list<OcmShareRequest>}, array{}>
	 * @throws OCSForbiddenException
	 *
	 * 200: Requests returned
	 */
	#[NoAdminRequired]
	public function index(?string $direction = null, ?string $status = null): DataResponse {
		if ($this->userId === null) {
			throw new OCSForbiddenException();
		}
		$rows = $this->mapper->findForUser($this->userId, $direction, $status);
		$serialized = array_map([$this, 'serialize'], $rows);
		return new DataResponse(['requests' => $serialized]);
	}

	/**
	 * @return OcmShareRequest
	 */
	private function serialize(ShareRequest $r): array {
		return [
			'id' => $r->getId(),
			'direction' => $r->getDirection(),
			'owner' => $r->getOwnerOcm(),
			'requester' => $r->getRequesterOcm(),
			'remote_host' => $r->getRemoteHost(),
			'share' => $r->getShareRef(),
			'status' => $r->getStatus(),
			'message' => $r->getMessage(),
			'error_message' => $r->getErrorMessage(),
			'created_at' => $r->getCreatedAt(),
			'decided_at' => $r->getDecidedAt(),
		];
	}
}
