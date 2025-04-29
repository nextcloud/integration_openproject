<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCA\UserOIDC\Event\ExchangedTokenRequestedEvent as ExchangedTokenEvent;
use OCA\UserOIDC\Event\ExternalTokenRequestedEvent as ExternalTokenEvent;
use OCA\UserOIDC\Event\InternalTokenRequestedEvent as InternalTokenEvent;
use OCP\IConfig;

class TokenEventFactory {
	private IConfig $config;

	public function __construct(
		IConfig $config
	) {
		$this->config = $config;
	}

	/**
	 * @return InternalTokenEvent|ExternalTokenEvent|ExchangedTokenEvent
	 */
	public function getEvent(): InternalTokenEvent|ExternalTokenEvent|ExchangedTokenEvent {
		$SSOProviderType = $this->config->getAppValue(Application::APP_ID, 'sso_provider_type', '');
		$tokenExchange = $this->config->getAppValue(Application::APP_ID, 'token_exchange', '');
		$targetAudience = $this->config->getAppValue(Application::APP_ID, 'targeted_audience_client_id', '');

		// If the SSO provider is Nextcloud Hub,
		// get token from internal IDP (oidc)
		if ($SSOProviderType === OpenProjectAPIService::NEXTCLOUD_HUB_PROVIDER) {
			return new InternalTokenEvent($targetAudience, Application::OPENPROJECT_API_SCOPES, $targetAudience);
		}

		// If the SSO provider is external and token exchange is disabled,
		// get the login token
		if (!$tokenExchange) {
			// NOTE: cannot request new scopes with ExternalTokenEvent
			return new ExternalTokenEvent();
		}

		// If the SSO provider is external and token exchange is enabled,
		// exchange the token for targeted audience client
		return new ExchangedTokenEvent($targetAudience, Application::OPENPROJECT_API_SCOPES);
	}
}
