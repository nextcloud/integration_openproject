<?php

/**
 * @copyright Copyright (c) 2023 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
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
 */

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Services\IInitialState;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class OpenProjectReferenceListener implements IEventListener {

	/**
	 * @var IInitialState
	 */
	private $initialStateService;

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var OpenProjectAPIService
	 */
	private $openProjectAPIService;

	public function __construct(
		IInitialState $initialStateService,
		IConfig $config,
		OpenProjectAPIService $openProjectAPIService,
	) {
		$this->initialStateService = $initialStateService;
		$this->config = $config;
		$this->openProjectAPIService = $openProjectAPIService;
	}
	public function handle(Event $event): void {
		// When user is non oidc based or there is some error when getting token for the targeted client
		// then we need to hide the oidc based connection for the user
		// so this check is required
		if (
			$this->config->getAppValue(Application::APP_ID, 'authorization_method', '') === OpenProjectAPIService::AUTH_METHOD_OIDC &&
			$this->openProjectAPIService->getOIDCToken() === null
		) {
			return;
		}
		if (!$event instanceof RenderReferenceEvent) {
			return;
		}
		Util::addScript(Application::APP_ID, Application::APP_ID . '-reference');
		$adminConfig = [
			'isAdminConfigOk' => OpenProjectAPIService::isAdminConfigOk($this->config),
			'authMethod' => $this->config->getAppValue(Application::APP_ID, 'authorization_method', '')
		];
		$this->initialStateService->provideInitialState(
			'admin-config',
			$adminConfig
		);
		$this->initialStateService->provideInitialState(
			'openproject-url',
			$this->config->getAppValue(Application::APP_ID, 'openproject_instance_url')
		);
	}
}
