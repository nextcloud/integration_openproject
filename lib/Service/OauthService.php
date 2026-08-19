<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Exceptions\ClientNotFoundException;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;

class OauthService {
	/**
	 * @var ISecureRandom
	 */
	private $secureRandom;

	public const validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	/**
	 * @var ClientMapper
	 */
	private $clientMapper;
	/**
	 * @var ICrypto
	 */
	private $crypto;

	/**
	 * Service to manipulate Nextcloud oauth clients
	 */
	public function __construct(ClientMapper $clientMapper,
		ISecureRandom $secureRandom,
		ICrypto $crypto
	) {
		$this->secureRandom = $secureRandom;
		$this->clientMapper = $clientMapper;
		$this->crypto = $crypto;
	}

	/**
	 * @param string $name
	 * @param string $redirectUri
	 * @return array<mixed>
	 */
	public function createNcOauthClient(string $name, string $redirectUri): array {
		$clientId = $this->secureRandom->generate(64, self::validChars);
		$clientSecret = $this->secureRandom->generate(64, self::validChars);
		$hashedClientSecret = bin2hex($this->crypto->calculateHMAC($clientSecret));
		$redirectUri = sprintf($redirectUri, $clientId);

		$client = new Client();
		$this->setClientProperty($client, 'name', $name);
		$this->setClientProperty($client, 'redirectUri', $redirectUri);
		$this->setClientProperty($client, 'clientIdentifier', $clientId);
		$this->setClientProperty($client, 'secret', $hashedClientSecret);

		$client = $this->clientMapper->insert($client);

		return [
			'id' => $this->getClientProperty($client, 'id'),
			'nextcloud_oauth_client_name' => $this->getClientProperty($client, 'name'),
			'openproject_redirect_uri' => $this->getClientProperty($client, 'redirectUri'),
			'nextcloud_client_id' => $this->getClientProperty($client, 'clientIdentifier'),
			'nextcloud_client_secret' => $clientSecret,
		];
	}

	/**
	 * @param int $id
	 * @return array<mixed>|null
	 */
	public function getClientInfo(int $id): ?array {
		try {
			$client = $this->clientMapper->getByUid($id);
			return [
				'id' => $this->getClientProperty($client, 'id'),
				'nextcloud_oauth_client_name' => $this->getClientProperty($client, 'name'),
				'openproject_redirect_uri' => $this->getClientProperty($client, 'redirectUri'),
				'nextcloud_client_id' => $this->getClientProperty($client, 'clientIdentifier')
			];
		} catch (ClientNotFoundException $e) {
			return null;
		}
	}

	/**
	 * @param int $id
	 * @param string $opUrl
	 * @return bool
	 */
	public function setClientRedirectUri(int $id, string $opUrl): bool {
		try {
			$client = $this->clientMapper->getByUid($id);
			$clientId = $this->getClientProperty($client, 'clientIdentifier');

			$redirectUri = rtrim($opUrl, '/') .'/oauth_clients/'.$clientId.'/callback';
			$client = $this->setClientProperty($client, 'redirectUri', $redirectUri);

			$this->clientMapper->update($client);
			return true;
		} catch (ClientNotFoundException $e) {
			return false;
		}
	}

	/**
	 * @param Client $client
	 * @param string $property
	 * @param string|int $value
	 *
	 * @return Client
	 */
	public function setClientProperty(Client $client, string $property, string|int $value): Client {
		$fn = 'set' . ucfirst($property);
		// In NC35, OAuth2 Client class changed to attribute-based entity.
		// NOTE: we can remove setClientProperty and getClientProperty methods
		// once we drop support for NC34 and below.
		if (\method_exists(Client::class, 'addType')) {
			$client->$fn($value);
		} else {
			$client->{$property} = $value;
		}

		return $client;
	}

	/**
	 * @param Client $client
	 * @param string $property
	 *
	 * @return string|int
	 */
	public function getClientProperty(Client $client, string $property): string|int {
		$fn = 'get' . ucfirst($property);
		// In NC35, OAuth2 Client class changed to attribute-based entity.
		// NOTE: we can remove setClientProperty and getClientProperty methods
		// once we drop support for NC34 and below.
		if (\method_exists(Client::class, 'addType')) {
			return $client->$fn();
		}
		return $client->{$property};
	}
}
