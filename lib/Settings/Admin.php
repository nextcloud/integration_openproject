<?php

namespace OCA\OpenProject\Settings;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OauthService;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;

use OCP\IConfig;
use OCP\Settings\ISettings;

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

	public function __construct(IConfig $config,
		OauthService $oauthService,
		OpenProjectAPIService $openProjectAPIService,
		IInitialState $initialStateService) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->oauthService = $oauthService;
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
		$projectFolderStatusInformation = $this->openProjectAPIService->getProjectFolderSetupInformation();
		$isAllTermsOfServiceSignedForUserOpenProject = $this->openProjectAPIService->isAllTermsOfServiceSignedForUserOpenProject();
		$isAdminAuditConfigurationSetUpCorrectly = $this->openProjectAPIService->isAdminAuditConfigSetCorrectly();
		$adminConfig = [
			'openproject_client_id' => $clientID,
			'openproject_client_secret' => $clientSecret,
			'openproject_instance_url' => $oauthUrl,
			'nc_oauth_client' => $clientInfo,
			'default_enable_navigation' => $this->config->getAppValue(Application::APP_ID, 'default_enable_navigation', '0') === '1',
			'default_enable_unified_search' => $this->config->getAppValue(Application::APP_ID, 'default_enable_unified_search', '0') === '1',
			'app_password_set' => $this->openProjectAPIService->hasAppPassword(),
			'project_folder_info' => $projectFolderStatusInformation,
			'fresh_project_folder_setup' => $this->config->getAppValue(Application::APP_ID, 'fresh_project_folder_setup', '0') === '1',
			'all_terms_of_services_signed' => $isAllTermsOfServiceSignedForUserOpenProject,
			'admin_audit_configuration_correct' => $isAdminAuditConfigurationSetUpCorrectly,
			'encryption_info' => [
				'server_side_encryption_enabled' => $this->openProjectAPIService->isServerSideEncryptionEnabled(),
				'encryption_enabled_for_groupfolders' => $this->config->getAppValue('groupfolders', 'enable_encryption', '') === 'true'
			]
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
