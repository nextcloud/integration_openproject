<?php

//
///**
// * Nextcloud - OpenProject
// *
// * @copyright Copyright (c) 2022 Swikriti Tripathi <swikriti@jankaritech.com>
// *
// * @author Your name <swikriti@jankaritech.com>
// *
// * @license GNU AGPL version 3 or any later version
// *
// * This program is free software: you can redistribute it and/or modify
// * it under the terms of the GNU Affero General Public License as
// * published by the Free Software Foundation, either version 3 of the
// * License, or (at your option) any later version.
// *
// * This program is distributed in the hope that it will be useful,
// * but WITHOUT ANY WARRANTY; without even the implied warranty of
// * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// * GNU Affero General Public License for more details.
// *
// * You should have received a copy of the GNU Affero General Public License
// * along with this program. If not, see <http://www.gnu.org/licenses/>.
// *
// */
//
//namespace OCA\OpenProject\Service;
//
//use OC_Util;
//use OCA\DAV\Controller\DirectController;
//use OCA\DAV\Db\DirectMapper;
//use OCA\OpenProject\AppInfo\Application;
//use OCP\AppFramework\Utility\ITimeFactory;
//use OCP\EventDispatcher\IEventDispatcher;
//use OCP\Files\IRootFolder;
//use OCP\IRequest;
//use OCP\IURLGenerator;
//use OCP\IUserManager;
//use OCP\Security\ISecureRandom;
//use PHPUnit\Framework\TestCase;
//
//class DirectUploadServiceTest extends TestCase {
//	private const USER_ID = 'test';
//	private const USER_PASSWORD = 'T0T0T0T0T0T0T0';
//	/**
//	 * @var DirectUploadService
//	 */
//	private $directUploadService;
//	/**
//	 * @var \OCP\Files\Folder
//	 */
//	private $userFolder;
//	/**
//	 * @var DirectController
//	 */
//	private $directController;
//
//	public static function setUpBeforeClass(): void {
//		$app = new Application();
//		$c = $app->getContainer();
//
//		$userManager = $c->get(IUserManager::class);
//		$user = $userManager->get(self::USER_ID);
//		if ($user !== null) {
//			$user->delete();
//		}
//
//		// create dummy user
//		$userManager->createUser(self::USER_ID, self::USER_PASSWORD);
//	}
//
//	protected function setUp(): void {
//		$app = new Application();
//		$c = $app->getContainer();
//
//		/** @var DirectUploadService directUploadService */
//		$directUploadService = $c->get(DirectUploadService::class);
//		$this->directUploadService = $directUploadService;
//
//		/** @var IRootFolder $root */
//		$root = $c->get(IRootFolder::class);
//		$this->userFolder = $root->getUserFolder(self::USER_ID);
//
//		// DirectController constructor changed from NC version 24, see https://github.com/nextcloud/server/pull/32482
//		if (version_compare(OC_Util::getVersionString(), '24') >= 0) {
//			// @phpstan-ignore-next-line
//			$this->directController = new DirectController(
//				'dav',
//				$c->get(IRequest::class),
//				$root,
//				self::USER_ID,
//				$c->get(DirectMapper::class),
//				$c->get(ISecureRandom::class),
//				$c->get(ITimeFactory::class),
//				$c->get(IURLGenerator::class),
//				$c->get(IEventDispatcher::class)
//			);
//		} else {
//			// @phpstan-ignore-next-line
//			$this->directController = new DirectController(
//				'dav',
//				$c->get(IRequest::class),
//				$root,
//				self::USER_ID,
//				$c->get(DirectMapper::class),
//				$c->get(ISecureRandom::class),
//				$c->get(ITimeFactory::class),
//				$c->get(IURLGenerator::class)
//			);
//		}
//	}
//
//	public static function tearDownAfterClass(): void {
//		$app = new Application();
//		$c = $app->getContainer();
//		$userManager = $c->get(IUserManager::class);
//		$user = $userManager->get('test');
//		$user->delete();
//	}
//
//	protected function tearDown(): void {
//	}
//
//	/**
//	 * @return void
//	 * @throws \OCP\AppFramework\OCS\OCSBadRequestException
//	 * @throws \OCP\AppFramework\OCS\OCSNotFoundException
//	 * @throws \OCP\Files\InvalidPathException
//	 * @throws \OCP\Files\NotFoundException
//	 * @throws \OCP\Files\NotPermittedException
//	 * @throws \OCP\Lock\LockedException
//	 */
//	public function testGetTokenForDirectUpload() {
//		// create a file
//		$originalFile = $this->userFolder->newFolder('new-folder');
//
//		// create a direct download link and get its token
//		$folderId = $originalFile->getId();
//		// get the file with our custom service
//		$serviceFile = $this->directUploadService->getDirectDownloadFile($folderId, self::USER_ID);
//		$this->assertSame($originalFile->getId(), $serviceFile->getId());
//		$this->assertSame($originalFile->getContent(), $serviceFile->getContent());
//	}
//}
