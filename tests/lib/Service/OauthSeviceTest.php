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
}
