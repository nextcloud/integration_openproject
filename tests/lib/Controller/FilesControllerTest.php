<?php

namespace OCA\OpenProject\Controller;

use OCP\IRequest;
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

		$storageMock = $this->getMockBuilder('\OCP\Files\IRootFolder')->getMock();
		$storageMock->method('getUserFolder')->willReturn($folderMock);

		$filesController = new FilesController(
			'integration_openproject',
			$this->createMock(IRequest::class), $storageMock, 'testUser'
		);

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
				"owner_id" => "3df8ff78-49cb-4d60-8d8b-171b29591fd3"
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	/**
	 * @param string $path
	 * @param string $name
	 * @param string $mimeType
	 * @return \OCP\Files\Node
	 */
	private function getNodeMock($path, $name, $mimeType) {
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getDisplayName')->willReturn('Test User');
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');

		$fileMock = $this->createMock('\OCP\Files\Node');
		$fileMock->method('getId')->willReturn(123);
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
