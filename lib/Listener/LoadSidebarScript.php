<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Kiran Parajuli <kiran@jankaritech.com>
 *
 * @author Kiran Parajuli <kiran@jankaritech.com>
 *
 * @license GNU Affero General Public License v3.0 or later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OpenProject\Listener;

use OCA\Files\Event\LoadSidebar;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class LoadSidebarScript implements IEventListener {

	/**
	 * @var IURLGenerator
	 */
	private $url;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * @var string "error"|"success"|""
	 */
	private $oauthConnectionResult;
	/**
	 * @var string
	 */
	private $oauthConnectionErrorMessage;

	public function __construct(
		IInitialState $initialStateService,
		IURLGenerator $url,
		IConfig $config,
		IUserSession $userSession
	) {
		$this->initialStateService = $initialStateService;
		$this->config = $config;
		$this->url = $url;
		$user = $userSession->getUser();
		if (strpos(\OC::$server->get(IRequest::class)->getRequestUri(), 'files') !== false) {
			$this->oauthConnectionResult = $this->config->getUserValue(
				$user->getUID(), Application::APP_ID, 'oauth_connection_result'
			);
			$this->config->deleteUserValue(
				$user->getUID(), Application::APP_ID, 'oauth_connection_result'
			);
			$this->oauthConnectionErrorMessage = $this->config->getUserValue(
				$user->getUID(), Application::APP_ID, 'oauth_connection_error_message'
			);
			$this->config->deleteUserValue(
				$user->getUID(), Application::APP_ID, 'oauth_connection_error_message'
			);
		}
	}

	public function handle(Event $event): void {
		if (!($event instanceof LoadSidebar)) {
			return;
		}
		$currentVersion = implode('.', Util::getVersion());
		//changed from nextcloud 24
		if (version_compare($currentVersion, '24') >= 0) {
			// @phpstan-ignore-next-line
			Util::addScript(Application::APP_ID, 'integration_openproject-projectTab', 'files');
		} else {
			Util::addScript(Application::APP_ID, 'integration_openproject-projectTab');
		}
		Util::addStyle(Application::APP_ID, 'tab');

		try {
			$requestUrl = OpenProjectAPIService::getOpenProjectOauthURL($this->config, $this->url);
			$this->initialStateService->provideInitialState('request-url', $requestUrl);
		} catch (\Exception $e) {
			$this->initialStateService->provideInitialState('request-url', false);
		}

		$this->initialStateService->provideInitialState(
			'oauth-connection-result', $this->oauthConnectionResult
		);
		$this->initialStateService->provideInitialState(
			'oauth-connection-error-message', $this->oauthConnectionErrorMessage
		);
	}
}
