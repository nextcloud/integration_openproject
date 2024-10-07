<?php

declare(strict_types=1);

namespace OCA\OpenProject;

use OC_Util;

class ServerVersionHelper {
	/**
	 * Get Nextcloud version string.
	 *
	 * @return string
	 */
	public static function getNextcloudVersion(): string {
		// for nextcloud above 31 OC_Util::getVersionString() method does not exists
		if (class_exists('OCP\ServerVersion')) {
			return (new \OCP\ServerVersion())->getVersionString();
		}

		/** @psalm-suppress UndefinedMethod getVersionString() method is not in stable31 so making psalm not complain */
		return OC_Util::getVersionString();
	}
}
