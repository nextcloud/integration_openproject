<?php

declare(strict_types=1);

namespace OCA\OpenProject;

use OC_Util;
use OCP\ServerVersion;

class VersionUtil {

	/**
	 * @var ServerVersion|null
	 */
	private ?ServerVersion $serverVersion = null;

	public function __construct(ServerVersion $serverVersion) {
		if (class_exists(ServerVersion::class)) {
			$this->serverVersion = $serverVersion;
		}
	}

	/**
	 * Get Nextcloud version string.
	 *
	 * @return string
	 */
	public function getNextcloudVersion(): string {
		// for nextcloud above 31 OC_Util::getVersionString() method not exists
		if ($this->serverVersion) {
			return $this->serverVersion->getVersionString();
		}

		/** @psalm-suppress UndefinedMethod getVersionString() method is not in stable31 so making psalm not complain */
		return OC_Util::getVersionString();
	}
}
