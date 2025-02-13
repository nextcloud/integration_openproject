<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCA\TermsOfService\Events\SignaturesResetEvent;
use OCA\TermsOfService\Events\TermsCreatedEvent;
use OCP\App\Events\AppEnableEvent;
use OCP\DB\Exception as DBException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class TermsOfServiceEventListener implements IEventListener {

	/**
	 * @var OpenProjectAPIService
	 */
	private OpenProjectAPIService $openprojectAPIService;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct(
		OpenProjectAPIService $openprojectAPIService,
		LoggerInterface $logger
	) {
		$this->openprojectAPIService = $openprojectAPIService;
		$this->logger = $logger;
	}

	public function handle(Event $event): void {
		try {
			if ($event instanceof TermsCreatedEvent) {
				$this->openprojectAPIService->signTermsOfServiceForUserOpenProject();
			}
			if ($event instanceof SignaturesResetEvent) {
				$this->openprojectAPIService->signTermsOfServiceForUserOpenProject();
			}
			if ($event instanceof AppEnableEvent) {
				if ($event->getAppId() === 'terms_of_service') {
					$this->openprojectAPIService->signTermsOfServiceForUserOpenProject();
				}
			}
		} catch (DBException $e) {
			$this->logger->error(
				'Error: ' . $e->getMessage(),
				['app' => Application::APP_ID]
			);
		}
	}
}
