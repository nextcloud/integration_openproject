<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021-2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Listener;

use OCA\Files\Event\LoadSidebar;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;
use OCP\Config\IUserConfig;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class LoadSidebarScript implements IEventListener {

	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var IAppConfig
	 */
	private $appConfig;
	/**
	 * @var IUserConfig
	 */
	private $userConfig;
	/**
	 * @var string "error"|"success"|""
	 */
	private $oauthConnectionResult = '';
	/**
	 * @var string
	 */
	private $oauthConnectionErrorMessage = '';

	/**
	 * @var IAppManager
	 */
	protected $appManager;

	/**
	 * @var OpenProjectAPIService
	 */
	private $openProjectAPIService;
	private IUserSession $userSession;
	/**
	 * @var string|null
	 */
	private $userId;

	public function __construct(
		IInitialState $initialStateService,
		IAppConfig $appConfig,
		IUserConfig $userConfig,
		IUserSession $userSession,
		IAppManager $appManager,
		OpenProjectAPIService $openProjectAPIService,
		?string $userId
	) {
		$this->initialStateService = $initialStateService;
		$this->appConfig = $appConfig;
		$this->userConfig = $userConfig;
		$this->appManager = $appManager;
		$this->userId = $userId;
		$user = $userSession->getUser();
		$this->openProjectAPIService = $openProjectAPIService;
		if (strpos(\OC::$server->get(IRequest::class)->getRequestUri(), 'files') !== false) {
			$this->oauthConnectionResult = $this->userConfig->getValueString(
				$user->getUID(), Application::APP_ID, 'oauth_connection_result', ''
			);
			$this->userConfig->deleteUserConfig(
				$user->getUID(), Application::APP_ID, 'oauth_connection_result'
			);
			$this->oauthConnectionErrorMessage = $this->userConfig->getValueString(
				$user->getUID(), Application::APP_ID, 'oauth_connection_error_message', ''
			);
			$this->userConfig->deleteUserConfig(
				$user->getUID(), Application::APP_ID, 'oauth_connection_error_message'
			);
		}
	}

	public function handle(Event $event): void {
		$authorizationMethod = $this->appConfig->getValueString(Application::APP_ID, 'authorization_method', '');
		if (!($event instanceof LoadSidebar)) {
			return;
		}
		if (!$this->appManager->isEnabledForUser(Application::APP_ID)) {
			return;
		}
		$currentVersion = implode('.', Util::getVersion());
		//changed from nextcloud 24
		if (version_compare($currentVersion, '24') >= 0) {
			Util::addScript(Application::APP_ID, 'integration_openproject-projectTab', 'files');
		} else {
			Util::addScript(Application::APP_ID, 'integration_openproject-projectTab');
		}
		Util::addStyle(Application::APP_ID, 'tab');

		$this->initialStateService->provideInitialState('authorization_method', $authorizationMethod);
		$this->initialStateService->provideInitialState(
			'openproject-url', $this->appConfig->getValueString(Application::APP_ID, 'openproject_instance_url')
		);
		$this->initialStateService->provideInitialState(
			'admin_config_ok', OpenProjectAPIService::isAdminConfigOk($this->appConfig)
		);
		// for 'oauth2' state to be loaded
		$this->initialStateService->provideInitialState(
			'oauth-connection-result', $this->oauthConnectionResult
		);
		$this->initialStateService->provideInitialState(
			'oauth-connection-error-message', $this->oauthConnectionErrorMessage
		);
	}
}
