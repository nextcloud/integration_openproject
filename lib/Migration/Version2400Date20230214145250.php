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

use OCP\IConfig;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;

class Version2400Date20230214145250 {
	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var ISecureRandom
	 */
	private ISecureRandom $secureRandom;

	public function __construct(IUserManager $userManager, ISecureRandom $secureRandom) {
		$this->userManager = $userManager;
		$this->secureRandom = $secureRandom;
	}

	public function createUserOpenproject(){
		$password = $this->secureRandom->generate(10, ISecureRandom::CHAR_HUMAN_READABLE);
		$username = 'openproject';
		if(!$this->userManager->userExists($username)){
			$this->userManager->createUser($username , $password);
		}
	}

}
