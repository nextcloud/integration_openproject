<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Controller;

use OCP\Files\Folder;
use OCP\Files\ForbiddenException as FileAccessForbiddenException;
use OCP\Files\InvalidContentException;
use OCP\Files\IUserFolder;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertSame;

class DirectUploadControllerTest extends TestCase {

	/**
	 * @var IL10N
	 */
	private $l;

	/**
	 * @return mixed
	 */
	public function getFolderMock(): mixed {
		if (interface_exists(IUserFolder::class)) {
			return $this->createMock(IUserFolder::class);
		}
		return $this->createMock(Folder::class);
	}

	/**
	 * @return void
	 */
	public function testprepareDirectUpload() {
		$folderMock = $this->getFolderMock();
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
		$folderMock = $this->getFolderMock();
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
		$folderMock = $this->getFolderMock();
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
		$folderMock = $this->getFolderMock();
		$folderMock->method('getById')->willReturn($this->getNodeMock('folder'));
		$directUploadController = $this->createDirectUploadController($folderMock);
		$result = $directUploadController->directUpload($token);
		assertSame(
			[
				'error' => 'invalid token'
			],
			$result->getData()
		);
		assertSame(401, $result->getStatus());
	}

	public function testDirectUploadNotEnoughSpace():void {
		$nodeMock = $this->getNodeMock('folder');
		$nodeMock[0]->method('getFreeSpace')->willReturn(100);

		$userFolderMock = $this->getFolderMock();
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
			[UPLOAD_ERR_INI_SIZE, 'The uploaded file exceeds the upload_max_filesize directive in php.ini'],
			[UPLOAD_ERR_FORM_SIZE, 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'],
			[UPLOAD_ERR_PARTIAL, 'The file was only partially uploaded'],
			[UPLOAD_ERR_NO_FILE, 'No file was uploaded'],
			[UPLOAD_ERR_NO_TMP_DIR, 'Missing a temporary folder'],
			[UPLOAD_ERR_CANT_WRITE, 'Could not write file to disk'],
			[UPLOAD_ERR_EXTENSION, 'A PHP extension stopped the file upload'],
		];
	}
	/**
	 * @param int $error
	 * @param string $expectedErrorMessage
	 * @return void
	 * @dataProvider fileNotUploadedDataProvider
	 */
	public function testDirectUploadFileNotUploaded(int $error, string $expectedErrorMessage):void {
		$nodeMock = $this->getNodeMock('folder');

		$userFolderMock = $this->getFolderMock();
		$userFolderMock->method('getById')->willReturn($nodeMock);
		$directUploadController = $this->createDirectUploadController(
			$userFolderMock, 100, '', $error
		);
		$result = $directUploadController->directUpload(
			'WampxL5Z97CndGwB7qLPfotosDT5mXk7oFyGLa64nmY35ANtkzT7zDQwYyXrbdC3'
		);
		$resultArray = $result->getData();
		assertSame(
			$expectedErrorMessage,
			$resultArray['error']
		);
		assertSame(413, $result->getStatus());
	}

	/**
	 * @return void
	 */
	public function testDirectUploadNoFile():void {
		$nodeMock = $this->getNodeMock('folder');

		$userFolderMock = $this->getFolderMock();
		$userFolderMock->method('getById')->willReturn($nodeMock);
		$directUploadController = $this->createDirectUploadController(
			folderMock: $userFolderMock, noUploadedFile: true
		);
		$result = $directUploadController->directUpload(
			'WampxL5Z97CndGwB7qLPfotosDT5mXk7oFyGLa64nmY35ANtkzT7zDQwYyXrbdC3'
		);
		$resultArray = $result->getData();
		assertSame(
			'No file is present to upload.',
			$resultArray['error']
		);
		assertSame(400, $result->getStatus());
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
		$userFolderMock = $this->getFolderMock();
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
		$storageMock = $this->getMockBuilder('\OCP\Files\Storage\IStorage')->disableOriginalConstructor()->getMock();
		$storageMock->method('getCache')->willReturn($cacheMock);

		$fileMock = $this->getMockBuilder('\OC\Files\Node\File')->disableOriginalConstructor()->getMock();
		$fileMock->method('getId')->willReturn(123);
		$fileMock->method('getStorage')->willReturn($storageMock);
		$nodeMock = $this->getNodeMock('folder');
		$tmpFileName = '/tmp/integration_openproject_unit_test';
		touch($tmpFileName);
		$nodeMock[0]->method('getFreeSpace')->willReturn(-3);
		$nodeMock[0]->method('newFile')->willReturn($fileMock);
		$userFolderMock = $this->getFolderMock();
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
	 * @param mixed $folderMock
	 * @param int $uploadedFileSize
	 * @param string $uploadedFileTmpName
	 * @param int $uploadedFileError
	 * @param bool $noUploadedFile
	 * @return DirectUploadController
	 */
	private function createDirectUploadController(
		mixed $folderMock,
		int $uploadedFileSize = 9999,
		string $uploadedFileTmpName = '/tmp/andjashd',
		int $uploadedFileError = 0,
		bool $noUploadedFile = false
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

		$requestMock->method('getUploadedFile')->willReturn($noUploadedFile ? null : [
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
	 * @return array<mixed>
	 */
	private function getNodeMock(string $type, int $id = 123): array {
		$ownerMock = $this->getMockBuilder('\OCP\IUser')->getMock();
		$ownerMock->method('getDisplayName')->willReturn('Test User');
		$ownerMock->method('getUID')->willReturn('3df8ff78-49cb-4d60-8d8b-171b29591fd3');

		$cacheMock = $this->getMockBuilder('\OCP\Files\Cache\ICache')->getMock();
		$cacheMock->method('update')->willReturn(true);
		$storageMock = $this->getMockBuilder('\OCP\Files\Storage\IStorage')->disableOriginalConstructor()->getMock();
		$storageMock->method('getCache')->willReturn($cacheMock);

		$fileMock = $this->createMock('\OCP\Files\File');
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
}
