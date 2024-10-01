<?php
/**
 * @copyright Copyright (c) 2024 Sagar Gurung <sagar@jankaritech.com>
 *
 * @author Your name <sagar@jankaritech.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OpenProject\Service;

use OCA\OAuth2\Db\ClientMapper;
use OCA\OpenProject\VersionUtil;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;

class OauthServiceTest extends TestCase {
	protected function getOauthServiceMock(
		$clientMapperMock = null,
		$iSecureRandomMock = null,
		$iCryptoMock = null,
		$versionUtil = null,
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
		if ($versionUtil === null) {
			$versionUtil = $this->getMockBuilder(VersionUtil::class)->disableOriginalConstructor()->getMock();
		}

		return new OauthService(
			$clientMapperMock,
			$iSecureRandomMock,
			$iCryptoMock,
			$versionUtil
		);
	}


	/**
	 * @return array<mixed>
	 */
	public function gethashOrEncryptSecretBasedOnNextcloudVersion(): array {
		return [
			[
				"30.0.0",
				"calculateHMAC"
			],
			[
				"29.0.7",
				"calculateHMAC"
			],
			[
				"29.1.0",
				"calculateHMAC"
			],
			[
				"29.0.6",
				"encrypt"
			],
			[
				"28.0.10",
				"calculateHMAC"
			],
			[
				"28.2.0",
				"calculateHMAC"
			],
			[
				"28.0.0",
				"encrypt"
			],
			[
				"29.0.0",
				"encrypt"
			],
			[
				"27.1.11.8",
				"calculateHMAC"
			],
			[
				"27.1.12.0",
				"calculateHMAC"
			],
			[
				"27.1.1.0",
				"encrypt"
			]
		];
	}


	/**
	 * @dataProvider gethashOrEncryptSecretBasedOnNextcloudVersion
	 * @param string $nextcloudVersion
	 * @param string $hashOrEncryptFunction
	 *
	 * @return void
	 *
	 */
	public function testGetHashedOrEncryptedClientSecretBasedOnNextcloudVersions(string $nextcloudVersion, string $hashOrEncryptFunction) {
		$iCryptoMock = $this->getMockBuilder(ICrypto::class)->getMock();
		$oAuthService = $this->getOauthServiceMock(null, null, $iCryptoMock);
		$iCryptoMock->expects($this->once())->method($hashOrEncryptFunction);
		$oAuthService->hashOrEncryptSecretBasedOnNextcloudVersion("client_secret", $nextcloudVersion);
	}
}
