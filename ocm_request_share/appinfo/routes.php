<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 SUNET <kano@sunet.se>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		// OCM Request-for-a-Share endpoint (RFC: POST /ocm/request-share)
		[
			'name' => 'RequestHandler#requestShare',
			'url' => '/ocm/request-share',
			'verb' => 'POST',
			'root' => '',
			'postfix' => 'request-share',
		],
	],
	'ocs' => [
		// Outgoing: a local user asks a remote owner to share a resource.
		[
			'name' => 'ShareRequest#create',
			'url' => '/api/v1/share-requests',
			'verb' => 'POST',
		],
		// Local user decides on an incoming pending request.
		[
			'name' => 'ShareRequest#decide',
			'url' => '/api/v1/share-requests/{id}/decision',
			'verb' => 'POST',
		],
		[
			'name' => 'ShareRequest#index',
			'url' => '/api/v1/share-requests',
			'verb' => 'GET',
		],
	],
];
