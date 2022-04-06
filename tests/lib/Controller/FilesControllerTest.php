<?php

namespace OCA\OpenProject\Controller;

use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\Files\Node;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertSame;

class FilesControllerTest extends TestCase {

	/**
	 * @return array<mixed>
	 */
	public function getFileInfoDataProvider() {
		return [
			// getById returns only one result
			[
				[
					$this->getNodeMock(
						'files/logo.png', 'logo.png', 'image/png'
					)
				],
				'/logo.png',
				'logo.png',
				'image/png'
			],
			// getById returns multiple results e.g. if the file was received through multiple path
			[
				[
					$this->getNodeMock(
						'files/receivedAsFolderShare/logo.png',
						'logo.png',
						'image/png'
					),
					$this->getNodeMock(
						'files/receivedAsFileShareAndRenamed.png',
						'receivedAsFileShareAndRenamed.png',
						'image/png'
					)

				],
				'/receivedAsFolderShare/logo.png',
				'logo.png',
				'image/png',
			],
			// getById returns a folder
			[
				[
					$this->getNodeMock(
						'files/myFolder',
						'myFolder',
						'httpd/unix-directory'
					)
				],
				'/myFolder',
				'myFolder',
				'httpd/unix-directory'
			],
			// getById returns a sub folder
			[
				[
					$this->getNodeMock(
						'files/myFolder/a-sub-folder',
						'a-sub-folder',
						'httpd/unix-directory'
					)
				],
				'/myFolder/a-sub-folder',
				'a-sub-folder',
				'httpd/unix-directory'
			],
			// getById returns the root folder
			[
				[
					$this->getNodeMock(
						'files',
						'files',
						'httpd/unix-directory'
					)
				],
				'/',
				'files',
				'httpd/unix-directory'
			],
		];
	}

	/**
	 * @dataProvider getFileInfoDataProvider
	 * @param array<mixed> $nodeMocks
	 * @param string $expectedPath
	 * @param string $expectedName
	 * @param string $expectedMimeType
	 * @return void
	 */
	public function testGetFileInfo(
		$nodeMocks,
		$expectedPath,
		$expectedName,
		$expectedMimeType
	) {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn($nodeMocks);

		$filesController = $this->createFilesController($folderMock);

		$result = $filesController->getFileInfo(123);
		assertSame(
			[
				"id" => 123,
				"name" => $expectedName,
				"mtime" => 1640008813,
				"ctime" => 1639906930,
				"mimetype" => $expectedMimeType,
				"path" => $expectedPath,
				"size" => 200245,
				"owner_name" => "Test User",
				"owner_id" => "3df8ff78-49cb-4d60-8d8b-171b29591fd3",
				'trashed' => false
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
		assertSame([], $result->getData());
		assertSame(404, $result->getStatus());
	}

	public function testGetFileInfoFileInTrash(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn([]);

		$trashManagerMock = $this->getMockBuilder('\OCA\Files_Trashbin\Trash\ITrashManager')->getMock();
		$trashManagerMock->method('getTrashNodeById')->willReturn(
			$this->getNodeMock(
				'files_trashbin/files/welcome.txt.d1648724302',
				'welcome.txt.d1648724302',
				'text/plain',
				759
			)
		);

		$filesController = $this->createFilesController($folderMock, $trashManagerMock);

		$result = $filesController->getFileInfo(759);
		assertSame($this->trashedWelcomeTxtResult, $result->getData());
		assertSame(200, $result->getStatus());
	}

	public function testGetFilesInfoOneIdRequestedFileInTrash(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn([]);

		$trashManagerMock = $this->getMockBuilder('\OCA\Files_Trashbin\Trash\ITrashManager')->getMock();
		$trashManagerMock->method('getTrashNodeById')->willReturn(
			$this->getNodeMock(
				'files_trashbin/files/welcome.txt.d1648724302',
				'welcome.txt.d1648724302',
				'text/plain',
				759
			)
		);

		$filesController = $this->createFilesController($folderMock, $trashManagerMock);

		$result = $filesController->getFilesInfo([759]);
		assertSame(
			[
				759 => $this->trashedWelcomeTxtResult,
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	public function testGetFilesInfoThreeIdsRequestedOneExistsOneInTrashOneNotExisiting(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')
			->withConsecutive([123], [759], [365])
			->willReturnOnConsecutiveCalls(
				[
					$this->getNodeMock(
						'files/logo.png', 'logo.png', 'image/png'
					)
				],
				[],
				[]
			);

		$trashManagerMock = $this->getMockBuilder('\OCA\Files_Trashbin\Trash\ITrashManager')->getMock();
		$trashManagerMock->method('getTrashNodeById')
			->withConsecutive([$this->anything(), 759], [$this->anything(), 365])
			->willReturnOnConsecutiveCalls(
			$this->getNodeMock(
				'files_trashbin/files/welcome.txt.d1648724302',
				'welcome.txt.d1648724302',
				'text/plain',
				759
			),
				null
		);

		$filesController = $this->createFilesController($folderMock, $trashManagerMock);

		$result = $filesController->getFilesInfo([123, 759, 365]);
		assertSame(
			[
				123 => $this->logoPngResult,
				759 => $this->trashedWelcomeTxtResult,
				365 => null
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
					$this->getNodeMock(
					'files/logo.png', 'logo.png', 'image/png'
					)
				]
			);

		$filesController = $this->createFilesController($folderMock);

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
						$this->getNodeMock(
							'files/logo.png', 'logo.png', 'image/png'
						)
					],
					[],
					[]
			);

		$filesController = $this->createFilesController($folderMock);

		$result = $filesController->getFilesInfo([123,256,365]);
		assertSame(
			[
				123 => $this->logoPngResult,
				256 => null,
				365 => null
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
					$this->getNodeMock(
						'files/logo.png', 'logo.png', 'image/png', 123
					)
				],
				[
					$this->getNodeMock(
						'files/inFolder/image.png', 'image.png', 'image/png', 365
					),
				]
			);

		$filesController = $this->createFilesController($folderMock);

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
					$this->getNodeMock(
						'files/logo.png', 'logo.png', 'image/png'
					),
					$this->getNodeMock(
						'files/receivedAsFileShareAndRenamed.png',
						'receivedAsFileShareAndRenamed.png',
						'image/png'
					)
				],
				[
					$this->getNodeMock(
						'files/inFolder/image.png', 'image.png', 'image/png', 365
					),
					$this->getNodeMock(
						'files/subfolder/receivedAsFileShareAndRenamed.png',
						'receivedAsFileShareAndRenamed.png',
						'image/png',
						365
					)
				]
			);

		$filesController = $this->createFilesController($folderMock);

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
						'files/myFolder/a-sub-folder',
						'a-sub-folder',
						'httpd/unix-directory',
						2
					)
				],
				[
					$this->getNodeMock(
						'files',
						'files',
						'httpd/unix-directory',
						3
					)
				]
			);

		$filesController = $this->createFilesController($folderMock);

		$result = $filesController->getFilesInfo([2,3]);
		assertSame(
			[
				2 => [
					'id' => 2,
					'name' => 'a-sub-folder',
					'mtime' => 1640008813,
					'ctime' => 1639906930,
					'mimetype' => 'httpd/unix-directory',
					'path' => '/myFolder/a-sub-folder',
					'size' => 200245,
					'owner_name' => 'Test User',
					'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
					'trashed' => false
				],
				3 => [
					'id' => 3,
					'name' => 'files',
					'mtime' => 1640008813,
					'ctime' => 1639906930,
					'mimetype' => 'httpd/unix-directory',
					'path' => '/',
					'size' => 200245,
					'owner_name' => 'Test User',
					'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
					'trashed' => false
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
	private array $trashedWelcomeTxtResult = [
		"id" => 759,
		"name" => 'welcome.txt.d1648724302',
		"mtime" => 1640008813,
		"ctime" => 1639906930,
		"mimetype" => 'text/plain',
		"path" => '/welcome.txt.d1648724302',
		"size" => 200245,
		"owner_name" => "Test User",
		"owner_id" => "3df8ff78-49cb-4d60-8d8b-171b29591fd3",
		"trashed" => true
	];

	/**
	 * @var array<mixed>
	 */
	private array $logoPngResult = [
		'id' => 123,
		'name' => 'logo.png',
		'mtime' => 1640008813,
		'ctime' => 1639906930,
		'mimetype' => 'image/png',
		'path' => '/logo.png',
		'size' => 200245,
		'owner_name' => 'Test User',
		'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
		'trashed' => false
	];

	/**
	 * @var array<mixed>
	 */
	private array $imagePngResult = [
		'id' => 365,
		'name' => 'image.png',
		'mtime' => 1640008813,
		'ctime' => 1639906930,
		'mimetype' => 'image/png',
		'path' => '/inFolder/image.png',
		'size' => 200245,
		'owner_name' => 'Test User',
		'owner_id' => '3df8ff78-49cb-4d60-8d8b-171b29591fd3',
		'trashed' => false
	];

	/**
	 * @param MockObject $folderMock
	 * @param MockObject|ITrashManager|null $trashManagerMock
	 * @return FilesController
	 */
	private function createFilesController(MockObject $folderMock, $trashManagerMock = null): FilesController {
		$storageMock = $this->getMockBuilder('\OCP\Files\IRootFolder')->getMock();
		$storageMock->method('getUserFolder')->willReturn($folderMock);

		$userMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$userMock->method('getUID')->willReturn('testUser');
		$mountFileInfoMock = $this->getMockBuilder('\OCP\Files\Config\ICachedMountFileInfo')->getMock();
		$mountFileInfoMock->method('getUser')->willReturn($userMock);
		$mountCacheMock = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')->getMock();
		$mountCacheMock->method('getMountsForFileId')->willReturn([$mountFileInfoMock]);
		$mountProviderCollectionMock = $this->getMockBuilder(
			'OCP\Files\Config\IMountProviderCollection'
		)->getMock();
		$mountProviderCollectionMock->method('getMountCache')->willReturn($mountCacheMock);


		$userSessionMock = $this->getMockBuilder('\OCP\IUserSession')->getMock();
		$userSessionMock->method('getUser')->willReturn($userMock);
		if ($trashManagerMock === null) {
			$trashManagerMock = $this->createMock(ITrashManager::class);
		}

		return new FilesController(
			'integration_openproject',
			$this->createMock(IRequest::class), $storageMock, $trashManagerMock, $mountProviderCollectionMock
		);
	}

	private function getNodeMock(
		string $path, string $name, string $mimeType, int $id = 123
	): Node {
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getDisplayName')->willReturn('Test User');
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');

		$fileMock = $this->createMock('\OCP\Files\Node');
		$fileMock->method('getId')->willReturn($id);
		$fileMock->method('getOwner')->willReturn($ownerMock);
		$fileMock->method('getSize')->willReturn(200245);
		$fileMock->method('getName')->willReturn($name);
		$fileMock->method('getMimeType')->willReturn($mimeType);
		$fileMock->method('getCreationTime')->willReturn(1639906930);
		$fileMock->method('getMTime')->willReturn(1640008813);
		$fileMock->method('getInternalPath')->willReturn($path);
		return $fileMock;
	}
}
