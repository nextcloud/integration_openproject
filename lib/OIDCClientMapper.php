<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\OpenProject;

use OCA\OIDCIdentityProvider\Db\Client;
use OCA\OIDCIdentityProvider\Db\ClientMapper;
use OCA\OIDCIdentityProvider\Db\RedirectUriMapper;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class OIDCClientMapper {
	private ClientMapper $clientMapper;

	public function __construct(
		private LoggerInterface $logger,
		private ISecureRandom $random,
		private IDBConnection $db,
		private ITimeFactory $timeFactory,
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @param string $clientIdentifier
	 *
	 * @return Client
	 */
	public function getClient(string $clientIdentifier): ?Client {
		/**
		 * @psalm-suppress RedundantPropertyInitializationCheck
		 */
		if (!isset($this->clientMapper)) {
			$this->clientMapper = new ClientMapper(
				$this->db,
				$this->timeFactory,
				$this->appConfig,
				new RedirectUriMapper($this->db, $this->timeFactory, $this->appConfig),
				$this->random,
				$this->logger,
			);
		}

		return $this->clientMapper->getByIdentifier($clientIdentifier);
	}
}
