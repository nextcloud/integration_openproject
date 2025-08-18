<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCP\AppFramework\OCS\OCSBadRequestException;
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

		$fromGroup = $event->getGroup()->getGID();
		$userToRemove = $event->getUser()->getUID();
		$adminUser = $this->userSession->getUser();
		if ($adminUser === null) {
			return;
		}

		// Only handle OpenProject group
		if ($fromGroup !== Application::OPEN_PROJECT_ENTITIES_NAME) {
			return;
		}

		// Prevent removing users from OpenProject group by other admins
		if ($adminUser->getUID() !== Application::OPEN_PROJECT_ENTITIES_NAME) {
			$errorMessage = "Cannot remove user from group '$fromGroup'. " .
				"This action can only be performed by '" . Application::OPEN_PROJECT_ENTITIES_NAME . "' admin user.";
			$this->logger->error($errorMessage);
			throw new OCSBadRequestException($errorMessage);
		}

		if (!$this->groupManager->isInGroup($userToRemove, Application::OPENPROJECT_ALL_GROUP_NAME)) {
			$this->logger->debug('User not found in "' . Application::OPENPROJECT_ALL_GROUP_NAME . '" group.');
			$allGroup = $this->groupManager->get(Application::OPENPROJECT_ALL_GROUP_NAME);

			if ($allGroup === null) {
				$errorMessage = 'Group "' . Application::OPENPROJECT_ALL_GROUP_NAME . '" not found.' .
					' This group is required before removing users from "' . Application::OPEN_PROJECT_ENTITIES_NAME . '" group.';
				$this->logger->error($errorMessage);
				throw new OCSBadRequestException($errorMessage);
			}

			$allGroup->addUser($event->getUser());
			$this->logger->debug("User '$userToRemove' added to '" . Application::OPENPROJECT_ALL_GROUP_NAME . "' group.");
		} else {
			$this->logger->debug('User already exists in "' . Application::OPENPROJECT_ALL_GROUP_NAME . '" group');
		}
	}
}
