<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\EventDispatcher\Event;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\IGroup;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BeforeGroupDeletedListenerTest extends TestCase {
	/**
	 * @return array<mixed>
	 */
	public function dataTestHandle(): array {
		return [
			[
				'eventClass' => BeforeGroupDeletedEvent::class,
				'group' => Application::OPEN_PROJECT_ENTITIES_NAME,
				'expectError' => true,
			],
			[
				'eventClass' => BeforeGroupDeletedEvent::class,
				'group' => Application::OPENPROJECT_ALL_GROUP_NAME,
				'expectError' => true,
			],
			[
				'eventClass' => BeforeGroupDeletedEvent::class,
				'group' => 'testgroup',
				'expectError' => false,
			],
			[
				'eventClass' => Event::class,
				'group' => Application::OPEN_PROJECT_ENTITIES_NAME,
				'expectError' => false,
			],
		];
	}

	/**
	 * @dataProvider dataTestHandle
	 *
	 * @param string $eventClass
	 * @param string $group
	 * @param bool $expectError
	 *
	 * @return void
	 */
	public function testHandle(string $eventClass, string $group, bool $expectError) {
		$groupMock = $this->createMock(IGroup::class);
		$event = $this->createMock($eventClass);
		$logger = $this->createMock(LoggerInterface::class);
		if ($expectError) {
			$groupMock->expects($this->once())->method('getGID')->willReturn($group);
			$logger->expects($this->once())->method('error');
			$event->expects($this->once())
				->method('getGroup')
				->willReturn($groupMock);
			$this->expectException(OCSBadRequestException::class);
			$this->expectExceptionMessageMatches('/Group (.*) is needed to be protected by the app(.*)/');
		} else {
			$logger->expects($this->never())->method('error');
		}
		$listener = new BeforeGroupDeletedListener($logger);
		$listener->handle($event);
	}
}
