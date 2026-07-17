<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use InvalidArgumentException;
use OCA\OpenProject\AppInfo\Application;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;

class SettingsService {
	// <setting_name> => <data_type>
	private const GENERAL_ADMIN_SETTINGS = [
		// general settings
		'openproject_instance_url' => 'string',
		'authorization_method' => [Application::AUTH_METHOD_OAUTH, Application::AUTH_METHOD_OIDC],
		'default_enable_navigation' => 'boolean',
		'default_enable_unified_search' => 'boolean',
		// groupfolders settings
		'setup_project_folder' => 'boolean',
		'setup_app_password' => 'boolean',
	];
	private const OAUTH_ADMIN_SETTINGS = [
		'openproject_client_id' => 'string',
		'openproject_client_secret' => 'string',
	];
	private const OIDC_ADMIN_SETTINGS = [
		'sso_provider_type' => [Application::NEXTCLOUD_HUB_OIDC_PROVIDER_TYPE, Application::EXTERNAL_OIDC_PROVIDER_TYPE],
		'oidc_provider' => 'string',
		'targeted_audience_client_id' => 'string',
		'token_exchange' => 'boolean',
	];

	public function __construct(
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private OpenProjectAPIService $openprojectAPIService,
		private ISecureRandom $secureRandom,
		private ISubAdmin $subAdmin,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getDefaultSettings(): array {
		$settings = $this->getAllSettings();
		$defaultSettings = \array_reduce($settings, function ($acc, $setting) {
			$acc[$setting] = null;
			return $acc;
		}, []);
		return $defaultSettings;
	}

	/**
	 * @return array<string, string|array>
	 */
	private function getAllSettingsSchema(): array {
		return \array_merge(self::GENERAL_ADMIN_SETTINGS, self::OAUTH_ADMIN_SETTINGS, self::OIDC_ADMIN_SETTINGS);
	}

	/**
	 * @return array<string>
	 */
	public function getCompleteOAuthSettings(): array {
		return \array_keys(\array_merge(self::GENERAL_ADMIN_SETTINGS, self::OAUTH_ADMIN_SETTINGS));
	}

	/**
	 * @return array<string>
	 */
	public function getCompleteOIDCSettings(): array {
		return \array_keys(\array_merge(self::GENERAL_ADMIN_SETTINGS, self::OIDC_ADMIN_SETTINGS));
	}

	/**
	 * @return array<string>
	 */
	public function getAllSettings(): array {
		return \array_keys($this->getAllSettingsSchema());
	}

	/**
	 * @param array<string> $settingsToCheck
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 */
	private function checkUnknownSettings(array $settingsToCheck): void {
		$allowedSettings = $this->getAllSettings();
		foreach ($settingsToCheck as $key) {
			if (!\in_array($key, $allowedSettings, true)) {
				throw new InvalidArgumentException('Unknown setting: ' . (string)$key);
			}
		}
	}

	/**
	 * @param array<string, string|null|bool> $settings
	 *
	 * @return array<string>
	 * @throws InvalidArgumentException
	 */
	private function getRequiredOIDCSettings(array $settings): array {
		$allSettings = $this->getCompleteOIDCSettings();
		$settingsToSkip = [];
		if (!\array_key_exists('sso_provider_type', $settings)) {
			throw new InvalidArgumentException('Missing required field: sso_provider_type');
		}

		$providerType = $settings['sso_provider_type'];
		if ($providerType === Application::NEXTCLOUD_HUB_OIDC_PROVIDER_TYPE) {
			// for 'nextcloud_hub' type
			// 'oidc_provider' and 'token_exchange' settings are not required
			$settingsToSkip[] = 'oidc_provider';
			$settingsToSkip[] = 'token_exchange';
		} elseif ($providerType === Application::EXTERNAL_OIDC_PROVIDER_TYPE) {
			if (!\array_key_exists('token_exchange', $settings)) {
				throw new InvalidArgumentException('Missing required field: token_exchange');
			}
			// for 'external' type and disabled 'token_exchange'
			// 'targeted_audience_client_id' setting is not required
			if ($settings['token_exchange'] === false) {
				$settingsToSkip[] = 'targeted_audience_client_id';
			}
		} else {
			// let the validator check for 'sso_provider_type' value
			$settingsToSkip[] = 'oidc_provider';
			$settingsToSkip[] = 'token_exchange';
			$settingsToSkip[] = 'targeted_audience_client_id';
		}

		return \array_diff($allSettings, $settingsToSkip);
	}

	/**
	 * @param array<string, string|null|bool> $settings
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 */
	private function checkRequiredSettings(array $settings): void {
		$requiredFields = ['openproject_instance_url', 'authorization_method'];
		foreach ($requiredFields as $field) {
			if (!\array_key_exists($field, $settings)) {
				throw new InvalidArgumentException('Missing required field: ' . $field);
			}
		}

		$authMethod = $settings['authorization_method'];
		if (!\in_array($authMethod, self::GENERAL_ADMIN_SETTINGS['authorization_method'], true)) {
			throw new InvalidArgumentException('Invalid authorization method.');
		}

		// check settings based on authorization method
		if ($authMethod === Application::AUTH_METHOD_OAUTH) {
			$requiredFields = $this->getCompleteOAuthSettings();
		} else {
			$requiredFields = $this->getRequiredOIDCSettings($settings);
		}
		// check if all required settings are present
		foreach ($requiredFields as $key) {
			if (!\array_key_exists($key, $settings)) {
				throw new InvalidArgumentException('Missing required field: ' . $key);
			}
		}
	}

	/**
	 * @param array<string, string|null|bool> $settings
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 */
	private function validateSettingsDataType(array $settings): void {
		$settingsSchema = $this->getAllSettingsSchema();
		foreach ($settings as $key => $value) {
			if (!$this->hasValidDataType($value, $settingsSchema[$key]) || $value === '') {
				throw new InvalidArgumentException('Invalid value for setting: ' . $key);
			}
			if ($key === 'openproject_instance_url' && !$this->isValidURL((string)$value)) {
				throw new InvalidArgumentException('Invalid OpenProject URL.');
			}
		}
	}

	/**
	 * @param array<string, string|null|bool> $values
	 * @param bool $fullSetup
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 */
	public function validateAdminSettingsForm(?array $values, bool $fullSetup = false): void {
		if (!$values) {
			throw new InvalidArgumentException('Invalid settings.');
		}

		$this->checkUnknownSettings(\array_keys($values));

		if ($fullSetup) {
			$this->checkRequiredSettings($values);

			if (($values['setup_project_folder'] === true && $values['setup_app_password'] === false) ||
				($values['setup_project_folder'] === false && $values['setup_app_password'] === true)
			) {
				$errMessage = 'Invalid team folder setup configuration: '
					. 'Both "setup_project_folder" and "setup_app_password" must be either true or false.';
				throw new InvalidArgumentException($errMessage);
			}
		}

		// validate datatype
		$this->validateSettingsDataType($values);
	}

	/**
	 * @param mixed $value
	 * @param string|array $type
	 *
	 * @return bool
	 */
	private function hasValidDataType(mixed $value, string|array $type): bool {
		if (\is_array($type)) {
			return \in_array($value, $type, true);
		}
		return $type === \gettype($value);
	}

	/**
	 * @param string $url
	 *
	 * @return bool
	 */
	private function isValidURL(string $url): bool {
		return filter_var($url, FILTER_VALIDATE_URL) &&
			preg_match('/^https?/', $url);
	}

	/**
	 * @return void
	 */
	public function setupProjectFolder(): void {
		$isSystemReady = $this->openprojectAPIService->isSystemReadyForProjectFolderSetUp();
		if ($isSystemReady) {
			$password = $this->secureRandom->generate($this->openprojectAPIService->getPasswordLength(), ISecureRandom::CHAR_ALPHANUMERIC.ISecureRandom::CHAR_SYMBOLS);
			$user = $this->userManager->createUser(Application::OPEN_PROJECT_ENTITIES_NAME, $password);
			$group = $this->groupManager->createGroup(Application::OPEN_PROJECT_ENTITIES_NAME);
			$allGroup = $this->groupManager->createGroup(Application::OPENPROJECT_ALL_GROUP_NAME);
			$group->addUser($user);
			$allGroup->addUser($user);
			$this->subAdmin->createSubAdmin($user, $group);
			$this->subAdmin->createSubAdmin($user, $allGroup);
			$this->openprojectAPIService->createGroupfolder();
			if ($this->openprojectAPIService->isTermsOfServiceAppEnabled() && $this->userManager->userExists(Application::OPEN_PROJECT_ENTITIES_NAME)) {
				$this->openprojectAPIService->signTermsOfServiceForUserOpenProject();
			}
		}
	}
}
