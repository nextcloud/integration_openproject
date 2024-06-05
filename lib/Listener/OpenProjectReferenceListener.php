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

	public function __construct(
		IInitialState $initialStateService,
		IConfig $config
	) {
		$this->initialStateService = $initialStateService;
		$this->config = $config;
	}
	public function handle(Event $event): void {
		if (!$event instanceof RenderReferenceEvent) {
			return;
		}

		Util::addScript(Application::APP_ID, Application::APP_ID . '-reference');
		$this->initialStateService->provideInitialState('admin-config-status', OpenProjectAPIService::isAdminConfigOk($this->config));

		$this->initialStateService->provideInitialState(
			'openproject-url',
			$this->config->getAppValue(Application::APP_ID, 'openproject_instance_url')
		);
	}
}
