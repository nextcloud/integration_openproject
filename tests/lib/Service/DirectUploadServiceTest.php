<?php

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

namespace OCA\OpenProject\Service;

use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;

class DirectUploadServiceTest extends TestCase {
	protected function getDirectUploadService(int $expiresOn): DirectUploadService {
		$databaseServiceMock = $this->getMockBuilder(DatabaseService::class)->disableOriginalConstructor()->getMock();
		$databaseServiceMock->method('getTokenInfoFromDB')->willReturn([
			'user_id' => 'testUser',
			'expires_on' => $expiresOn,
			'folder_id' => '123'
		]);

		$userMock = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$userMock->method('isEnabled')->willReturn(true);

		$userManagerMock = $this->getMockBuilder(IUserManager::class)->disableOriginalConstructor()->getMock();
		$userManagerMock->method('userExists')->willReturn(true);
		$userManagerMock->method('get')->willReturn($userMock);

		return new DirectUploadService(
			$userManagerMock,
			$this->createMock(IL10N::class),
			$this->createMock(ISecureRandom::class),
			$databaseServiceMock
		);
	}


	/**
	 * @return void
	 *
	 */
	public function testGetTokenInfoExpiredTime() {
		//send an already expired time
		$direcUploadService = $this->getDirectUploadService(1671537979);
		$this->expectException(NotFoundException::class);
		$direcUploadService->getTokenInfo('ziPpdeFW4qoTg7AzEc4E9bnREkF97f5B2q65M4t3iex58E7ENZK4GomwEZCPjeNa');
	}
}
