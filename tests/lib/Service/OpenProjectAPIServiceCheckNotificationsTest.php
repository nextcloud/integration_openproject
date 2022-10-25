<?php
/**
 * Nextcloud - OpenProject
 *
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Artur Neumann 2022
 */

namespace OCA\OpenProject\Service;

use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;

class OpenProjectAPIServiceCheckNotificationsTest extends TestCase {
	public function testCheckNotifications(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getUserValue')
			->withConsecutive(
				[$this->anything(), 'integration_openproject', 'token'],
				[$this->anything(), 'integration_openproject', 'notification_enabled'],
				[$this->anything(), 'integration_openproject', 'token'],
				[$this->anything(), 'integration_openproject', 'refresh_token'],
			)
			->willReturnOnConsecutiveCalls(
				'123456',
				'1',
				'123456',
				'refresh-token',
			);

		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'default_enable_notifications','0'],
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url'],
			)->willReturnOnConsecutiveCalls(
				'0',
				'clientID',
				'SECRET',
				'https://openproject',
			);

		$response = $this->getMockBuilder(IResponse::class)->getMock();
		$oPNotificationAPIResponse = '{
			"_type": "Collection",
			"_embedded": {
			"elements":
		';
		$oPNotificationAPIResponse .= file_get_contents(
			__DIR__ . '/../../jest/fixtures/notificationsResponse.json'
		);
		$oPNotificationAPIResponse .= '}}';
		$response->method('getBody')->willReturn($oPNotificationAPIResponse);
		$ocClient = $this->getMockBuilder('\OCP\Http\Client\IClient')->getMock();
		$ocClient->method('get')->willReturn($response);
		$clientService = $this->getMockBuilder('\OCP\Http\Client\IClientService')->getMock();
		$clientService->method('newClient')->willReturn($ocClient);

		$notificationManagerMock = $this->getMockBuilder(IManager::class)->getMock();


		$notificationMock = $this->getMockBuilder(INotification::class)
			->getMock();

		$notificationMock
			->expects($this->exactly(3))
			->method('setSubject')
			->withConsecutive(
				[
					'op_notification',
					[
						'wpId' => '36',
						'resourceTitle' => 'write a software',
						'projectTitle' => 'Dev-large',
						'count' => 2,
						'reasons' => ['assigned'],
						'actors' => ['Admin de DEV user'],
						'updatedAt' => '2022-08-17T10:28:12Z'
					]
				],
				[
					'op_notification',
					[
						'wpId' => '17',
						'resourceTitle' => 'Create wireframes for new landing page',
						'projectTitle' => 'Scrum project',
						'count' => 5,
						'reasons' => [0 => 'assigned', 3 => 'mentioned'],
						'actors' => [0 => 'Admin de DEV user', 2 => 'Artur Neumann'],
						'updatedAt' => '2022-08-17T10:27:41Z'
					]
				],
				[
					'op_notification',
					[
						'wpId' => '18',
						'resourceTitle' => 'Contact form',
						'projectTitle' => 'Scrum project',
						'count' => 1,
						'reasons' => ['mentioned'],
						'actors' => ['Artur Neumann'],
						'updatedAt' => '2022-08-09T08:00:08Z'
					]
				]
			);

		$notificationManagerMock
			->expects($this->exactly(4)) //once for marking as read and once for every notification
			->method('createNotification')
			->willReturn($notificationMock);

		$notificationManagerMock
			->expects($this->exactly(3))
			->method('notify');

		$service = new OpenProjectAPIService(
			'integration_openproject',
			\OC::$server->get(IUserManager::class),
			$this->createMock(\OCP\IAvatarManager::class),
			$this->createMock(\Psr\Log\LoggerInterface::class),
			$this->createMock(\OCP\IL10N::class),
			$configMock,
			$notificationManagerMock,
			$clientService,
			$this->createMock(\OCP\Files\IRootFolder::class),
			$this->createMock(\OCP\IURLGenerator::class),
			$this->createMock(\OCP\ICacheFactory::class),
			$this->createMock(\OCA\Notifications\Handler::class),
		);
		$service->checkNotifications();
	}
}
