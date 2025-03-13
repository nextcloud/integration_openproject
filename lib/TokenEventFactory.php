<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCA\UserOIDC\Event\ExchangedTokenRequestedEvent;
use OCA\UserOIDC\Event\ExternalTokenRequestedEvent;
use OCA\UserOIDC\Event\InternalTokenRequestedEvent;
use OCP\EventDispatcher\Event;
use OCP\IConfig;

class TokenEventFactory {
	private IConfig $config;

	public function __construct(
		IConfig $config
	) {
		$this->config = $config;
	}

	/**
	 * @return Event
	 */
	public function getEvent(): Event {
		$SSOProvider = $this->config->getAppValue(Application::APP_ID, 'sso_provider_type', OpenProjectAPIService::NEXTCLOUD_HUB_PROVIDER);
		$tokenExchange = $this->config->getAppValue(Application::APP_ID, 'token_exchange', false);
		$targetAudience = $this->config->getAppValue(Application::APP_ID, 'targeted_audience_client_id', '');

		// If the SSO provider is Nextcloud Hub,
		// get token from internal IDP (oidc)
		if ($SSOProvider === OpenProjectAPIService::NEXTCLOUD_HUB_PROVIDER) {
			return new InternalTokenRequestedEvent($targetAudience);
		}

		// If the SSO provider is external and token exchange is disabled,
		// get the login token
		if (!$tokenExchange) {
			return new ExternalTokenRequestedEvent();
		}

		// If the SSO provider is external and token exchange is enabled,
		// exchange the token for targeted audience client
		return new ExchangedTokenRequestedEvent($targetAudience);
	}
}
