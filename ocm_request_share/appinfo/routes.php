<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 SUNET <kano@sunet.se>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// POST /ocm/request-share is handled by listening on OCMEndpointRequestEvent
// (dispatched by cloud_federation_api's catch-all controller), not by registering
// our own route — see lib/Listener/OCMEndpointRequestEventListener.php.
return [
	'ocs' => [
		// Outgoing: a local user asks a remote owner to share a resource.
		[
			'name' => 'ShareRequest#create',
			'url' => '/api/v1/share-requests',
			'verb' => 'POST',
		],
		[
			'name' => 'ShareRequest#index',
			'url' => '/api/v1/share-requests',
			'verb' => 'GET',
		],
		// Local user decides on an incoming pending request.
		[
			'name' => 'ShareRequest#decide',
			'url' => '/api/v1/share-requests/{id}/decision',
			'verb' => 'POST',
		],
	],
];
