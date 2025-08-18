<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\EventDispatcher\Event;
use OCP\Group\Events\BeforeUserRemovedEvent;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BeforeUserRemovedListenerTest extends TestCase {
	private MockObject|IGroup $groupMock;
	private MockObject|IUser $userMock;
	private MockObject|LoggerInterface $logger;
	private MockObject|IGroupManager $groupManager;

	protected function setUp(): void {
		parent::setUp();
		// mocks
		$this->groupMock = $this->createMock(IGroup::class);
		$this->userMock = $this->createMock(IUser::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
	}

	/**
	 * @return array<mixed>
	 */
	public function dataTestHandleEarlyReturn(): array {
		return [
			[
				'eventClass' => Event::class,
				'group' => Application::OPEN_PROJECT_ENTITIES_NAME,
			],
			[
				'eventClass' => BeforeUserRemovedEvent::class,
				'group' => 'testgroup',
			],
		];
	}

	/**
	 * @dataProvider dataTestHandleEarlyReturn
	 *
	 * @param string $eventClass
	 * @param string $group
	 *
	 * @return void
	 */
	public function testHandleEarlyReturn(string $eventClass, string $group) {
		$event = $this->createMock($eventClass);
		if ($eventClass === BeforeUserRemovedEvent::class) {
			$this->groupMock->expects($this->once())->method('getGID')->willReturn($group);
			$this->userMock->expects($this->once())->method('getUID')->willReturn('testUser');
			$event->expects($this->once())->method('getGroup')->willReturn($this->groupMock);
			$event->expects($this->once())->method('getUser')->willReturn($this->userMock);
		}
		$this->logger->expects($this->never())->method('error');
		$this->logger->expects($this->never())->method('debug');

		$listener = new BeforeUserRemovedListener(
			$this->logger,
			$this->groupManager,
		);
		$listener->handle($event);
	}

	/**
	 * @return void
	 */
	public function testHandleError() {
		$userToRemove = 'testUser';
		$errorMessage = 'This group is required before removing users from';

		$event = $this->createMock(BeforeUserRemovedEvent::class);
		$this->groupMock->expects($this->once())
			->method('getGID')
			->willReturn(Application::OPEN_PROJECT_ENTITIES_NAME);
		$this->userMock->expects($this->once())->method('getUID')->willReturn($userToRemove);
		$event->expects($this->once())->method('getGroup')->willReturn($this->groupMock);
		$event->expects($this->once())->method('getUser')->willReturn($this->userMock);

		$this->groupManager->expects($this->once())
			->method('isInGroup')
			->with($userToRemove, Application::OPENPROJECT_ALL_GROUP_NAME)
			->willReturn(false);
		$this->groupManager->expects($this->once())
			->method('get')
			->with(Application::OPENPROJECT_ALL_GROUP_NAME)
			->willReturn(null);
		$this->logger->expects($this->once())
			->method('debug')
			->with($this->stringContains('User not found in'));
		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains($errorMessage));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessageMatches('/(.*)' . preg_quote($errorMessage, '/') . '(.*)/');

		$listener = new BeforeUserRemovedListener(
			$this->logger,
			$this->groupManager,
		);
		$listener->handle($event);
	}

	/**
	 * @return array<mixed>
	 */
	public function dataTestHandleSuccess(): array {
		return [
			[
				'alreadyInGroup' => false,
			],
			[
				'alreadyInGroup' => true,
			],
		];
	}

	/**
	 * @dataProvider dataTestHandleSuccess
	 *
	 * @param bool $alreadyInGroup
	 *
	 * @return void
	 */
	public function testHandleSuccess(bool $alreadyInGroup) {
		$userToRemove = 'testUser';
		$event = $this->createMock(BeforeUserRemovedEvent::class);
		$this->groupMock->expects($this->once())
			->method('getGID')
			->willReturn(Application::OPEN_PROJECT_ENTITIES_NAME);
		$this->userMock->expects($this->once())->method('getUID')->willReturn($userToRemove);
		$event->expects($this->once())->method('getGroup')->willReturn($this->groupMock);
		$this->groupManager->expects($this->once())
			->method('isInGroup')
			->with($userToRemove, Application::OPENPROJECT_ALL_GROUP_NAME)
			->willReturn($alreadyInGroup);

		$opAllGroup = $this->createMock(IGroup::class);
		if (!$alreadyInGroup) {
			$event->expects($this->exactly(2))
				->method('getUser')
				->willReturn($this->userMock);
			$opAllGroup->expects($this->once())
				->method('addUser')
				->with($this->userMock);
			$this->groupManager->expects($this->once())
				->method('get')
				->with(Application::OPENPROJECT_ALL_GROUP_NAME)
				->willReturn($opAllGroup);
			$this->logger->expects($this->exactly(2))
				->method('debug');
		} else {
			$event->expects($this->once())->method('getUser')->willReturn($this->userMock);
			$this->groupManager->expects($this->never())
				->method('get');
			$opAllGroup->expects($this->never())
				->method('addUser');
			$this->logger->expects($this->once())
				->method('debug')
				->with($this->stringContains('User already exists in'));
		}

		$this->logger->expects($this->never())
			->method('error');

		$listener = new BeforeUserRemovedListener(
			$this->logger,
			$this->groupManager,
		);
		$listener->handle($event);
	}
}
