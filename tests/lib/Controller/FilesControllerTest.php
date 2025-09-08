<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2022-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Controller;

use OCA\Activity\Data;
use OCA\Activity\GroupHelperDisabled;
use OCA\Activity\UserSettings;
use OCP\Activity\IManager;
use OCP\Files\Config\ICachedMountFileInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\DavUtil;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use function PHPUnit\Framework\assertSame;

class FilesControllerTest extends TestCase {
	use PHPMock;

	/**
	 * @return array<mixed>
	 */
	public function getFileInfoDataProvider() {
		return [
			// getById returns only one result
			[
				[
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/logo.png', 'files/logo.png')
				],
				'files/logo.png',
				'logo.png',
				'image/png',
				'files/logo.png'
			],
			// getById returns multiple results e.g. if the file was received through multiple path
			[
				[
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/receivedAsFolderShare/logo.png', 'files/receivedAsFolderShare/logo.png'),
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/receivedAsFolderShare/logo.png', 'files/receivedAsFolderShare/logo.png')

				],
				'files/receivedAsFolderShare/logo.png',
				'logo.png',
				'image/png',
				'files/receivedAsFolderShare/logo.png'
			],
			// getById returns a folder
			[
				[
					$this->getNodeMock('httpd/unix-directory', 123, 'dir', '/testUser/files/myFolder', 'files/myFolder')
				],
				'files/myFolder',
				'myFolder',
				'application/x-op-directory',
				'files/myFolder/'
			],
			// getById returns a sub folder
			[
				[
					$this->getNodeMock('httpd/unix-directory', 123, 'dir', '/testUser/files/myFolder/a-sub-folder', 'files/myFolder/a-sub-folder')
				],
				'files/myFolder/a-sub-folder',
				'a-sub-folder',
				'application/x-op-directory',
				'files/myFolder/a-sub-folder/'
			],
			// getById returns the root folder
			[
				[
					$this->getNodeMock('httpd/unix-directory', 123, 'dir', '/testUser/files', 'files')
				],
				'files',
				'files',
				'application/x-op-directory',
				'files/'
			],
		];
	}

	/**
	 * @dataProvider getFileInfoDataProvider
	 * @param array<mixed> $nodeMocks
	 * @param string $internalPath
	 * @param string $expectedName
	 * @param string $expectedMimeType
	 * @param string $expectedPath
	 * @return void
	 */
	public function testGetFileInfo(
		$nodeMocks,
		$internalPath,
		$expectedName,
		$expectedMimeType,
		$expectedPath
	) {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')->willReturn($nodeMocks);

		$mountCacheMock = $this->getSimpleMountCacheMock($internalPath);
		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock
		);
		$filesController->method('getDavPermissions')->willReturn('RGDNVCK');
		$result = $filesController->getFileInfo(123);
		assertSame(
			[
				'status' => 'OK',
				'statuscode' => 200,
				"id" => 123,
				"name" => $expectedName,
				"mtime" => 1640008813,
				"ctime" => 1639906930,
				"mimetype" => $expectedMimeType,
				"size" => 200245,
				"owner_name" => "Test User",
				"owner_id" => "3df8ff78-49cb-4d60-8d8b-171b29591fd3",
				'modifier_name' => null,
				'modifier_id' => null,
				'dav_permissions' => 'RGDNVCK',
				'path' => $expectedPath
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFileInfoFileNotFound(): void {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')->willReturn([]);

		$filesController = $this->createFilesController($folderMock);

		$result = $filesController->getFileInfo(123);
		assertSame($this->notFoundResponse, $result->getData());
		assertSame(404, $result->getStatus());
	}

	public function testGetFileInfoFileExistingButNotReadable(): void {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')->willReturn([]);
		$mountCacheMock = $this->getMockBuilder(IUserMountCache::class)->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$this->createMock(ICachedMountFileInfo::class)]
			);

		$filesController = $this->createFilesController(
			$folderMock, $mountCacheMock
		);

		$result = $filesController->getFileInfo(759);
		assertSame($this->forbiddenResponse, $result->getData());
		assertSame(403, $result->getStatus());
	}

	public function testGetFileInfoFileExistingButCannotGetNameInContextOfOwner(): void {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')->willReturn(
			[$this->getNodeMock('image/png', 586, 'file', '/testUser/files/name-in-the-context-of-requester')]
		);

		$mountCacheMock = $this->getMockBuilder(IUserMountCache::class)->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[null]
			);

		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock
		);
		$filesController->method('getDavPermissions')->willReturn('RGDNVW');
		$result = $filesController->getFileInfo(586);
		assertSame($this->fileNameInTheContextOfRequesterResult, $result->getData());
		assertSame(200, $result->getStatus());
	}

	public function testGetFilesInfoFourIdsRequestedOneExistsOneInTrashOneNotExisitingOneForbidden(): void {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')
			->withConsecutive([123], [759], [365], [956])
			->willReturnOnConsecutiveCalls(
				[
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/logo.png')
				],
				[],
				[]
			);

		$cachedMountFileInfoMock = $this->getMockBuilder(
			ICachedMountFileInfo::class
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturnOnConsecutiveCalls(
				'files/logo.png',
				'files_trashbin/files/welcome.txt.d1648724302',
				'/anotherUser/files/logo.png'
			);

		$ownerMock = $this->getMockBuilder(Iuser::class)->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);

		$mountCacheMock = $this->getMockBuilder(IUserMountCache::class)->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->withConsecutive([123], [759], [365], [956])
			->willReturnOnConsecutiveCalls(
				[$cachedMountFileInfoMock],
				[$cachedMountFileInfoMock],
				[], // not found
				[$cachedMountFileInfoMock],
			);

		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock
		);
		$filesController->method('getDavPermissions')
			->willReturnOnConsecutiveCalls('RGDNVW', 'RGDNVW');

		$result = $filesController->getFilesInfo([123, 759, 365, 956]);
		assertSame(
			[
				123 => $this->logoPngResult,
				759 => $this->notFoundResponse,
				365 => $this->notFoundResponse,
				956 => $this->forbiddenResponse
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFilesInfoOneIdRequestedFileExistsReturnsOneResult(): void {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')
			->willReturn(
				[
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/logo.png')
				]
			);

		$mountCacheMock = $this->getSimpleMountCacheMock('files/logo.png');
		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock
		);
		$filesController->method('getDavPermissions')->willReturn('RGDNVW');

		$result = $filesController->getFilesInfo([123]);
		assertSame(
			[
				123 => $this->logoPngResult,
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFilesInfoThreeIdsRequestedOneFileExistsReturnsOneResult(): void {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')
			->withConsecutive([123], [256], [365])
			->willReturnOnConsecutiveCalls(
				[
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/logo.png')
				],
				[],
				[]
			);

		$cachedMountFileInfoMock = $this->getMockBuilder(
			ICachedMountFileInfo::class
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturn('files/logo.png');
		$ownerMock = $this->getMockBuilder(Iuser::class)->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder(IUserMountCache::class)
			->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturnOnConsecutiveCalls(
				[$cachedMountFileInfoMock], [], []
			);

		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock
		);
		$filesController->method('getDavPermissions')
			->willReturn('RGDNVW');
		$result = $filesController->getFilesInfo([123,256,365]);
		assertSame(
			[
				123 => $this->logoPngResult,
				256 => $this->notFoundResponse,
				365 => $this->notFoundResponse
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFilesInfoTwoIdsRequestedAllFilesExistsEachReturnsOneResult(): void {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')
			->withConsecutive([123], [365])
			->willReturnOnConsecutiveCalls(
				[
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/logo.png')
				],
				[
					$this->getNodeMock('image/png', 365, 'file', '/testUser/files/inFolder/image.png'),
				]
			);
		$cachedMountFileInfoMock = $this->getMockBuilder(
			ICachedMountFileInfo::class
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturnOnConsecutiveCalls(
				'files/logo.png',
				'files/inFolder/image.png',
			);
		$ownerMock = $this->getMockBuilder(Iuser::class)->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder(IUserMountCache::class)
			->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$cachedMountFileInfoMock]
			);
		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock
		);
		$filesController->method('getDavPermissions')
			->willReturnOnConsecutiveCalls('RGDNVW', 'RGDNVW');
		$result = $filesController->getFilesInfo([123,365]);
		assertSame(
			[
				123 => $this->logoPngResult,
				365 => $this->imagePngResult,
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFilesInfoTwoIdsRequestedAllFilesExistsEachReturnsMultipleResults(): void {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')
			->withConsecutive([123], [365])
			->willReturnOnConsecutiveCalls(
				[
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/logo.png'),
				],
				[
					$this->getNodeMock('image/png', 365, 'file', '/testUser/files/inFolder/image.png'),
				]
			);

		$cachedMountFileInfoMock = $this->getMockBuilder(
			ICachedMountFileInfo::class
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturnOnConsecutiveCalls(
				'files/logo.png',
				'files/inFolder/image.png',
			);
		$ownerMock = $this->getMockBuilder(Iuser::class)->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder(IUserMountCache::class)
			->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$cachedMountFileInfoMock]
			);
		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock
		);
		$filesController->method('getDavPermissions')
			->willReturnOnConsecutiveCalls('RGDNVW', 'RGDNVW');

		$result = $filesController->getFilesInfo([123,365]);
		assertSame(
			[
				123 => $this->logoPngResult,
				365 => $this->imagePngResult
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFilesInfoTwoIdsRequestedEachReturnsOneFolder(): void {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')
			->withConsecutive([2], [3])
			->willReturnOnConsecutiveCalls(
				[
					$this->getNodeMock(
						'httpd/unix-directory',
						2,
						'dir',
						'/testUser/files/myFolder/a-sub-folder'
					)
				],
				[
					$this->getNodeMock(
						'httpd/unix-directory',
						3,
						'dir',
						'/testUser/files'
					)
				]
			);

		$cachedMountFileInfoMock = $this->getMockBuilder(
			ICachedMountFileInfo::class
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturnOnConsecutiveCalls(
				'files/myFolder/a-sub-folder',
				'files'
			);
		$ownerMock = $this->getMockBuilder(Iuser::class)->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder(IUserMountCache::class)->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$cachedMountFileInfoMock]
			);

		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock
		);
		$filesController->method('getDavPermissions')
			->willReturnOnConsecutiveCalls('RGDNVCK', 'RGDNVCK');

		$result = $filesController->getFilesInfo([2,3]);
		assertSame(
			[
				2 => [
					'status' => 'OK',
					'statuscode' => 200,
					'id' => 2,
					'name' => 'a-sub-folder',
					'mtime' => 1640008813,
					'ctime' => 1639906930,
					'mimetype' => 'application/x-op-directory',
					'size' => 200245,
					'owner_name' => 'Test User',
					'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
					'modifier_name' => null,
					'modifier_id' => null,
					'dav_permissions' => 'RGDNVCK',
					'path' => 'files/myFolder/a-sub-folder/'
				],
				3 => [
					'status' => 'OK',
					'statuscode' => 200,
					'id' => 3,
					'name' => 'files',
					'mtime' => 1640008813,
					'ctime' => 1639906930,
					'mimetype' => 'application/x-op-directory',
					'size' => 200245,
					'owner_name' => 'Test User',
					'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
					'modifier_name' => null,
					'modifier_id' => null,
					'dav_permissions' => 'RGDNVCK',
					'path' => 'files/'
				]
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFilesInfoInvalidRequest(): void {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$filesController = $this->createFilesController($folderMock);

		$result = $filesController->getFilesInfo(null);
		assertSame(
			'invalid request',
			$result->getData()
		);
		assertSame(400, $result->getStatus());
	}

	public function testGetFilesInfoSendStringIds(): void {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')
			->withConsecutive([2], [3])
			->willReturnOnConsecutiveCalls(
				[
					$this->getNodeMock(
						'httpd/unix-directory',
						2,
						'dir',
						'/testUser/files/myFolder/a-sub-folder'
					)
				],
				[
					$this->getNodeMock(
						'httpd/unix-directory',
						3,
						'dir',
						'/testUser/files/testFolder'
					)
				]
			);

		$cachedMountFileInfoMock = $this->getMockBuilder(
			ICachedMountFileInfo::class
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturnOnConsecutiveCalls(
				'files/myFolder/a-sub-folder',
				''
			);
		$ownerMock = $this->getMockBuilder(Iuser::class)->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder(IUserMountCache::class)->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$cachedMountFileInfoMock]
			);

		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock
		);
		$filesController->method('getDavPermissions')
			->willReturnOnConsecutiveCalls('RGDNVCK', 'RGDNVCK');

		$result = $filesController->getFilesInfo(["2","3"]);
		assertSame(
			[
				2 => [
					'status' => 'OK',
					'statuscode' => 200,
					'id' => 2,
					'name' => 'a-sub-folder',
					'mtime' => 1640008813,
					'ctime' => 1639906930,
					'mimetype' => 'application/x-op-directory',
					'size' => 200245,
					'owner_name' => 'Test User',
					'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
					'modifier_name' => null,
					'modifier_id' => null,
					'dav_permissions' => 'RGDNVCK',
					'path' => 'files/myFolder/a-sub-folder/'
				],
				3 => [
					'status' => 'OK',
					'statuscode' => 200,
					'id' => 3,
					'name' => 'name-in-the-context-of-requester',
					'mtime' => 1640008813,
					'ctime' => 1639906930,
					'mimetype' => 'application/x-op-directory',
					'size' => 200245,
					'owner_name' => 'Test User',
					'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
					'modifier_name' => null,
					'modifier_id' => null,
					'dav_permissions' => 'RGDNVCK',
					'path' => 'files/testFolder/'
				]
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFileInfoWithLastModifier() {
		$classExistsMock = $this->getFunctionMock(__NAMESPACE__, "class_exists");
		$classExistsMock->expects($this->any())->willReturn(false);

		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')
			->willReturn([
				$this->getNodeMock('image/png', 1, 'file', '/testUser/files/inFolder/image.png')
			]);
		$cachedMountFileInfoMock = $this->getMockBuilder(ICachedMountFileInfo::class)->getMock();
		$cachedMountFileInfoMock
			->method('getInternalPath')
			->willReturn('files/inFolder/image.png');
		$ownerMock = $this->getMockBuilder(IUser::class)->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock
			->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder(IUserMountCache::class)->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$cachedMountFileInfoMock]
			);
		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock
		);
		$filesController
			->method('getDavPermissions')
			->willReturn('RGDNVCK');

		$result = $filesController->getFileInfo(1);
		$this->assertNull($result->getData()['modifier_name']);
		$this->assertNull($result->getData()['modifier_id']);
	}

	/**
	 * @return array<mixed>
	 */
	public function davPermissionDataProvider() {
		return [
			[
				// All permision set for file
				[
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/logo.png', 'files/logo.png', true, true, true, true, true, true, true)
				],
				'SRMGDNVW',
				'image/png',
				'logo.png',
				'files/logo.png'
			],
			// All permission set for folder
			[
				[
					$this->getNodeMock('httpd/unix-directory', 123, 'dir', '/testUser/files/Folder', 'files/Folder', true, true, true, true, true, true, true)
				],
				'SRMGDNVCK',
				'application/x-op-directory',
				'Folder',
				'files/Folder/'
			],
			// only read permision set for file
			[
				[
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/logo.png', 'files/logo.png', false, false, false, true, false, false, false)
				],
				'G',
				'image/png',
				'logo.png',
				'files/logo.png'
			],
			// only read permision set for folder
			[
				[
					$this->getNodeMock('httpd/unix-directory', 123, 'dir', '/testUser/files/Folder', 'files/Folder', false, false, false, true, false, false, false)
				],
				'G',
				'application/x-op-directory',
				'Folder',
				'files/Folder/'
			],
			// create+update permision set for folder
			[
				[
					$this->getNodeMock('httpd/unix-directory', 123, 'dir', '/testUser/files/Folder', 'files/Folder', false, false, false, false, false, true, true)
				],
				'NVCK',
				'application/x-op-directory',
				'Folder',
				'files/Folder/'
			],
			// share+read permision set for file
			[
				[
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/logo.png', 'files/logo.png', false, true, false, true, false, false, false)
				],
				'RG',
				'image/png',
				'logo.png',
				'files/logo.png'
			],
			// share+read permision set for folder
			[
				[
					$this->getNodeMock('httpd/unix-directory', 123, 'dir', '/testUser/files/Folder', 'files/Folder', false, true, false, true, false, false, false)
				],
				'RG',
				'application/x-op-directory',
				'Folder',
				'files/Folder/'
			],
			// shared+shareable+update permision set for file
			[
				[
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/logo.png', 'files/logo.png', true, true, false, false, false, true, false)
				],
				'SRNVW',
				'image/png',
				'logo.png',
				'files/logo.png'
			],
			// shared+shareable+update permision set for folder
			[
				[
					$this->getNodeMock('httpd/unix-directory', 123, 'dir', '/testUser/files/Folder', 'files/Folder', true, true, false, false, false, true, false)
				],
				'SRNV',
				'application/x-op-directory',
				'Folder',
				'files/Folder/'
			],
			// shared+shareable+update+create permision set for folder
			[
				[
					$this->getNodeMock('httpd/unix-directory', 123, 'dir', '/testUser/files/Folder', 'files/Folder', true, true, false, false, false, true, true)
				],
				'SRNVCK',
				'application/x-op-directory',
				'Folder',
				'files/Folder/'
			],
		];
	}

	/**
	 * @dataProvider davPermissionDataProvider
	 * @param array<mixed> $nodeMocks
	 * @param string $davPermission
	 * @param string $mimeType
	 * @param string $name
	 * @param string $path
	 * @return void
	 */

	public function testDavPermissions(
		$nodeMocks,
		$davPermission,
		$mimeType,
		$name,
		$path
	): void {
		$folderMock = $this->getMockBuilder(Folder::class)->getMock();
		$folderMock->method('getById')->willReturn($nodeMocks);

		$mountCacheMock = $this->getSimpleMountCacheMock($path);

		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock
		);
		$filesController->method('getDavPermissions')
			->willReturn($davPermission);
		$result = $filesController->getFileInfo(123);
		assertSame(
			[
				'status' => 'OK',
				'statuscode' => 200,
				"id" => 123,
				"name" => $name,
				"mtime" => 1640008813,
				"ctime" => 1639906930,
				"mimetype" => $mimeType,
				"size" => 200245,
				"owner_name" => "Test User",
				"owner_id" => "3df8ff78-49cb-4d60-8d8b-171b29591fd3",
				'modifier_name' => null,
				'modifier_id' => null,
				'dav_permissions' => $davPermission,
				'path' => $path
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	/**
	 * @var array<mixed>
	 */
	private array $notFoundResponse = [
		'status' => 'Not Found',
		'statuscode' => 404,
	];

	/**
	 * @var array<mixed>
	 */
	private array $forbiddenResponse = [
		'status' => 'Forbidden',
		'statuscode' => 403
	];

	/**
	 * @var array<mixed>
	 */
	private array $logoPngResult = [
		'status' => 'OK',
		'statuscode' => 200,
		'id' => 123,
		'name' => 'logo.png',
		'mtime' => 1640008813,
		'ctime' => 1639906930,
		'mimetype' => 'image/png',
		'size' => 200245,
		'owner_name' => 'Test User',
		'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
		'modifier_name' => null,
		'modifier_id' => null,
		'dav_permissions' => 'RGDNVW',
		'path' => 'files/logo.png'
	];

	/**
	 * @var array<mixed>
	 */
	private array $imagePngResult = [
		'status' => 'OK',
		'statuscode' => 200,
		'id' => 365,
		'name' => 'image.png',
		'mtime' => 1640008813,
		'ctime' => 1639906930,
		'mimetype' => 'image/png',
		'size' => 200245,
		'owner_name' => 'Test User',
		'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
		'modifier_name' => null,
		'modifier_id' => null,
		'dav_permissions' => 'RGDNVW',
		'path' => 'files/inFolder/image.png'
	];

	/**
	 * @var array<mixed>
	 */
	private array $fileNameInTheContextOfRequesterResult = [
		'status' => 'OK',
		'statuscode' => 200,
		'id' => 586,
		'name' => 'name-in-the-context-of-requester',
		'mtime' => 1640008813,
		'ctime' => 1639906930,
		'mimetype' => 'image/png',
		'size' => 200245,
		'owner_name' => 'Test User',
		'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
		'modifier_name' => null,
		'modifier_id' => null,
		'dav_permissions' => 'RGDNVW',
		'path' => 'files/name-in-the-context-of-requester'
	];

	/**
	 * @param MockObject $folderMock
	 * @param MockObject|null $mountCacheMock mock for Files that exist but cannot be accessed by this user
	 * @return FilesController
	 */
	private function createFilesController(
		MockObject $folderMock,
		MockObject $mountCacheMock = null
	): FilesController {
		$storageMock = $this->getMockBuilder('\OCP\Files\IRootFolder')->getMock();
		$storageMock->method('getUserFolder')->willReturn($folderMock);

		$userMock = $this->getMockBuilder(Iuser::class)->getMock();
		$userMock->method('getUID')->willReturn('testUser');

		$userSessionMock = $this->getMockBuilder('\OCP\IUserSession')->getMock();
		$userSessionMock->method('getUser')->willReturn($userMock);

		if ($mountCacheMock === null) {
			$mountCacheMock = $this->getMockBuilder(IUserMountCache::class)->getMock();
			$mountCacheMock->method('getMountsForFileId')->willReturn([]);
		}

		$mountProviderCollectionMock = $this->getMockBuilder(
			'OCP\Files\Config\IMountProviderCollection'
		)->getMock();
		$mountProviderCollectionMock->method('getMountCache')->willReturn($mountCacheMock);

		$controller = new FilesController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$storageMock,
			$userSessionMock,
			$mountProviderCollectionMock,
			$this->createMock(IManager::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IUserManager::class),
			$this->createMock(DavUtil::class),
		);
		return $controller;
	}

	/**
	 * @param array<string> $onlyMethods
	 * @param MockObject $folderMock
	 * @param MockObject|null $mountCacheMock mock for Files that exist but cannot be accessed by this user
	 * @param MockObject|null $davUtilsMock
	 * @return FilesController|MockObject
	 */
	public function getFilesControllerMock(
		array $onlyMethods,
		MockObject $folderMock,
		MockObject $mountCacheMock = null,
		MockObject $davUtilsMock = null
	): FilesController|MockObject {
		$storageMock = $this->getMockBuilder('\OCP\Files\IRootFolder')->getMock();
		$storageMock->method('getUserFolder')->willReturn($folderMock);

		$userMock = $this->getMockBuilder(Iuser::class)->getMock();
		$userMock->method('getUID')->willReturn('testUser');

		$userSessionMock = $this->getMockBuilder('\OCP\IUserSession')->getMock();
		$userSessionMock->method('getUser')->willReturn($userMock);

		if ($mountCacheMock === null) {
			$mountCacheMock = $this->getMockBuilder(IUserMountCache::class)->getMock();
			$mountCacheMock->method('getMountsForFileId')->willReturn([]);
		}
		if ($davUtilsMock === null) {
			$davUtilsMock = $this->getMockBuilder('\OCP\Files\DavUtil')->getMock();
		}

		$mountProviderCollectionMock = $this->getMockBuilder(
			'OCP\Files\Config\IMountProviderCollection'
		)->getMock();
		$mountProviderCollectionMock->method('getMountCache')->willReturn($mountCacheMock);
		$controller = $this->getMockBuilder(FilesController::class)
			->setConstructorArgs(
				[
					'integration_openproject',
					$this->createMock(IRequest::class),
					$storageMock,
					$userSessionMock,
					$mountProviderCollectionMock,
					$this->createMock(IManager::class),
					$this->createMock(IDBConnection::class),
					$this->createMock(LoggerInterface::class),
					$this->createMock(IUserManager::class),
					$davUtilsMock
				])
			->onlyMethods($onlyMethods)
			->getMock();

		return $controller;
	}

	private function getNodeMock(
		string $mimeType,
		int $id = 123,
		string $fileType = 'dir',
		string $path = '/testUser/files/test',
		string $internalPath = 'files/test',
		bool $isShared = false,
		bool $isShareable = true,
		bool $isMounted = false,
		bool $isReadable = true,
		bool $isDeletable = true,
		bool $isUpdateable = true,
		bool $isCreatable = true
	): Node {
		$ownerMock = $this->getMockBuilder(Iuser::class)->getMock();
		$ownerMock->method('getDisplayName')->willReturn('Test User');
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');

		$fileMock = $this->createMock('\OCP\Files\Node');
		$fileMock->method('getId')->willReturn($id);
		$fileMock->method('getOwner')->willReturn($ownerMock);
		$fileMock->method('getSize')->willReturn(200245);
		$fileMock->method('getMimeType')->willReturn($mimeType);
		$fileMock->method('getCreationTime')->willReturn(1639906930);
		$fileMock->method('getMTime')->willReturn(1640008813);
		$fileMock->method('getName')->willReturn('name-in-the-context-of-requester');
		$fileMock->method('getPath')->willReturn($path);
		$fileMock->method('getInternalPath')->willReturn($internalPath);
		$fileMock->method('getType')->willReturn($fileType);
		$fileMock->method('isShared')->willReturn($isShared);
		$fileMock->method('isShareable')->willReturn($isShareable);
		$fileMock->method('isMounted')->willReturn($isMounted);
		$fileMock->method('isReadable')->willReturn($isReadable);
		$fileMock->method('isDeletable')->willReturn($isDeletable);
		$fileMock->method('isUpdateable')->willReturn($isUpdateable);
		$fileMock->method('isCreatable')->willReturn($isCreatable);

		return $fileMock;
	}

	private function getSimpleMountCacheMock(string $internalPath): MockObject {
		$ownerMock = $this->getMockBuilder(Iuser::class)->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock = $this->getMockBuilder(
			ICachedMountFileInfo::class
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturn($internalPath);
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder(IUserMountCache::class)
			->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$cachedMountFileInfoMock]
			);

		return $mountCacheMock;
	}
}
