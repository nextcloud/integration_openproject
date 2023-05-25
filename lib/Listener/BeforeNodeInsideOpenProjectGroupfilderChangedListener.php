<?php

declare(strict_types=1);

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

class BeforeNodeInsideOpenProjectGroupfilderChangedListener implements IEventListener {
	/**
	 * @var OpenProjectAPIService
	 */
	private $openprojectAPIService;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * @var IUserSession
	 */
	private $userSession;
	/**
	 * @var IConfig
	 */
	private $config;

	public function __construct(
		OpenProjectAPIService $openprojectAPIService,
		IUserSession $userSession,
		IGroupManager $groupManager,
		IConfig $config
	) {
		$this->openprojectAPIService = $openprojectAPIService;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->config = $config;
	}

	public function handle(Event $event): void {
		if (($event instanceof BeforeNodeDeletedEvent)) {
			$parentNode = $event->getNode()->getParent();
		} elseif (($event instanceof BeforeNodeRenamedEvent)) {
			$parentNode = $event->getSource()->getParent();
		} else {
			return;
		}
		$currentUserId = $this->userSession->getUser()->getUID();
		if (
			$this->openprojectAPIService->isProjectFoldersSetupComplete() &&
			$parentNode->getId() === (int)$this->config->getAppValue(
				Application::APP_ID,
				'openproject_groupfolder_id',
			) &&
			$currentUserId !== Application::OPEN_PROJECT_ENTITIES_NAME &&
			$this->groupManager->isInGroup($currentUserId, Application::OPEN_PROJECT_ENTITIES_NAME)
		) {
			if (!class_exists("\OCP\HintException")) {
				// @phpstan-ignore-next-line that public class only exists from NC 23
				throw new \OCP\HintException(
					'project folders cannot be deleted or renamed'
				);
			}
			throw new \OC\HintException(
				'project folders cannot be deleted or renamed'
			);
		}
	}
}
