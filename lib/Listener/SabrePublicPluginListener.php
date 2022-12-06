<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU Affero General Public License v3.0 or later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OpenProject\Listener;

use OCA\Files\Event\LoadSidebar;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Sabre\CorsPlugin;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\SabrePublicPluginEvent;
use OCP\Util;

class SabrePublicPluginListener implements IEventListener {

	/**
	 * @var CorsPlugin
	 */
	private $corsPlugin;

	public function __construct(CorsPlugin $corsPlugin) {
		$this->corsPlugin = $corsPlugin;
	}

	public function handle(Event $event): void {
		error_log('LISTENER');
		if (!($event instanceof SabrePublicPluginEvent)) {
			return;
		}

		$event->getServer()->addPlugin($this->corsPlugin);
	}
}
