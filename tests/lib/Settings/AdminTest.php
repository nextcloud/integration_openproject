<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Settings;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OauthService;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCA\OpenProject\Service\SettingsService;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminTest extends TestCase {
	private Admin $setting;
	private MockObject|IConfig $config;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$initialState = $this->createMock(IInitialState::class);
		$oauthService = $this->createMock(OauthService::class);
		$openProjectService = $this->createMock(OpenProjectAPIService::class);
		$this->setting = new Admin($this->config, $oauthService, $openProjectService, $initialState);
	}

	/**
	 * @return array<mixed>
	 */
	public function dataTestGetForm(): array {
		return [
			"initial admin config" => [
				"config" => [
					"openproject_instance_url" => "",
					"authorization_method" => "",
					"openproject_client_id" => "",
					"openproject_client_secret" => "",
				],
				"setupWithOauth" => false,
			],
			"incomplete admin config" => [
				"config" => [
					"openproject_instance_url" => "http://op.local.test",
					"authorization_method" => "",
					"openproject_client_id" => "",
					"openproject_client_secret" => "",
				],
				"setupWithOauth" => false,
			],
			"complete oauth2 admin config with unset authorization_method" => [
				"config" => [
					"openproject_instance_url" => "http://op.local.test",
					"authorization_method" => "",
					"openproject_client_id" => "openproject",
					"openproject_client_secret" => "op-secret",
				],
				"setupWithOauth" => true,
			],
			"complete oauth2 admin config with correct authorization_method" => [
				"config" => [
					"openproject_instance_url" => "http://op.local.test",
					"authorization_method" => SettingsService::AUTH_METHOD_OAUTH,
					"openproject_client_id" => "openproject",
					"openproject_client_secret" => "op-secret",
				],
				"setupWithOauth" => true,
			],
			"complete oidc admin config" => [
				"config" => [
					"openproject_instance_url" => "http://op.local.test",
					"authorization_method" => SettingsService::AUTH_METHOD_OIDC,
				],
				"setupWithOauth" => false,
			],
		];
	}

	/**
	 * @dataProvider dataTestGetForm
	 *
	 * @param array $config
	 * @param bool $setupWithOauth
	 * @return void
	 */
	public function testGetForm(array $config, bool $setupWithOauth): void {
		$appValues = [];
		foreach ($config as $key => $value) {
			$appValues[] = [Application::APP_ID, $key, '', $value];
		}
		$this->config
			->method('getAppValue')
			->willReturnMap($appValues);

		if (!$config['authorization_method'] && $setupWithOauth) {
			$this->config->expects($this->once())
				->method('setAppValue')
				->with(Application::APP_ID, 'authorization_method', SettingsService::AUTH_METHOD_OAUTH);
		} else {
			$this->config->expects($this->never())->method('setAppValue');
		}
		$this->setting->getForm();
	}
}
