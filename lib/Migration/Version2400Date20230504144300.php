<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Jankari Tech Pvt. Ltd.
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
class Version2400Date20230504144300 extends SimpleMigrationStep {

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
		// set only once, as changeSchema() is also replayed by the schema check
		if (!in_array('fresh_project_folder_setup', $this->appConfig->getKeys(Application::APP_ID), true)) {
			$this->appConfig->setValueString(
				Application::APP_ID, 'fresh_project_folder_setup', "1"
			);
		}
		return null;
	}
}
