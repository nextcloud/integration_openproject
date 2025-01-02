<?php

/**
 * Nextcloud - openproject
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sagar Gurung
 * @copyright Sagar Gurung 2024
 */

namespace OCA\OpenProject;

use OCA\OpenProject\AppInfo\Application;
use OCA\UserOIDC\Event\ExchangedTokenRequestedEvent;
use OCP\IConfig;

class ExchangedTokenRequestedEventHelper {
	private IConfig $config;

	public function __construct(
		IConfig $config
	) {
		$this->config = $config;
	}

	/**
	 * @return ExchangedTokenRequestedEvent
	 */
	public function getEvent(): ExchangedTokenRequestedEvent {
		return new ExchangedTokenRequestedEvent(
			$this->config->getAppValue(Application::APP_ID, 'targeted_audience_client_id', '')
		);
	}
}
