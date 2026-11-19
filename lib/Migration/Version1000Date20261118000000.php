<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Leiterrunde\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20261118000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('lr_documents')) {
			$table = $schema->createTable('lr_documents');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('mode', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'text']);
			$table->addColumn('body_text', Types::TEXT, ['notnull' => false]);
			$table->addColumn('required', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->addColumn('file_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('file_owner', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('file_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('lr_document_reqs')) {
			$table = $schema->createTable('lr_document_reqs');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('document_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'pending']);
			$table->addColumn('method', Types::STRING, ['notnull' => false, 'length' => 20]);
			$table->addColumn('signature_text', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('signed_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('signed_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('file_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('file_owner', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('file_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['document_id', 'person_id'], 'lr_dreq_uniq');
			$table->addIndex(['person_id'], 'lr_dreq_person_idx');
			$table->addIndex(['status'], 'lr_dreq_status_idx');
		}

		return $schema;
	}
}
