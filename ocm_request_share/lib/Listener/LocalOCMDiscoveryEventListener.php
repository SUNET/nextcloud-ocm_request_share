<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 SUNET <kano@sunet.se>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OcmRequestShare\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\OCM\Events\LocalOCMDiscoveryEvent;

/**
 * Advertises the OCM "request-share" capability on this server's discovery
 * document, signalling to peers that POST /ocm/request-share is accepted.
 *
 * @template-implements IEventListener<LocalOCMDiscoveryEvent>
 */
class LocalOCMDiscoveryEventListener implements IEventListener {
	public const CAPABILITY = 'request-share';

	public function handle(Event $event): void {
		if (!$event instanceof LocalOCMDiscoveryEvent) {
			return;
		}

		$provider = $event->getProvider();
		$capabilities = $provider->getCapabilities();
		if (in_array(self::CAPABILITY, $capabilities, true)) {
			return;
		}
		$capabilities[] = self::CAPABILITY;
		$provider->setCapabilities($capabilities);
	}
}
