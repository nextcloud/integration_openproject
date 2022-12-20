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
use OCA\OpenProject\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;


class Version2002Date20221219170111 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('directUpload')) {
			$table = $schema->createTable('directUpload');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('token', 'string', [
				'notnull' => true,
				'length' => 200
			]);
			$table->addColumn('created_at', 'datetime', [
				'notnull' => true,
				'length' => 200
			]);
			$table->addColumn('expires_on', 'datetime', [
				'notnull' => true,
				'length' => 200
			]);
			$table->addColumn('folder_id', 'integer', [
				'notnull' => true,
				'length' => 200
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 200,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['token'], 'directUpload_token_index');
		}
		return $schema;
	}
}
