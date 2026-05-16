<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 SUNET <kano@sunet.se>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OcmRequestShare\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[CreateTable(table: 'ocm_share_requests', columns: ['id', 'direction', 'owner_ocm', 'requester_ocm', 'remote_host', 'local_user', 'share_ref', 'resolved_node_id', 'status', 'message', 'error_message', 'created_at', 'decided_at'], description: 'Pending and historical OCM Request-for-a-Share rows, in both directions')]
class Version0001Date20260516120000 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$tableName = 'ocm_share_requests';

		if ($schema->hasTable($tableName)) {
			return null;
		}

		$table = $schema->createTable($tableName);
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 11,
			'unsigned' => true,
		]);
		$table->addColumn('direction', Types::STRING, [
			'notnull' => true,
			'length' => 8,
		]);
		$table->addColumn('owner_ocm', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('requester_ocm', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('remote_host', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);
		$table->addColumn('local_user', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);
		$table->addColumn('share_ref', Types::STRING, [
			'notnull' => true,
			'length' => 2048,
		]);
		$table->addColumn('resolved_node_id', Types::BIGINT, [
			'notnull' => false,
		]);
		$table->addColumn('status', Types::STRING, [
			'notnull' => true,
			'length' => 16,
		]);
		$table->addColumn('message', Types::TEXT, [
			'notnull' => false,
		]);
		$table->addColumn('error_message', Types::TEXT, [
			'notnull' => false,
		]);
		$table->addColumn('created_at', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('decided_at', Types::BIGINT, [
			'notnull' => false,
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['direction', 'status'], 'ocm_share_req_dir_st');
		$table->addIndex(['local_user', 'status'], 'ocm_share_req_usr_st');

		return $schema;
	}
}
