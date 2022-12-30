<?php

namespace OCA\OpenProject\Controller;

use OCP\IRequest;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use OCA\OpenProject\Service\DatabaseService;
use function PHPUnit\Framework\assertSame;

class DirectUploadControllerTest extends TestCase {


	/**
	 * @return void
	 */
	public function testprepareDirectUpload() {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn($this->getNodeMock());
		$directUploadController = $this->createDirectUploadController($folderMock);
		$result = $directUploadController->prepareDirectUpload(123);
		assertSame(
			[
				'token' => 'WampxL5Z97CndGwB7qLPfotosDT5mXk7oFyGLa64nmY35ANtkzT7zDQwYyXrbdC3',
				'expires_on' => 1671537939
			],
			$result->getData()
		);
		assertSame(200, $result->getStatus());
	}

	/**
	 * @param MockObject $folderMock
	 * @return DirectUploadController
	 */
	private function createDirectUploadController(
		MockObject $folderMock
	): DirectUploadController {
		$storageMock = $this->getMockBuilder('\OCP\Files\IRootFolder')->getMock();
		$storageMock->method('getUserFolder')->willReturn($folderMock);

		$userMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$userMock->method('getUID')->willReturn('testUser');

		$userSessionMock = $this->getMockBuilder('\OCP\IUserSession')->getMock();
		$userSessionMock->method('getUser')->willReturn($userMock);

		$directUploadServiceMock = $this->getMockBuilder(
			'OCA\OpenProject\Service\DirectUploadService'
		)->disableOriginalConstructor()->getMock();

		$directUploadServiceMock->method('getTokenForDirectUpload')
			->willReturn([
				'token' => 'WampxL5Z97CndGwB7qLPfotosDT5mXk7oFyGLa64nmY35ANtkzT7zDQwYyXrbdC3',
				'expires_on' => 1671537939
			]);
		return new DirectUploadController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$storageMock,
			$userSessionMock,
			$this->createMock(IUserManager::class),
			$directUploadServiceMock,
			$this->createMock(DatabaseService::class),
			'testUser'

		);
	}

	private function getNodeMock(int $id = 123
	): array {
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getDisplayName')->willReturn('Test User');
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');

		$fileMock = $this->createMock('\OCP\Files\FileInfo');
		$fileMock->method('getId')->willReturn($id);
		$fileMock->method('getOwner')->willReturn($ownerMock);
		$fileMock->method('getSize')->willReturn(200245);
		$fileMock->method('getMimeType')->willReturn('httpd/unix-directory');
		$fileMock->method('getCreationTime')->willReturn(1639906930);
		$fileMock->method('getMTime')->willReturn(1640008813);
		$fileMock->method('getName')->willReturn('name-in-the-context-of-requester');
		$fileMock->method('getType')->willReturn('dir');
		$fileMock->method('isCreatable')->willReturn(true);
		return [$fileMock];
	}
}
