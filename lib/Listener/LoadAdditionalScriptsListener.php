<?php

/**
 * SPDX-FileCopyrightText: 2023-2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\ServerVersionHelper;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\Util;

/**
 *	@template-implements IEventListener<Event>
 */
class LoadAdditionalScriptsListener implements IEventListener {

	/**
	 * @var OpenProjectAPIService
	 */
	private $openProjectAPIService;
	/**
	 * @var IConfig
	 */
	private $config;

	public function __construct(
		IConfig $config,
		OpenProjectAPIService $openProjectAPIService,
		private ?string $userId,
	) {
		$this->config = $config;
		$this->openProjectAPIService = $openProjectAPIService;
	}

	public function handle(Event $event): void {
		// When user is non oidc based or there is some error when getting token for the targeted client
		// then we need to hide the oidc based connection for the user
		// so this check is required
		if (
			$this->config->getAppValue(Application::APP_ID, 'authorization_method', '') === OpenProjectAPIService::AUTH_METHOD_OIDC &&
			!$this->config->getUserValue($this->userId, Application::APP_ID, 'token')
		) {
			return;
		}
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}
		if (version_compare(ServerVersionHelper::getNextcloudVersion(), '28') < 0) {
			Util::addScript(Application::APP_ID, Application::APP_ID . '-fileActions');
			Util::addScript(Application::APP_ID, Application::APP_ID . '-filesPluginLessThan28', 'files');
		} else {
			Util::addScript(Application::APP_ID, Application::APP_ID . '-filesPlugin');
		}
	}
}
