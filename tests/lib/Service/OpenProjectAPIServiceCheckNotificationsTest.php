<?php
/**
 * Nextcloud - OpenProject
 *
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Artur Neumann 2022
 */

namespace OCA\OpenProject\Service;

use OCA\Notifications\Handler;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;

class OpenProjectAPIServiceCheckNotificationsTest extends TestCase {
	/** @var IConfig $configMock */
	private $configMock;

	/**
	 * @return void
	 * @before
	 */
	public function setUpMocks(): void {
		$this->configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$this->configMock
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

		$this->configMock
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
	}

	/**
	 * @param string|null $oPNotificationAPIResponse
	 * @return IClientService
	 */
	private function getClientServiceMock($oPNotificationAPIResponse = null): IClientService {
		$response = $this->getMockBuilder(IResponse::class)->getMock();
		if ($oPNotificationAPIResponse === null) {
			$oPNotificationAPIResponse = '{
			"_type": "Collection",
			"_embedded": {
			"elements":
		';
			$oPNotificationAPIResponse .= file_get_contents(
				__DIR__ . '/../../jest/fixtures/notificationsResponse.json'
			);
			$oPNotificationAPIResponse .= '}}';
		}
		$response->method('getBody')->willReturn($oPNotificationAPIResponse);
		$ocClient = $this->getMockBuilder('\OCP\Http\Client\IClient')->getMock();
		$ocClient->method('get')->willReturn($response);
		$clientService = $this->getMockBuilder('\OCP\Http\Client\IClientService')->getMock();
		$clientService->method('newClient')->willReturn($ocClient);
		return $clientService;
	}

	/**
	 * @param IManager $notificationManagerMock
	 * @param IClientService $clientServiceMock
	 * @param Handler $handlerMock
	 * @return OpenProjectAPIService
	 */
	private function getService($notificationManagerMock, $clientServiceMock, $handlerMock): OpenProjectAPIService {
		return new OpenProjectAPIService(
			'integration_openproject',
			\OC::$server->get(IUserManager::class),
			$this->createMock(\OCP\IAvatarManager::class),
			$this->createMock(\Psr\Log\LoggerInterface::class),
			$this->createMock(\OCP\IL10N::class),
			$this->configMock,
			$notificationManagerMock,
			$clientServiceMock,
			$this->createMock(\OCP\Files\IRootFolder::class),
			$this->createMock(\OCP\IURLGenerator::class),
			$this->createMock(\OCP\ICacheFactory::class),
			$handlerMock,
		);
	}

	public function testCheckNotifications(): void {
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

		$service = $this->getService(
			$notificationManagerMock,
			$this->getClientServiceMock(),
			$this->createMock(Handler::class)
		);
		$service->checkNotifications();
	}
	public function testCheckNotificationsAfterAllNotificationsAreMarkedAsRead(): void {
		$oPNotificationAPIResponse = '{
			"_type": "Collection",
			"_embedded": {
			"elements": []
			}
		}
		';
		$notificationManagerMock = $this->getMockBuilder(IManager::class)->getMock();
		$notificationMock = $this->getMockBuilder(INotification::class)
			->getMock();

		$notificationMock
			->expects($this->never())
			->method('setSubject');

		$notificationManagerMock
			->expects($this->exactly(1)) //for marking as read
			->method('createNotification')
			->willReturn($notificationMock);

		$notificationManagerMock
			->expects($this->exactly(2))
			->method('markProcessed');

		$currentNotificationMock0 = $this->getMockBuilder(INotification::class)->getMock();
		$currentNotificationMock0
			->method('getSubjectParameters')
			->willReturn(['wpId' => 34, 'updatedAt' => '2022-11-08T06:34:40Z']);
		$currentNotificationMock1 = $this->getMockBuilder(INotification::class)->getMock();
		$currentNotificationMock1
			->method('getSubjectParameters')
			->willReturn(['wpId' => 16, 'updatedAt' => '2022-11-07T06:34:40Z']);

		$handlerMock = $this->getMockBuilder(Handler::class)->disableOriginalConstructor()->getMock();
		$handlerMock->method('get')
			->willReturn(
				[12 => $currentNotificationMock0, 13 => $currentNotificationMock1]
			);
		$service = $this->getService(
			$notificationManagerMock,
			$this->getClientServiceMock($oPNotificationAPIResponse),
			$handlerMock
		);
		$service->checkNotifications();
	}
	public function testCheckNotificationsAfterAllNotificationsOfOneWPAreMarkedAsRead(): void {
		$notificationManagerMock = $this->getMockBuilder(IManager::class)->getMock();
		$notificationMock = $this->getMockBuilder(INotification::class)
			->getMock();

		$notificationMock
			->expects($this->exactly(3)) // new notifications should be set
			->method('setSubject');

		$notificationManagerMock
			->expects($this->exactly(4)) //once for marking as read and once for every notification
			->method('createNotification')
			->willReturn($notificationMock);

		$notificationManagerMock
			->expects($this->exactly(3)) // for new notifications
			->method('notify');

		$notificationManagerMock
			->expects($this->exactly(2))
			->method('markProcessed'); // for current ones that do not exist in the new response

		// current notifications of WP that do not exist in the new response
		$currentNotificationMock0 = $this->getMockBuilder(INotification::class)->getMock();
		$currentNotificationMock0
			->method('getSubjectParameters')
			->willReturn(['wpId' => 34, 'updatedAt' => '2022-11-08T06:34:40Z']);
		$currentNotificationMock1 = $this->getMockBuilder(INotification::class)->getMock();
		$currentNotificationMock1
			->method('getSubjectParameters')
			->willReturn(['wpId' => 16, 'updatedAt' => '2022-11-07T06:34:40Z']);

		$handlerMock = $this->getMockBuilder(Handler::class)->disableOriginalConstructor()->getMock();
		$handlerMock->method('get')
			->willReturn(
				[12 => $currentNotificationMock0, 13 => $currentNotificationMock1]
			);
		$service = $this->getService(
			$notificationManagerMock,
			$this->getClientServiceMock(),
			$handlerMock
		);
		$service->checkNotifications();
	}
	public function testCheckNotificationsAfterOneWPReceivedANewNotification(): void {
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
			->expects($this->exactly(3)) // for new notifications
			->method('notify');

		$notificationManagerMock
			->expects($this->exactly(1))
			->method('markProcessed'); // for the notification that needs to be upldated

		// this notification is also part of the response, but the response also
		// contains an other newer notification of that WP
		$currentNotificationMock0 = $this->getMockBuilder(INotification::class)->getMock();
		$currentNotificationMock0
			->method('getSubjectParameters')
			->willReturn(['wpId' => 36, 'updatedAt' => '2022-08-17T10:13:25Z']);

		$handlerMock = $this->getMockBuilder(Handler::class)->disableOriginalConstructor()->getMock();
		$handlerMock->method('get')
			->willReturn([12 => $currentNotificationMock0]);
		$service = $this->getService(
			$notificationManagerMock,
			$this->getClientServiceMock(),
			$handlerMock
		);
		$service->checkNotifications();
	}
}
