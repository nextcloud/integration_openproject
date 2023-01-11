<?php
/**
 * @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OpenProject\Dashboard;

use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Services\IInitialState;
use OCP\Dashboard\IWidget;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;

use OCA\OpenProject\AppInfo\Application;

class OpenProjectWidget implements IWidget {

	/**
	 * @var IL10N
	 */
	private $l10n;
	/**
	 * @var IURLGenerator
	 */
	private $url;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * @var IUser
	 */
	private $user;

	public function __construct(
		IL10N $l10n,
		IInitialState $initialStateService,
		IURLGenerator $url,
		IConfig $config,
		IUserSession $userSession
	) {
		$this->initialStateService = $initialStateService;
		$this->l10n = $l10n;
		$this->url = $url;
		$this->config = $config;
		$this->user = $userSession->getUser();
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'openproject_notifications';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('OpenProject');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		$iconClass = 'icon-openproject';
		$currNcVersion = $this->config->getSystemValue('version', '0.0.0.0');

		switch ($currNcVersion) {
			case (version_compare($currNcVersion, '25', "<")):
				return $iconClass . ' nc-before-v24';
			default:
				return $iconClass;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return $this->url->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']);
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-dashboard');
		Util::addStyle(Application::APP_ID, 'dashboard');

		$this->initialStateService->provideInitialState('admin-config-status', OpenProjectAPIService::isAdminConfigOk($this->config));

		$oauthConnectionResult = $this->config->getUserValue(
			$this->user->getUID(), Application::APP_ID, 'oauth_connection_result', ''
		);
		$this->config->deleteUserValue(
			$this->user->getUID(), Application::APP_ID, 'oauth_connection_result'
		);
		$this->initialStateService->provideInitialState(
			'oauth-connection-result', $oauthConnectionResult
		);
		$oauthConnectionErrorMessage = $this->config->getUserValue(
			$this->user->getUID(), Application::APP_ID, 'oauth_connection_error_message', ''
		);
		$this->config->deleteUserValue(
			$this->user->getUID(), Application::APP_ID, 'oauth_connection_error_message'
		);
		$this->initialStateService->provideInitialState(
			'oauth-connection-error-message', $oauthConnectionErrorMessage
		);
	}
}
