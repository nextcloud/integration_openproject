<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use InvalidArgumentException;
use OCA\OpenProject\AppInfo\Application;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase {
	/**
	 * Format has to be [<string> => <object|string>] with the first being the constructor parameter name and the second one the mock.
	 * Example: ['config' => $createdMockObject]
	 * @param array<string, object|string> $constructParams specific mocks for the constructor of OpenProjectAPIService
	 *
	 * @return array
	 */
	private function getSettingsServiceConstructArgs(array $constructParams = []): array {
		$constructArgs = [
			// order should be the same as in the constructor
			'userManager' => $this->createMock(IUserManager::class),
			'groupManager' => $this->createMock(IGroupManager::class),
			'openProjectAPIService' => $this->createMock(OpenProjectAPIService::class),
			'secureRandom' => $this->createMock(ISecureRandom::class),
			'subAdmin' => $this->createMock(ISubAdmin::class),
		];

		// replace default mocks with manually passed in mocks
		foreach ($constructParams as $key => $value) {
			if (!array_key_exists($key, $constructArgs)) {
				throw new InvalidArgumentException("Invalid construct parameter: $key");
			}

			$constructArgs[$key] = $value;
		}

		return array_values($constructArgs);
	}

	/**
	 * @param array<string> $mockMethods
	 * @param array<string, object> $constructParams
	 *
	 * @return MockObject|SettingsService
	 */
	private function getSettingsServiceMock(
		array $mockMethods = [],
		array $constructParams = [],
	): MockObject|SettingsService {
		$constructArgs = $this->getSettingsServiceConstructArgs($constructParams);
		$mock = $this->getMockBuilder(SettingsService::class)
			->setConstructorArgs($constructArgs)
			->onlyMethods($mockMethods)
			->getMock();
		return $mock;
	}

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
		$service = $this->getSettingsServiceMock();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		$service->validateAdminSettingsForm($configs, $completeSetup);
	}

	/**
	 * @dataProvider validSettingsProvider
	 */
	public function testValidateAdminSettingsFormValid(array $configs, bool $completeSetup): void {
		$service = $this->getSettingsServiceMock();

		$this->assertNull($service->validateAdminSettingsForm($configs, $completeSetup));
	}

	public function setProjectFolderDataProvider(): array {
		return [
			'system is not ready' => [
				'systemReady' => false,
				'tosEnabled' => false,
			],
			'system is ready' => [
				'systemReady' => true,
				'tosEnabled' => true,
			],
			'system is ready without TOS' => [
				'systemReady' => true,
				'tosEnabled' => false,
			],
		];
	}

	/**
	 * @dataProvider setProjectFolderDataProvider
	 * @param bool $systemReady
	 * @param bool $tosEnabled
	 *
	 * @return void
	 */
	public function testSetupProjectFolder(bool $systemReady, bool $tosEnabled): void {
		$user = $this->createMock(IUser::class);
		$group = $this->createMock(IGroup::class);
		$groupAll = $this->createMock(IGroup::class);
		$userManager = $this->createMock(IUserManager::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$subAdmin = $this->createMock(ISubAdmin::class);
		$opApiService = $this->createMock(OpenProjectAPIService::class);
		$opApiService
			->expects($this->once())
			->method('isSystemReadyForProjectFolderSetUp')
			->willReturn($systemReady);

		$subAdminCalls = [];
		$expectedSubAdminCalls = [];

		if ($systemReady) {
			$expectedSubAdminCalls[] = [$user, $group];
			$expectedSubAdminCalls[] = [$user, $groupAll];

			$userManager->expects($this->once())
				->method('createUser')
				->with(Application::OPEN_PROJECT_ENTITIES_NAME)
				->willReturn($user);
			$group->expects($this->once())->method('addUser')->with($user);
			$groupAll->expects($this->once())->method('addUser')->with($user);
			$groupManager->expects($this->exactly(2))
				->method('createGroup')
				->willReturnMap([
					[Application::OPEN_PROJECT_ENTITIES_NAME, $group],
					[Application::OPENPROJECT_ALL_GROUP_NAME, $groupAll]
				]);
			$subAdmin->expects($this->exactly(2))
				->willReturnCallback(function () use (&$subAdminCalls) {
					$subAdminCalls[] = func_get_args();
				})
				->method('createSubAdmin');

			$opApiService
				->expects($this->once())
				->method('createGroupfolder');
			$opApiService
				->expects($this->once())
				->method('isTermsOfServiceAppEnabled')
				->willReturn($tosEnabled);
			if ($tosEnabled) {
				$userManager->expects($this->once())->method('userExists')->willReturn(true);
				$opApiService
					->expects($this->once())
					->method('signTermsOfServiceForUserOpenProject');
			} else {
				$opApiService
					->expects($this->never())
					->method('signTermsOfServiceForUserOpenProject');
			}
		} else {
			$userManager->expects($this->never())->method('createUser');
			$groupManager->expects($this->never())->method('createGroup');
			$opApiService
				->expects($this->never())
				->method('createGroupfolder');
			$opApiService
				->expects($this->never())
				->method('isTermsOfServiceAppEnabled');
		}
		$service = $this->getSettingsServiceMock(
			[],
			[
				'userManager' => $userManager,
				'groupManager' => $groupManager,
				'openProjectAPIService' => $opApiService,
				'subAdmin' => $subAdmin,
			],
		);

		$service->setupProjectFolder();
		$this->assertSame($expectedSubAdminCalls, $subAdminCalls);
	}
}
