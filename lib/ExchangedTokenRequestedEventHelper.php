<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
