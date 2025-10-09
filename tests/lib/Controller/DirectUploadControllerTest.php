<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Controller;

use OCP\Files\Folder;
use OCP\Files\ForbiddenException as FileAccessForbiddenException;
use OCP\Files\InvalidContentException;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertSame;

class DirectUploadControllerTest extends TestCase {

	/**
	 * @var IL10N
	 */
	private $l;

	/**
	 * @return void
	 */
	public function testprepareDirectUpload() {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn($this->getNodeMock('dir'));
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
	 * @return void
	 */
	public function testprepareDirectUploadTypeFile(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn($this->getNodeMock('file'));
		$directUploadController = $this->createDirectUploadController($folderMock);
		$result = $directUploadController->prepareDirectUpload(123);
		assertSame(
			[
				'error' => 'folder not found or not enough permissions'
			],
			$result->getData()
		);
		assertSame(404, $result->getStatus());
	}

	public function testprepareDirectUploadException(): void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')
			->will($this->throwException(new \Exception('something bad happened')));
		$directUploadController = $this->createDirectUploadController($folderMock);
		$result = $directUploadController->prepareDirectUpload(123);
		assertSame(
			[
				'error' => 'folder not found or not enough permissions'
			],
			$result->getData()
		);
		assertSame(404, $result->getStatus());
	}

	/**
	 * @return array<mixed>
	 */
	public function directUploadInvalidTokenDataProvider() {
		return [
			[
				'msnjsdba'
			],
			[
				'CyeKfQaJpEgBHTMnCJBCiXWEWWr9fddSzSte3fNWo9tfFmwnn5fkEa9o2i3$%w2qg'
			]
		];
	}

	/**
	 * @dataProvider directUploadInvalidTokenDataProvider
	 *  @param string $token
	 * @return void
	 */
	public function testDirectUploadInvalidToken(string $token):void {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn($this->getNodeMock('folder'));
		$directUploadController = $this->createDirectUploadController($folderMock);
		$result = $directUploadController->directUpload($token);
		assertSame(
			[
				'error' => 'invalid token'
			],
			$result->getData()
		);
		assertSame(404, $result->getStatus());
	}

	public function testDirectUploadNotEnoughSpace():void {
		$nodeMock = $this->getNodeMock('folder');
		$nodeMock[0]->method('getFreeSpace')->willReturn(100);

		$userFolderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$userFolderMock->method('getById')->willReturn($nodeMock);
		$directUploadController = $this->createDirectUploadController(
			$userFolderMock, 101
		);
		$result = $directUploadController->directUpload(
			'WampxL5Z97CndGwB7qLPfotosDT5mXk7oFyGLa64nmY35ANtkzT7zDQwYyXrbdC3'
		);
		assertSame(
			[
				'error' => 'insufficient quota'
			],
			$result->getData()
		);
		assertSame(507, $result->getStatus());
	}

	/**
	 * @return array<int, array<int, int|string>>
	 */
	public function fileNotUploadedDataProvider() {
		return [
			['', 1],
			['some name', 1],
			['', 0],
		];
	}
	/**
	 * @param string $tmpName
	 * @param int $error
	 * @return void
	 * @dataProvider fileNotUploadedDataProvider
	 */
	public function testDirectUploadFileNotUploaded(string $tmpName, int $error):void {
		$nodeMock = $this->getNodeMock('folder');

		$userFolderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$userFolderMock->method('getById')->willReturn($nodeMock);
		$directUploadController = $this->createDirectUploadController(
			$userFolderMock, 100, $tmpName, $error
		);
		$result = $directUploadController->directUpload(
			'WampxL5Z97CndGwB7qLPfotosDT5mXk7oFyGLa64nmY35ANtkzT7zDQwYyXrbdC3'
		);
		$resultArray = $result->getData();
		assertSame(
			'File was not uploaded. upload_max_filesize exceeded?',
			$resultArray['error']
		);
		self::assertIsNumeric($resultArray['upload_limit']);
		assertSame(413, $result->getStatus());
	}

	/**
	 * @return array<int, array<int, \Exception|int|string>>
	 */
	public function newFileExceptionsDataProvider() {
		return [
			[new InvalidContentException('Virus detected'), 'Virus detected', 415],
			[new FileAccessForbiddenException('Access denied by the access control', false), 'Access denied by the access control', 403],
			[new \Exception('could not upload'), 'could not upload', 500],
		];
	}

	/**
	 * @return void
	 * @dataProvider newFileExceptionsDataProvider
	 */
	public function testDirectUploadException(
		\Exception $exception, string $expectedErrorMessage, int $expectedStatusCode
	):void {
		$nodeMock = $this->getNodeMock('folder');
		$tmpFileName = '/tmp/integration_openproject_unit_test';
		touch($tmpFileName);
		$nodeMock[0]->method('newFile')->will($this->throwException($exception));
		$userFolderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$userFolderMock->method('getById')->willReturn($nodeMock);
		$directUploadController = $this->createDirectUploadController(
			$userFolderMock, 0, $tmpFileName);
		$result = $directUploadController->directUpload(
			'WampxL5Z97CndGwB7qLPfotosDT5mXk7oFyGLa64nmY35ANtkzT7zDQwYyXrbdC3'
		);
		$resultArray = $result->getData();
		assertSame(
			$expectedErrorMessage,
			$resultArray['error']
		);
		assertSame($expectedStatusCode, $result->getStatus());
	}

	public function testNegativeFreeSpace(): void {
		$cacheMock = $this->getMockBuilder('\OCP\Files\Cache\ICache')->getMock();
		$cacheMock->method('update')->willReturn(true);
		$storageMock = $this->getMockBuilder('\OCP\Files\Storage\IStorage')->getMock();
		$storageMock->method('getCache')->willReturn($cacheMock);

		$fileMock = $this->getMockBuilder('\OC\Files\Node\File')->disableOriginalConstructor()->getMock();
		$fileMock->method('getId')->willReturn(123);
		$fileMock->method('getStorage')->willReturn($storageMock);
		$nodeMock = $this->getNodeMock('folder');
		$tmpFileName = '/tmp/integration_openproject_unit_test';
		touch($tmpFileName);
		$nodeMock[0]->method('getFreeSpace')->willReturn(-3);
		$nodeMock[0]->method('newFile')->willReturn($fileMock);
		$userFolderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$userFolderMock->method('getById')->willReturn($nodeMock);
		$directUploadController = $this->createDirectUploadController(
			$userFolderMock, 101, $tmpFileName
		);
		$result = $directUploadController->directUpload(
			'WampxL5Z97CndGwB7qLPfotosDT5mXk7oFyGLa64nmY35ANtkzT7zDQwYyXrbdC3'
		);
		$resultArray = $result->getData();
		assertSame(
			[
				'file_name' => 'file.txt',
				'file_id' => 123
			],
			$resultArray
		);
	}


	/**
	 * @param MockObject $folderMock
	 * @param int $uploadedFileSize
	 * @param string $uploadedFileTmpName
	 * @param int $uploadedFileError
	 * @return DirectUploadController
	 */
	private function createDirectUploadController(
		MockObject $folderMock,
		int $uploadedFileSize = 9999,
		string $uploadedFileTmpName = '/tmp/andjashd',
		int $uploadedFileError = 0
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

		$this->l = $this->createMock(IL10N::class);
		$this->l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($string, $args) {
				return vsprintf($string, $args);
			});

		$directUploadServiceMock->method('getTokenInfo')->willReturn(
			[
				'user_id' => 'testUser',
				'expires_on' => 1671537939,
				'folder_id' => 123
			]
		);
		$userManagerMock = $this->getMockBuilder('OCP\IUserManager')->disableOriginalConstructor()->getMock();
		$userManagerMock->method('get')->willReturn($userMock);

		$requestMock = $this->getMockBuilder(IRequest::class)->disableOriginalConstructor()->getMock();

		$requestMock->method('getUploadedFile')->willReturn([
			'name' => 'file.txt',
			'tmp_name' => $uploadedFileTmpName,
			'size' => $uploadedFileSize,
			'error' => $uploadedFileError
		]);
		return new DirectUploadController(
			'integration_openproject',
			$requestMock,
			$storageMock,
			$userSessionMock,
			$userManagerMock,
			$directUploadServiceMock,
			$this->getMockBuilder('OCA\OpenProject\Service\DatabaseService')->disableOriginalConstructor()->getMock(),
			$this->l,
			'testUser',
		);
	}

	/**
	 *
	 * @param string $type
	 * @param int $id
	 * @return array<MockObject|Folder>
	 */
	private function getNodeMock(string $type, int $id = 123): array {
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getDisplayName')->willReturn('Test User');
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');

		$cacheMock = $this->getMockBuilder('\OCP\Files\Cache\ICache')->getMock();
		$cacheMock->method('update')->willReturn(true);
		$storageMock = $this->getMockBuilder('\OCP\Files\Storage\IStorage')->getMock();
		$storageMock->method('getCache')->willReturn($cacheMock);

		$fileMock = $this->createMock('\OCP\Files\File');
		$fileMock->method('getId')->willReturn(123);
		$fileMock->method('getStorage')->willReturn($storageMock);

		$folderMock = $this->createMock('\OCP\Files\Folder');
		$folderMock->method('getId')->willReturn($id);
		$folderMock->method('getType')->willReturn($type);
		$folderMock->method('isCreatable')->willReturn(true);
		$folderMock->method('nodeExists')->willReturn(false);
		$folderMock->method('newFile')->willReturn($fileMock);
		return [$folderMock];
	}
}
