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

use OC_Util;
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
		$client = new Client();
		$client->setName($name);
		$client->setRedirectUri(sprintf($redirectUri, $clientId));
		$secret = $this->secureRandom->generate(64, self::validChars);
		if (version_compare(OC_Util::getVersionString(), '27.0.1') >= 0) {
			$encryptedSecret = $this->crypto->encrypt($secret);
		} else {
			$encryptedSecret = $secret;
		}
		$client->setSecret($encryptedSecret);
		$client->setClientIdentifier($clientId);
		$client = $this->clientMapper->insert($client);

		return [
			'id' => $client->getId(),
			'nextcloud_oauth_client_name' => $client->getName(),
			'openproject_redirect_uri' => $client->getRedirectUri(),
			'nextcloud_client_id' => $client->getClientIdentifier(),
			'nextcloud_client_secret' => $secret,
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
				'id' => $client->getId(),
				'nextcloud_oauth_client_name' => $client->getName(),
				'openproject_redirect_uri' => $client->getRedirectUri(),
				'nextcloud_client_id' => $client->getClientIdentifier(),
				'nextcloud_client_secret' => $this->crypto->decrypt($client->getSecret()),
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
			$clientId = $client->getClientIdentifier();
			$redirectUri = rtrim($opUrl, '/') .'/oauth_clients/'.$clientId.'/callback';
			$client->setRedirectUri($redirectUri);
			$this->clientMapper->update($client);
			return true;
		} catch (ClientNotFoundException $e) {
			return false;
		}
	}
}
