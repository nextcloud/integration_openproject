<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase {
	public function invalidSettingsProvider(): array {
		return [
			"Random missing settings" => [
				"configs" => [
					"authorization_method" => SettingsService::AUTH_METHOD_OAUTH,
				],
				"completeSetup" => true,
				"message" => "invalid key",
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
				"message" => "invalid key",
			],
			"incomplete settings(oidc): missing 'sso_provider_type'" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OIDC,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					"oidc_provider" => "Nextcloud Hub",
					"token_exchange" => false,
					"targeted_audience_client_id" => "test",
					"setup_project_folder" => false,
					"setup_app_password" => false,
				],
				"completeSetup" => true,
				"message" => "Incomplete settings: 'sso_provider_type' is required with 'oidc' method",
			],
			"incomplete settings (oidc): missing 'token_exchange' with external provider" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OIDC,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					'sso_provider_type' => SettingsService::EXTERNAL_OIDC_PROVIDER_TYPE,
					"oidc_provider" => "Nextcloud Hub",
					"setup_project_folder" => false,
					"setup_app_password" => false,
				],
				"completeSetup" => true,
				"message" => "Incomplete settings: 'token_exchange' is required with external provider",
			],
			"invalid oidc provider type" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OIDC,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					'sso_provider_type' => 'test', // invalid provider type
					"oidc_provider" => "Nextcloud Hub",
					"token_exchange" => false,
					"targeted_audience_client_id" => "test",
					"setup_project_folder" => false,
					"setup_app_password" => false,
				],
				"completeSetup" => true,
				"message" => "invalid data",
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
				"message" => "invalid data",
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
				"message" => "invalid data",
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
				"message" => "invalid data",
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
				"message" => "invalid data",
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
				"message" => "invalid key",
			],
			"update action - unknown settings" => [
				"configs" => [
					"default_enable_navigation" => true,
					"test_setting" => 'test', // unknown setting
				],
				"completeSetup" => false,
				"message" => "invalid key",
			],
			"update action - invalid url" => [
				"configs" => [
					"openproject_instance_url" => "test", // invalid URL
				],
				"completeSetup" => false,
				"message" => "invalid data",
			],
		];
	}

	public function validSettingsProvider(): array {
		return [
			"complete settings: oauth2" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OAUTH,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					"openproject_client_id" => "test",
					"openproject_client_secret" => "test",
					"setup_project_folder" => false,
					"setup_app_password" => false,
				],
				"completeSetup" => true,
			],
			"complete settings: oidc" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OIDC,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					"sso_provider_type" => SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE,
					"oidc_provider" => "Nextcloud Hub",
					"token_exchange" => false,
					"targeted_audience_client_id" => "test",
					"setup_project_folder" => true,
					"setup_app_password" => true,
				],
				"completeSetup" => true,
			],
			"complete settings (oidc): missing 'token_exchange' with NC Hub" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OIDC,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					"sso_provider_type" => SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE,
					"oidc_provider" => "Nextcloud Hub",
					"targeted_audience_client_id" => "test",
					"setup_project_folder" => true,
					"setup_app_password" => true,
				],
				"completeSetup" => true,
			],
			"complete settings (oidc): missing 'targeted_audience_client_id' with external and disabled token exchange" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op.example",
					"authorization_method" => SettingsService::AUTH_METHOD_OIDC,
					"default_enable_navigation" => false,
					"default_enable_unified_search" => false,
					"sso_provider_type" => SettingsService::EXTERNAL_OIDC_PROVIDER_TYPE,
					"oidc_provider" => "Nextcloud Hub",
					"token_exchange" => false,
					"setup_project_folder" => true,
					"setup_app_password" => true,
				],
				"completeSetup" => true,
			],
			"update settings" => [
				"configs" => [
					"openproject_instance_url" => "http://test.op",
					"default_enable_navigation" => true,
				],
				"completeSetup" => false,
			],
		];
	}

	/**
	 * @dataProvider invalidSettingsProvider
	 */
	public function testValidateAdminSettingsFormInvalid(array $configs, bool $completeSetup, string $message): void {
		$service = new SettingsService();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		$service->validateAdminSettingsForm($configs, $completeSetup);
	}

	/**
	 * @dataProvider validSettingsProvider
	 */
	public function testValidateAdminSettingsFormValid(array $configs, bool $completeSetup): void {
		$service = new SettingsService();

		$this->assertNull($service->validateAdminSettingsForm($configs, $completeSetup));
	}
}
