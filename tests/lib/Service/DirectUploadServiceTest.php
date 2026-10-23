<?php

/**
 * SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use OC\Files\Node\Folder;
use OCP\Files\IUserFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;

class DirectUploadServiceTest extends TestCase {

	/**
	 * @return mixed
	 */
	public function getFolderMock(): mixed {
		if (interface_exists(IUserFolder::class)) {
			return $this->createMock(IUserFolder::class);
		}
		return $this->createMock(Folder::class);
	}

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
		$userSessionMock = $this->getMockBuilder('\OCP\IUserSession')->getMock();
		$userSessionMock->method('getUser')->willReturn($userMock);
		$folderMock = $this->getFolderMock();
		$storageMock = $this->getMockBuilder('\OCP\Files\IRootFolder')->getMock();
		$storageMock->method('getUserFolder')->willReturn($folderMock);

		return new DirectUploadService(
			$userManagerMock,
			$this->createMock(IL10N::class),
			$this->createMock(ISecureRandom::class),
			$databaseServiceMock,
			$userSessionMock,
			$storageMock
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
