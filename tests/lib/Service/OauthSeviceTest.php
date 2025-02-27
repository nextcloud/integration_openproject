<?php

/**
 * SPDX-FileCopyrightText: 2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use OCA\OAuth2\Db\ClientMapper;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;

class OauthServiceTest extends TestCase {
	protected function getOauthServiceMock(
		$clientMapperMock = null,
		$iSecureRandomMock = null,
		$iCryptoMock = null,
	): OauthService {

		if ($clientMapperMock === null) {
			$clientMapperMock = $this->getMockBuilder(ClientMapper::class)->disableOriginalConstructor()->getMock();
		}
		if ($iSecureRandomMock === null) {
			$iSecureRandomMock = $this->getMockBuilder(ISecureRandom::class)->getMock();
		}
		if ($iCryptoMock === null) {
			$iCryptoMock = $this->getMockBuilder(ICrypto::class)->getMock();
		}

		return new OauthService(
			$clientMapperMock,
			$iSecureRandomMock,
			$iCryptoMock
		);
	}


	/**
	 * @return array<mixed>
	 */
	public function gethashOrEncryptSecretBasedOnNextcloudVersion(): array {
		return [
			[
				"30.0.0.14",
				"calculateHMAC"
			],
			[
				"30.0.0.1",
				"calculateHMAC"
			],
			[
				"29.0.7.0",
				"calculateHMAC"
			],
			[
				"29.1.0.0",
				"calculateHMAC"
			],
			[
				"29.0.6.0",
				"encrypt"
			],
			[
				"28.0.10.0",
				"calculateHMAC"
			],
			[
				"28.2.0.0",
				"calculateHMAC"
			],
			[
				"28.0.0.0",
				"encrypt"
			],
			[
				"28.0.10.1",
				"calculateHMAC"
			],
			[
				"28.0.0.1",
				"encrypt"
			],
			[
				"29.0.0.0",
				"encrypt"
			],
			[
				"29.0.0.1",
				"encrypt"
			],
			[
				"29.0.7.1",
				"calculateHMAC"
			]
		];
	}


	/**
	 * @dataProvider gethashOrEncryptSecretBasedOnNextcloudVersion
	 * @param string $nextcloudVersion
	 * @param string|null $hashOrEncryptFunction
	 *
	 * @return void
	 *
	 */
	public function testGetHashedOrEncryptedClientSecretBasedOnNextcloudVersions(string $nextcloudVersion, ?string $hashOrEncryptFunction) {
		$iCryptoMock = $this->getMockBuilder(ICrypto::class)->getMock();
		$oAuthService = $this->getOauthServiceMock(null, null, $iCryptoMock);
		if ($hashOrEncryptFunction !== null) {
			$iCryptoMock->expects($this->once())->method($hashOrEncryptFunction);
		} else {
			$iCryptoMock->expects($this->never())->method('calculateHMAC');
			$iCryptoMock->expects($this->never())->method('encrypt');
		}
		$oAuthService->hashOrEncryptSecretBasedOnNextcloudVersion("client_secret", $nextcloudVersion);
	}
}
