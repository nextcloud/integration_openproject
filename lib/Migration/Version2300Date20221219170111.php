<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Swikriti Tripathi <swikriti@jankaritech.com>
 *
 * @author Your name <swikriti@jankaritech.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OpenProject\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2300Date20221219170111 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array<string, string|null> $options
	 * @return null|ISchemaWrapper
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

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
			$table->addIndex(['token'], 'directUpload_token_index');
		}
		return $schema;
	}
}
