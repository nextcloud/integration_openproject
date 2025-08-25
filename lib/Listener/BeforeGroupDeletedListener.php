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
use OCP\Group\Events\BeforeGroupDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class BeforeGroupDeletedListener implements IEventListener {

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
		if (!($event instanceof BeforeGroupDeletedEvent)) {
			return;
		}

		$group = $event->getGroup()->getGID();
		if ($group === Application::OPEN_PROJECT_ENTITIES_NAME || $group === Application::OPENPROJECT_ALL_GROUP_NAME) {
			$troubleshootLink = 'https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#troubleshooting';
			$this->logger->error(
				"Group '$group' is needed to be protected by the app 'OpenProject Integration', thus cannot be deleted." .
				" Please check the documentation '$troubleshootLink' for further information."
			);
			throw new OCSBadRequestException(
				"<p>&nbsp;Group '$group' is needed to be protected by the app 'OpenProject Integration', thus cannot be deleted." .
				" Please check the " .
				"<a href='$troubleshootLink' target='_blank'><u>troubleshooting guide</u></a>" .
				" for further information.</p>"
			);
		}
	}
}
