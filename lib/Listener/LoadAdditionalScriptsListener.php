<?php

namespace OCA\OpenProject\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\VersionUtil;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 *	@template-implements IEventListener<Event>
 */
class LoadAdditionalScriptsListener implements IEventListener {
	/**
	 * @var VersionUtil
	 */
	private $versionUtil;

	public function __construct(
		VersionUtil $versionUtil
	) {
		$this->versionUtil = $versionUtil;
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}
		$nextcloudVersion = $this->versionUtil->getNextcloudVersion();
		if (version_compare($nextcloudVersion, '28') < 0) {
			Util::addScript(Application::APP_ID, Application::APP_ID . '-fileActions');
			Util::addScript(Application::APP_ID, Application::APP_ID . '-filesPluginLessThan28', 'files');
		} else {
			Util::addScript(Application::APP_ID, Application::APP_ID . '-filesPlugin');
		}
	}
}
