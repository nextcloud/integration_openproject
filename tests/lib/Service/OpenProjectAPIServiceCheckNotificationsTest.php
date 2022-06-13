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
	/**
	 * @var string
	 */
	private $oPNotificationAPIResponse = '
					{
					  "_type": "Collection",
					  "_embedded": {
						"elements": [
						  {
							"_type": "Notification",
							"id": 21,
							"reason": "commented",
							"createdAt": "2022-05-11T10:10:10Z"
						  },
						  {
							"_type": "Notification",
							"id": 22,
							"reason": "commented",
							"createdAt": "2022-05-12T10:10:10Z"
						  },
						  {
							"_type": "Notification",
							"id": 23,
							"reason": "commented",
							"createdAt": "2022-05-13T10:10:10Z"
						  },
						  {
							"_type": "Notification",
							"id": 25,
							"reason": "commented",
							"createdAt": "2022-05-14T10:10:10Z"
						  }
						]
					  }
					}';

	/**
	 * @return array<mixed>
	 */
	public function checkNotificationDataProvider(): array {
		return [
			[ '', 4, true ], // last_notification_check was not set yet
			[ '1652132430', 4, true ], // all notifications are were created after the last_notification_check
			[ '1652350210', 2, true ], // some notifications were created after and some befor the last_notification_check
			[ '1652605230', 0, false] // all notifications are older that last_notification_check
		];
	}
	/**
	 * @dataProvider checkNotificationDataProvider
	 */
	public function testCheckNotifications(
		string $lastNotificationCheck,
		int    $countOfReportedOPNotifications,
		bool   $nextCloudNotificationFired
	): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getUserValue')
			->withConsecutive(
				[$this->anything(), 'integration_openproject', 'token'],
				[$this->anything(), 'integration_openproject', 'notification_enabled'],
				[$this->anything(), 'integration_openproject', 'last_notification_check'],
				[$this->anything(), 'integration_openproject', 'token'],
				[$this->anything(), 'integration_openproject', 'refresh_token'],
			)
			->willReturnOnConsecutiveCalls(
				'123456',
				'1',
				$lastNotificationCheck,
				'123456',
				'refresh-token',
			);

		$configMock
			->expects($this->once())
			->method('setUserValue')
			->with(
				$this->anything(),
				'integration_openproject',
				'last_notification_check',
				$this->anything()
			);

		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'oauth_instance_url'],
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url'],
				['integration_openproject', 'client_id'],
				['integration_openproject', 'oauth_instance_url'],
			)->willReturnOnConsecutiveCalls(
				'https://openproject',
				'clientID',
				'SECRET',
				'https://openproject',
				'clientID',
				'https://openproject'
			);

		$response = $this->getMockBuilder(IResponse::class)->getMock();
		$response->method('getBody')->willReturn($this->oPNotificationAPIResponse);
		$ocClient = $this->getMockBuilder('\OCP\Http\Client\IClient')->getMock();
		$ocClient->method('get')->willReturn($response);
		$clientService = $this->getMockBuilder('\OCP\Http\Client\IClientService')->getMock();
		$clientService->method('newClient')->willReturn($ocClient);

		$notificationManagerMock = $this->getMockBuilder(IManager::class)->getMock();

		if ($nextCloudNotificationFired) {
			$notificationMock = $this->getMockBuilder(INotification::class)
				->getMock();

			$notificationMock
				->expects($this->once())
				->method('setSubject')
				->with(
					'new_open_tickets',
					[
						'nbNotifications' => $countOfReportedOPNotifications,
						'link' => 'https://openproject/notifications'
					]
				);

			$notificationManagerMock
				->expects($this->once())
				->method('createNotification')
				->willReturn($notificationMock);

			$notificationManagerMock
				->expects($this->once())
				->method('notify');
		} else {
			$notificationManagerMock
				->expects($this->never())
				->method('notify');
		}
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
		);

		$service->checkNotifications();
	}
}
