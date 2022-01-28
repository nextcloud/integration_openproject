<?php
/**
 * Nextcloud - OpenProject
 *
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Artur Neumann 2021
 */

namespace OCA\OpenProject\Service;

use GuzzleHttp\Client as GuzzleClient;
use OC\Http\Client\Client;
use OCP\ICertificateManager;
use OCP\IConfig;
use OCP\ILogger;
use PhpPact\Consumer\InteractionBuilder;
use PhpPact\Consumer\Model\ConsumerRequest;
use PhpPact\Consumer\Model\ProviderResponse;
use PhpPact\Standalone\MockService\MockServerEnvConfig;
use PHPUnit\Framework\TestCase;

class OpenProjectAPIServiceTest extends TestCase
{
	/**
	 * @var InteractionBuilder
	 */
	private $builder;

	/**
	 * @var OpenProjectAPIService
	 */
	private $service;

	private $mockServerBaseUri;
	private $clientId = 'U3V9_l262pNSENBnsqD2Uwylv5hQWCQ8lFPjCvGPbQc';
	private $clientSecret = 'P5eu43P8YFFM9jeZKWcrpbskAUgHUBGYFQKB_8aeBtU';
	private $workPackagesPath = '/api/v3/work_packages';
	/**
	 * @return void
	 * @before
	 */
	function setupMockServer(): void {
		$config = new MockServerEnvConfig();
		$this->builder = new InteractionBuilder($config);
		$this->mockServerBaseUri = $config->getBaseUri()->__toString();
	}

	/**
	 * @return void
	 * @before
	 */
	function setUpMocks(): void {
		/** @var IConfig $config */
		$config = $this->createMock(IConfig::class);
		/** @var ICertificateManager $certificateManager */
		$certificateManager = $this->getMockBuilder('\OCP\ICertificateManager')->getMock();
		$certificateManager->method('getAbsoluteBundlePath')->willReturn('/');
		$logger = $this->createMock(ILogger::class);

		$client = new GuzzleClient();
		$ocClient = new Client(
			$config,
			$logger,
			$certificateManager,
			$client,
			$this->createMock(\OC\Http\Client\LocalAddressChecker::class)
		);
		$clientService = $this->getMockBuilder('\OCP\Http\Client\IClientService')->getMock();
		$clientService->method('newClient')->willReturn($ocClient);
		$this->service = new OpenProjectAPIService(
			'integration_openproject',
			$this->createMock(\OCP\IUserManager::class),
			$this->createMock(\OCP\IAvatarManager::class),
			$this->createMock(\Psr\Log\LoggerInterface::class),
			$this->createMock(\OCP\IL10N::class),
			$this->createMock(\OCP\IConfig::class),
			$this->createMock(\OCP\Notification\IManager::class),
			$clientService
		);
	}

	/**
	 * @param array $onlyMethods
	 * @return OpenProjectAPIService|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function getServiceMock(array $onlyMethods = ['request']): OpenProjectAPIService {
		return $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods($onlyMethods)
			->getMock();
	}

	public function urlsDataProvider(): array {
		return [
			['http://127.0.0.1', true],
			['https://127.0.0.1', true],
			['https://127.0.0.1:443', true],
			['http://127.0.0.1:8080', true],
			['http://localhost', true],
			['http://localhost', true],
			['http://www.openproject.com', true],
			['http://www.openproject.it:3000', true],
			['https://www.openproject.it:8081', true],
			['https://www.openproject.it:8081/home', true],
			['ftp://localhost', false],
			['http://loca lhost', false],
			['https://loca lhost', false],
			['http://openproject.dev ', false],
			['http:/openproject.dev', false],
			['http//openproject.dev', false],
			['openproject.dev', false],
			['://openproject.dev', false],
		];
	}

	/**
	 * @dataProvider urlsDataProvider
	 */
	public function testValidateOpenProjectURL(string $url, bool $expected) {
		$result = OpenProjectAPIService::validateOpenProjectURL($url);
		$this->assertSame($expected, $result);
	}

	public function searchWorkPackageDataProvider() {
		return [
			[   // description and subject search, both return a result
				["_embedded" => ["elements" => [['id' => 1], ['id' => 2], ['id' => 3]]]],
				["_embedded" => ["elements" => [['id' => 3], ['id' => 4], ['id' => 5]]]],
				[['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]]
			],
			[   // only subject search returns a result
				[],
				["_embedded" => ["elements" => [['id' => 3], ['id' => 4], ['id' => 5]]]],
				[['id' => 3], ['id' => 4], ['id' => 5]]
			],
			[   // only description search returns a result
				["_embedded" => ["elements" => [['id' => 1], ['id' => 2], ['id' => 3]]]],
				[],
				[['id' => 1], ['id' => 2], ['id' => 3]]
			],
			[   // no search result returned
				[],
				[],
				[]
			]
		];
	}

	/**
	 * @param array $descriptionResponse
	 * @param array $subjectResponse
	 * @param array $expectedResult
	 * @return void
	 * @dataProvider searchWorkPackageDataProvider
	 */
	public function testSearchWorkPackageDescAndSubjectResponse(
		array $descriptionResponse, array $subjectResponse, array $expectedResult
	) {
		$service = $this->getServiceMock();
		$service->method('request')
			->withConsecutive(
				[
					'url','token', 'type', 'refresh', 'id', 'secret', 'user', 'work_packages',
					[
						'filters' => '[{"description":{"operator":"~","values":["search query"]}},{"status":{"operator":"!","values":["14"]}}]',
						'sortBy' => '[["updatedAt", "desc"]]',
					]
				],
				[
					'url','token', 'type', 'refresh', 'id', 'secret', 'user', 'work_packages',
					[
						'filters' => '[{"subject":{"operator":"~","values":["search query"]}},{"status":{"operator":"!","values":["14"]}}]',
						'sortBy' => '[["updatedAt", "desc"]]',
					]
				]
			)
			->willReturnOnConsecutiveCalls(
				$descriptionResponse,
				$subjectResponse
			);
		$result = $service->searchWorkPackage(
			'url','token', 'type', 'refresh', 'id', 'secret', 'user','search query'
		);
		$this->assertSame($expectedResult, $result);
	}

	public function testGetNotificationsRequest() {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath($this->workPackagesPath)
			->setHeaders(["Authorization" =>  "Bearer 1234567890"])
			->addQueryParameter('filters', '[{"status":{"operator":"!","values":["14"]}}]')
			->addQueryParameter('sortBy', '[["updatedAt", "desc"]]');

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(200)
			->addHeader('Content-Type', 'application/json')
			->setBody(["_embedded" => ["elements" => [['some' =>'data']]]]);

		$this->builder
			->uponReceiving('a GET request to /work_packages with filter and sorting')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->getNotifications(
			$this->mockServerBaseUri,
			'1234567890',
			'oauth',
			'',
			$this->clientId,
			$this->clientSecret,
			'admin'
		);
		$this->assertSame([['some' =>'data']], $result);
	}

	public function malformedResponsesDataProvider() {
		return [
			[["_embedded" => []]],
			[["_embedded" => ['element']]],
			[["embedded" => ['elements']]],
		];
	}
	/**
	 * @dataProvider malformedResponsesDataProvider
	 */
	public function testGetNotificationsMalformedResponse($response) {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn($response);
		$result = $service->getNotifications('', '', '', '', '', '', '');
		$this->assertSame(["error" => "Malformed response"], $result);
	}

	public function testGetNotificationsErrorResponse() {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(['error' => 'my error']);
		$result = $service->getNotifications('', '', '', '', '', '', '');
		$this->assertSame(["error" => "my error"], $result);
	}

	public function testGetNotificationsFilters() {
		$service = $this->getServiceMock(['request', 'now']);
		$service->method('now')
			->willReturn("2022-01-27T08:15:48Z");
		$service->expects($this->once())
			->method('request')
			->with(
				'url','token', 'type', 'refresh', 'id', 'secret','user', 'work_packages',
				[
					'filters' => '[{"updatedAt":{"operator":"<>d","values":["2022-01-01T12:01:01Z","2022-01-27T08:15:48Z"]}},{"status":{"operator":"!","values":["14"]}}]',
					'sortBy' => '[["updatedAt", "desc"]]'
			]);

		$service->getNotifications('url','token', 'type', 'refresh', 'id', 'secret', 'user', '2022-01-01T12:01:01Z');
	}

	public function testGetNotificationsLimit() {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(["_embedded" => ["elements" => [['id' => 1], ['id' => 2], ['id' => 3]]]]);
		$result = $service->getNotifications('', '', '', '', '', '', '','',2);
		$this->assertSame([['id' => 1], ['id' => 2]], $result);
	}

	public function testRequestUsingOAuthToken() {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath($this->workPackagesPath)
			->setHeaders(["Authorization" =>  "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(200)
			->addHeader('Content-Type', 'application/json')
			->setBody(["_embedded" => ["elements" => []]]);

		$this->builder
			->uponReceiving('an OAuth GET request to /work_packages')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->request(
			$this->mockServerBaseUri,
			'1234567890',
			'oauth',
			'',
			$this->clientId,
			$this->clientSecret,
			'admin',
			'work_packages'
		);
		$this->assertSame(["_embedded" => ["elements" => []]], $result);

	}
	public function testRequestRefreshOAuthToken() {
		$consumerRequestInvalidOAuthToken = new ConsumerRequest();
		$consumerRequestInvalidOAuthToken
			->setMethod('GET')
			->setPath($this->workPackagesPath)
			->setHeaders(["Authorization" =>  "Bearer invalid"]);

		$providerResponseInvalidOAuthToken = new ProviderResponse();
		$providerResponseInvalidOAuthToken
			->setStatus(401)
			->addHeader('Content-Type', 'application/json');

		$this->builder
			->uponReceiving('an OAuth GET request to /work_packages with invalid OAuth Token')
			->with($consumerRequestInvalidOAuthToken)
			->willRespondWith($providerResponseInvalidOAuthToken);

		$refreshTokenRequest = new ConsumerRequest();
		$refreshTokenRequest
			->setMethod('POST')
			->setPath('/oauth/token')
			->setBody(
				'client_id=' . $this->clientId .
				'&client_secret=' . $this->clientSecret .
				'&grant_type=refresh_token&refresh_token=myRefreshToken'
			);

		$refreshTokenResponse = new ProviderResponse();
		$refreshTokenResponse
			->setStatus(200)
			->setBody(["access_token" => "new-Token"]);

		$this->builder->uponReceiving('a POST request to renew token')
			->with($refreshTokenRequest)
			->willRespondWith($refreshTokenResponse);

		$consumerRequestNewOAuthToken = new ConsumerRequest();
		$consumerRequestNewOAuthToken
			->setMethod('GET')
			->setPath($this->workPackagesPath)
			->setHeaders(["Authorization" =>  "Bearer new-Token"]);

		$providerResponseNewOAuthToken = new ProviderResponse();
		$providerResponseNewOAuthToken
			->setStatus(200)
			->addHeader('Content-Type', 'application/json')
			->setBody(["_embedded" => ["elements" => [['id' => 1], ['id' => 2]]]]);

		$this->builder
			->uponReceiving('an OAuth GET request to /work_packages with new Token')
			->with($consumerRequestNewOAuthToken)
			->willRespondWith($providerResponseNewOAuthToken);

		$result = $this->service->request(
			$this->mockServerBaseUri,
			'invalid',
			'oauth',
			'myRefreshToken',
			$this->clientId,
			$this->clientSecret,
			'admin',
			'work_packages'
		);
		$this->assertSame(["_embedded" => ["elements" => [['id' => 1], ['id' => 2]]]], $result);
	}
}
