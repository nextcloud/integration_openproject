<?php
/**
 * Nextcloud - OpenProject
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\OpenProject\Service;

use OCA\DAV\Controller\DirectController;
use OCA\DAV\Db\DirectMapper;
use OCA\OpenProject\AppInfo\Application;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;

class DirectDownloadServiceTest extends TestCase {
	private const USER_ID = 'test';
	private const USER_PASSWORD = 'T0T0T0T0T0T0T0';
	/**
	 * @var DirectDownloadService
	 */
	private $directDownloadService;
	/**
	 * @var \OCP\Files\Folder
	 */
	private $userFolder;
	/**
	 * @var DirectController
	 */
	private $directController;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();

		$userManager = $c->get(IUserManager::class);
		$user = $userManager->get(self::USER_ID);
		if ($user !== null) {
			$user->delete();
		}

		// create dummy user
		$userManager->createUser(self::USER_ID, self::USER_PASSWORD);
	}

	protected function setUp(): void {
		$app = new Application();
		$c = $app->getContainer();

		/** @var DirectDownloadService directDownloadService */
		$directDownloadService = $c->get(DirectDownloadService::class);
		$this->directDownloadService = $directDownloadService;

		/** @var IRootFolder $root */
		$root = $c->get(IRootFolder::class);
		$this->userFolder = $root->getUserFolder(self::USER_ID);

		$this->directController = new DirectController(
			'dav',
			$c->get(IRequest::class),
			$root,
			self::USER_ID,
			$c->get(DirectMapper::class),
			$c->get(ISecureRandom::class),
			$c->get(ITimeFactory::class),
			$c->get(IURLGenerator::class)
		);
	}

	public static function tearDownAfterClass(): void {
		$app = new Application();
		$c = $app->getContainer();
		$userManager = $c->get(IUserManager::class);
		$user = $userManager->get('test');
		$user->delete();
	}

	protected function tearDown(): void {
	}

	/**
	 * @return void
	 * @throws \OCP\AppFramework\OCS\OCSBadRequestException
	 * @throws \OCP\AppFramework\OCS\OCSNotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\Lock\LockedException
	 */
	public function testGetDirectDownloadFile() {
		// create a file
		$originalFile = $this->userFolder->newFile('example.txt', 'dummy content');

		// create a direct download link and get its token
		$response = $this->directController->getUrl($originalFile->getId());
		$directLinkUrl = $response->getData()['url'];
		preg_match('/.*\/([^\/]*)$/', $directLinkUrl, $matches);
		$directLinkToken = $matches[1];

		// get the file with our custom service
		$serviceFile = $this->directDownloadService->getDirectDownloadFile($directLinkToken);
		$this->assertSame($originalFile->getId(), $serviceFile->getId());
		$this->assertSame($originalFile->getContent(), $serviceFile->getContent());
	}
}
