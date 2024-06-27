<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Sagar Gurung <sagar@jankaritech.com>
 *
 * @author Your name <sagar@jankaritech.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OpenProject\Migration;

use Closure;
use OCA\OpenProject\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version2001Date20221213083550 extends SimpleMigrationStep {

	/**
	 * @var IConfig
	 */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$oldOpenProjectOauthUrl = $this->config->getAppValue(
			Application::APP_ID, 'oauth_instance_url', ''
		);
		$oldClientId = $this->config->getAppValue(
			Application::APP_ID, 'client_id', ''
		);
		$oldClientSecret = $this->config->getAppValue(
			Application::APP_ID, 'client_secret', ''
		);

		$this->config->setAppValue(
			Application::APP_ID, 'openproject_instance_url', $oldOpenProjectOauthUrl
		);
		$this->config->setAppValue(
			Application::APP_ID, 'openproject_client_id', $oldClientId
		);
		$this->config->setAppValue(
			Application::APP_ID, 'openproject_client_secret', $oldClientSecret
		);

		$this->config->deleteAppValue(Application::APP_ID, 'oauth_instance_url');
		$this->config->deleteAppValue(Application::APP_ID, 'client_id');
		$this->config->deleteAppValue(Application::APP_ID, 'client_secret');

		return null;
	}
}
