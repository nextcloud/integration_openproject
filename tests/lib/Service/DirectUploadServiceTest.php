<?php

/**
 * SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
