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
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
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
	 * @var IConfig
	 */
	private $config;

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
		IConfig $config,
		IUserSession $userSession,
		IAppManager $appManager,
		OpenProjectAPIService $openProjectAPIService,
		?string $userId
	) {
		$this->initialStateService = $initialStateService;
		$this->config = $config;
		$this->appManager = $appManager;
		$this->userId = $userId;
		$user = $userSession->getUser();
		$this->openProjectAPIService = $openProjectAPIService;
		if (strpos(\OC::$server->get(IRequest::class)->getRequestUri(), 'files') !== false) {
			$this->oauthConnectionResult = $this->config->getUserValue(
				$user->getUID(), Application::APP_ID, 'oauth_connection_result', ''
			);
			$this->config->deleteUserValue(
				$user->getUID(), Application::APP_ID, 'oauth_connection_result'
			);
			$this->oauthConnectionErrorMessage = $this->config->getUserValue(
				$user->getUID(), Application::APP_ID, 'oauth_connection_error_message', ''
			);
			$this->config->deleteUserValue(
				$user->getUID(), Application::APP_ID, 'oauth_connection_error_message'
			);
		}
	}

	public function handle(Event $event): void {
		$authorizationMethod = $this->config->getAppValue(Application::APP_ID, 'authorization_method', '');
		$accessToken = null;
		// When user is non oidc based or there is some error when getting token for the targeted client
		// then we need to hide the oidc based connection for the user
		// so this check is required
		if ($authorizationMethod === OpenProjectAPIService::AUTH_METHOD_OIDC) {
			$accessToken = $this->openProjectAPIService->getOIDCToken();
			if (!$accessToken) {
				return;
			}
			// for 'oidc' the user info needs to be set (once token has been exchanged)
			$this->openProjectAPIService->setUserInfoForOidcBasedAuth($this->userId);
		}
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
			'openproject-url', $this->config->getAppValue(Application::APP_ID, 'openproject_instance_url')
		);
		$this->initialStateService->provideInitialState(
			'admin_config_ok', OpenProjectAPIService::isAdminConfigOk($this->config)
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
