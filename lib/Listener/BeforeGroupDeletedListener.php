<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Swikriti Tripathi <swikriti@jankaritech.com>
 *
 * @author Swikriti Tripathi <swikriti@jankaritech.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\OpenProject\Listener;

use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCA\OpenProject\AppInfo\Application;

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

		$group = $event->getGroup();
		if ($group->getGID() === Application::OPEN_PROJECT_ENTITIES_NAME) {
			$this->logger->info('Group openproject cannot be deleted');
			throw new OCSBadRequestException();
		}
	}
}
