<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase {
	public function incompleteSettingsProvider(): array {
		return [
			"Random missing settings" => [
				"configs" => [
					"authorization_method" => SettingsService::AUTH_METHOD_OAUTH,
				],
				"completeSetup" => true,
				"message" => "Incomplete settings",
			],
			"missing 'authorization_method' setting" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
				],
				"completeSetup" => true,
				"message" => "'authorization_method' setting is missing",
			],
			"invalid 'authorization_method' value" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => "test",
				],
				"completeSetup" => true,
				"message" => "Invalid authorization method",
			],
			"incomplete settings: oauth2" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OAUTH,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					'openproject_client_id' => 'test',
					"setup_project_folder" => false,
					"setup_app_password" => false,
				],
				"completeSetup" => true,
				"message" => "Incomplete settings",
			],
			"incomplete settings: oidc" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OIDC,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					'sso_provider_type' => SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE,
					"oidc_provider" => "Nextcloud Hub",
					"setup_project_folder" => false,
					"setup_app_password" => false,
				],
				"completeSetup" => true,
				"message" => "Incomplete settings",
			],
			"invalid oidc provider type" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OIDC,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					'sso_provider_type' => 'test', // invalid provider type
					"oidc_provider" => "Nextcloud Hub",
					"targeted_audience_client_id" => "test",
					"setup_project_folder" => false,
					"setup_app_password" => false,
				],
				"completeSetup" => true,
				"message" => "Invalid data type: sso_provider_type",
			],
			"invalid groupfolder settings: true & false" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OAUTH,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					'openproject_client_id' => 'test',
					'openproject_client_secret' => 'test',
					"setup_project_folder" => true,
					"setup_app_password" => false,
				],
				"completeSetup" => true,
				"message" => "Invalid project folder settings",
			],
			"invalid groupfolder settings: false & true" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OAUTH,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					'openproject_client_id' => 'test',
					'openproject_client_secret' => 'test',
					"setup_project_folder" => false,
					"setup_app_password" => true,
				],
				"completeSetup" => true,
				"message" => "Invalid project folder settings",
			],
			"invalid settings value: incorrect data type" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OAUTH,
					"default_enable_navigation" => 'false', // string instead of boolean
					"default_enable_unified_search" => false,
					'openproject_client_id' => 'test',
					'openproject_client_secret' => 'test',
					"setup_project_folder" => false,
					"setup_app_password" => false,
				],
				"completeSetup" => true,
				"message" => "Invalid data type: default_enable_navigation",
			],
			"invalid settings value: empty string" => [
				"configs" => [
					"openproject_instance_url" => "", // empty string
					"authorization_method" => SettingsService::AUTH_METHOD_OAUTH,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					'openproject_client_id' => 'test',
					'openproject_client_secret' => 'test',
					"setup_project_folder" => false,
					"setup_app_password" => false,
				],
				"completeSetup" => true,
				"message" => "Invalid setting value: openproject_instance_url",
			],
			"unknown settings" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OAUTH,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					'openproject_client_id' => 'test',
					'openproject_client_secret' => 'test',
					"setup_project_folder" => false,
					"setup_app_password" => false,
					"test_setting" => 'test', // unknown setting
				],
				"completeSetup" => true,
				"message" => "Unknown setting: test_setting",
			],
			"update action - unknown settings" => [
				"configs" => [
					"default_enable_navigation" => true,
					"test_setting" => 'test', // unknown setting
				],
				"completeSetup" => false,
				"message" => "Unknown setting: test_setting",
			],
			"update action - invalid url" => [
				"configs" => [
					"openproject_instance_url" => "test", // invalid URL
				],
				"completeSetup" => false,
				"message" => "Invalid URL",
			],
		];
	}

	/**
	 * @dataProvider incompleteSettingsProvider
	 */
	public function testValidateAdminSettingsForm(array $configs, bool $completeSetup, string $message): void {
		$service = new SettingsService();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		$service->validateAdminSettingsForm($configs, $completeSetup);
	}
}
