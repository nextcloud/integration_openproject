<?php

namespace OCA\OpenProject\Listener;

use OC_Util;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\OpenProject\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\ServerVersion;
use OCP\Util;

/**
 *	@template-implements IEventListener<Event>
 */
class LoadAdditionalScriptsListener implements IEventListener {
	/**
	 * @var ServerVersion
	 */
	private $serverVersion;

	public function __construct(
		ServerVersion $serverVersion = null
	) {
		if (class_exists('OCP\ServerVersion')) {
			$this->serverVersion = $serverVersion;
		}
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}

		// for nextcloud above 31 OC_Util::getVersionString() method not exists
		if ($this->serverVersion !== null) {
			$nextcloudVersion = $this->serverVersion->getVersionString();
		} else {
			/** @psalm-suppress UndefinedMethod getVersionString() method is not in stable31 so making psalm not complain*/
			$nextcloudVersion = OC_Util::getVersionString();
		}

		if (version_compare($nextcloudVersion, '28') < 0) {
			Util::addScript(Application::APP_ID, Application::APP_ID . '-fileActions');
			Util::addScript(Application::APP_ID, Application::APP_ID . '-filesPluginLessThan28', 'files');
		} else {
			Util::addScript(Application::APP_ID, Application::APP_ID . '-filesPlugin');
		}
	}
}
