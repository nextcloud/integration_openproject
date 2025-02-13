<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
