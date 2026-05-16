<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 SUNET <kano@sunet.se>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OcmRequestShare\Listener;

use OCA\OcmRequestShare\Db\ShareRequest;
use OCA\OcmRequestShare\Db\ShareRequestMapper;
use OCA\OcmRequestShare\Federation\IncomingRequestVerifier;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Federation\ICloudIdManager;
use OCP\IUserManager;
use OCP\OCM\Events\OCMEndpointRequestEvent;
use OCP\Security\Signature\Exceptions\IncomingRequestException;
use Psr\Log\LoggerInterface;

/**
 * Listens on the OCM catch-all event dispatched by cloud_federation_api's
 * OCMRequestController for any /ocm/<path> request not bound to a named
 * route. Handles "request-share" per the cs3org OCM specification.
 *
 * @template-implements IEventListener<OCMEndpointRequestEvent>
 */
class OCMEndpointRequestEventListener implements IEventListener {
	public const CAPABILITY = 'request-share';

	public function __construct(
		private readonly ShareRequestMapper $mapper,
		private readonly IncomingRequestVerifier $verifier,
		private readonly ICloudIdManager $cloudIdManager,
		private readonly IUserManager $userManager,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof OCMEndpointRequestEvent) {
			return;
		}
		if ($event->getRequestedCapability() !== self::CAPABILITY) {
			return;
		}
		if ($event->getResponse() !== null) {
			// Another listener already handled it.
			return;
		}
		if ($event->getUsedMethod() !== 'POST') {
			$event->setResponse(new JSONResponse(
				['message' => 'Method not allowed'],
				Http::STATUS_METHOD_NOT_ALLOWED,
			));
			return;
		}

		$payload = $event->getPayload();
		$owner = $payload['owner'] ?? null;
		$shareWith = $payload['shareWith'] ?? null;
		$share = $payload['share'] ?? null;
		// RFC does not define a message field; we accept it as an extension
		// so the local owner sees the requester's stated reason in the inbox.
		$message = $payload['message'] ?? null;

		if (!is_string($owner) || !is_string($shareWith) || !is_string($share)
			|| $owner === '' || $shareWith === '' || $share === '') {
			$event->setResponse(new JSONResponse(
				['message' => 'owner, shareWith, share are required strings'],
				Http::STATUS_BAD_REQUEST,
			));
			return;
		}

		try {
			$this->verifier->verifyOriginMatches($event->getRemote(), $shareWith);
		} catch (IncomingRequestException $e) {
			$this->logger->warning('rejected /ocm/request-share: signed-origin mismatch', ['exception' => $e]);
			$event->setResponse(new JSONResponse(
				['message' => $e->getMessage()],
				Http::STATUS_FORBIDDEN,
			));
			return;
		}

		try {
			$cloudId = $this->cloudIdManager->resolveCloudId($owner);
		} catch (\InvalidArgumentException $e) {
			$event->setResponse(new JSONResponse(
				['message' => 'owner is not a valid OCM address'],
				Http::STATUS_BAD_REQUEST,
			));
			return;
		}

		$localUid = $cloudId->getUser();
		if (!$this->userManager->userExists($localUid)) {
			$event->setResponse(new JSONResponse(
				['message' => 'owner not found at this server'],
				Http::STATUS_NOT_FOUND,
			));
			return;
		}

		$existing = $this->mapper->findPendingIncoming($owner, $shareWith, $share);
		if ($existing !== null) {
			$event->setResponse(new JSONResponse(
				['id' => $existing->getId(), 'message' => 'request already pending'],
				Http::STATUS_OK,
			));
			return;
		}

		$entity = new ShareRequest();
		$entity->setDirection(ShareRequest::DIRECTION_INCOMING);
		$entity->setOwnerOcm($owner);
		$entity->setRequesterOcm($shareWith);
		$entity->setRemoteHost($event->getRemote());
		$entity->setLocalUser($localUid);
		$entity->setShareRef($share);
		$entity->setStatus(ShareRequest::STATUS_PENDING);
		$entity->setMessage(is_string($message) ? $message : null);
		$entity->setCreatedAt($this->timeFactory->getTime());

		$entity = $this->mapper->insert($entity);

		$this->logger->info('accepted incoming /ocm/request-share', [
			'id' => $entity->getId(),
			'owner' => $owner,
			'requester' => $shareWith,
			'share_ref' => $share,
		]);

		// TODO: emit an OCP notification to $localUid so it appears in the
		// bell-icon menu. The inbox UI in task #7 will poll the table directly
		// for now; the notifier integration ships in a follow-up.

		$event->setResponse(new JSONResponse(
			['id' => $entity->getId()],
			Http::STATUS_CREATED,
		));
	}
}
