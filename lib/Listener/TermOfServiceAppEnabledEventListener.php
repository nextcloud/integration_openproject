<?php
/**
 * @copyright Copyright (c) 2024 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
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
 */

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\App\Events\AppEnableEvent;
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class TermOfServiceAppEnabledEventListener implements IEventListener {

	/**
	 * @var OpenProjectAPIService
	 */
	private OpenProjectAPIService $openprojectAPIService;

	public function __construct(
		OpenProjectAPIService $openprojectAPIService

	) {
		$this->openprojectAPIService = $openprojectAPIService;
	}

	/**
	 * @throws Exception
	 */
	public function handle(Event $event): void {
		// @phpstan-ignore-next-line - make phpstan not complain in nextcloud version other than 26
		if (!$event instanceof AppEnableEvent) {
			return;
		}
		if ($event->getAppId() !== 'terms_of_service') {
			return;
		}
		$this->openprojectAPIService->signTOSForUserOPenProject();
	}
}
