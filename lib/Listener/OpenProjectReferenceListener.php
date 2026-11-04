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
use OCP\IAppConfig;
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
	 * @var IAppConfig
	 */
	private $appConfig;
	/**
	 * @var OpenProjectAPIService
	 */
	private $openProjectAPIService;

	public function __construct(
		IInitialState $initialStateService,
		IAppConfig $appConfig,
		OpenProjectAPIService $openProjectAPIService,
		private ?string $userId,
	) {
		$this->initialStateService = $initialStateService;
		$this->appConfig = $appConfig;
		$this->openProjectAPIService = $openProjectAPIService;
	}
	public function handle(Event $event): void {
		// When user is non oidc based or there is some error when getting token for the targeted client
		// then we need to hide the oidc based connection for the user
		// so this check is required
		if (
			$this->appConfig->getValueString(Application::APP_ID, 'authorization_method', '') === Application::AUTH_METHOD_OIDC &&
			!$this->openProjectAPIService->getAccessToken($this->userId)
		) {
			return;
		}
		if (!$event instanceof RenderReferenceEvent) {
			return;
		}
		Util::addScript(Application::APP_ID, Application::APP_ID . '-reference');
		$adminConfig = [
			'isAdminConfigOk' => OpenProjectAPIService::isAdminConfigOk($this->appConfig),
			'authMethod' => $this->appConfig->getValueString(Application::APP_ID, 'authorization_method', '')
		];
		$this->initialStateService->provideInitialState(
			'admin-appConfig',
			$adminConfig
		);
		$this->initialStateService->provideInitialState(
			'openproject-url',
			$this->appConfig->getValueString(Application::APP_ID, 'openproject_instance_url')
		);
	}
}
