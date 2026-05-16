<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 SUNET <kano@sunet.se>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OcmRequestShare\Federation;

use OCP\IAppConfig;
use OCP\Security\Signature\Exceptions\IdentityNotFoundException;
use OCP\Security\Signature\Exceptions\IncomingRequestException;
use OCP\Security\Signature\Exceptions\SignatoryNotFoundException;
use OCP\Security\Signature\ISignatureManager;

/**
 * Verifies that the verified origin of an incoming OCM request matches the
 * host portion of an OCM address taken from the payload.
 *
 * Mirrors the private confirmSignedOrigin / getHostFromFederationId behaviour
 * of OCA\CloudFederationAPI\Controller\RequestHandlerController so that the
 * "request-share" listener can apply the same identity check that addShare
 * applies to its `owner` field — but on `shareWith` (the requester) here,
 * since that is who signs a Request-for-a-Share.
 *
 * Honours the three-state native NC signing policy via two booleans:
 *   - core.ocm_signed_request_disabled = true  -> all signing checks off
 *   - core.ocm_signed_request_enforced = true  -> handled upstream by the
 *     catch-all OCMRequestController, unsigned requests never reach us
 *   - both false (default, permissive) -> we apply confirmSignedOrigin logic
 */
class IncomingRequestVerifier {
	// Key names taken from OC\OCM\OCMSignatoryManager. We can't reference the
	// constants directly without crossing the OC\ private namespace boundary;
	// upstream-hoisting this verifier (see related issue) will let us drop
	// the duplication.
	private const APPCONFIG_SIGN_DISABLED = 'ocm_signed_request_disabled';

	public function __construct(
		private readonly ISignatureManager $signatureManager,
		private readonly IAppConfig $appConfig,
	) {
	}

	/**
	 * @throws IncomingRequestException when the signed origin disagrees with
	 *   the claimed host, or when the request is unsigned but the claimed
	 *   host is known to support signing.
	 */
	public function verifyOriginMatches(?string $signedOrigin, string $ocmAddress): void {
		if ($this->appConfig->getValueBool('core', self::APPCONFIG_SIGN_DISABLED, lazy: true)) {
			return;
		}

		$instance = $this->getHostFromFederationId($ocmAddress);

		if ($signedOrigin === null) {
			try {
				$this->signatureManager->getSignatory($instance);
			} catch (SignatoryNotFoundException) {
				return;
			}
			throw new IncomingRequestException('instance is supposed to sign its request');
		}

		if ($instance !== $signedOrigin) {
			throw new IncomingRequestException(
				'claimed origin ' . $instance . ' does not match signed origin ' . $signedOrigin
			);
		}
	}

	/**
	 * @throws IncomingRequestException
	 */
	private function getHostFromFederationId(string $entry): string {
		$entry = trim($entry, '@');
		if (!str_contains($entry, '@')) {
			throw new IncomingRequestException('entry ' . $entry . ' does not contain @');
		}
		$host = substr($entry, strrpos($entry, '@') + 1);
		$host = preg_replace('#^https?://#i', '', $host) ?? $host;

		try {
			return $this->signatureManager->extractIdentityFromUri('https://' . $host);
		} catch (IdentityNotFoundException) {
			throw new IncomingRequestException('invalid host within federation id: ' . $entry);
		}
	}
}
