<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Migration;

use Closure;
use OCA\OpenProject\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version2001Date20221213083550 extends SimpleMigrationStep {

	/**
	 * @var IAppConfig
	 */
	private $appConfig;

	public function __construct(IAppConfig $appConfig) {
		$this->appConfig = $appConfig;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		// nothing to migrate once the old keys are gone (changeSchema() is also replayed by the schema check)
		$oldKeys = ['oauth_instance_url', 'client_id', 'client_secret'];
		if (array_intersect($oldKeys, $this->appConfig->getKeys(Application::APP_ID)) === []) {
			return null;
		}

		$oldOpenProjectOauthUrl = $this->appConfig->getValueString(
			Application::APP_ID, 'oauth_instance_url', ''
		);
		$oldClientId = $this->appConfig->getValueString(
			Application::APP_ID, 'client_id', ''
		);
		$oldClientSecret = $this->appConfig->getValueString(
			Application::APP_ID, 'client_secret', ''
		);

		$this->appConfig->setValueString(
			Application::APP_ID, 'openproject_instance_url', $oldOpenProjectOauthUrl
		);
		$this->appConfig->setValueString(
			Application::APP_ID, 'openproject_client_id', $oldClientId
		);
		$this->appConfig->setValueString(
			Application::APP_ID, 'openproject_client_secret', $oldClientSecret
		);

		$this->appConfig->deleteKey(Application::APP_ID, 'oauth_instance_url');
		$this->appConfig->deleteKey(Application::APP_ID, 'client_id');
		$this->appConfig->deleteKey(Application::APP_ID, 'client_secret');

		return null;
	}
}
