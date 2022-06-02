<?php
/**
 * Nextcloud - openproject
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2022
 */

namespace OCA\OpenProject\Service;

use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Exceptions\ClientNotFoundException;
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
	 * @var AccessTokenMapper
	 */
	private $accessTokenMapper;

	/**
	 * Service to manipulate Nextcloud oauth clients
	 */
	public function __construct(ClientMapper $clientMapper,
								AccessTokenMapper $accessTokenMapper,
								ISecureRandom $secureRandom) {
		$this->secureRandom = $secureRandom;
		$this->clientMapper = $clientMapper;
		$this->accessTokenMapper = $accessTokenMapper;
	}

	/**
	 * @param string $name
	 * @param string $redirectUri
	 * @return array<mixed>
	 */
	public function createNcOauthClient(string $name, string $redirectUri): array {
		$clientId = $this->secureRandom->generate(64, self::validChars);
		$client = new Client();
		$client->setName($name);
		$client->setRedirectUri(sprintf($redirectUri, $clientId));
		$client->setSecret($this->secureRandom->generate(64, self::validChars));
		$client->setClientIdentifier($clientId);
		$client = $this->clientMapper->insert($client);

		return $this->generateClientInfo($client);
	}

	/**
	 * @param int $id
	 * @return array<mixed>|null
	 */
	public function getClientInfo(int $id): ?array {
		try {
			$client = $this->clientMapper->getByUid($id);
			return $this->generateClientInfo($client);
		} catch (ClientNotFoundException $e) {
			return null;
		}
	}

	/**
	 * @param Client $client
	 * @return array<mixed>
	 */
	private function generateClientInfo(Client $client): array {
		return [
			'id' => $client->getId(),
			'name' => $client->getName(),
			'redirectUri' => $client->getRedirectUri(),
			'clientId' => $client->getClientIdentifier(),
			'clientSecret' => $client->getSecret(),
		];
	}

	/**
	 * @param int $id
	 * @return void
	 */
	public function deleteClient(int $id): void {
		try {
			$client = $this->clientMapper->getByUid($id);
			$this->accessTokenMapper->deleteByClientId($id);
			$this->clientMapper->delete($client);
		} catch (ClientNotFoundException $e) {
		}
	}
}
