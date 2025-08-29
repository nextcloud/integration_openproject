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
use OCP\Group\ISubAdmin;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20100Date20250820101358 extends SimpleMigrationStep {
	/**
	 * @param IConfig $config
	 * @param OpenProjectAPIService $openProjectAPIService
	 * @param IGroupManager $groupManager
	 * @param IUserManager $userManager
	 * @param ISubAdmin $subAdmin
	 */
	public function __construct(
		private IConfig $config,
		private OpenProjectAPIService $openProjectAPIService,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private ISubAdmin $subAdmin,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (OpenProjectAPIService::isCommonAdminConfigOk($this->config) && $this->openProjectAPIService->isProjectFoldersSetupComplete()) {
			$opAllGroup = $this->groupManager->get(Application::OPENPROJECT_ALL_GROUP_NAME);
			if ($opAllGroup === null) {
				$opUser = $this->userManager->get(Application::OPEN_PROJECT_ENTITIES_NAME);
				$opAllGroup = $this->groupManager->createGroup(Application::OPENPROJECT_ALL_GROUP_NAME);
				$opAllGroup->addUser($opUser);
				$this->subAdmin->createSubAdmin($opUser, $opAllGroup);
			}
		}
	}
}
