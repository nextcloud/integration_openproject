<?php

/**
 * SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCP\Files\Cache\ICache;
use OCP\Files\ForbiddenException as FileAccessForbiddenException;
use OCP\Files\InvalidContentException;
use OCP\Files\IRootFolder;
use OCP\Files\IUserFolder;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertSame;

class DirectUploadServiceTest extends TestCase {

	private const TOKEN = 'ziPpdeFW4qoTg7AzEc4E9bnREkF97f5B2q65M4t3iex58E7ENZK4GomwEZCPjeNa';
	private const TMP_NAME = '/tmp/integration_openproject_unit_test';

	/**
	 * @return mixed
	 */
	public function getFolderMock(): mixed {
		if (interface_exists(IUserFolder::class)) {
			return $this->createMock(IUserFolder::class);
		}
		return $this->createMock(Folder::class);
	}

	protected function getDirectUploadService(int $expiresOn, mixed $userFolderMock = null): DirectUploadService {
		$databaseServiceMock = $this->createMock(DatabaseService::class);
		$databaseServiceMock->method('getTokenInfoFromDB')->willReturn([
			'user_id' => 'testUser',
			'expires_on' => $expiresOn,
			'folder_id' => '123'
		]);

		$userMock = $this->createMock(IUser::class);
		$userMock->method('isEnabled')->willReturn(true);

		$userManagerMock = $this->createMock(IUserManager::class);
		$userManagerMock->method('userExists')->willReturn(true);
		$userManagerMock->method('get')->willReturn($userMock);
		$userSessionMock = $this->createMock(IUserSession::class);
		$userSessionMock->method('getUser')->willReturn($userMock);
		$storageMock = $this->createMock(IRootFolder::class);
		$storageMock->method('getUserFolder')->willReturn($userFolderMock ?? $this->getFolderMock());

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
		$directUploadService = $this->getDirectUploadService(1671537979);
		$this->expectException(NotFoundException::class);
		$directUploadService->getTokenInfo(self::TOKEN);
	}

	/**
	 *
	 * @param string $type
	 * @param int $id
	 * @return array<mixed>
	 */
	private function getNodeMock(string $type, int $id = 123): array {
		$ownerMock = $this->createMock(IUser::class);
		$ownerMock->method('getDisplayName')->willReturn('Test User');
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');

		$cacheMock = $this->createMock(ICache::class);
		$cacheMock->method('update')->willReturn(true);
		$storageMock = $this->createMock(IStorage::class);
		$storageMock->method('getCache')->willReturn($cacheMock);

		$fileMock = $this->createMock(File::class);
		$fileMock->method('getId')->willReturn(123);
		$fileMock->method('getStorage')->willReturn($storageMock);

		$folderMock = $this->getFolderMock();
		$folderMock->method('getId')->willReturn($id);
		$folderMock->method('getType')->willReturn($type);
		$folderMock->method('isCreatable')->willReturn(true);
		$folderMock->method('nodeExists')->willReturn(false);
		$folderMock->method('newFile')->willReturn($fileMock);
		return [$folderMock];
	}

	public function testDirectUploadNotEnoughSpace(): void {
		$nodeMock = $this->getNodeMock('folder');
		$nodeMock[0]->method('getFreeSpace')->willReturn(100);

		$userFolderMock = $this->getFolderMock();
		$userFolderMock->method('getById')->willReturn($nodeMock);

		$directUploadServiceMock = $this->getDirectUploadService(time() + 1000, $userFolderMock);
		$this->expectException(NotEnoughSpaceException::class);
		$this->expectExceptionMessage('insufficient quota');
		$directUploadServiceMock->handleFileUpload(self::TOKEN, [ 'name' => 'file.txt', 'tmp_name' => self::TMP_NAME, 'size' => 101], FileUploadAction::Upload);
	}

	public function testNegativeFreeSpace(): void {
		$cacheMock = $this->createMock(ICache::class);
		$cacheMock->method('update')->willReturn(true);
		$storageMock = $this->createMock(IStorage::class);
		$storageMock->method('getCache')->willReturn($cacheMock);

		$fileMock = $this->createMock(File::class);
		$fileMock->method('getId')->willReturn(123);
		$fileMock->method('getStorage')->willReturn($storageMock);
		$nodeMock = $this->getNodeMock('folder');
		touch(self::TMP_NAME);
		$nodeMock[0]->method('getFreeSpace')->willReturn(-3);
		$nodeMock[0]->method('newFile')->willReturn($fileMock);
		$userFolderMock = $this->getFolderMock();
		$userFolderMock->method('getById')->willReturn($nodeMock);

		$directUploadServiceMock = $this->getDirectUploadService(time() + 1000, $userFolderMock);
		$result = $directUploadServiceMock->handleFileUpload(self::TOKEN, [ 'name' => 'file.txt', 'tmp_name' => self::TMP_NAME, 'size' => 101], FileUploadAction::Upload);
		assertSame(
			[
				'file_id' => 123,
				'file_name' => 'file.txt',
				'overwrite' => false,
			],
			$result
		);
	}

	/**
	 * @return array<int, array{\Exception, string}>
	 */
	public function newFileExceptionsDataProvider() {
		return [
			[new InvalidContentException('Virus detected'), 'Virus detected'],
			[new FileAccessForbiddenException('Access denied by the access control', false), 'Access denied by the access control'],
			[new \Exception('could not upload'), 'could not upload'],
		];
	}

	/**
	 * @return void
	 * @dataProvider newFileExceptionsDataProvider
	 */
	public function testDirectUploadException(
		\Exception $exception, string $expectedErrorMessage
	):void {
		$nodeMock = $this->getNodeMock('folder');
		touch(self::TMP_NAME);
		$nodeMock[0]->method('newFile')->willThrowException($exception);
		$userFolderMock = $this->getFolderMock();
		$userFolderMock->method('getById')->willReturn($nodeMock);

		$directUploadServiceMock = $this->getDirectUploadService(time() + 1000, $userFolderMock);
		$this->expectException($exception::class);
		$this->expectExceptionMessage($expectedErrorMessage);
		$directUploadServiceMock->handleFileUpload(self::TOKEN, [ 'name' => 'file.txt', 'tmp_name' => self::TMP_NAME, 'size' => 0], FileUploadAction::Upload);
	}
}
