<?php

/**
 * Nextcloud - OpenProject
 *
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Artur Neumann 2022
 */

namespace OCA\OpenProject\Controller;

use Exception;
use InvalidArgumentException;
use OCA\OpenProject\Exception\OpenprojectErrorException;
use OCA\OpenProject\Exception\OpenprojectResponseException;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Http;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class OpenProjectAPIControllerTest extends TestCase {
	/**
	 * @var array <mixed>
	 */
	private $fileInformationToLinkToWorkPackage = [
		"workpackageId" => 1,
		"fileinfo" => [
			[
				"id" => 3,
				"name" => "testFile.txt"
			]
		]
	];

	/**
	 * @param string $authToken
	 * @param string $opUrl
	 * @psalm-suppress UndefinedInterfaceMethod
	 * @return IConfig
	 */
	public function getConfigMock($authToken = null, $opUrl = null): IConfig {
		$token = $authToken ?? '123';

		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getUserValue')
			->withConsecutive(
				['test','integration_openproject', 'token'],
				['test','integration_openproject', 'refresh_token'],
			)->willReturnOnConsecutiveCalls($token, 'refreshToken');

		$opUrl = $opUrl ?? 'https://openproject.org';
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url'],
			)->willReturnOnConsecutiveCalls($opUrl);
		return $configMock;
	}

	/**
	 * @param array<string, object> $constructParams
	 *
	 * @return OpenProjectAPIController
	 */
	public function getOpenProjectAPIControllerMock(array $constructParams = []): OpenProjectAPIController {
		$constructArgs = [
			'request' => $this->createMock(IRequest::class),
			'config' => $this->createMock(IConfig::class),
			'openProjectAPIService' => $this->createMock(OpenProjectAPIService::class),
			'userId' => 'test',
		];
		foreach ($constructParams as $key => $value) {
			if (!array_key_exists($key, $constructArgs)) {
				throw new \InvalidArgumentException("Invalid construct parameter: $key");
			}

			$constructArgs[$key] = $value;
		}

		/**
		 * @psalm-suppress InvalidArgument
		 */
		return new OpenProjectAPIController('integration_openproject', ...array_values($constructArgs));
	}

	/**
	 * @return void
	 */
	public function testGetNotifications(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getNotifications'])
			->getMock();
		$service->expects($this->once())
			->method('getNotifications')
			->willReturn(['some' => 'data']);

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getNotifications();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['some' => 'data'], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetNotificationsNoAccessToken() {
		$authToken = '';
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock($authToken),
		]);
		$response = $controller->getNotifications();
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}


	/**
	 * @return void
	 */
	public function testGetNotificationsBadOPInstanceUrl() {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(null, 'http:openproject.org'),
		]);
		$response = $controller->getNotifications();
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetNotificationsErrorResponse() {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getNotifications')
			->willReturn(['error' => 'something went wrong']);

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getNotifications();
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['error' => 'something went wrong'], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectAvatar() {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service->expects($this->once())
			->method('getOpenProjectAvatar')
			->with(
				'id', 'name'
			)
			->willReturn(['avatar' => 'some image data', 'type' => 'image/png']);
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
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
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service->expects($this->once())
			->method('getOpenProjectAvatar')
			->with(
				'id', 'name'
			)
			->willReturn(['avatar' => 'some image data']);
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
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
			['test', 9090,  [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]]],
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
	public function testGetSearchedWorkPackages(?string $searchQuery, ?int $fileId, array $expectedResponse):void {
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

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getSearchedWorkPackages($searchQuery, $fileId);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($expectedResponse, $response->getData());
	}


	/**
	 * @return void
	 */
	public function testGetSearchedWorkPackagesNoAccessToken(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(''),
		]);
		$response = $controller->getSearchedWorkPackages('test');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetSearchedWorkPackagesBadOPInstanceUrl(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(null, 'http:openproject'),
		]);
		$response = $controller->getSearchedWorkPackages('test');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetSearchedWorkPackagesErrorResponse(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service->method('searchWorkPackage')->willReturn(['error' => 'something went wrong']);
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getSearchedWorkPackages('test');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'something went wrong'], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageStatus(): void {
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

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
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
	public function testGetOpenProjectWorkPackageStatusNoToken(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(''),
		]);
		$response = $controller->getOpenProjectWorkPackageStatus('7');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageStatusWithErrorResponse(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getOpenProjectWorkPackageStatus')
			->willReturn(['error' => 'something went wrong']);

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getOpenProjectWorkPackageStatus('7');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['error' => 'something went wrong'], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageStatusBadOPInstanceUrl(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(null, 'http:openproject'),
		]);
		$response = $controller->getOpenProjectWorkPackageStatus('7');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageType(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getOpenProjectWorkPackageType'])
			->getMock();
		$service->expects($this->once())
			->method('getOpenProjectWorkPackageType')
			->willReturn(["_type" => "Type", "id" => 3, "name" => "Phase",
				"color" => "#CC5DE8", "position" => 4, "isDefault" => true, "isMilestone" => false, "createdAt" => "2022-01-12T08:53:15Z", "updatedAt" => "2022-01-12T08:53:34Z"]);

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getOpenProjectWorkPackageType('3');
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(["_type" => "Type", "id" => 3, "name" => "Phase",
			"color" => "#CC5DE8", "position" => 4, "isDefault" => true, "isMilestone" => false, "createdAt" => "2022-01-12T08:53:15Z", "updatedAt" => "2022-01-12T08:53:34Z"], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageTypeNoAccessToken(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(''),
		]);
		$response = $controller->getOpenProjectWorkPackageType('3');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageTypeErrorResponse(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getOpenProjectWorkPackageType')
			->willReturn(['error' => 'something went wrong']);

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getOpenProjectWorkPackageType('3');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['error' => 'something went wrong'], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageTypeBadOPInstanceUrl(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(null, 'http:openproject'),
		]);
		$response = $controller->getOpenProjectWorkPackageType('3');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinks(): void {
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

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
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
	public function testGetWorkPackageFileLinksNoAccessToken(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(''),
		]);
		$response = $controller->getWorkPackageFileLinks(7);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinksNotFound(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getWorkPackageFileLinks')
			->willThrowException(new NotFoundException('work package not found'));
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getWorkPackageFileLinks(7);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame('work package not found', $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinksInternalServerError(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getWorkPackageFileLinks')
			->willThrowException(new Exception('something went wrong'));
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getWorkPackageFileLinks(7);
		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame('something went wrong', $response->getData());
	}

	/**
	 * @return void
	 */
	public function testDeleteFileLink(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['deleteFileLink'])
			->getMock();
		$service->expects($this->once())
			->method('deleteFileLink')
			->willReturn(['success' => true]);

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->deleteFileLink(7);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['success' => true], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testDeleteFileLinkNoAccessToken(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(''),
		]);
		$response = $controller->deleteFileLink(7);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testDeleteFileLinkFileNotFound(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('deleteFileLink')
			->willThrowException(new NotFoundException('file not found'));
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->deleteFileLink(7);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame('file not found', $response->getData());
	}

	/**
	 * @return void
	 */
	public function testDeleteFileLinkInternalServerError(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('deleteFileLink')
			->willThrowException(new Exception('something went wrong'));
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->deleteFileLink(7);
		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame('something went wrong', $response->getData());
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToSingleFile() {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['linkWorkPackageToFile'])
			->getMock();
		$service->expects($this->once())
			->method('linkWorkPackageToFile')
			->with($this->fileInformationToLinkToWorkPackage, 'test')
			->willReturn([2]);

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->linkWorkPackageToFile($this->fileInformationToLinkToWorkPackage);
		$this->assertSame([2], $response->getData());
	}


	/**
	 * @return void
	 */
	public function testLinkWorkPackageToMultipleFiles() {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['linkWorkPackageToFile'])
			->getMock();
		$service->expects($this->once())
			->method('linkWorkPackageToFile')
			->with([
				"workpackageId" => 123,
				"fileinfo" => [
					[
						"id" => 5503,
						"name" => "logo.png"
					],
					[
						"id" => 5504,
						"name" => "pogo.png"
					],
					[
						"id" => 5505,
						"name" => "dogo.png"
					]
				]
			], 'test')
			->willReturn([5, 6, 7]);

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->linkWorkPackageToFile([
			"workpackageId" => 123,
			"fileinfo" => [
				[
					"id" => 5503,
					"name" => "logo.png"
				],
				[
					"id" => 5504,
					"name" => "pogo.png"
				],
				[
					"id" => 5505,
					"name" => "dogo.png"
				]
			]
		]);
		$this->assertSame([5, 6, 7], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileNoAccessToken() {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(''),
		]);
		$response = $controller->linkWorkPackageToFile($this->fileInformationToLinkToWorkPackage);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileNotEnoughPermissions(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('linkWorkPackageToFile')
			->willThrowException(new NotPermittedException());
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->linkWorkPackageToFile($this->fileInformationToLinkToWorkPackage);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileOpenProjectErrorException(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('linkWorkPackageToFile')
			->willThrowException(new OpenprojectErrorException('Error while linking file to a work package', 400));
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->linkWorkPackageToFile($this->fileInformationToLinkToWorkPackage);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('Error while linking file to a work package', $response->getData());
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileOpenprojectResponseException(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('linkWorkPackageToFile')
			->willThrowException(new OpenprojectResponseException('Malformed response'));
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->linkWorkPackageToFile($this->fileInformationToLinkToWorkPackage);
		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame('Malformed response', $response->getData());
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileForInvalidKeyError(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('linkWorkPackageToFile')
			->willThrowException(new InvalidArgumentException('invalid key'));
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->linkWorkPackageToFile($this->fileInformationToLinkToWorkPackage);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('invalid key', $response->getData());
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileForInvalidDataError(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('linkWorkPackageToFile')
			->willThrowException(new InvalidArgumentException('invalid data'));
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->linkWorkPackageToFile($this->fileInformationToLinkToWorkPackage);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('invalid data', $response->getData());
	}

	/**
	 * @return array<mixed>
	 */
	public function exceptionDataProvider(): array {
		return [
			[
				new OpenprojectErrorException('Precondition failed', 412),
				412,
				'Precondition failed'
			],
			[
				new OpenprojectResponseException('Malformed response'),
				500,
				'Malformed response'
			],
			[
				new Exception("Internal server error"),
				500,
				'Internal server error'
			]
		];
	}

	/**
	 * @return void
	 */
	public function testGetAvailableOpenProjectProjects(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getAvailableOpenProjectProjects'])
			->getMock();
		$service->expects($this->once())
			->method('getAvailableOpenProjectProjects')
			->with('test')
			->willReturn([
				6 => [
					"_type" => "Project",
					"id" => 6,
					"name" => "[dev] Custom fields",
					"_links" => [
						"parent" => ["href" => "https://openproject.local/projects/6"]
					]
				]
			]);
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getAvailableOpenProjectProjects();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			6 => [
				"_type" => "Project",
				"id" => 6,
				"name" => "[dev] Custom fields",
				"_links" => [
					"parent" => ["href" => "https://openproject.local/projects/6"]
				]
			]
		], $response->getData());
	}

	/**
	 * @param \Exception $exception
	 * @param int $expectedHttpStatusCode
	 * @param string $expectedError
	 * @dataProvider exceptionDataProvider
	 *
	 *@return void
	 */
	public function testGetAvailableOpenProjectProjectsException(
		Exception $exception,
		int $expectedHttpStatusCode,
		string $expectedError
	):void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getAvailableOpenProjectProjects')
			->willThrowException($exception);
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getAvailableOpenProjectProjects();
		$this->assertSame($expectedHttpStatusCode, $response->getStatus());
		$this->assertSame($expectedError, $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageForm(): void {
		$body = [
			"_links" => [
				"type" => [
					"href" => "/api/v3/types/2",
					"title" => "Milestone"
				],
				"status" => [
					"href" => "/api/v3/statuses/1",
					"title" => "New"
				],
				"subject" => "This is a new workpackage",
				"description" => [
					"format" => "markdown",
					"raw" => "this is a default description for milestone type",
					"html" => null
				]
			]
		];
		$result = [
			"payload" => [
				"subject" => "This is a new workpackage",
				"description" => [
					"format" => "markdown",
					"raw" => "this is a default description for task type",
					"html" => "<p class=\"op-uc-p\">this is a default description for task type</p>"
				],
				"_links" => [
					"type" => [
						"href" => "/api/v3/types/2",
						"title" => "Milestone"
					],
					"status" => [
						"href" => "/api/v3/statuses/1",
						"title" => "New"
					],
					"project" => [
						"href" => "/api/v3/projects/6",
						"title" => "Demo project"
					],
					"assignee" => [
						"href" => null
					],
				]
			],
			"schema" => [],
			"validationErrors" => []
		];
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getOpenProjectWorkPackageForm'])
			->getMock();
		$service->expects($this->once())
			->method('getOpenProjectWorkPackageForm')
			->with('test', 6, $body)
			->willReturn($result);
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getOpenProjectWorkPackageForm('6', $body);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($result, $response->getData());
	}

	/**
	 * @param \Exception $exception
	 * @param int $expectedHttpStatusCode
	 * @param string $expectedError
	 * @dataProvider exceptionDataProvider
	 *
	 *@return void
	 */
	public function testGetOpenProjectWorkPackageFormException(
		Exception $exception,
		int $expectedHttpStatusCode,
		string $expectedError): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getOpenProjectWorkPackageForm')
			->willThrowException($exception);
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getOpenProjectWorkPackageForm('6', ["_links" => [
			"type" => [
				"href" => "/api/v3/types/2",
				"title" => "Milestone"
			]]]);
		$this->assertSame($expectedHttpStatusCode, $response->getStatus());
		$this->assertSame($expectedError, $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageFormEmptyBody(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getOpenProjectWorkPackageForm')
			->willReturn([]);
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getOpenProjectWorkPackageForm('6', []);
		$this->assertSame(HTTP::STATUS_OK, $response->getStatus());
	}

	/**
	 * @return void
	 */
	public function testGetAvailableAssigneesOfAProject(): void {
		$result = [ 0 => [
			"_type" => "User",
			"id" => 10,
			"name" => "openproject admin",
			"_links" => [
				"self" => [
					"href" => "/api/v3/users/10",
					"title" => "openproject admin"
				]
			]
		],
			1 => [
				"_type" => "User",
				"id" => 11,
				"name" => "openproject member",
				"_links" => [
					"self" => [
						"href" => "/api/v3/users/11",
						"title" => "openproject member"
					]
				]
			]
		];
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getAvailableAssigneesOfAProject'])
			->getMock();
		$service->expects($this->once())
			->method('getAvailableAssigneesOfAProject')
			->with('test', 6)
			->willReturn($result);
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getAvailableAssigneesOfAProject('6');
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(
			$result, $response->getData());
	}

	/**
	 * @param \Exception $exception
	 * @param int $expectedHttpStatusCode
	 * @param string $expectedError
	 * @dataProvider exceptionDataProvider
	 *
	 *@return void
	 */
	public function testGetAvailableAssigneesOfAProjectException(
		Exception $exception,
		int $expectedHttpStatusCode,
		string $expectedError
	):void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('getAvailableAssigneesOfAProject')
			->willThrowException($exception);
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->getAvailableAssigneesOfAProject('6');
		$this->assertSame($expectedHttpStatusCode, $response->getStatus());
		$this->assertSame($expectedError, $response->getData());
	}

	/**
	 * @return void
	 */
	public function testCreateWorkpackages(): void {
		$body = [
			"_links" => [
				"type" => [
					"href" => "/api/v3/types/2",
					"title" => "Milestone"
				],
				"status" => [
					"href" => "/api/v3/statuses/1",
					"title" => "New"
				],
				"project" => [
					"href" => "/api/v3/projects/6",
					"title" => "Demo project"
				],
				"assignee" => [
					"href" => "/api/v3/users/4",
					"title" => "OpenProject Admin"
				],
			],
			"subject" => "This is a new work package",
			"description" => [
				"format" => "markdown",
				"raw" => "this is a default description for milestone type",
				"html" => null
			],
		];
		$response = [
			"_embedded" => [
				"type" => [
					"_type" => "Type",
					"id" => 2,
					"name" => "Milestone",
					"color" => "#FF922B",
				],
				"status" => [
					"_type" => "Status",
					"id" => 1,
					"name" => "New",
					"color" => "#DEE2E6",
				]
			],
			"_type" => "WorkPackage",
			"id" => 12,
			"subject" => "This is a new work package",
			"description" => [
				"format" => "markdown",
				"raw" => "this is a default description for milestone type",
				"html" => "<p class=\"op-uc-p\">this is a default description for milestone type</p>"
			],
			"_links" => [
				"self" => [
					"href" => "/api/v3/work_packages/12",
					"title" => "This is a new work package"
				]
			]
		];
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['createWorkPackage'])
			->getMock();
		$service->expects($this->once())
			->method('createWorkPackage')
			->with('test', $body)
			->willReturn($response);

		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$result = $controller->createWorkPackage($body);
		$this->assertSame(Http::STATUS_CREATED, $result->getStatus());
		$this->assertSame(
			$response, $result->getData());
	}

	/**
	 * @param \Exception $exception
	 * @param int $expectedHttpStatusCode
	 * @param string $expectedError
	 * @dataProvider exceptionDataProvider
	 *
	 *@return void
	 */
	public function testCreateWorkpackagesException(
		Exception $exception,
		int $expectedHttpStatusCode,
		string $expectedError
	):void {
		$body = [
			"_links" => [
				"type" => [
					"href" => "/api/v3/types/2",
					"title" => "Milestone"
				],
				"status" => [
					"href" => "/api/v3/statuses/1",
					"title" => "New"
				],
				"project" => [
					"href" => "/api/v3/projects/6",
					"title" => "Demo project"
				],
				"assignee" => [
					"href" => "/api/v3/users/4",
					"title" => "OpenProject Admin"
				],
			],
			"subject" => "This is a new work package",
			"description" => [
				"format" => "markdown",
				"raw" => "this is a default description for milestone type",
				"html" => null
			],
		];
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('createWorkPackage')
			->willThrowException($exception);
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->createWorkPackage($body);
		$this->assertSame($expectedHttpStatusCode, $response->getStatus());
		$this->assertSame($expectedError, $response->getData());
	}

	/**
	 * @return void
	 */
	public function testCreateWorkpackagesEmptyBody(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$controller = $this->getOpenProjectAPIControllerMock([
			'openProjectAPIService' => $service,
			'config' => $this->getConfigMock(),
		]);
		$response = $controller->createWorkPackage([]);
		$this->assertSame(HTTP::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('Body cannot be empty', $response->getData());
	}
}
