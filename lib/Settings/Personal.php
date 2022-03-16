<?php

namespace OCA\OpenProject\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;

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
	 * @var IURLGenerator
	 */
	private $url;

	public function __construct(
								IConfig $config,
								IInitialState $initialStateService,
								IURLGenerator $url,
								?string $userId) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->url = $url;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$searchEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_enabled', '0');
		$notificationEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'notification_enabled', '0');
		$navigationEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'navigation_enabled', '0');

		$isAdminConfigOk = OpenProjectAPIService::isAdminConfigOk($this->config);

		// for OAuth
		$requestUrl = OpenProjectAPIService::getOpenProjectOauthURL($this->config, $this->url);

		$userConfig = [
			'token' => $token,
			'search_enabled' => ($searchEnabled === '1'),
			'notification_enabled' => ($notificationEnabled === '1'),
			'navigation_enabled' => ($navigationEnabled === '1'),
			'user_name' => $userName,
			'request_url' => $requestUrl,
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);
		$this->initialStateService->provideInitialState('admin-config-status', $isAdminConfigOk);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
