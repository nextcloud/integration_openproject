<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Swikriti Tripathi <swikriti@jankaritech.com>
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

use OCP\DB\ISchemaWrapper;
use Closure;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Security\ISecureRandom;

class Version2400Date20230214145250 extends SimpleMigrationStep {
	/**
	 * @var IUserManager
	 */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/**
	 * @var ISecureRandom
	 */
	private ISecureRandom $secureRandom;

	/**
	 * @var ISubAdmin
	 */
	private ISubAdmin $subAdminManager;

	public function __construct(IUserManager $userManager, ISecureRandom $secureRandom, IGroupManager $groupManager, ISubAdmin $subAdminManager) {
		$this->userManager = $userManager;
		$this->secureRandom = $secureRandom;
		$this->groupManager = $groupManager;
		$this->subAdminManager = $subAdminManager;
	}
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array<string, string|null> $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$password = $this->secureRandom->generate(10, ISecureRandom::CHAR_HUMAN_READABLE);
		$name = 'openproject';
		if (!$this->userManager->userExists($name)) {
			$this->userManager->createUser($name, $password);
		}

		if (!$this->groupManager->groupExists($name)) {
			$group = $this->groupManager->createGroup($name);
			$user = $this->userManager->get($name);
			$group->addUser($user);
			$this->subAdminManager->createSubAdmin($user, $group);
		}
		return null;
	}
}
