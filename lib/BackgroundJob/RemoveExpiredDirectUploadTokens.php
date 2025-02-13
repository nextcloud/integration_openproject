<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\BackgroundJob;

use OCA\OpenProject\Service\DatabaseService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\Exception;

use Psr\Log\LoggerInterface;

class RemoveExpiredDirectUploadTokens extends TimedJob {

	/** @var LoggerInterface */
	protected LoggerInterface $logger;

	/** @var  DatabaseService */
	protected DatabaseService $databaseService;

	public function __construct(ITimeFactory $time, DatabaseService $databaseService, LoggerInterface $logger) {
		parent::__construct($time);
		// runs once a day
		$this->setInterval(24 * 3600);
		$this->databaseService = $databaseService;
		$this->logger = $logger;
	}

	/**
	 * @param mixed $argument
	 * @return void
	 * @throws Exception
	 */
	public function run($argument): void {
		$this->databaseService->deleteExpiredTokens();
		$this->logger->info('Deleted all the expired tokens from Database');
	}
}
