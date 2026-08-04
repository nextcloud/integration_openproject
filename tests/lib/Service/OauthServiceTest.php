<?php

/**
 * SPDX-FileCopyrightText: 2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Exceptions\ClientNotFoundException;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OauthServiceTest extends TestCase {

	/**
	 * @param MockObject|null $clientMapperMock
	 *
	 * @return OauthService
	 */
	protected function getOauthServiceMock(MockObject $clientMapperMock = null): OauthService {
		$secureRandomMock = $this->createMock(ISecureRandom::class);
		$secureRandomMock->method('generate')->willReturn('randomString');
		$cryptoMock = $this->createMock(ICrypto::class);
		$cryptoMock->method('calculateHMAC')->willReturn('hmacValue');

		if ($clientMapperMock === null) {
			$clientMapperMock = $this->createMock(ClientMapper::class);
		}

		return new OauthService(
			$clientMapperMock,
			$secureRandomMock,
			$cryptoMock
		);
	}

	/**
	 * @return Client
	 */
	protected function getClient(int $clientId): Client {
		$client = new Client();
		$client->setId($clientId);
		$client->setName('Test Client');
		$client->setRedirectUri('https://example.com/callback');
		$client->setClientIdentifier('randomString');
		$client->setSecret('randomString');

		return $client;
	}

	/**
	 * @return void
	 */
	public function testCreateNcOauthClient(): void {
		$clientId = 1;
		$testClient = $this->getClient($clientId);
		$expectedClient = [
			'id' => $clientId,
			'nextcloud_oauth_client_name' => $testClient->getName(),
			'openproject_redirect_uri' => $testClient->getRedirectUri(),
			'nextcloud_client_id' => $testClient->getClientIdentifier(),
			'nextcloud_client_secret' => $testClient->getSecret(),
		];

		$clientMapperMock = $this->createMock(ClientMapper::class);
		$clientMapperMock->expects($this->once())->method('insert')
			->with($this->isInstanceOf(Client::class))
			->willReturnCallback(function ($client) use ($clientId) {
				$client->setId($clientId);
				return $client;
			});

		$oauthService = $this->getOauthServiceMock($clientMapperMock);

		$clientInfo = $oauthService->createNcOauthClient($testClient->getName(), $testClient->getRedirectUri());
		$this->assertSame($expectedClient, $clientInfo);
	}

	/**
	 * @return void
	 */
	public function testGetClientInfo(): void {
		$clientId = 1;
		$client = $this->getClient($clientId);
		$expectedClient = [
			'id' => $clientId,
			'nextcloud_oauth_client_name' => $client->getName(),
			'openproject_redirect_uri' => $client->getRedirectUri(),
			'nextcloud_client_id' => $client->getClientIdentifier(),
		];

		$clientMapperMock = $this->createMock(ClientMapper::class);
		$clientMapperMock->expects($this->once())->method('getByUid')
			->with($clientId)
			->willReturn($client);

		$oauthService = $this->getOauthServiceMock($clientMapperMock);

		$clientInfo = $oauthService->getClientInfo($clientId);
		$this->assertIsArray($clientInfo);
		$this->assertSame($expectedClient, $clientInfo);
	}

	/**
	 * @return void
	 */
	public function testGetClientInfoError(): void {
		$clientId = 1;
		$clientMapperMock = $this->createMock(ClientMapper::class);
		$clientMapperMock->expects($this->once())->method('getByUid')
			->with($clientId)
			->willThrowException(new ClientNotFoundException());

		$oauthService = $this->getOauthServiceMock($clientMapperMock);

		$clientInfo = $oauthService->getClientInfo($clientId);
		$this->assertNull($clientInfo);
	}

	/**
	 * @return void
	 */
	public function testSetClientRedirectUri(): void {
		$clientId = 1;
		$client = $this->getClient($clientId);

		$clientMapperMock = $this->createMock(ClientMapper::class);
		$clientMapperMock->expects($this->once())->method('getByUid')
			->with($clientId)
			->willReturn($client);
		$clientMapperMock->expects($this->once())->method('update')
			->with($client);

		$oauthService = $this->getOauthServiceMock($clientMapperMock);

		$result = $oauthService->setClientRedirectUri($clientId, $client->getRedirectUri());
		$this->assertTrue($result);
	}

	/**
	 * @return void
	 */
	public function testSetClientRedirectUriError(): void {
		$clientId = 1;
		$client = $this->getClient($clientId);

		$clientMapperMock = $this->createMock(ClientMapper::class);
		$clientMapperMock->expects($this->once())->method('getByUid')
			->with($clientId)
			->willThrowException(new ClientNotFoundException());
		$clientMapperMock->expects($this->never())->method('update');

		$oauthService = $this->getOauthServiceMock($clientMapperMock);

		$result = $oauthService->setClientRedirectUri($clientId, $client->getRedirectUri());
		$this->assertFalse($result);
	}
}
