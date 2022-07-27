<?php

namespace OCA\OpenProject\Controller;

use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\Activity\IManager;
use OCP\Files\Config\ICachedMountFileInfo;
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
				'httpd/unix-directory'
			],
			// getById returns a sub folder
			[
				[
					$this->getNodeMock('httpd/unix-directory')
				],
				'files/myFolder/a-sub-folder',
				'a-sub-folder',
				'httpd/unix-directory'
			],
			// getById returns the root folder
			[
				[
					$this->getNodeMock('httpd/unix-directory')
				],
				'files',
				'files',
				'httpd/unix-directory'
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

		$filesController = $this->createFilesController(
			$folderMock, null, $mountCacheMock
		);

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
				'modifier_id' => null
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
			$this->getNodeMock('text/plain', 759)
		);

		$mountCacheMock = $this->getSimpleMountCacheMock(
			'files_trashbin/files/welcome.txt.d1648724302'
		);

		$filesController = $this->createFilesController(
			$folderMock, $trashManagerMock, $mountCacheMock
		);

		$result = $filesController->getFileInfo(759);
		assertSame($this->trashedWelcomeTxtResult, $result->getData());
		assertSame(200, $result->getStatus());
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

	public function testGetFilesInfoOneIdRequestedFileInTrash(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn([]);

		$trashManagerMock = $this->getMockBuilder('\OCA\Files_Trashbin\Trash\ITrashManager')->getMock();
		$trashManagerMock->method('getTrashNodeById')->willReturn(
			$this->getNodeMock('text/plain', 759)
		);

		$mountCacheMock = $this->getSimpleMountCacheMock(
			'files_trashbin/files/welcome.txt.d1648724302'
		);

		$filesController = $this->createFilesController(
			$folderMock, $trashManagerMock, $mountCacheMock
		);

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
					$this->getNodeMock('image/png')
				],
				[],
				[]
			);

		$trashManagerMock = $this->getMockBuilder('\OCA\Files_Trashbin\Trash\ITrashManager')->getMock();
		$trashManagerMock->method('getTrashNodeById')
			->withConsecutive([$this->anything(), 759], [$this->anything(), 365], [$this->anything(), 956])
			->willReturnOnConsecutiveCalls(
			$this->getNodeMock('text/plain', 759),
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


		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->withConsecutive([123], [759], [365], [956])
			->willReturnOnConsecutiveCalls(
				[$cachedMountFileInfoMock],
				[$cachedMountFileInfoMock],
				[], // not found
				[$cachedMountFileInfoMock],
			);

		$filesController = $this->createFilesController(
			$folderMock,
			$trashManagerMock,
			$mountCacheMock
		);

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
					$this->getNodeMock('image/png')
				]
			);

		$mountCacheMock = $this->getSimpleMountCacheMock('files/logo.png');
		$filesController = $this->createFilesController(
			$folderMock, null, $mountCacheMock
		);

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
						$this->getNodeMock('image/png')
					],
					[],
					[]
			);

		$cachedMountFileInfoMock = $this->getMockBuilder(
			'\OCP\Files\Config\ICachedMountFileInfo'
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturn('files/logo.png');
		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')
			->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturnOnConsecutiveCalls(
				[$cachedMountFileInfoMock], [], []
			);

		$filesController = $this->createFilesController(
			$folderMock, null, $mountCacheMock
		);

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
					$this->getNodeMock('image/png', 123)
				],
				[
					$this->getNodeMock('image/png', 365),
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
		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')
			->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$cachedMountFileInfoMock]
			);
		$filesController = $this->createFilesController(
			$folderMock, null, $mountCacheMock
		);

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
					$this->getNodeMock('image/png'),
					$this->getNodeMock('image/png')
				],
				[
					$this->getNodeMock('image/png', 365),
					$this->getNodeMock('image/png', 365)
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
		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')
			->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$cachedMountFileInfoMock]
			);

		$filesController = $this->createFilesController(
			$folderMock, null, $mountCacheMock
		);

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
						2
					)
				],
				[
					$this->getNodeMock(
						'httpd/unix-directory',
						3
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
		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$cachedMountFileInfoMock]
			);

		$filesController = $this->createFilesController($folderMock, null, $mountCacheMock);

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
					'mimetype' => 'httpd/unix-directory',
					'size' => 200245,
					'owner_name' => 'Test User',
					'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
					'trashed' => false,
					'modifier_name' => null,
					'modifier_id' => null
				],
				3 => [
					'status' => 'OK',
					'statuscode' => 200,
					'id' => 3,
					'name' => 'files',
					'mtime' => 1640008813,
					'ctime' => 1639906930,
					'mimetype' => 'httpd/unix-directory',
					'size' => 200245,
					'owner_name' => 'Test User',
					'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
					'trashed' => false,
					'modifier_name' => null,
					'modifier_id' => null
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
		'modifier_id' => null
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
		'modifier_id' => null
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
		'modifier_id' => null
	];

	/**
	 * @param MockObject $folderMock
	 * @param MockObject|ITrashManager|null $trashManagerMock
	 * @param MockObject|null $mountCacheMock mock for Files that exist but cannot be accessed by this user
	 * @return FilesController
	 */
	private function createFilesController(
		MockObject $folderMock, $trashManagerMock = null, MockObject $mountCacheMock = null
): FilesController {
		$storageMock = $this->getMockBuilder('\OCP\Files\IRootFolder')->getMock();
		$storageMock->method('getUserFolder')->willReturn($folderMock);

		$userMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$userMock->method('getUID')->willReturn('testUser');

		$userSessionMock = $this->getMockBuilder('\OCP\IUserSession')->getMock();
		$userSessionMock->method('getUser')->willReturn($userMock);
		if ($trashManagerMock === null) {
			$trashManagerMock = $this->createMock(ITrashManager::class);
		}

		if ($mountCacheMock === null) {
			$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')->getMock();
			$mountCacheMock->method('getMountsForFileId')->willReturn([]);
		}

		$mountProviderCollectionMock = $this->getMockBuilder(
			'OCP\Files\Config\IMountProviderCollection'
		)->getMock();
		$mountProviderCollectionMock->method('getMountCache')->willReturn($mountCacheMock);

		return new FilesController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$storageMock,
			$trashManagerMock,
			$userSessionMock,
			$mountProviderCollectionMock,
			$this->createMock(IManager::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(IValidator::class),
			$this->createMock(ILogger::class),
			$this->createMock(IL10N::class),
			$this->createMock(IConfig::class),
			$this->createMock(IUserManager::class)
		);
	}

	private function getNodeMock(string $mimeType, int $id = 123
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
		return $fileMock;
	}

	private function getSimpleMountCacheMock(string $internalPath): MockObject {
		$cachedMountFileInfoMock = $this->getMockBuilder(
			'\OCP\Files\Config\ICachedMountFileInfo'
		)->getMock();
		$cachedMountFileInfoMock->method('getInternalPath')
			->willReturn($internalPath);
		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')
			->getMock();
		$mountCacheMock->method('getMountsForFileId')
			->willReturn(
				[$cachedMountFileInfoMock]
			);
		return $mountCacheMock;
	}
}
