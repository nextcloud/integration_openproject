<?php

/**
 * @copyright Copyright (c) 2023, Swikriti Tripathi <swikriti@jankaritech.com>
 *
 * @author Swikriti Tripathi <swikriti@jankaritech.com>
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
namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

/**
 * @template-implements IEventListener<Event>
 */
class AddContentSecurityPolicyListener implements IEventListener {
	/**
	 * @var IConfig
	 */
	protected $config;

	/**
	 * @var IRequest
	 */
	protected $request;

	public function __construct(IConfig $config, IRequest $request) {
		$this->config = $config;
		$this->request = $request;
	}

	public function handle(Event $event): void {
		if (!($event instanceof AddContentSecurityPolicyEvent)) {
			return;
		}

		// only allow through csp if the page is `index.php` i.e files app
//		if (!$this->isPageLoad()) {
//			return;
//		}

		$csp = new ContentSecurityPolicy();
		$baseUrl = $this->config->getAppValue(Application::APP_ID, 'openproject_instance_url', '');
		$csp->addAllowedFrameDomain($baseUrl);
		$event->addPolicy($csp);
	}

//	private function isPageLoad(): bool {
//		$scriptNameParts = explode('/', $this->request->getScriptName());
//		return end($scriptNameParts) === 'index.php';
//	}
}
