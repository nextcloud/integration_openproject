<?php

namespace OCA\OpenProject\Controller;

use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\Activity\IManager;
use OCP\Files\Config\ICachedMountFileInfo;
use OCP\Files\DavUtil;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\RichObjectStrings\IValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use function PHPUnit\Framework\assertSame;

/**
 * overriding the class_exists method, so that the unit tests always pass,
 * no matter if the activity app is enabled or not
 */
function class_exists(string $className): bool {
	if ($className === '\OCA\Activity\Data') {
		return false;
	} else {
		return \class_exists($className);
	}
}

class FilesControllerTest extends TestCase {

	/**
	 * @return array<mixed>
	 */
	public function getFileInfoDataProvider() {
		return [
			// getById returns only one result
			[
				[
					$this->getNodeMock('image/png')
				],
				'files/logo.png',
				'logo.png',
				'image/png'
			],
			// getById returns multiple results e.g. if the file was received through multiple path
			[
				[
					$this->getNodeMock('image/png'),
					$this->getNodeMock('image/png')

				],
				'files/receivedAsFolderShare/logo.png',
				'logo.png',
				'image/png',
			],
			// getById returns a folder
			[
				[
					$this->getNodeMock('httpd/unix-directory')
				],
				'files/myFolder',
				'myFolder',
				'application/x-op-directory'
			],
			// getById returns a sub folder
			[
				[
					$this->getNodeMock('httpd/unix-directory')
				],
				'files/myFolder/a-sub-folder',
				'a-sub-folder',
				'application/x-op-directory'
			],
			// getById returns the root folder
			[
				[
					$this->getNodeMock('httpd/unix-directory')
				],
				'files',
				'files',
				'application/x-op-directory'
			],
		];
	}

	/**
	 * @dataProvider getFileInfoDataProvider
	 * @param array<mixed> $nodeMocks
	 * @param string $internalPath
	 * @param string $expectedName
	 * @param string $expectedMimeType
	 * @return void
	 */
	public function testGetFileInfo(
		$nodeMocks,
		$internalPath,
		$expectedName,
		$expectedMimeType
	) {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
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
				'trashed' => false,
				'modifier_name' => null,
				'modifier_id' => null,
				'dav_permissions' => 'RGDNVCK',
				'path' => 'files/test'
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFileInfoFileNotFound(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn([]);

		$filesController = $this->createFilesController($folderMock);

		$result = $filesController->getFileInfo(123);
		assertSame($this->notFoundResponse, $result->getData());
		assertSame(404, $result->getStatus());
	}

	public function testGetFileInfoFileInTrash(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn([]);

		$trashManagerMock = $this->getMockBuilder('\OCA\Files_Trashbin\Trash\ITrashManager')->getMock();
		$trashManagerMock->method('getTrashNodeById')->willReturn(
			$this->getNodeMock('text/plain', 759, 'file', '/testUser/files_trashbin/files/welcome.txt.d1648724302')
		);

		$mountCacheMock = $this->getSimpleMountCacheMock(
			'files_trashbin/files/welcome.txt.d1648724302'
		);
		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock, true, null, $trashManagerMock
		);
		$filesController->method('getDavPermissions')->willReturn('RGDNVW');

		$result = $filesController->getFileInfo(759);
		assertSame($this->trashedWelcomeTxtResult, $result->getData());
		assertSame(200, $result->getStatus());
	}

	// this case happens for files that have been deleted before the trashbinapp got disabled
	public function testGetFileInfoFileFileExistsTrashappDisabled(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn([]);
		$mountCacheMock = $this->getSimpleMountCacheMock(
			'files_trashbin/files/welcome.txt.d1648724302'
		);
		$filesController = $this->createFilesController(
			$folderMock, null, $mountCacheMock, false
		);

		$result = $filesController->getFileInfo(123);
		assertSame($this->forbiddenResponse, $result->getData());
		assertSame(403, $result->getStatus());
	}

	// this case happens for files that get deleted while the trashbinapp was disabled
	public function testGetFileInfoFileFileDoesNotExistsTrashappDisabled(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn([]);
		$filesController = $this->createFilesController(
			$folderMock, null, null, false
		);

		$result = $filesController->getFileInfo(123);
		assertSame($this->notFoundResponse, $result->getData());
		assertSame(404, $result->getStatus());
	}

	public function testGetFileInfoFileTrashappThrowsException(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn([]);

		$trashManagerMock = $this->getMockBuilder('\OCA\Files_Trashbin\Trash\ITrashManager')->getMock();
		$trashManagerMock->method('getTrashNodeById')->willThrowException(new \Exception());

		$filesController = $this->createFilesController(
			$folderMock, $trashManagerMock
		);

		$result = $filesController->getFileInfo(123);
		assertSame($this->notFoundResponse, $result->getData());
		assertSame(404, $result->getStatus());
	}

	public function testGetFileInfoFileExistingButNotReadable(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn([]);

		$trashManagerMock = $this->getMockBuilder('\OCA\Files_Trashbin\Trash\ITrashManager')->getMock();
		$trashManagerMock->method('getTrashNodeById')->willReturn(null);

		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$this->createMock(ICachedMountFileInfo::class)]
			);

		$filesController = $this->createFilesController(
			$folderMock, $trashManagerMock, $mountCacheMock
		);

		$result = $filesController->getFileInfo(759);
		assertSame($this->forbiddenResponse, $result->getData());
		assertSame(403, $result->getStatus());
	}

	public function testGetFileInfoFileExistingButCannotGetNameInContextOfOwner(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn(
			[$this->getNodeMock('image/png', 586, 'file', '/testUser/files/name-in-the-context-of-requester')]
		);

		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')->getMock();
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

	public function testGetFilesInfoOneIdRequestedFileInTrash(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn([]);

		$trashManagerMock = $this->getMockBuilder('\OCA\Files_Trashbin\Trash\ITrashManager')->getMock();
		$trashManagerMock->method('getTrashNodeById')->willReturn(
			$this->getNodeMock('text/plain', 759, 'file', '/testUser/files_trashbin/files/welcome.txt.d1648724302')
		);

		$appManagerMock = $this->getMockBuilder('\OCP\App\IAppManager')->getMock();
		$appManagerMock->method('isEnabledForUser')->willReturn(
			true
		);

		$mountCacheMock = $this->getSimpleMountCacheMock(
			'files_trashbin/files/welcome.txt.d1648724302'
		);

		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock, true, null, $trashManagerMock
		);
		$filesController->method('getDavPermissions')->willReturn('RGDNVW');
		$result = $filesController->getFilesInfo([759]);
		assertSame(
			[
				759 => $this->trashedWelcomeTxtResult,
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFilesInfoFourIdsRequestedOneExistsOneInTrashOneNotExisitingOneForbidden(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')
			->withConsecutive([123], [759], [365], [956])
			->willReturnOnConsecutiveCalls(
				[
					$this->getNodeMock('image/png', 123, 'file', '/testUser/files/logo.png')
				],
				[],
				[]
			);

		$trashManagerMock = $this->getMockBuilder('\OCA\Files_Trashbin\Trash\ITrashManager')->getMock();
		$trashManagerMock->method('getTrashNodeById')
			->withConsecutive([$this->anything(), 759], [$this->anything(), 365], [$this->anything(), 956])
			->willReturnOnConsecutiveCalls(
			$this->getNodeMock('text/plain', 759, 'file', '/testUser/files_trashbin/files/welcome.txt.d1648724302'),
			null,
			null
		);


		$cachedMountFileInfoMock = $this->getMockBuilder(
			'\OCP\Files\Config\ICachedMountFileInfo'
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturnOnConsecutiveCalls(
				'files/logo.png',
				'files_trashbin/files/welcome.txt.d1648724302',
				[],
				[]
			);

		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);

		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->withConsecutive([123], [759], [365], [956])
			->willReturnOnConsecutiveCalls(
				[$cachedMountFileInfoMock],
				[$cachedMountFileInfoMock],
				[], // not found
				[$cachedMountFileInfoMock],
			);

		$filesController = $this->getFilesControllerMock(
			['getDavPermissions'], $folderMock, $mountCacheMock, true, null, $trashManagerMock
		);
		$filesController->method('getDavPermissions')
			->willReturnOnConsecutiveCalls('RGDNVW', 'RGDNVW');

		$result = $filesController->getFilesInfo([123, 759, 365, 956]);
		assertSame(
			[
				123 => $this->logoPngResult,
				759 => $this->trashedWelcomeTxtResult,
				365 => $this->notFoundResponse,
				956 => $this->forbiddenResponse
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFilesInfoOneIdRequestedFileExistsReturnsOneResult(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
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
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
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
			'\OCP\Files\Config\ICachedMountFileInfo'
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturn('files/logo.png');
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')
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
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
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
			'\OCP\Files\Config\ICachedMountFileInfo'
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturnOnConsecutiveCalls(
				'files/logo.png',
				'files/inFolder/image.png',
			);
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')
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
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
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
			'\OCP\Files\Config\ICachedMountFileInfo'
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturnOnConsecutiveCalls(
				'files/logo.png',
				'files/inFolder/image.png',
			);
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')
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
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
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
			'\OCP\Files\Config\ICachedMountFileInfo'
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturnOnConsecutiveCalls(
				'files/myFolder/a-sub-folder',
				'files'
			);
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')->getMock();
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
					'trashed' => false,
					'modifier_name' => null,
					'modifier_id' => null,
					'dav_permissions' => 'RGDNVCK',
					'path' => 'files/myFolder/a-sub-folder'
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
					'trashed' => false,
					'modifier_name' => null,
					'modifier_id' => null,
					'dav_permissions' => 'RGDNVCK',
					'path' => 'files'
				]
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFilesInfoInvalidRequest(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$filesController = $this->createFilesController($folderMock);

		$result = $filesController->getFilesInfo(null);
		assertSame(
			'invalid request',
			$result->getData()
		);
		assertSame(400, $result->getStatus());
	}

	public function testGetFilesInfoSendStringIds(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
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
			'\OCP\Files\Config\ICachedMountFileInfo'
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturnOnConsecutiveCalls(
				'files/myFolder/a-sub-folder',
				''
			);
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')->getMock();
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
					'trashed' => false,
					'modifier_name' => null,
					'modifier_id' => null,
					'dav_permissions' => 'RGDNVCK',
					'path' => 'files/myFolder/a-sub-folder'
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
					'trashed' => false,
					'modifier_name' => null,
					'modifier_id' => null,
					'dav_permissions' => 'RGDNVCK',
					'path' => 'files/testFolder'
				]
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
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
				'files/Folder'
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
				'files/Folder'
			],
			// create+update permision set for folder
			[
				[
					$this->getNodeMock('httpd/unix-directory', 123, 'dir', '/testUser/files/Folder', 'files/Folder', false, false, false, false, false, true, true)
				],
				'NVCK',
				'application/x-op-directory',
				'Folder',
				'files/Folder'
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
				'files/Folder'
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
				'files/Folder'
			],
			// shared+shareable+update+create permision set for folder
			[
				[
					$this->getNodeMock('httpd/unix-directory', 123, 'dir', '/testUser/files/Folder', 'files/Folder', true, true, false, false, false, true, true)
				],
				'SRNVCK',
				'application/x-op-directory',
				'Folder',
				'files/Folder'
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
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
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
				'trashed' => false,
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
	private array $trashedWelcomeTxtResult = [
		'status' => 'OK',
		'statuscode' => 200,
		"id" => 759,
		"name" => 'welcome.txt.d1648724302',
		"mtime" => 1640008813,
		"ctime" => 1639906930,
		"mimetype" => 'text/plain',
		"size" => 200245,
		"owner_name" => "Test User",
		"owner_id" => "3df8ff78-49cb-4d60-8d8b-171b29591fd3",
		"trashed" => true,
		'modifier_name' => null,
		'modifier_id' => null,
		'dav_permissions' => 'RGDNVW',
		'path' => 'files_trashbin/files/welcome.txt.d1648724302'
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
		'trashed' => false,
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
		'trashed' => false,
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
		'trashed' => false,
		'modifier_name' => null,
		'modifier_id' => null,
		'dav_permissions' => 'RGDNVW',
		'path' => 'files/name-in-the-context-of-requester'
	];

	/**
	 * @param MockObject $folderMock
	 * @param MockObject|ITrashManager|null $trashManagerMock
	 * @param MockObject|null $mountCacheMock mock for Files that exist but cannot be accessed by this user
	 * @return FilesController
	 */
	private function createFilesController(
		MockObject $folderMock,
		$trashManagerMock = null,
		MockObject $mountCacheMock = null,
		bool $isAppEnabled = true
	): FilesController {
		$storageMock = $this->getMockBuilder('\OCP\Files\IRootFolder')->getMock();
		$storageMock->method('getUserFolder')->willReturn($folderMock);

		$userMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$userMock->method('getUID')->willReturn('testUser');

		$userSessionMock = $this->getMockBuilder('\OCP\IUserSession')->getMock();
		$userSessionMock->method('getUser')->willReturn($userMock);

		if ($mountCacheMock === null) {
			$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')->getMock();
			$mountCacheMock->method('getMountsForFileId')->willReturn([]);
		}

		$mountProviderCollectionMock = $this->getMockBuilder(
			'OCP\Files\Config\IMountProviderCollection'
		)->getMock();
		$mountProviderCollectionMock->method('getMountCache')->willReturn($mountCacheMock);
		$appManagerMock = $this->getMockBuilder('\OCP\App\IAppManager')->getMock();
		$appManagerMock->method('isEnabledForUser')->willReturn(
			$isAppEnabled
		);

		$controller = new FilesController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$storageMock,
			$userSessionMock,
			$mountProviderCollectionMock,
			$this->createMock(IManager::class),
			$appManagerMock,
			$this->createMock(IDBConnection::class),
			$this->createMock(IValidator::class),
			$this->createMock(ILogger::class),
			$this->createMock(IL10N::class),
			$this->createMock(IConfig::class),
			$this->createMock(IUserManager::class),
			$this->createMock(DavUtil::class),
			$this->createMock(LoggerInterface::class)
		);
		if ($trashManagerMock === null) {
			$trashManagerMock = $this->getMockBuilder('\OCA\Files_Trashbin\Trash\ITrashManager')->getMock();
			$trashManagerMock->method('getTrashNodeById')->willReturn(null);
		}
		$controller->setTrashManager($trashManagerMock);
		return $controller;
	}

	/**
	 * @param array<string> $onlyMethods
	 * @param MockObject $folderMock
	 * @param MockObject|null $mountCacheMock mock for Files that exist but cannot be accessed by this user
	 * @param bool $isAppEnabled
	 * @param MockObject|null $davUtilsMock
	 * @param MockObject|ITrashManager|null $trashManagerMock
	 * @return FilesController|MockObject
	 */
	public function getFilesControllerMock(
		array $onlyMethods,
		MockObject $folderMock,
		MockObject $mountCacheMock = null,
		bool $isAppEnabled = true,
		MockObject $davUtilsMock = null,
		$trashManagerMock = null
	): FilesController {
		$storageMock = $this->getMockBuilder('\OCP\Files\IRootFolder')->getMock();
		$storageMock->method('getUserFolder')->willReturn($folderMock);

		$userMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$userMock->method('getUID')->willReturn('testUser');

		$userSessionMock = $this->getMockBuilder('\OCP\IUserSession')->getMock();
		$userSessionMock->method('getUser')->willReturn($userMock);

		if ($mountCacheMock === null) {
			$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')->getMock();
			$mountCacheMock->method('getMountsForFileId')->willReturn([]);
		}
		if ($davUtilsMock === null) {
			$davUtilsMock = $this->getMockBuilder('\OCP\Files\DavUtil')->getMock();
		}

		$mountProviderCollectionMock = $this->getMockBuilder(
		'OCP\Files\Config\IMountProviderCollection'
		)->getMock();
		$mountProviderCollectionMock->method('getMountCache')->willReturn($mountCacheMock);
		$appManagerMock = $this->getMockBuilder('\OCP\App\IAppManager')->getMock();
		$appManagerMock->method('isEnabledForUser')->willReturn($isAppEnabled);
		$controller = $this->getMockBuilder(FilesController::class)
		->setConstructorArgs(
			[
				'integration_openproject',
				$this->createMock(IRequest::class),
				$storageMock,
				$userSessionMock,
				$mountProviderCollectionMock,
				$this->createMock(IManager::class),
				$appManagerMock,
				$this->createMock(IDBConnection::class),
				$this->createMock(IValidator::class),
				$this->createMock(ILogger::class),
				$this->createMock(IL10N::class),
				$this->createMock(IConfig::class),
				$this->createMock(IUserManager::class),
				$davUtilsMock,
				$this->createMock(LoggerInterface::class)
			])
		->onlyMethods($onlyMethods)
		->getMock();
		if ($trashManagerMock) {
			$controller->setTrashManager($trashManagerMock);
		}

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
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
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
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');
		$cachedMountFileInfoMock = $this->getMockBuilder(
			'\OCP\Files\Config\ICachedMountFileInfo'
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturn($internalPath);
		$cachedMountFileInfoMock->method('getUser')
			->willReturn($ownerMock);
		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')
			->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$cachedMountFileInfoMock]
			);

		return $mountCacheMock;
	}
}
