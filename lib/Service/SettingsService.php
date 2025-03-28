<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use InvalidArgumentException;

class SettingsService {
	public const AUTH_METHOD_OAUTH = 'oauth2';
	public const AUTH_METHOD_OIDC = 'oidc';
	public const NEXTCLOUDHUB_OIDC_PROVIDER_TYPE = "nextcloud_hub";
	public const EXTERNAL_OIDC_PROVIDER_TYPE = "external";
	public const NEXTCLOUDHUB_OIDC_PROVIDER_LABEL = "Nextcloud Hub";
	// <setting_name> => <data_type>
	private const GENERAL_ADMIN_SETTINGS = [
		// general settings
		'openproject_instance_url' => 'string',
		'authorization_method' => [self::AUTH_METHOD_OAUTH, self::AUTH_METHOD_OIDC],
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
		'sso_provider_type' => [self::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE, self::EXTERNAL_OIDC_PROVIDER_TYPE],
		'oidc_provider' => 'string',
		'targeted_audience_client_id' => 'string',
		'token_exchange' => 'boolean',
	];

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
	private function getAllSettingsType(): array {
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
		return \array_keys($this->getAllSettingsType());
	}

	/**
	 * @param array<string, string|null|bool> $values
	 * @param bool $completeSetup
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 */
	public function validateAdminSettingsForm(?array $values, bool $completeSetup = false): void {
		if (!$values) {
			throw new InvalidArgumentException('The data is not a valid JSON.');
		}

		$settingsToCheck = \array_keys($values);
		$settingsToSkip = [];

		if ($completeSetup) {
			if (!\in_array('authorization_method', $settingsToCheck)) {
				throw new InvalidArgumentException("'authorization_method' setting is missing");
			}
			$authMethod = $values['authorization_method'];
			if (!\in_array($authMethod, [self::AUTH_METHOD_OAUTH, self::AUTH_METHOD_OIDC])) {
				throw new InvalidArgumentException('Invalid authorization method');
			}
			if ($authMethod === self::AUTH_METHOD_OAUTH) {
				$settings = $this->getCompleteOAuthSettings();
			} else {
				$settings = $this->getCompleteOIDCSettings();
				if (!\array_key_exists('sso_provider_type', $values)) {
					throw new InvalidArgumentException(
						"Incomplete settings: 'sso_provider_type' is required with '"
						. self::AUTH_METHOD_OIDC
						. "' method"
					);
				}
				if ($values['sso_provider_type'] === self::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE) {
					// for 'nextcloud_hub' type
					// 'oidc_provider' and 'token_exchange' settings are not required
					$settingsToSkip[] = 'oidc_provider';
					$settingsToSkip[] = 'token_exchange';
				} elseif ($values['sso_provider_type'] === self::EXTERNAL_OIDC_PROVIDER_TYPE) {
					if (!\array_key_exists('token_exchange', $values)) {
						throw new InvalidArgumentException(
							"Incomplete settings: 'token_exchange' is required with external provider"
						);
					}
					// for 'external' type and disabled 'token_exchange'
					// 'targeted_audience_client_id' setting is not required
					if ($values['token_exchange'] === false) {
						$settingsToSkip[] = 'targeted_audience_client_id';
					}
				}
			}

			// check if all required settings are present
			foreach ($settings as $key) {
				if (\in_array($key, $settingsToSkip)) {
					continue;
				}
				if (!\in_array($key, $settingsToCheck)) {
					// throw new InvalidArgumentException('Incomplete settings');
					// for error message compatibility
					throw new InvalidArgumentException('invalid key');
				}
			}
			// check if there are no unknown settings
			foreach ($settingsToCheck as $key) {
				if (!in_array($key, $settings)) {
					// throw new InvalidArgumentException("Unknown setting: $key");
					// for error message compatibility
					throw new InvalidArgumentException('invalid key');
				}
			}

			if (($values['setup_project_folder'] === true && $values['setup_app_password'] === false) ||
				($values['setup_project_folder'] === false && $values['setup_app_password'] === true)
			) {
				// throw new InvalidArgumentException('Invalid project folder settings');
				// for error message compatibility
				throw new InvalidArgumentException('invalid data');
			}
		} else {
			$settings = $this->getAllSettings();
			foreach ($settingsToCheck as $key) {
				if (!in_array($key, $settings)) {
					// throw new InvalidArgumentException("Unknown setting: $key");
					// for error message compatibility
					throw new InvalidArgumentException('invalid key');
				}
			}
		}

		// validate datatype
		$settingsType = $this->getAllSettingsType();
		foreach ($values as $key => $value) {
			if (!$this->hasValidType($value, $settingsType[$key])) {
				// throw new InvalidArgumentException("Invalid data type: $key");
				// for error message compatibility
				throw new InvalidArgumentException('invalid data');
			}
			if ($value === '') {
				// throw new InvalidArgumentException("Invalid setting value: $key");
				// for error message compatibility
				throw new InvalidArgumentException('invalid data');
			}
			if ($key === 'openproject_instance_url' && !$this->isValidURL((string)$value)) {
				// throw new InvalidArgumentException('Invalid URL');
				// for error message compatibility
				throw new InvalidArgumentException('invalid data');
			}
		}
	}

	/**
	 * @param mixed $value
	 * @param string|array $type
	 *
	 * @return bool
	 */
	private function hasValidType(mixed $value, string|array $type): bool {
		if (\is_array($type)) {
			return \in_array($value, $type);
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
}
