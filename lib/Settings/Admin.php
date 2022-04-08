<?php

namespace OCA\OpenProject\Settings;

use OCA\OpenProject\Service\OauthService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\OpenProject\Service\OpenProjectAPIService;
use OCA\OpenProject\AppInfo\Application;

class Admin implements ISettings {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var OauthService
	 */
	private $oauthService;

	public function __construct(IConfig $config,
								OauthService $oauthService,
								IInitialState $initialStateService) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->oauthService = $oauthService;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$oauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');

		// get automatically created NC oauth client for OP
		$clientInfo = null;
		$oauthClientInternalId = $this->config->getAppValue(Application::APP_ID, 'nc-oauth-client-id', '');
		if ($oauthClientInternalId !== '') {
			$id = (int)$oauthClientInternalId;
			$clientInfo = $this->oauthService->getClientInfo($id);
		}

		$adminConfig = [
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'oauth_instance_url' => $oauthUrl,
			'nc_oauth_client' => $clientInfo,
		];

		$adminConfigStatus = OpenProjectAPIService::isAdminConfigOk($this->config);

		$this->initialStateService->provideInitialState('admin-config', $adminConfig);
		$this->initialStateService->provideInitialState('admin-config-status', $adminConfigStatus);

		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
