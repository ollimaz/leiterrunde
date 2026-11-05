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

class Version1000Date20261105000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('lr_persons')) {
			$table = $schema->createTable('lr_persons');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('first_name', Types::STRING, ['notnull' => true, 'length' => 200, 'default' => '']);
			$table->addColumn('last_name', Types::STRING, ['notnull' => true, 'length' => 200, 'default' => '']);
			$table->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 320]);
			$table->addColumn('phone', Types::STRING, ['notnull' => false, 'length' => 100]);
			$table->addColumn('mobile', Types::STRING, ['notnull' => false, 'length' => 100]);
			$table->addColumn('street', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('zip', Types::STRING, ['notnull' => false, 'length' => 20]);
			$table->addColumn('city', Types::STRING, ['notnull' => false, 'length' => 200]);
			$table->addColumn('birthdate', Types::DATE, ['notnull' => false]);
			$table->addColumn('birth_year', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'aktiv']);
			$table->addColumn('function_label', Types::STRING, ['notnull' => false, 'length' => 200]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('nc_user_id', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['email'], 'lr_pers_email_idx');
			$table->addIndex(['status'], 'lr_pers_status_idx');
			$table->addIndex(['nc_user_id'], 'lr_pers_user_idx');
		}

		if (!$schema->hasTable('lr_audit')) {
			$table = $schema->createTable('lr_audit');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('entity_type', Types::STRING, ['notnull' => true, 'length' => 40]);
			$table->addColumn('entity_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('field', Types::STRING, ['notnull' => true, 'length' => 100]);
			$table->addColumn('old_value', Types::TEXT, ['notnull' => false]);
			$table->addColumn('new_value', Types::TEXT, ['notnull' => false]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['entity_type', 'entity_id'], 'lr_audit_ent_idx');
		}

		if (!$schema->hasTable('lr_training_types')) {
			$table = $schema->createTable('lr_training_types');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 200]);
			$table->addColumn('validity_months', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('counts_hours', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('required', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('required_for_event', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('sort', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('lr_training_recs')) {
			$table = $schema->createTable('lr_training_recs');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('type_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('attended_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('hours', Types::DECIMAL, ['notnull' => true, 'precision' => 6, 'scale' => 2, 'default' => 0]);
			$table->addColumn('title', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('file_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('file_owner', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('file_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'pending']);
			$table->addColumn('decided_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('decided_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('decision_note', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['person_id'], 'lr_trec_person_idx');
			$table->addIndex(['type_id'], 'lr_trec_type_idx');
			$table->addIndex(['status'], 'lr_trec_status_idx');
		}

		if (!$schema->hasTable('lr_fz')) {
			$table = $schema->createTable('lr_fz');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('issued_at', Types::DATE, ['notnull' => true]);
			$table->addColumn('valid_months', Types::INTEGER, ['notnull' => true, 'default' => 36]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['person_id'], 'lr_fz_person_idx');
		}

		if (!$schema->hasTable('lr_states')) {
			$table = $schema->createTable('lr_states');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('subject_type', Types::STRING, ['notnull' => true, 'length' => 40]);
			$table->addColumn('subject_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('check_key', Types::STRING, ['notnull' => true, 'length' => 100]);
			$table->addColumn('state', Types::STRING, ['notnull' => true, 'length' => 20]);
			$table->addColumn('changed_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['subject_type', 'subject_id', 'check_key'], 'lr_states_uniq');
		}

		if (!$schema->hasTable('lr_accounts')) {
			$table = $schema->createTable('lr_accounts');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 200]);
			$table->addColumn('iban', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('parser_key', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('mail_hint', Types::STRING, ['notnull' => false, 'length' => 200]);
			$table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('lr_transactions')) {
			$table = $schema->createTable('lr_transactions');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('booking_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('amount', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2]);
			$table->addColumn('counterparty', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('purpose', Types::TEXT, ['notnull' => false]);
			$table->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 20]);
			$table->addColumn('dedupe_hash', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('obligation_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('raw', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['dedupe_hash'], 'lr_tx_dedupe_uniq');
			$table->addIndex(['account_id'], 'lr_tx_acc_idx');
			$table->addIndex(['obligation_id'], 'lr_tx_obl_idx');
		}

		if (!$schema->hasTable('lr_obligations')) {
			$table = $schema->createTable('lr_obligations');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('payer_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('amount_due', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2]);
			$table->addColumn('due_date', Types::DATE, ['notnull' => false]);
			$table->addColumn('reference', Types::STRING, ['notnull' => false, 'length' => 200]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'open']);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('registration_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['reference'], 'lr_obl_ref_uniq');
			$table->addIndex(['status'], 'lr_obl_status_idx');
		}

		if (!$schema->hasTable('lr_expenses')) {
			$table = $schema->createTable('lr_expenses');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('submitted_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('expense_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('amount', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2]);
			$table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 100]);
			$table->addColumn('description', Types::STRING, ['notnull' => false, 'length' => 500]);
			$table->addColumn('file_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('file_owner', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('file_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'pending']);
			$table->addColumn('decided_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('decided_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('decision_note', Types::TEXT, ['notnull' => false]);
			$table->addColumn('paid_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('transaction_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['person_id'], 'lr_exp_person_idx');
			$table->addIndex(['status'], 'lr_exp_status_idx');
		}

		if (!$schema->hasTable('lr_events')) {
			$table = $schema->createTable('lr_events');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('start_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('end_date', Types::DATE, ['notnull' => false]);
			$table->addColumn('location', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('fee', Types::DECIMAL, ['notnull' => false, 'precision' => 12, 'scale' => 2]);
			$table->addColumn('capacity', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('reg_deadline', Types::DATE, ['notnull' => false]);
			$table->addColumn('reg_fields', Types::TEXT, ['notnull' => false]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'draft']);
			$table->addColumn('remote_id', Types::STRING, ['notnull' => false, 'length' => 100]);
			$table->addColumn('last_pushed_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('last_sync_status', Types::TEXT, ['notnull' => false]);
			$table->addColumn('sync_pending', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('lr_event_team')) {
			$table = $schema->createTable('lr_event_team');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('role', Types::STRING, ['notnull' => false, 'length' => 200]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['event_id'], 'lr_eteam_evt_idx');
		}

		if (!$schema->hasTable('lr_event_costs')) {
			$table = $schema->createTable('lr_event_costs');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('amount', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2]);
			$table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 100]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['event_id'], 'lr_ecost_evt_idx');
		}

		if (!$schema->hasTable('lr_registrations')) {
			$table = $schema->createTable('lr_registrations');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('remote_reg_id', Types::STRING, ['notnull' => false, 'length' => 100]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 320]);
			$table->addColumn('phone', Types::STRING, ['notnull' => false, 'length' => 100]);
			$table->addColumn('data', Types::TEXT, ['notnull' => false]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'active']);
			$table->addColumn('payment_state', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'open']);
			$table->addColumn('payment_ref', Types::STRING, ['notnull' => false, 'length' => 200]);
			$table->addColumn('remote_updated_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('obligation_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['event_id', 'remote_reg_id'], 'lr_reg_uniq');
		}

		return $schema;
	}
}
