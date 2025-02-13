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
use OCP\User\Events\BeforeUserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class BeforeUserDeletedListener implements IEventListener {

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}

	/**
	 * @throws \Exception
	 */
	public function handle(Event $event): void {
		if (!($event instanceof BeforeUserDeletedEvent)) {
			return;
		}
		$user = $event->getUser();
		if ($user->getUID() === Application::OPEN_PROJECT_ENTITIES_NAME) {
			$this->logger->error('User "OpenProject" is needed to be protected by the app "OpenProject Integration", thus cannot be deleted. Please check the documentation "https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#troubleshooting" for further information.');
			throw new OCSBadRequestException('<p>&nbsp;User "OpenProject" is needed to be protected by the app "OpenProject Integration", thus cannot be deleted.
			Please check the <a style="color:var(--color-primary-default)" href="https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#troubleshooting"
			target="_blank"><u>troubleshooting guide</u></a> for further information.</p>');
		}
	}
}
