<?php

namespace OCA\OpenProject\Settings;

use OCA\OpenProject\Service\OauthService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\OpenProject\Service\OpenProjectAPIService;
use OCA\OpenProject\AppInfo\Application;
use OC\Authentication\Token\IProvider;

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

	/**
	 * @var OpenProjectAPIService
	 */
	private $openProjectAPIService;

	/**
	 * @var IProvider
	 */
	private $tokenProvider;

	public function __construct(IConfig $config,
								OauthService $oauthService,
								IProvider $tokenProvider,
								OpenProjectAPIService $openProjectAPIService,
								IInitialState $initialStateService) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->oauthService = $oauthService;
		$this->tokenProvider = $tokenProvider;
		$this->openProjectAPIService = $openProjectAPIService;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'openproject_client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'openproject_client_secret');
		$oauthUrl = $this->config->getAppValue(Application::APP_ID, 'openproject_instance_url');

		// get automatically created NC oauth client for OP
		$clientInfo = null;
		$oauthClientInternalId = $this->config->getAppValue(Application::APP_ID, 'nc_oauth_client_id', '');
		if ($oauthClientInternalId !== '') {
			$id = (int)$oauthClientInternalId;
			$clientInfo = $this->oauthService->getClientInfo($id);
		}
		// We only need a single app password for user OpenProject
		$appPasswordCount = sizeof($this->tokenProvider->getTokenByUser(Application::OPEN_PROJECT_ENTITIES_NAME));
		$groupFolderStatusInformation = $this->openProjectAPIService-> isGroupFolderSetupInformation();
		$adminConfig = [
			'openproject_client_id' => $clientID,
			'openproject_client_secret' => $clientSecret,
			'openproject_instance_url' => $oauthUrl,
			'nc_oauth_client' => $clientInfo,
			'default_enable_navigation' => $this->config->getAppValue(Application::APP_ID, 'default_enable_navigation', '0') === '1',
			'default_enable_unified_search' => $this->config->getAppValue(Application::APP_ID, 'default_enable_unified_search', '0') === '1',
			'app_password_set' => ($appPasswordCount === 1),
			'default_managed_folders' => $this->config->getAppValue(Application::APP_ID, 'default_managed_folders', '0') === '1',
			'managed_folder_state' => $this->config->getAppValue(Application::APP_ID, 'managed_folder_state', '0') === '1',
			'group_folder_status' => $groupFolderStatusInformation
		];

		$adminConfigStatus = OpenProjectAPIService::isAdminConfigOk($this->config);

		$this->initialStateService->provideInitialState('admin-config', $adminConfig);
		$this->initialStateService->provideInitialState('admin-config-status', $adminConfigStatus);

		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'openproject';
	}

	public function getPriority(): int {
		return 10;
	}
}
