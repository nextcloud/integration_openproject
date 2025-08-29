<?php

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
		IInitialState $initialStateService
	) {
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
		$opUrl = $this->config->getAppValue(Application::APP_ID, 'openproject_instance_url');

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

		// NOTE: for migration compatibility
		// set 'authorization_method' to Oauth2 if authorization_method is not set
		// and there is existing complete Oauth2 setup
		$authenticationMethod = $this->config->getAppValue(Application::APP_ID, 'authorization_method', '');
		if (!$authenticationMethod && OpenProjectAPIService::isAdminConfigOkForOauth2($this->config)) {
			$authenticationMethod = OpenProjectAPIService::AUTH_METHOD_OAUTH;
			$this->config->setAppValue(Application::APP_ID, 'authorization_method', $authenticationMethod);
		}

		$adminConfig = [
			// admin settings form
			'openproject_instance_url' => $opUrl,
			'authorization_method' => $authenticationMethod,
			// oauth2 form configs
			'openproject_client_id' => $clientID,
			'openproject_client_secret' => $clientSecret,
			'nc_oauth_client' => $clientInfo,
			// oidc form configs
			'authorization_settings' => [
				'oidc_provider' => $this->config->getAppValue(Application::APP_ID, 'oidc_provider', ''),
				'targeted_audience_client_id' => $this->config->getAppValue(
					Application::APP_ID, 'targeted_audience_client_id', ''
				),
				'sso_provider_type' => $this->config->getAppValue(Application::APP_ID, 'sso_provider_type', ''),
				'token_exchange' => \boolval($this->config->getAppValue(Application::APP_ID, 'token_exchange')),
			],
			// project folder form configs
			'fresh_project_folder_setup' => $this->config->getAppValue(Application::APP_ID, 'fresh_project_folder_setup', '0') === '1',
			'project_folder_info' => $projectFolderStatusInformation,
			'app_password_set' => $this->openProjectAPIService->hasAppPassword(),
			// general form configs
			'default_enable_navigation' => $this->config->getAppValue(Application::APP_ID, 'default_enable_navigation', '0') === '1',
			'default_enable_unified_search' => $this->config->getAppValue(Application::APP_ID, 'default_enable_unified_search', '0') === '1',
			// other states
			'all_terms_of_services_signed' => $isAllTermsOfServiceSignedForUserOpenProject,
			'admin_audit_configuration_correct' => $isAdminAuditConfigurationSetUpCorrectly,
			'encryption_info' => [
				'server_side_encryption_enabled' => $this->openProjectAPIService->isServerSideEncryptionEnabled(),
				'encryption_enabled_for_groupfolders' => $this->config->getAppValue('groupfolders', 'enable_encryption', '') === 'true'
			],
			'oidc_providers' => $this->openProjectAPIService->getRegisteredOidcProviders(),
			'user_oidc_enabled' => $this->openProjectAPIService->isUserOIDCAppInstalledAndEnabled(),
			'user_oidc_supported' => $this->openProjectAPIService->isUserOIDCAppSupported(),
			'user_oidc_minimum_version' => OpenProjectAPIService::MIN_SUPPORTED_USER_OIDC_APP_VERSION,
			'apps' => [
				'oidc' => [
					'name' => $this->openProjectAPIService->getAppsName('oidc'),
					'enabled' => $this->openProjectAPIService->isOIDCAppEnabled(),
					'supported' => $this->openProjectAPIService->isOIDCAppSupported(),
					'minimum_version' => OpenProjectAPIService::MIN_SUPPORTED_OIDC_APP_VERSION,
				],
				'user_oidc' => [
					'name' => $this->openProjectAPIService->getAppsName('user_oidc'),
					'enabled' => $this->openProjectAPIService->isUserOIDCAppInstalledAndEnabled(),
					'supported' => $this->openProjectAPIService->isUserOIDCAppSupported(),
					'minimum_version' => OpenProjectAPIService::MIN_SUPPORTED_USER_OIDC_APP_VERSION,
				],
				'groupfolders' => [
					'name' => $this->openProjectAPIService->getAppsName('groupfolders'),
					'enabled' => $this->openProjectAPIService->isGroupfoldersAppEnabled(),
					'supported' => $this->openProjectAPIService->isGroupfoldersAppSupported(),
					'minimum_version' => OpenProjectAPIService::MIN_SUPPORTED_GROUPFOLDERS_APP_VERSION,
				],
			],
		];

		$this->initialStateService->provideInitialState('admin-settings-config', $adminConfig);
		$this->initialStateService->provideInitialState(
			'admin-config-status', OpenProjectAPIService::isAdminConfigOk($this->config)
		);


		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'openproject';
	}

	public function getPriority(): int {
		return 10;
	}
}
