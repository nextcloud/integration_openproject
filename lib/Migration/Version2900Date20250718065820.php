<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Migration;

use Closure;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version2900Date20250718065820 extends SimpleMigrationStep {
	public function __construct(private IConfig $config) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		// set 'authorization_method' to Oauth2 if authorization_method is not set
		// and there is existing complete Oauth2 setup
		$authenticationMethod = $this->config->getAppValue(Application::APP_ID, 'authorization_method', '');
		$opClientId = $this->config->getAppValue(Application::APP_ID, 'openproject_client_id');
		$opClientSecret = $this->config->getAppValue(Application::APP_ID, 'openproject_client_secret');
		$ncClientId = $this->config->getAppValue(Application::APP_ID, 'nc_oauth_client_id');

		$hasCompleteOAuthSetup = OpenProjectAPIService::isCommonAdminConfigOk($this->config) &&
			!(empty($opClientId) || empty($opClientSecret) || empty($ncClientId));

		if (!$authenticationMethod && $hasCompleteOAuthSetup) {
			$this->config->setAppValue(Application::APP_ID, 'authorization_method', OpenProjectAPIService::AUTH_METHOD_OAUTH);
		}

		return null;
	}
}
