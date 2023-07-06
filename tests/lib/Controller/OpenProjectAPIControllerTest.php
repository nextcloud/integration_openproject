<?php
/**
 * Nextcloud - OpenProject
 *
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Artur Neumann 2022
 */

namespace OCA\OpenProject\Controller;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\Http\Client\IResponse;
use OCP\Http\Client\LocalServerException;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Exception;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

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
	 * @var LoggerInterface
	 */
	private $loggerMock;
	/**
	 * @return void
	 * @before
	 */
	public function setUpMocks(): void {
		$this->requestMock = $this->createMock(IRequest::class);
		$this->urlGeneratorMock = $this->createMock(IURLGenerator::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);
		$this->configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$this->configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url'],
			)->willReturnOnConsecutiveCalls('http://openproject.org');
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test',
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->getNotifications();
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}


	/**
	 * @return void
	 */
	public function testGetNotificationsBadOPInstanceUrl() {
		$this->configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$this->configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url'],
			)->willReturnOnConsecutiveCalls('http:openproject.org');
		$this->getUserValueMock();
		$service = $this->createMock(OpenProjectAPIService::class);
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
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
				'id', 'name'
			)
			->willReturn(['avatar' => 'some image data', 'type' => 'image/png']);
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
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
				'id', 'name'
			)
			->willReturn(['avatar' => 'some image data']);
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->getSearchedWorkPackages('test');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetSearchedWorkPackagesBadOPInstanceUrl(): void {
		$this->configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$this->configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url'],
			)->willReturnOnConsecutiveCalls('http:openproject');
		$this->getUserValueMock();
		$service = $this->createMock(OpenProjectAPIService::class);
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->getSearchedWorkPackages('test');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->getOpenProjectWorkPackageStatus('7');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->getOpenProjectWorkPackageStatus('7');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['error' => 'something went wrong'], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageStatusBadOPInstanceUrl(): void {
		$this->configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$this->configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url'],
			)->willReturnOnConsecutiveCalls('http:openproject');
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->getOpenProjectWorkPackageStatus('7');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->getOpenProjectWorkPackageType('3');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->getOpenProjectWorkPackageType('3');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['error' => 'something went wrong'], $response->getData());
	}
	
	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageTypeBadOPInstanceUrl(): void {
		$this->configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$this->configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url'],
			)->willReturnOnConsecutiveCalls('http:openproject');
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->getOpenProjectWorkPackageType('3');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
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
			->willReturn([[
				'id' => 8,
				'_type' => "FileLink",
				'originData' => [
					'id' => 5
				]
			]]);

		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->getWorkPackageFileLinks(7);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([[
			'id' => 8,
			'_type' => "FileLink",
			'originData' => [
				'id' => 5
			]
		]], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinksErrorResponse(): void {
		$this->getUserValueMock('');
		$service = $this->createMock(OpenProjectAPIService::class);
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->getWorkPackageFileLinks(7);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
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
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->getWorkPackageFileLinks(7);
		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame('something went wrong', $response->getData());
	}

	/**
	 * @return void
	 */
	public function testDeleteFileLink(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['deleteFileLink'])
			->getMock();
		$service->expects($this->once())
			->method('deleteFileLink')
			->willReturn(['success' => true]);

		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->deleteFileLink(7);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['success' => true], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testDeleteFileLinkErrorResponse(): void {
		$this->getUserValueMock('');
		$service = $this->createMock(OpenProjectAPIService::class);
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->deleteFileLink(7);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testDeleteFileLinkFileNotFound(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('deleteFileLink')
			->willThrowException(new NotFoundException('file not found'));
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->deleteFileLink(7);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame('file not found', $response->getData());
	}

	/**
	 * @return void
	 */
	public function testDeleteFileLinkInternalServerError(): void {
		$this->getUserValueMock();
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('deleteFileLink')
			->willThrowException(new Exception('something went wrong'));
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$response = $controller->deleteFileLink(7);
		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame('something went wrong', $response->getData());
	}

	/**
	 * @return array<mixed>
	 */
	public function isValidOpenProjectInstanceDataProvider() {
		return [
			['{"_type":"Root","instanceName":"OpenProject"}', true],
			['{"_type":"something","instanceName":"OpenProject"}', 'not_valid_body'],
			['{"_type":"Root","someData":"whatever"}', 'not_valid_body'],
			['<h1>hello world</h1>', 'not_valid_body'],
		];
	}

	/**
	 * @dataProvider isValidOpenProjectInstanceDataProvider
	 * @param string $body
	 * @param string|bool $expectedResult
	 * @return void
	 */
	public function testIsValidOpenProjectInstance(
		string $body, $expectedResult
	): void {
		$response = $this->getMockBuilder(IResponse::class)->getMock();
		$response->method('getBody')->willReturn($body);
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['rawRequest'])
			->getMock();
		$service
			->method('rawRequest')
			->willReturn($response);
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$result = $controller->isValidOpenProjectInstance('http://openproject.org');
		if ($expectedResult === true) {
			$this->assertSame([ 'result' => true], $result->getData());
		} else {
			$this->assertSame(
				[ 'result' => $expectedResult, 'details' => $body ],
				$result->getData()
			);
		}
	}


	public function testIsValidOpenProjectInstanceRedirect(): void {
		$response = $this->getMockBuilder(IResponse::class)->getMock();
		$response->method('getStatusCode')->willReturn(302);
		$response->method('getHeader')
			->with('Location')
			->willReturn('https://openproject.org/api/v3/');
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['rawRequest'])
			->getMock();
		$service
			->method('rawRequest')
			->willReturn($response);
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$result = $controller->isValidOpenProjectInstance('http://openproject.org');
		$this->assertSame(
			[ 'result' => 'redirected', 'details' => 'https://openproject.org/'],
			$result->getData()
		);
	}

	public function testIsValidOpenProjectInstanceRedirectNoLocationHeader(): void {
		$response = $this->getMockBuilder(IResponse::class)->getMock();
		$response->method('getStatusCode')->willReturn(302);
		$response->method('getHeader')
			->with('Location')
			->willReturn('');
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['rawRequest'])
			->getMock();
		$service
			->method('rawRequest')
			->willReturn($response);
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$result = $controller->isValidOpenProjectInstance('http://openproject.org');
		$this->assertSame(
			[
				'result' => 'unexpected_error',
				'details' => 'received a redirect status code (302) but no "Location" header'
			],
			$result->getData()
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function isValidOpenProjectInstanceExpectionDataProvider() {
		$requestMock = $this->getMockBuilder('\Psr\Http\Message\RequestInterface')->getMock();
		$privateInstance = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$privateInstance->method('getBody')->willReturn(
			'{"_type":"Error","errorIdentifier":"urn:openproject-org:api:v3:errors:Unauthenticated"}'
		);
		$notOP = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$notOP->method('getBody')->willReturn('Unauthenticated');
		$notOP->method('getReasonPhrase')->willReturn('Unauthenticated');
		$notOP->method('getStatusCode')->willReturn('401');
		$notOPButJSON = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$notOPButJSON->method('getBody')->willReturn(
			'{"what":"Error","why":"Unauthenticated"}'
		);
		$notOPButJSON->method('getReasonPhrase')->willReturn('Unauthenticated');
		$notOPButJSON->method('getStatusCode')->willReturn('401');
		$otherResponseMock = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$otherResponseMock->method('getReasonPhrase')->willReturn('Internal Server Error');
		$otherResponseMock->method('getStatusCode')->willReturn('500');
		return [
			[
				new ConnectException('a connection problem', $requestMock),
				[ 'result' => 'network_error', 'details' => 'a connection problem']
			],
			[
				new ClientException('valid but private instance', $requestMock, $privateInstance),
				[ 'result' => true ]
			],
			[
				new ClientException('not a OP instance', $requestMock, $notOP),
				[ 'result' => 'client_exception', 'details' => '401 Unauthenticated' ]
			],
			[
				new ClientException('not a OP instance but return JSON', $requestMock, $notOPButJSON),
				[ 'result' => 'client_exception', 'details' => '401 Unauthenticated' ]
			],
			[
				new ServerException('some server issue', $requestMock, $otherResponseMock),
				[ 'result' => 'server_exception', 'details' => '500 Internal Server Error' ]
			],
			[
				new BadResponseException('some issue', $requestMock, $otherResponseMock),
				[ 'result' => 'request_exception', 'details' => 'some issue' ]
			],
			[
				new LocalServerException('Host violates local access rules'),
				[ 'result' => 'local_remote_servers_not_allowed' ]
			],
			[
				new RequestException('some issue', $requestMock, $otherResponseMock),
				[ 'result' => 'request_exception', 'details' => 'some issue' ]
			],
			[
				new \Exception('some issue'),
				[ 'result' => 'unexpected_error', 'details' => 'some issue' ]
			],

		];
	}

	/**
	 * @dataProvider isValidOpenProjectInstanceExpectionDataProvider
	 * @param Exception $thrownException
	 * @param bool|string $expectedResult
	 * @return void
	 */
	public function testIsValidOpenProjectInstanceException(
		$thrownException, $expectedResult
	): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['rawRequest'])
			->getMock();
		$service
			->method('rawRequest')
			->willThrowException($thrownException);
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$result = $controller->isValidOpenProjectInstance('http://openproject.org');
		$this->assertSame($expectedResult, $result->getData());
	}

	/**
	 * @return array<int, array<int, string>>
	 */
	public function isValidOpenProjectInstanceInvalidUrlDataProvider() {
		return [
			[ '123' ],
			[ 'htt://something' ],
			[ '' ],
			[ 'ftp://something.org ']
		];
	}
	/**
	 * @dataProvider isValidOpenProjectInstanceInvalidUrlDataProvider
	 * @return void
	 */
	public function testIsValidOpenProjectInstanceInvalidUrl(string $url): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();
		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$result = $controller->isValidOpenProjectInstance($url);
		$this->assertSame(['result' => 'invalid'], $result->getData());
	}

	public function testGetOpenProjectOauthURLWithStateAndPKCE(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();
		$this->configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$this->configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url'],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url'],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_instance_url'],
			)->willReturnOnConsecutiveCalls(
				'http://openproject.org',
				'myClientID',
				'myClientSecret',
				'http://openproject.org',
				'myClientID',
				'http://openproject.org',
			);
		$this->configMock
			->expects($this->exactly(2))
			->method('setUserValue')
			->withConsecutive(
				[
					'test',
					'integration_openproject',
					'oauth_state',
					$this->matchesRegularExpression('/[a-z0-9]{10}/')
				],
				[
					'test',
					'integration_openproject',
					'code_verifier',
					$this->matchesRegularExpression('/[A-Za-z0-9\-._~]{128}/')
				],
			);

		$controller = new OpenProjectAPIController(
			'integration_openproject',
			$this->requestMock,
			$this->configMock,
			$service,
			$this->urlGeneratorMock,
			$this->loggerMock,
			'test'
		);
		$result = $controller->getOpenProjectOauthURLWithStateAndPKCE();
		$this->assertMatchesRegularExpression(
			'/^http:\/\/openproject\.org\/oauth\/authorize\?' .
			'client_id=myClientID&' .
			'redirect_uri=&' .
			'response_type=code&' .
			'state=[a-z0-9]{10}&' .
			'code_challenge=[a-zA-Z0-9\-_]{43}&' .
			'code_challenge_method=S256$/',
			(string) $result->getData()
		);
	}
}
