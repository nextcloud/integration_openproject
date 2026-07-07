<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\ISession;
use OCP\User\Events\UserLoggedInEvent;

/**
 * @template-implements IEventListener<UserLoggedInEvent>
 */
class UserLoggedInEventListener implements IEventListener {
	public function __construct(
		private ISession $session,
		private ITimeFactory $timeFactory,
	) {
	}

	/**
	 * @param UserLoggedInEvent $event
	 *
	 * @return void
	 */
	public function handle(Event $event): void {
		if (!($event instanceof UserLoggedInEvent)) {
			return;
		}

		if ($event->getUid() === Application::OPEN_PROJECT_ENTITIES_NAME && $event->isTokenLogin()) {
			// set the last-password-confirm session variable to allow OpenProject user
			// to perform actions using app-password without requiring password confirmation
			$this->session->set('last-password-confirm', $this->timeFactory->getTime());
		}
	}
}
