<?php

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2021-2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Dashboard;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Services\IInitialState;
use OCP\Config\IUserConfig;
use OCP\Dashboard\IWidget;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;

use OCP\Util;

class OpenProjectWidget implements IWidget {

	/**
	 * @var IL10N
	 */
	private $l10n;
	/**
	 * @var IURLGenerator
	 */
	private $url;
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
	 * @var IUser
	 */
	private $user;

	/**
	 * @var OpenProjectAPIService
	 */
	private OpenProjectAPIService $openProjectAPIService;


	public function __construct(
		IL10N $l10n,
		IInitialState $initialStateService,
		IURLGenerator $url,
		IAppConfig $appConfig,
		IUserConfig $userConfig,
		IUserSession $userSession,
		OpenProjectAPIService $openProjectAPIService
	) {
		$this->initialStateService = $initialStateService;
		$this->l10n = $l10n;
		$this->url = $url;
		$this->appConfig = $appConfig;
		$this->userConfig = $userConfig;
		$this->user = $userSession->getUser();
		$this->openProjectAPIService = $openProjectAPIService;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'openproject_notifications';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('OpenProject');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		return 'icon-openproject';
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return $this->url->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']);
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-dashboard');
		Util::addStyle(Application::APP_ID, 'dashboard');

		$oauthConnectionResult = $this->userConfig->getValueString(
			$this->user->getUID(), Application::APP_ID, 'oauth_connection_result', ''
		);
		$this->userConfig->deleteUserConfig(
			$this->user->getUID(), Application::APP_ID, 'oauth_connection_result'
		);

		$authorizationMethod = $this->appConfig->getValueString(Application::APP_ID, 'authorization_method', '');
		$this->initialStateService->provideInitialState('authorization_method', $authorizationMethod);
		$this->initialStateService->provideInitialState(
			'admin_config_ok', OpenProjectAPIService::isAdminConfigOk($this->appConfig)
		);

		$token = $this->openProjectAPIService->getAccessToken($this->user->getUID());
		$this->initialStateService->provideInitialState('user-has-oidc-token', boolval($token));
		$this->initialStateService->provideInitialState('oidc_user', $this->openProjectAPIService->isOIDCUser());

		// for 'oauth2' state to be loaded
		$this->initialStateService->provideInitialState(
			'oauth-connection-result', $oauthConnectionResult
		);
		$oauthConnectionErrorMessage = $this->userConfig->getValueString(
			$this->user->getUID(), Application::APP_ID, 'oauth_connection_error_message', ''
		);
		$this->userConfig->deleteUserConfig(
			$this->user->getUID(), Application::APP_ID, 'oauth_connection_error_message'
		);
		$this->initialStateService->provideInitialState(
			'oauth-connection-error-message', $oauthConnectionErrorMessage
		);
	}
}
