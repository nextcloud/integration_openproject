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
use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\PublicKeyTokenMapper;
use OCA\OpenProject\AppInfo\Application;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Authentication\Token\IToken;
use OCP\DB\Exception;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version2640Date20240628114300 extends SimpleMigrationStep {

	/**
	 * @var PublicKeyTokenMapper
	 */
	private $mapper;
	/**
	 * @var IProvider
	 */
	private $tokenProvider;


	public function __construct(
		PublicKeyTokenMapper $mapper,
		IProvider $tokenProvider
	) {
		$this->mapper = $mapper;
		$this->tokenProvider = $tokenProvider;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array<string, string|null> $options
	 * @return null|ISchemaWrapper
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$tokens = $this->tokenProvider->getTokenByUser(Application::OPEN_PROJECT_ENTITIES_NAME);
		foreach ($tokens as $token) {
			if ($token->getName() === Application::OPEN_PROJECT_ENTITIES_NAME) {
				$tokenId = $token->getId();
				// We convert current "OpenProject" user with temporary token types to permanent one.
				$publicTokenForOpenProjectUser = $this->mapper->getTokenById($tokenId);
				$publicTokenForOpenProjectUser->setType(IToken::PERMANENT_TOKEN);
				$this->mapper->update($publicTokenForOpenProjectUser);
			}
		}
		return null;
	}
}
