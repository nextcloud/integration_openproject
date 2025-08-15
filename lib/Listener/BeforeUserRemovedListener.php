<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\BeforeUserRemovedEvent;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class BeforeUserRemovedListener implements IEventListener {
	/**
	 * @param LoggerInterface $logger
	 * @param IUserSession $userSession
	 * @param IGroupManager $groupManager
	 */
	public function __construct(
		private LoggerInterface $logger,
		private IUserSession $userSession,
		private IGroupManager $groupManager
	) {
	}

	/**
	 * @param Event $event
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function handle(Event $event): void {
		if (!($event instanceof BeforeUserRemovedEvent)) {
			return;
		}
		$userToRemove = $event->getUser()->getUID();
		$fromGroup = $event->getGroup()->getGID();
		$this->logger->error('User: ' . $userToRemove);
		$this->logger->error('Group: ' . $fromGroup);

		// TODO: check user is OpenProject and the target group is OpenProject
		$adminUser = $this->userSession->getUser();
		if ($adminUser === null) {
			return;
		}
		$this->logger->error('By User: ' . $adminUser->getUID());
		if (!$this->groupManager->isInGroup($userToRemove, Application::OPENPROJECT_ALL_GROUP_NAME)) {
			$this->logger->error('User not found in the "' . Application::OPENPROJECT_ALL_GROUP_NAME . '" group.');
			$this->logger->error("Adding user '$userToRemove' to '" . Application::OPENPROJECT_ALL_GROUP_NAME . "' group...");
			$allGroup = $this->groupManager->get(Application::OPENPROJECT_ALL_GROUP_NAME);
			if ($allGroup === null) {
				throw new \Exception('Group "' . Application::OPENPROJECT_ALL_GROUP_NAME . '" not found');
			}
			$allGroup->addUser($event->getUser());
		} else {
			$this->logger->error('User is already in the "' . Application::OPENPROJECT_ALL_GROUP_NAME . '" group');
		}
		if ($this->groupManager->isInGroup($userToRemove, Application::OPEN_PROJECT_ENTITIES_NAME)) {
			$this->logger->error('User found in "' . Application::OPEN_PROJECT_ENTITIES_NAME . '" group. Removing...');
		}
	}
}
