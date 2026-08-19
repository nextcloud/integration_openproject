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
	protected function getClient(int $clientDbId): Client {
		$client = new Client();
		$name = 'Test Client';
		$redirectUri = 'https://example.com/callback';
		$clientId = 'randomString';
		$clientSecret = 'randomString';

		$this->setClientProperty($client, 'id', $clientDbId);
		$this->setClientProperty($client, 'name', $name);
		$this->setClientProperty($client, 'redirectUri', $redirectUri);
		$this->setClientProperty($client, 'clientIdentifier', $clientId);
		$this->setClientProperty($client, 'secret', $clientSecret);
		return $client;
	}

	/**
	 * @param Client $client
	 * @param string $property
	 * @param string|int $value
	 *
	 * @return Client
	 */
	protected function setClientProperty(Client $client, string $property, string|int $value): Client {
		$fn = 'set' . ucfirst($property);
		if (\method_exists($client, $fn)) {
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
	protected function getClientProperty(Client $client, string $property): string|int {
		$fn = 'get' . ucfirst($property);
		if (\method_exists($client, $fn)) {
			return $client->$fn();
		}
		return $client->{$property};
	}

	/**
	 * @return void
	 */
	public function testCreateNcOauthClient(): void {
		$clientId = 1;
		$testClient = $this->getClient($clientId);
		$expectedClient = [
			'id' => $clientId,
			'nextcloud_oauth_client_name' => $this->getClientProperty($testClient, 'name'),
			'openproject_redirect_uri' => $this->getClientProperty($testClient, 'redirectUri'),
			'nextcloud_client_id' => $this->getClientProperty($testClient, 'clientIdentifier'),
			'nextcloud_client_secret' => $this->getClientProperty($testClient, 'secret'),
		];

		$clientMapperMock = $this->createMock(ClientMapper::class);
		$clientMapperMock->expects($this->once())->method('insert')
			->with($this->isInstanceOf(Client::class))
			->willReturnCallback(function ($client) use ($clientId) {
				return $this->setClientProperty($client, 'id', $clientId);
			});

		$oauthService = $this->getOauthServiceMock($clientMapperMock);

		$clientInfo = $oauthService->createNcOauthClient(
			(string)$this->getClientProperty($testClient, 'name'),
			(string)$this->getClientProperty($testClient, 'redirectUri')
		);
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
			'nextcloud_oauth_client_name' => $this->getClientProperty($client, 'name'),
			'openproject_redirect_uri' => $this->getClientProperty($client, 'redirectUri'),
			'nextcloud_client_id' => $this->getClientProperty($client, 'clientIdentifier'),
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

		$result = $oauthService->setClientRedirectUri(
			$clientId,
			(string)$this->getClientProperty($client, 'redirectUri'),
		);
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

		$result = $oauthService->setClientRedirectUri(
			$clientId,
			(string)$this->getClientProperty($client, 'redirectUri'),
		);
		$this->assertFalse($result);
	}
}
