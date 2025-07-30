<?php

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Settings;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Http\TemplateResponse;
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
				// valid dataset
				"config" => [
					"clientId" => 'some-client-id',
					"clientSecret" => 'some-client-secret',
					"oauthInstanceUrl" => 'http://some.url',
					"nc_oauth_client_id" => 'nc-client',
					"authentication_method" => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					"fresh_project_folder_setup" => false,
				],
				"adminConfigStatus" => true,
			],
			[
				// dataset with empty client secret
				"config" => [
					"clientId" => 'some-client-id',
					"clientSecret" => '',
					"oauthInstanceUrl" => 'http://some.url',
				],
				"adminConfigStatus" => false,
			],
			[
				// dataset with invalid oauth instance url
				"config" => [
					"clientId" => 'some-client-id',
					"clientSecret" => 'some-secret',
					"oauthInstanceUrl" => 'http:/',
				],
				"adminConfigStatus" => false,
			],
		];
	}

	/**
	 * @dataProvider dataTestGetForm
	 *
	 * @param array $config
	 * @param bool $adminConfigStatus
	 * @return void
	 */
	public function testGetForm(
		array $config,
		bool $adminConfigStatus,
	) {
		$this->config
			->method('getUserValue')
			->withConsecutive(
				['testUser', 'integration_openproject', 'token'],
				['testUser', 'integration_openproject', 'user_name'],
				['testUser', 'integration_openproject', 'search_enabled', '0'],
				['testUser', 'integration_openproject', 'navigation_enabled', '0'],
			)
			->willReturnOnConsecutiveCalls(
				'some-token',
				'some-username',
				'0', '0',
			);
		$this->config
			->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'authorization_method', '', OpenProjectAPIService::AUTH_METHOD_OAUTH],
				[Application::APP_ID, 'openproject_instance_url', '', $config['oauthInstanceUrl']],
				[Application::APP_ID, 'openproject_client_id', '', $config['clientId']],
				[Application::APP_ID, 'openproject_client_secret', '', $config['clientSecret']],
				[Application::APP_ID, 'fresh_project_folder_setup', '', false],
				[Application::APP_ID, 'nc_oauth_client_id', '', 'nc-client'],
				[Application::APP_ID, 'default_enable_unified_search', '0', '0'],
				[Application::APP_ID, 'default_enable_navigation', '0', '0'],
			]);

		$this->initialState
			->method('provideInitialState')
			->withConsecutive(
				[
					'user-config', [
						'token' => 'some-token',
						'user_name' => 'some-username',
						'search_enabled' => false,
						'navigation_enabled' => false,
						'admin_config_ok' => $adminConfigStatus,
						'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
						'oidc_user' => false,
					]
				],
				['oauth-connection-result'],
				['oauth-connection-error-message']
			);

		$form = $this->setting->getForm();
		$expected = new TemplateResponse('integration_openproject', 'personalSettings');
		$this->assertEquals($expected, $form);
	}

	/**
	 * @return void
	 */
	public function testNoPersonalSettingsShouldUseValueFromTheDefaults() {
		$this->config
			->method('getUserValue')
			->withConsecutive(
				['testUser', 'integration_openproject', 'token'],
				['testUser', 'integration_openproject', 'user_name'],
				['testUser', 'integration_openproject', 'search_enabled', '1'],
				['testUser', 'integration_openproject', 'navigation_enabled', '1'],
			)
			->willReturnOnConsecutiveCalls(
				'some-token',
				'some-username',
				'1', '1',
			);
		$this->config
			->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'authorization_method', '', OpenProjectAPIService::AUTH_METHOD_OAUTH],
				[Application::APP_ID, 'openproject_instance_url', '', 'http://some.url'],
				[Application::APP_ID, 'openproject_client_id', '', 'op-client'],
				[Application::APP_ID, 'openproject_client_secret', '', 'op-secret'],
				[Application::APP_ID, 'fresh_project_folder_setup', '', false],
				[Application::APP_ID, 'nc_oauth_client_id', '', 'nc-client'],
				[Application::APP_ID, 'default_enable_unified_search', '0', '1'],
				[Application::APP_ID, 'default_enable_navigation', '0', '1'],
			]);
		$this->initialState
			->method('provideInitialState')
			->withConsecutive(
				[
					'user-config', [
						'token' => 'some-token',
						'user_name' => 'some-username',
						'search_enabled' => true,
						'navigation_enabled' => true,
						'admin_config_ok' => true,
						'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
						'oidc_user' => false,
					]
				],
				['oauth-connection-result'],
				['oauth-connection-error-message']
			);
		$form = $this->setting->getForm();
		$expected = new TemplateResponse('integration_openproject', 'personalSettings');
		$this->assertEquals($expected, $form);
	}
}
