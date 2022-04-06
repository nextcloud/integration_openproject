<?php
/**
 * Nextcloud - OpenProject
 *
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Artur Neumann 2022
 */

namespace OCA\OpenProject\Controller;

use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Exception;
use OCP\Files\NotFoundException;

class OpenProjectAPIControllerTest extends TestCase {
	/** @var IConfig $configMock */
	private $configMock;

	/** @var IRequest $requestMock */
	private $requestMock;

	/**
	 * @var IURLGenerator
	 */
	private $urlGeneratorMock;

	/**
	 * @return void
	 * @before
	 */
	public function setUpMocks(): void {
		$this->requestMock = $this->createMock(IRequest::class);
		$this->configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$this->configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url'],
			)->willReturnOnConsecutiveCalls('cliendID', 'clientSecret', 'http://openproject.org');
		$this->urlGeneratorMock = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$this->urlGeneratorMock
			->method('getBaseUrl')
			->willReturn('http://nextcloud.org/');
	}

	/**
	 * @param string $token
	 * @return void
	 */
	public function getUserValueMock($token = '123') {
		// @phpstan-ignore-next-line
		$this->configMock
			->method('getUserValue')
			->withConsecutive(
				['test','integration_openproject', 'token'],
				['test','integration_openproject', 'refresh_token'],
			)->willReturnOnConsecutiveCalls($token, 'refreshToken');
	}

	/**
	 * @return void
	 */
	public function testGetNotifications() {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getNotifications'])
			->getMock();
		$service->expects($this->once())
			->method('getNotifications')
			->willReturn(['some' => 'data']);

		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test',
		);
		$response = $controller->getNotifications();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['some' => 'data'], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetNotificationsNoAccessToken() {
		$this->getUserValueMock('');
		$service = $this->createMock(OpenProjectAPIService::class);
		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getNotifications();
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetNotificationsErrorResponse() {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getNotifications')
			->willReturn(['error' => 'something went wrong']);

		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getNotifications();
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['error' => 'something went wrong'], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectAvatar() {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service->expects($this->once())
			->method('getOpenProjectAvatar')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				'id', 'name'
			)
			->willReturn(['avatar' => 'some image data', 'type' => 'image/png']);
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			'test'
		);
		$response = $controller->getOpenProjectAvatar('id', 'name');
		$this->assertSame('some image data', $response->render());
		$this->assertSame(
			"attachment; filename=\"avatar\"",
			$response->getHeaders()["Content-Disposition"]
		);
		$this->assertSame(
			"image/png",
			$response->getHeaders()["Content-Type"]
		);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectAvatarNoType() {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service->expects($this->once())
			->method('getOpenProjectAvatar')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				'id', 'name'
			)
			->willReturn(['avatar' => 'some image data']);
		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getOpenProjectAvatar('id', 'name');
		$this->assertSame('some image data', $response->render());
		$this->assertSame(
			"attachment; filename=\"avatar\"",
			$response->getHeaders()["Content-Disposition"]
		);
		$this->assertEmpty($response->getHeaders()["Content-Type"]);
	}

	/**
	 * @return array<mixed>
	 */
	public function searchWorkPackagesDataProvider() {
		return [
			['test', null, [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]]],
			['test', 9090, [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]]],
			[null, 9090, [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]]]
		];
	}

	/**
	 * @param string|null $searchQuery
	 * @param int|null $fileId
	 * @param array<mixed> $expectedResponse
	 * @return void
	 * @dataProvider searchWorkPackagesDataProvider
	 */
	public function testGetSearchedWorkPackages($searchQuery, $fileId, array $expectedResponse):void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['searchWorkPackage'])
			->getMock();
		$service->expects($this->once())
			->method('searchWorkPackage')
			->with(
				$this->anything(),
				$searchQuery,
				$fileId
			)
			->willReturn($expectedResponse);

		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getSearchedWorkPackages($searchQuery, $fileId);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($expectedResponse, $response->getData());
	}


	/**
	 * @return void
	 */
	public function testGetSearchedWorkPackagesNoAccessToken(): void {
		$this->getUserValueMock('');
		$service = $this->createMock(OpenProjectAPIService::class);
		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getSearchedWorkPackages('test');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetSearchedWorkPackagesErrorResponse(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('searchWorkPackage')
			->willReturn(['error' => 'something went wrong']);

		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getSearchedWorkPackages('test');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'something went wrong'], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageStatus(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getOpenProjectWorkPackageStatus'])
			->getMock();
		$service->expects($this->once())
			->method('getOpenProjectWorkPackageStatus')
			->willReturn([
				"_type" => "Status", "id" => 7, "name" => "In progress",
				"isClosed" => false, "color" => "#CC5DE8", "isDefault" => false, "isReadonly" => false, "defaultDoneRatio" => null, "position" => 7
			]);

		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getOpenProjectWorkPackageStatus('7');
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			"_type" => "Status", "id" => 7, "name" => "In progress",
			"isClosed" => false, "color" => "#CC5DE8", "isDefault" => false, "isReadonly" => false, "defaultDoneRatio" => null, "position" => 7
		], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageStatusErrorResponse(): void {
		$this->getUserValueMock('');
		$service = $this->createMock(OpenProjectAPIService::class);
		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getOpenProjectWorkPackageStatus('7');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageStatusNoAccessToken(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getOpenProjectWorkPackageStatus')
			->willReturn(['error' => 'something went wrong']);

		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getOpenProjectWorkPackageStatus('7');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['error' => 'something went wrong'], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageType(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getOpenProjectWorkPackageType'])
			->getMock();
		$service->expects($this->once())
			->method('getOpenProjectWorkPackageType')
			->willReturn(["_type" => "Type", "id" => 3, "name" => "Phase",
				"color" => "#CC5DE8", "position" => 4, "isDefault" => true, "isMilestone" => false, "createdAt" => "2022-01-12T08:53:15Z", "updatedAt" => "2022-01-12T08:53:34Z"]);

		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getOpenProjectWorkPackageType('3');
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(["_type" => "Type", "id" => 3, "name" => "Phase",
			"color" => "#CC5DE8", "position" => 4, "isDefault" => true, "isMilestone" => false, "createdAt" => "2022-01-12T08:53:15Z", "updatedAt" => "2022-01-12T08:53:34Z"], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageTypeErrorResponse(): void {
		$this->getUserValueMock('');
		$service = $this->createMock(OpenProjectAPIService::class);
		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getOpenProjectWorkPackageType('3');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageTypeNoAccessToken(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getOpenProjectWorkPackageType')
			->willReturn(['error' => 'something went wrong']);

		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getOpenProjectWorkPackageType('3');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['error' => 'something went wrong'], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinks(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getWorkPackageFileLinks'])
			->getMock();
		$service->expects($this->once())
			->method('getWorkPackageFileLinks')
			->willReturn(["_type" => "FileLink", "id" => 8, "createdAt" => "2022-04-06T05:14:24Z", "updatedAt" => "2022-04-06T05:14:24Z"]);

		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getWorkPackageFileLinks(7);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(["_type" => "FileLink", "id" => 8, "createdAt" => "2022-04-06T05:14:24Z", "updatedAt" => "2022-04-06T05:14:24Z"], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinksErrorResponse(): void {
		$this->getUserValueMock('');
		$service = $this->createMock(OpenProjectAPIService::class);
		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getWorkPackageFileLinks(7);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinksNotFound(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getWorkPackageFileLinks')
			->willThrowException(new NotFoundException('work package not found'));
		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getWorkPackageFileLinks(7);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame('work package not found', $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinksInternalServerError(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getWorkPackageFileLinks')
			->willThrowException(new Exception('something went wrong'));
		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->getWorkPackageFileLinks(7);
		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame('something went wrong', $response->getData());
	}

	/**
	 * @return void
	 */
	public function testdeleteFileLink(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['deleteFileLink'])
			->getMock();
		$service->expects($this->once())
			->method('deleteFileLink')
			->willReturn(['success' => true]);

		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->deleteFileLink(7);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['success' => true], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testdeleteFileLinkErrorResponse(): void {
		$this->getUserValueMock('');
		$service = $this->createMock(OpenProjectAPIService::class);
		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->deleteFileLink(7);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testdeleteFileLinkFileNotFound(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('deleteFileLink')
			->willThrowException(new NotFoundException('file not found'));
		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->deleteFileLink(7);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame('file not found', $response->getData());
	}

	/**
	 * @return void
	 */
	public function testdeleteFileLinkInternalServerError(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('deleteFileLink')
			->willThrowException(new Exception('something went wrong'));
		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, $this->urlGeneratorMock, 'test'
		);
		$response = $controller->deleteFileLink(7);
		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame('something went wrong', $response->getData());
	}
}
