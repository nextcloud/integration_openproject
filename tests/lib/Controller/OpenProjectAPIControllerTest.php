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
use PHPUnit\Framework\TestCase;

class OpenProjectAPIControllerTest extends TestCase {
	/** @var IConfig $configMock */
	private $configMock;

	/** @var IRequest $requestMock */
	private $requestMock;

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
			)->willReturnOnConsecutiveCalls('cliendID', 'clientSecret');
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
				['test','integration_openproject', 'token_type'],
				['test','integration_openproject', 'refresh_token'],
				['test','integration_openproject', 'url'],
			)->willReturnOnConsecutiveCalls($token, 'oauth', 'refreshToken', 'http://openproject.org');
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
			'integration_openproject', $this->requestMock, $this->configMock, $service, 'test'
		);
		$response = $controller->getNotifications();
		$this->assertSame(200, $response->getStatus());
		$this->assertSame(['some' => 'data'], $response->getData());
	}

	/**
	 * @return void
	 */
	public function testGetNotificationsNoAccessToken() {
		$this->getUserValueMock('');
		$service = $this->createMock(OpenProjectAPIService::class);
		$controller = new OpenProjectAPIController(
			'integration_openproject', $this->requestMock, $this->configMock, $service, 'test'
		);
		$response = $controller->getNotifications();
		$this->assertSame(400, $response->getStatus());
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
			'integration_openproject', $this->requestMock, $this->configMock, $service, 'test'
		);
		$response = $controller->getNotifications();
		$this->assertSame(401, $response->getStatus());
		$this->assertSame(['error' => 'something went wrong'], $response->getData());
	}
}
