<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2310Date20230116153411 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// in case the previous migration created this table we drop them
		if ($schema->hasTable('directUpload')) {
			$schema->dropTable('directUpload');
		} elseif ($schema->hasTable('directupload')) {
			$schema->dropTable('directupload');
		}
		if (!$schema->hasTable('direct_upload')) {
			$table = $schema->createTable('direct_upload');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('token', 'string', [
				'notnull' => true,
				'length' => 64
			]);
			$table->addColumn('created_at', 'bigint', [
				'notnull' => true,
				'unsigned' => true
			]);
			$table->addColumn('expires_on', 'bigint', [
				'notnull' => true,
				'unsigned' => true
			]);
			$table->addColumn('folder_id', 'bigint', [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['token'], 'direct_upload_token_index');
		}
		return $schema;
	}
}
