<?php

/**
 * SPDX-FileCopyrightText: 2023-2025 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
			!$this->openProjectAPIService->getOIDCToken()
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
