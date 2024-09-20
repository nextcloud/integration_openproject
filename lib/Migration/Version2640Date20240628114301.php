<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Sagar Gurung <sagar@jankaritech.com>
 *
 * @author Your name <sagar@jankaritech.com>
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
use OC\Authentication\Token\PublicKeyTokenMapper;
use OCA\OpenProject\AppInfo\Application;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version2640Date20240628114301 extends SimpleMigrationStep {

	/**
	 * @var PublicKeyTokenMapper
	 */
	private $mapper;


	public function __construct(
		PublicKeyTokenMapper $mapper
	) {
		$this->mapper = $mapper;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$tokens = $this->mapper->getTokenByUser(Application::OPEN_PROJECT_ENTITIES_NAME);
		foreach ($tokens as $token) {
			if ($token->getName() === Application::OPEN_PROJECT_ENTITIES_NAME) {
				// We convert current "OpenProject" user with temporary app password token types to permanent one.
				// type 0 => Temporary app password token where as type 1 => Permanent app password token
				$token->setType(1);
				$this->mapper->update($token);
			}
		}
		return null;
	}
}
