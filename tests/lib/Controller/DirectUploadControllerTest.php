<?php
/**
 * @copyright Copyright (c) 2022 Swikriti Tripathi <swikriti@jankaritech.com>
 *
 * @author Your name <swikriti@jankaritech.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OpenProject\Controller;

use OCP\IRequest;
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
			'tmp_name' => '/tmp/andjashd'
		]);
		return new DirectUploadController(
			'integration_openproject',
			$requestMock,
			$storageMock,
			$userSessionMock,
			$userManagerMock,
			$directUploadServiceMock,
			$this->createMock(DatabaseService::class),
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

		$fileMock = $this->createMock('\OCP\Files\File');
		$fileMock->method('getId')->willReturn(123);

		$folderMock = $this->createMock('\OCP\Files\Folder');
		$folderMock->method('getId')->willReturn($id);
		$folderMock->method('getType')->willReturn($type);
		$folderMock->method('isCreatable')->willReturn(true);
		$folderMock->method('nodeExists')->willReturn(false);
		$folderMock->method('newFile')->willReturn($fileMock);
		return [$folderMock];
	}
}
