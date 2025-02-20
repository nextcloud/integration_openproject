<?php

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Controller;

use Exception;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\Http\Client\IResponse;
use OCP\Http\Client\LocalServerException;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OpenProjectControllerTest extends TestCase {
	/**
	 * @param array<string, object> $constructParams
	 *
	 * @return OpenProjectController
	 */
	public function getOpenProjectControllerMock(array $constructParams = []): OpenProjectController {
		$constructArgs = [
			'request' => $this->createMock(IRequest::class),
			'config' => $this->createMock(IConfig::class),
			'openProjectAPIService' => $this->createMock(OpenProjectAPIService::class),
			'urlGenerator' => $this->createMock(IURLGenerator::class),
			'loggerInterface' => $this->createMock(LoggerInterface::class),
			'userId' => 'test',
		];
		foreach ($constructParams as $key => $value) {
			if (!array_key_exists($key, $constructArgs)) {
				throw new \InvalidArgumentException("Invalid construct parameter: $key");
			}

			$constructArgs[$key] = $value;
		}

		/**
		 * @psalm-suppress InvalidArgument
		 */
		return new OpenProjectController('integration_openproject', ...array_values($constructArgs));
	}

	/**
	 * @return array<mixed>
	 */
	public function isValidOpenProjectInstanceDataProvider() {
		return [
			['{"_type":"Root","instanceName":"OpenProject"}', true],
			['{"_type":"something","instanceName":"OpenProject"}', 'not_valid_body'],
			['{"_type":"Root","someData":"whatever"}', 'not_valid_body'],
			['<h1>hello world</h1>', 'not_valid_body'],
		];
	}

	/**
	 * @dataProvider isValidOpenProjectInstanceDataProvider
	 * @param string $body
	 * @param string|bool $expectedResult
	 * @return void
	 */
	public function testIsValidOpenProjectInstance(
		string $body, $expectedResult
	): void {
		$response = $this->getMockBuilder(IResponse::class)->getMock();
		$response->method('getBody')->willReturn($body);
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['rawRequest','isAdminAuditConfigSetCorrectly'])
			->getMock();
		$service
			->method('isAdminAuditConfigSetCorrectly')
			->willReturn(false);
		$service
			->method('rawRequest')
			->willReturn($response);

		$controller = $this->getOpenProjectControllerMock([
			'openProjectAPIService' => $service,
		]);
		$result = $controller->isValidOpenProjectInstance('http://openproject.org');
		if ($expectedResult === true) {
			$this->assertSame([ 'result' => true], $result->getData());
		} else {
			$this->assertSame(
				[ 'result' => $expectedResult, 'details' => $body ],
				$result->getData()
			);
		}
	}


	public function testIsValidOpenProjectInstanceRedirect(): void {
		$response = $this->getMockBuilder(IResponse::class)->getMock();
		$response->method('getStatusCode')->willReturn(302);
		$response->method('getHeader')
			->with('Location')
			->willReturn('https://openproject.org/api/v3/');
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['rawRequest'])
			->getMock();
		$service
			->method('rawRequest')
			->willReturn($response);

		$controller = $this->getOpenProjectControllerMock([
			'openProjectAPIService' => $service,
		]);
		$result = $controller->isValidOpenProjectInstance('http://openproject.org');
		$this->assertSame(
			[ 'result' => 'redirected', 'details' => 'https://openproject.org/'],
			$result->getData()
		);
	}

	public function testIsValidOpenProjectInstanceRedirectNoLocationHeader(): void {
		$response = $this->getMockBuilder(IResponse::class)->getMock();
		$response->method('getStatusCode')->willReturn(302);
		$response->method('getHeader')
			->with('Location')
			->willReturn('');
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['rawRequest','isAdminAuditConfigSetCorrectly'])
			->getMock();
		$service
			->method('rawRequest')
			->willReturn($response);
		$service
			->method('isAdminAuditConfigSetCorrectly')
			->willReturn(false);

		$controller = $this->getOpenProjectControllerMock([
			'openProjectAPIService' => $service,
		]);
		$result = $controller->isValidOpenProjectInstance('http://openproject.org');
		$this->assertSame(
			[
				'result' => 'unexpected_error',
				'details' => 'received a redirect status code (302) but no "Location" header'
			],
			$result->getData()
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function isValidOpenProjectInstanceExpectionDataProvider() {
		$requestMock = $this->getMockBuilder('\Psr\Http\Message\RequestInterface')->getMock();
		$privateInstance = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$privateInstance->method('getBody')->willReturn(
			'{"_type":"Error","errorIdentifier":"urn:openproject-org:api:v3:errors:Unauthenticated"}'
		);
		$notOP = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$notOP->method('getBody')->willReturn('Unauthenticated');
		$notOP->method('getReasonPhrase')->willReturn('Unauthenticated');
		$notOP->method('getStatusCode')->willReturn('401');
		$notOPButJSON = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$notOPButJSON->method('getBody')->willReturn(
			'{"what":"Error","why":"Unauthenticated"}'
		);
		$notOPButJSON->method('getReasonPhrase')->willReturn('Unauthenticated');
		$notOPButJSON->method('getStatusCode')->willReturn('401');
		$otherResponseMock = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$otherResponseMock->method('getReasonPhrase')->willReturn('Internal Server Error');
		$otherResponseMock->method('getStatusCode')->willReturn('500');
		return [
			[
				new ConnectException('a connection problem', $requestMock),
				[ 'result' => 'network_error', 'details' => 'a connection problem']
			],
			[
				new ClientException('valid but private instance', $requestMock, $privateInstance),
				[ 'result' => true ]
			],
			[
				new ClientException('not a OP instance', $requestMock, $notOP),
				[ 'result' => 'client_exception', 'details' => '401 Unauthenticated' ]
			],
			[
				new ClientException('not a OP instance but return JSON', $requestMock, $notOPButJSON),
				[ 'result' => 'client_exception', 'details' => '401 Unauthenticated' ]
			],
			[
				new ServerException('some server issue', $requestMock, $otherResponseMock),
				[ 'result' => 'server_exception', 'details' => '500 Internal Server Error' ]
			],
			[
				new BadResponseException('some issue', $requestMock, $otherResponseMock),
				[ 'result' => 'request_exception', 'details' => 'some issue' ]
			],
			[
				new LocalServerException('Host violates local access rules'),
				[ 'result' => 'local_remote_servers_not_allowed' ]
			],
			[
				new RequestException('some issue', $requestMock, $otherResponseMock),
				[ 'result' => 'request_exception', 'details' => 'some issue' ]
			],
			[
				new \Exception('some issue'),
				[ 'result' => 'unexpected_error', 'details' => 'some issue' ]
			],

		];
	}

	/**
	 * @dataProvider isValidOpenProjectInstanceExpectionDataProvider
	 * @param Exception $thrownException
	 * @param bool|string $expectedResult
	 * @return void
	 */
	public function testIsValidOpenProjectInstanceException(
		$thrownException, $expectedResult
	): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods(['rawRequest', 'isAdminAuditConfigSetCorrectly'])
			->getMock();
		$service
			->method('isAdminAuditConfigSetCorrectly')
			->willReturn(false);
		$service
			->method('rawRequest')
			->willThrowException($thrownException);

		$controller = $this->getOpenProjectControllerMock([
			'openProjectAPIService' => $service,
		]);
		$result = $controller->isValidOpenProjectInstance('http://openproject.org');
		$this->assertSame($expectedResult, $result->getData());
	}

	/**
	 * @return array<int, array<int, string>>
	 */
	public function isValidOpenProjectInstanceInvalidUrlDataProvider() {
		return [
			[ '123' ],
			[ 'htt://something' ],
			[ '' ],
			[ 'ftp://something.org ']
		];
	}
	/**
	 * @dataProvider isValidOpenProjectInstanceInvalidUrlDataProvider
	 * @return void
	 */
	public function testIsValidOpenProjectInstanceInvalidUrl(string $url): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();

		$controller = $this->getOpenProjectControllerMock([
			'openProjectAPIService' => $service,
		]);
		$result = $controller->isValidOpenProjectInstance($url);
		$this->assertSame(['result' => 'invalid'], $result->getData());
	}

	public function testGetOpenProjectOauthURLWithStateAndPKCE(): void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();

		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'authorization_method'],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url'],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_instance_url'],
			)->willReturnOnConsecutiveCalls(
				OpenProjectAPIService::AUTH_METHOD_OAUTH,
				'myClientID',
				'myClientSecret',
				'http://openproject.org',
				'myClientID',
				'http://openproject.org',
			);
		$configMock
			->expects($this->exactly(2))
			->method('setUserValue')
			->withConsecutive(
				[
					'test',
					'integration_openproject',
					'oauth_state',
					$this->matchesRegularExpression('/[a-z0-9]{10}/')
				],
				[
					'test',
					'integration_openproject',
					'code_verifier',
					$this->matchesRegularExpression('/[A-Za-z0-9\-._~]{128}/')
				],
			);

		$controller = $this->getOpenProjectControllerMock([
			'openProjectAPIService' => $service,
			'config' => $configMock,
		]);
		$result = $controller->getOpenProjectOauthURLWithStateAndPKCE();
		$this->assertMatchesRegularExpression(
			'/^http:\/\/openproject\.org\/oauth\/authorize\?' .
			'client_id=myClientID&' .
			'redirect_uri=&' .
			'response_type=code&' .
			'state=[a-z0-9]{10}&' .
			'code_challenge=[a-zA-Z0-9\-_]{43}&' .
			'code_challenge_method=S256$/',
			(string) $result->getData()
		);
	}
}
