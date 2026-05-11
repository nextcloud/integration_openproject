<?php
/**
 * SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\OpenProject;

use OC_Util;
use OCP\IConfig;
use OCP\ServerVersion as NCServerVersion;

class ServerVersion {
	public function __construct(private IConfig $config) {
	}

	/**
	 * Get Nextcloud version.
	 *
	 * @return string
	 */
	public function getVersion(): string {
		// For Nextcloud below 31, ServerVersion class does not exist.
        // NOTE: we can remove alternative code path once we stop supporting Nextcloud 30
		if (class_exists(NCServerVersion::class)) {
			$serverVersion = new NCServerVersion();
            return $serverVersion->getVersionString();
		}

        return $this->config->getSystemValueString('version', '0.0.0');
	}

	/**
	 * Get Nextcloud MAJOR version.
	 *
	 * @return string
	 */
	public function getMajorVersion(): string {
		$versionString = $this->getVersion();
        return explode('.', $versionString)[0] ?? '0';
	}
}