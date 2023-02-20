<?php

declare(strict_types=1);

namespace OCA\OpenProject\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\ForbiddenException;
use Psr\Log\LoggerInterface;

class BeforeNodeDeletedListener implements IEventListener {
	private $logger;

	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}

	/**
	* @throws ForbiddenException
	*/
	public function handle(Event $event): void {
		if (!($event instanceof BeforeNodeDeletedEvent)) {
			return;
		}
		if ($event->getNode()->getParent()->getPath() === 'openproject') {
			throw new ForbiddenException(
				'project folders cannot be deleted',
				false
			);
		}
	}
}
