<?php

declare(strict_types=1);

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\IRootFolder;
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

	/** @var IRootFolder */
	private $storage;

	public function __construct(
		OpenProjectAPIService $openprojectAPIService,
		IUserSession $userSession,
		IGroupManager $groupManager,
		IConfig $config,
		IRootFolder $storage
	) {
		$this->openprojectAPIService = $openprojectAPIService;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->config = $config;
		$this->storage = $storage;
	}

	public function handle(Event $event): void {
		if (($event instanceof BeforeNodeDeletedEvent)) {
			$parentNode = $event->getNode()->getParent();
		} elseif (($event instanceof BeforeNodeRenamedEvent)) {
			$parentNode = $event->getSource()->getParent();
		} else {
			return;
		}
		$groupFolderId = $this->config->getAppValue(
			Application::APP_ID,
			'openproject_groupfolder_id',
		);
		//groupFolderId is set, but its not the parent folder, then there is nothing to do
		if ($groupFolderId !== '' && $parentNode->getId() !== (int)$groupFolderId) {
			return;
		}
		$currentUserId = $this->userSession->getUser()->getUID();
		if (
			$this->openprojectAPIService->isGroupFolderSetup() &&
			$currentUserId !== Application::OPEN_PROJECT_ENTITIES_NAME &&
			$this->groupManager->isInGroup($currentUserId, Application::OPEN_PROJECT_ENTITIES_NAME)
		) {
			// everything is setup and the user should have access to the groupfolder,
			// but we don't know the id of it
			// most likely because something went wrong during fetching the id in the setup process
			// so try to find it now and save for later
			if ($groupFolderId === '') {
				$userFolder = $this->storage->getUserFolder($currentUserId);
				$openProjectFolder = $userFolder->get(Application::OPEN_PROJECT_ENTITIES_NAME);
				$groupFolderId = (string)$openProjectFolder->getId();
				$this->config->setAppValue(
					Application::APP_ID,
					'openproject_groupfolder_id',
					$groupFolderId
				);
			}
			if ($parentNode->getId() === (int)$groupFolderId) {
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
}
