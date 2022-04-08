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


use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Exceptions\ClientNotFoundException;
use OCP\Security\ISecureRandom;

use OCA\OpenProject\AppInfo\Application;

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
	 * Service to manipulate Nextcloud oauth clients
	 */
	public function __construct(string $appName,
								ClientMapper $clientMapper,
								ISecureRandom $secureRandom) {
		$this->appName = $appName;
		$this->secureRandom = $secureRandom;
		$this->clientMapper = $clientMapper;
	}

	/**
	 * @param string $name
	 * @param string $redirectUri
	 * @return array
	 */
	public function createNcOauthClient(string $name, string $redirectUri): array {
		$client = new Client();
		$client->setName($name);
		$client->setRedirectUri($redirectUri);
		$client->setSecret($this->secureRandom->generate(64, self::validChars));
		$client->setClientIdentifier($this->secureRandom->generate(64, self::validChars));
		$client = $this->clientMapper->insert($client);

		return $this->generateClientInfo($client);
	}

	/**
	 * @param string $id
	 * @return array|null
	 */
	public function getClientInfo(string $id): ?array {
		try {
			$client = $this->clientMapper->getByUid($id);
			return $this->generateClientInfo($client);
		} catch (ClientNotFoundException $e) {
			return null;
		}
	}

	/**
	 * @param Client $client
	 * @return array
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
}
