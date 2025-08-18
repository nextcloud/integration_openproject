<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\BeforeUserRemovedEvent;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class BeforeUserRemovedListener implements IEventListener {
	/**
	 * @param LoggerInterface $logger
	 * @param IGroupManager $groupManager
	 */
	public function __construct(
		private LoggerInterface $logger,
		private IGroupManager $groupManager,
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

		// Only handle OpenProject group
		if ($fromGroup !== Application::OPEN_PROJECT_ENTITIES_NAME) {
			return;
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
