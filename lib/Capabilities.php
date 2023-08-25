<?php

declare(strict_types=1);

namespace OCA\OpenProject;

use OCA\OpenProject\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Capabilities\IPublicCapability;

class Capabilities implements IPublicCapability {

	/** @var IAppManager */
	private $appManager;

	public function __construct(
		IAppManager $appManager
	) {
		$this->appManager = $appManager;
	}

	/**
	 * @return array<string, array<string, bool|string>>
	 */
	public function getCapabilities(): array {
		$appVersion = $this->appManager->getAppVersion(Application::APP_ID);
		$groupfoldersVersion = $this->appManager->getAppVersion('groupfolders');
		$groupfoldersEnabled = $this->appManager->isEnabledForUser('groupfolders');
		return [
			Application::APP_ID => [
				'app_version' => $appVersion,
				'groupfolder_version' => $groupfoldersVersion,
				'groupfolders_enabled' => $groupfoldersEnabled,
			],
		];
	}
}
