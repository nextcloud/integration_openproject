<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Sagar Gurung <sagar@jankaritech.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OpenProject\BackgroundJob;

use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

use OCA\OpenProject\Service\DatabaseService;

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
