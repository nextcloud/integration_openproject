<?php

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Settings;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PersonalTest extends TestCase {
	/**
	 * @var Personal
	 */
	private $setting;

	/**
	 * @var MockObject | IConfig
	 */
	private $config;

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
		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
		$this->initialState = $this->getMockBuilder(IInitialState::class)->getMock();
		$this->openProjectService = $this->getMockBuilder(OpenProjectAPIService::class)->disableOriginalConstructor()->getMock();
		$this->setting = new Personal($this->config, $this->initialState, $this->openProjectService, "testUser");
	}

	/**
	 * @return array<mixed>
	 */
	public function dataTestGetForm(): array {
		return [
			[
				'token' => null,
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
					"authentication_method" => OpenProjectAPIService::AUTH_METHOD_OAUTH,
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
					"authentication_method" => OpenProjectAPIService::AUTH_METHOD_OIDC,
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
		$this->config
			->method('getUserValue')
			->willReturnMap([
				['testUser', Application::APP_ID, 'user_name', '', $username],
				['testUser', Application::APP_ID, 'search_enabled', $config['default_enable_unified_search'], $searchEnabled],
				['testUser', Application::APP_ID, 'navigation_enabled', $config['default_enable_navigation'], $navigationEnabled],
			]);
		$this->config
			->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'openproject_instance_url', '', $config['openproject_instance_url']],
				[Application::APP_ID, 'authorization_method', '', $config['authentication_method']],
				[Application::APP_ID, 'openproject_client_id', '', $config['openproject_client_id']],
				[Application::APP_ID, 'openproject_client_secret', '', $config['openproject_client_secret']],
				[Application::APP_ID, 'nc_oauth_client_id', '', $config['nc_oauth_client_id']],
				[Application::APP_ID, 'sso_provider_type', '', $config['sso_provider_type']],
				[Application::APP_ID, 'oidc_provider', '', $config['oidc_provider']],
				[Application::APP_ID, 'token_exchange', '', $config['token_exchange']],
				[Application::APP_ID, 'fresh_project_folder_setup', '', $config['fresh_project_folder_setup']],
				[Application::APP_ID, 'default_enable_unified_search', '0', $config['default_enable_unified_search']],
				[Application::APP_ID, 'default_enable_navigation', '0', $config['default_enable_navigation']],
			]);

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
					'search_enabled' => $searchEnabled,
					'navigation_enabled' => $navigationEnabled,
					'admin_config_ok' => $adminConfigStatus,
					'authorization_method' => $config['authentication_method'],
					'oidc_user' => $oidcUser,
				],
			],
			['oauth-connection-result', null],
			['oauth-connection-error-message', null]
		];

		$this->setting->getForm();
		$this->assertEquals($expectedCalls, $initStateCalls);
	}
}
