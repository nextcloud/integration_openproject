<?php

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Settings;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Services\IInitialState;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PersonalTest extends TestCase {
	/**
	 * @var Personal
	 */
	private $setting;

	/**
	 * @var MockObject | IAppConfig
	 */
	private $appConfig;

	/**
	 * @var MockObject | IUserConfig
	 */
	private $userConfig;

	/**
	 * @var MockObject | IInitialState
	 */
	private $initialState;

	/**
	 * @var MockObject | OpenProjectAPIService
	 */
	private $openProjectService;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->initialState = $this->getMockBuilder(IInitialState::class)->getMock();
		$this->openProjectService = $this->getMockBuilder(OpenProjectAPIService::class)->disableOriginalConstructor()->getMock();
		$this->setting = new Personal($this->appConfig, $this->userConfig, $this->initialState, $this->openProjectService, "testUser");
	}

	/**
	 * @return array<mixed>
	 */
	public function dataTestGetForm(): array {
		return [
			[
				'token' => '',
				'username' => '',
				"config" => [
					"openproject_instance_url" => '',
					"authentication_method" => '',
					"openproject_client_id" => '',
					"openproject_client_secret" => '',
					"nc_oauth_client_id" => '',
					"sso_provider_type" => '',
					"oidc_provider" => '',
					"token_exchange" => null,
					"fresh_project_folder_setup" => true,
					"default_enable_unified_search" => '0',
					"default_enable_navigation" => '0',
				],
				"searchEnabled" => '0',
				"navigationEnabled" => '0',
				"adminConfigStatus" => false,
				"oidcUser" => false,
			],
			[
				'token' => 'test-token',
				'username' => 'testUser',
				"config" => [
					"openproject_instance_url" => 'http://some.url',
					"authentication_method" => Application::AUTH_METHOD_OAUTH,
					"openproject_client_id" => 'some-client-id',
					"openproject_client_secret" => 'some-client-secret',
					"nc_oauth_client_id" => 'nc-client',
					"sso_provider_type" => '',
					"oidc_provider" => '',
					"token_exchange" => null,
					"fresh_project_folder_setup" => false,
					"default_enable_unified_search" => '1',
					"default_enable_navigation" => '1',
				],
				"searchEnabled" => '1',
				"navigationEnabled" => '1',
				"adminConfigStatus" => true,
				"oidcUser" => true,
			],
			[
				'token' => 'test-token',
				'username' => 'testUser',
				"config" => [
					"openproject_instance_url" => 'http://some.url',
					"authentication_method" => Application::AUTH_METHOD_OIDC,
					"openproject_client_id" => '',
					"openproject_client_secret" => '',
					"nc_oauth_client_id" => '',
					"sso_provider_type" => 'external',
					"oidc_provider" => 'test-idp',
					"token_exchange" => false,
					"fresh_project_folder_setup" => false,
					"default_enable_unified_search" => '1',
					"default_enable_navigation" => '1',
				],
				"searchEnabled" => '1',
				"navigationEnabled" => '1',
				"adminConfigStatus" => true,
				"oidcUser" => true,
			],
		];
	}

	/**
	 * @dataProvider dataTestGetForm
	 *
	 * @param ?string $token
	 * @param string $username
	 * @param array $config
	 * @param string $searchEnabled
	 * @param string $navigationEnabled
	 * @param bool $adminConfigStatus
	 * @param bool $oidcUser
	 *
	 * @return void
	 */
	public function testGetForm(
		?string $token,
		string $username,
		array $config,
		string $searchEnabled,
		string $navigationEnabled,
		bool $adminConfigStatus,
		bool $oidcUser
	): void {
		$this->openProjectService
			->method('getAccessToken')
			->willReturn($token);
		$this->openProjectService
			->method('isOIDCUser')
			->willReturn($oidcUser);

		$userConfigValues = [
			'user_name' => $username,
			'search_enabled' => $searchEnabled,
			'navigation_enabled' => $navigationEnabled,
		];
		$this->userConfig
			->method('getValueString')
			->willReturnCallback(
				fn (string $userId, string $app, string $key, string $default = ''): string => $userConfigValues[$key] ?? $default
			);

		$appConfigValues = [
			'openproject_instance_url' => $config['openproject_instance_url'],
			'authorization_method' => $config['authentication_method'],
			'openproject_client_id' => $config['openproject_client_id'],
			'openproject_client_secret' => $config['openproject_client_secret'],
			'nc_oauth_client_id' => $config['nc_oauth_client_id'],
			'sso_provider_type' => $config['sso_provider_type'],
			'oidc_provider' => $config['oidc_provider'],
			'token_exchange' => $config['token_exchange'],
			'fresh_project_folder_setup' => $config['fresh_project_folder_setup'],
			'default_enable_unified_search' => $config['default_enable_unified_search'],
			'default_enable_navigation' => $config['default_enable_navigation'],
		];
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(
				fn (string $app, string $key, string $default = ''): string => $appConfigValues[$key] ?? $default
			);

		$initStateCalls = [];
		$this->initialState
			->method('provideInitialState')
			->willReturnCallback(function ($state, $config) use (&$initStateCalls) {
				$initStateCalls[] = [$state, $config];
			});

		$expectedCalls = [
			[
				'user-config',
				[
					'token' => $token,
					'user_name' => $username,
					'search_enabled' => $searchEnabled === '1',
					'navigation_enabled' => $navigationEnabled === '1',
					'admin_config_ok' => $adminConfigStatus,
					'authorization_method' => $config['authentication_method'],
					'oidc_user' => $oidcUser,
				],
			],
			['oauth-connection-result', null],
			['oauth-connection-error-message', null]
		];

		$this->setting->getForm();
		$this->assertEqualsCanonicalizing($expectedCalls, $initStateCalls);
	}
}
