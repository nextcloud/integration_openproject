<?php

declare(strict_types=1);

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\ForbiddenException;
use OCP\HintException;
use OCP\IGroupManager;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;
use OCP\IUserSession;

class BeforeNodeDeletedListener implements IEventListener {
	private $logger;

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
	public function __construct(
		LoggerInterface $logger,
		OpenProjectAPIService $openprojectAPIService,
		IUserSession $userSession,
		IGroupManager $groupManager,
	) {
		$this->logger = $logger;
		$this->openprojectAPIService = $openprojectAPIService;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
	}

	/**
	* @throws ForbiddenException
	*/
	public function handle(Event $event): void {
		if (!($event instanceof BeforeNodeDeletedEvent)) {
			return;
		}
		$currentUserId = $this->userSession->getUser()->getUID();
		if (
			$this->openprojectAPIService->isGroupFolderSetup() &&
			$event->getNode()->getParent()->getPath() === "/$currentUserId/files/openproject" &&
			$currentUserId !== 'openproject' &&
			$this->groupManager->isInGroup($currentUserId, 'openproject')
		) {
			throw new HintException(
				'project folders cannot be deleted',
				'project folders cannot be deleted'
			);
		}
	}
}
