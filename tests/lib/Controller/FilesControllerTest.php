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
			[[$this->getNodeMock('/logo.png')], '/logo.png'],
			// getById returns multiple results e.g. if the file was received through multiple path
			[
				[
					$this->getNodeMock('files/receivedAsFolderShare/logo.png'),
					$this->getNodeMock('files/receivedAsFileShareAndRenamed.png')
				],
				'/receivedAsFolderShare/logo.png'
			]
		];
	}

	/**
	 * @dataProvider getFileInfoDataProvider
	 * @param array<mixed> $nodeMocks
	 * @param string $expectedPath
	 * @return void
	 */
	public function testGetFileInfo($nodeMocks, $expectedPath) {
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
				"name" => "logo.png",
				"mtime" => 1640008813,
				"ctime" => 1639906930,
				"mimetype" => "image/png",
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
	 * @return \OCP\Files\Node
	 */
	private function getNodeMock($path) {
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getDisplayName')->willReturn('Test User');
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');

		$fileMock = $this->createMock('\OCP\Files\Node');
		$fileMock->method('getId')->willReturn(123);
		$fileMock->method('getOwner')->willReturn($ownerMock);
		$fileMock->method('getSize')->willReturn(200245);
		$fileMock->method('getName')->willReturn('logo.png');
		$fileMock->method('getMimeType')->willReturn('image/png');
		$fileMock->method('getCreationTime')->willReturn(1639906930);
		$fileMock->method('getMTime')->willReturn(1640008813);
		$fileMock->method('getInternalPath')->willReturn($path);
		return $fileMock;
	}
}
