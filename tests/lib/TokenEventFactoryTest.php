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
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class TokenEventFactoryTest extends TestCase {
	/**
	 * @return array<array>
	 */
	public function settingsProvider(): array {
		return [
			"Nextcloud Hub setup" => [
				"providerType" => OpenProjectAPIService::NEXTCLOUD_HUB_PROVIDER,
				"tokenExchange" => false,
				"class" => InternalTokenRequestedEvent::class,
			],
			"External IDP without token exchange" => [
				"providerType" => "external",
				"tokenExchange" => false,
				"class" => ExternalTokenRequestedEvent::class,
			],
			"External IDP with token exchange" => [
				"providerType" => "external",
				"tokenExchange" => true,
				"class" => ExchangedTokenRequestedEvent::class,
			],
			"Nextcloud Hub with token exchange enabled" => [
				"providerType" => OpenProjectAPIService::NEXTCLOUD_HUB_PROVIDER,
				"tokenExchange" => true,
				"class" => InternalTokenRequestedEvent::class,
			],
		];
	}

	/**
	 * @dataProvider settingsProvider
	 * @return void
	 */
	public function testGetEvent($providerType, $tokenExchange, $class): void {
		$configMock = $this->createMock(IConfig::class);
		$configMock->method("getAppValue")->willReturnMap([
			[Application::APP_ID, "sso_provider_type", $providerType],
			[Application::APP_ID, "token_exchange", $tokenExchange],
			[Application::APP_ID, "targeted_audience_client_id", "test-client"],
		]);

		$factory = new TokenEventFactory($configMock);
		$event = $factory->getEvent();
		$this->assertInstanceOf($class, $event);
	}
}
