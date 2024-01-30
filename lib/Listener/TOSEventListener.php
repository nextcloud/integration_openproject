<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Sagar Gurung <sagar@jankaritech.com>
 *
 * @author Sagar Gurung <sagar@jankaritech.com>
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

use OCA\OpenProject\Exception\TermsOfServiceException;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCA\TermsOfService\Events\SignaturesResetEvent;
use OCA\TermsOfService\Events\TermsCreatedEvent;
use OCP\App\Events\AppEnableEvent;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class TOSEventListener implements IEventListener {

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
	 * @throws OCSBadRequestException
	 */
	public function handle(Event $event): void {
		try {
			if ($event instanceof TermsCreatedEvent) {
				$this->openprojectAPIService->signTOSForUserOPenProject();
			}
			if ($event instanceof SignaturesResetEvent) {
				$this->openprojectAPIService->signTOSForUserOPenProject();
			}
		} catch (TermsOfServiceException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}

		if ($event instanceof AppEnableEvent) {
			try {
				if ($event->getAppId() === 'terms_of_service') {
					$this->openprojectAPIService->signTOSForUserOPenProject();
				}
			} catch (TermsOfServiceException $e) {
			}
		}
	}
}
