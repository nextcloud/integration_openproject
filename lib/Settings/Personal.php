<?php

namespace OCA\OpenProject\Settings;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;

use OCP\IConfig;
use OCP\Settings\ISettings;

class Personal implements ISettings {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var string|null
	 */
	private $userId;

	/**
	 * @var OpenProjectAPIService
	 */
	private $openProjectAPIService;


	public function __construct(
		IConfig $config,
		IInitialState $initialStateService,
		OpenProjectAPIService $openProjectAPIService,
		?string $userId) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
		$this->openProjectAPIService = $openProjectAPIService;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$authorizationMethod = $this->config->getAppValue(Application::APP_ID, 'authorization_method', '');
		$token = '';
		if ($authorizationMethod === OpenProjectAPIService::AUTH_METHOD_OIDC) {
			$token = $this->openProjectAPIService->getOIDCToken();
			if ($token !== null) {
				// when connection is oidc based then user information needs to be saved
				$this->openProjectAPIService->setUserInfoForOidcBasedAuth($this->userId);
			}
		}
		if ($authorizationMethod === OpenProjectAPIService::AUTH_METHOD_OAUTH) {
			$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		}
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		// take the fallback value from the defaults
		$searchEnabled = $this->config->getUserValue(
			$this->userId,
			Application::APP_ID,
			'search_enabled',
			$this->config->getAppValue(Application::APP_ID, 'default_enable_unified_search', '0')
		);
		$navigationEnabled = $this->config->getUserValue(
			$this->userId,
			Application::APP_ID,
			'navigation_enabled',
			$this->config->getAppValue(Application::APP_ID, 'default_enable_navigation', '0')
		);

		$userConfig = [
			'token' => $token,
			'search_enabled' => ($searchEnabled === '1'),
			'navigation_enabled' => ($navigationEnabled === '1'),
			'user_name' => $userName,
		];

		$userConfig['admin_config_ok'] = OpenProjectAPIService::isAdminConfigOk($this->config);
		$userConfig['authorization_method'] = $authorizationMethod;
		$this->initialStateService->provideInitialState('user-config', $userConfig);

		$oauthConnectionResult = $this->config->getUserValue(
			$this->userId, Application::APP_ID, 'oauth_connection_result'
		);
		$this->config->deleteUserValue(
			$this->userId, Application::APP_ID, 'oauth_connection_result'
		);
		$this->initialStateService->provideInitialState(
			'oauth-connection-result', $oauthConnectionResult
		);
		$oauthConnectionErrorMessage = $this->config->getUserValue(
			$this->userId, Application::APP_ID, 'oauth_connection_error_message', ''
		);
		$this->config->deleteUserValue(
			$this->userId, Application::APP_ID, 'oauth_connection_error_message'
		);
		$this->initialStateService->provideInitialState(
			'oauth-connection-error-message', $oauthConnectionErrorMessage
		);


		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'openproject';
	}

	public function getPriority(): int {
		return 10;
	}
}
