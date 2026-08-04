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
	protected function getClient(): Client {
		$client = new Client();
		$client->setId(1);
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
		$testClient = $this->getClient();
		$expectedClient = [
			'id' => 1,
			'nextcloud_oauth_client_name' => $testClient->getName(),
			'openproject_redirect_uri' => $testClient->getRedirectUri(),
			'nextcloud_client_id' => $testClient->getClientIdentifier(),
			'nextcloud_client_secret' => $testClient->getSecret(),
		];

		$clientMapperMock = $this->createMock(ClientMapper::class);
		$clientMapperMock->expects($this->once())->method('insert')
			->with($this->isInstanceOf(Client::class))
			->willReturnCallback(function ($client) {
				$client->setId(1);
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
		$client = $this->getClient();
		$expectedClient = [
			'id' => 1,
			'nextcloud_oauth_client_name' => $client->getName(),
			'openproject_redirect_uri' => $client->getRedirectUri(),
			'nextcloud_client_id' => $client->getClientIdentifier(),
		];

		$clientMapperMock = $this->createMock(ClientMapper::class);
		$clientMapperMock->expects($this->once())->method('getByUid')
			->with(1)
			->willReturn($client);

		$oauthService = $this->getOauthServiceMock($clientMapperMock);

		$clientInfo = $oauthService->getClientInfo(1);
		$this->assertIsArray($clientInfo);
		$this->assertSame($expectedClient, $clientInfo);
	}

	/**
	 * @return void
	 */
	public function testGetClientInfoError(): void {
		$clientMapperMock = $this->createMock(ClientMapper::class);
		$clientMapperMock->expects($this->once())->method('getByUid')
			->with(1)
			->willThrowException(new ClientNotFoundException());

		$oauthService = $this->getOauthServiceMock($clientMapperMock);

		$clientInfo = $oauthService->getClientInfo(1);
		$this->assertNull($clientInfo);
	}

	/**
	 * @return void
	 */
	public function testSetClientRedirectUri(): void {
		$client = $this->getClient();

		$clientMapperMock = $this->createMock(ClientMapper::class);
		$clientMapperMock->expects($this->once())->method('getByUid')
			->with(1)
			->willReturn($client);
		$clientMapperMock->expects($this->once())->method('update')
			->with($client);

		$oauthService = $this->getOauthServiceMock($clientMapperMock);

		$result = $oauthService->setClientRedirectUri(1, $client->getRedirectUri());
		$this->assertTrue($result);
	}

	/**
	 * @return void
	 */
	public function testSetClientRedirectUriError(): void {
		$client = $this->getClient();

		$clientMapperMock = $this->createMock(ClientMapper::class);
		$clientMapperMock->expects($this->once())->method('getByUid')
			->with(1)
			->willThrowException(new ClientNotFoundException());
		$clientMapperMock->expects($this->never())->method('update');

		$oauthService = $this->getOauthServiceMock($clientMapperMock);

		$result = $oauthService->setClientRedirectUri(1, $client->getRedirectUri());
		$this->assertFalse($result);
	}
}
