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
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;
use OC\Avatar\GuestAvatar;
use OC\Http\Client\Client;
use OC_Util;
use OCA\OpenProject\Exception\OpenprojectErrorException;
use OCA\OpenProject\Exception\OpenprojectResponseException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClientService;
use OCP\IAvatarManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Notification\IManager;
use PhpPact\Consumer\InteractionBuilder;
use PhpPact\Consumer\Model\ConsumerRequest;
use PhpPact\Consumer\Model\ProviderResponse;
use PhpPact\Standalone\MockService\MockServerEnvConfig;
use PHPUnit\Framework\TestCase;
use OCP\AppFramework\Http;
use Psr\Log\LoggerInterface;

class OpenProjectAPIServiceTest extends TestCase {
	/**
	 * @var InteractionBuilder
	 */
	private $builder;

	/**
	 * @var OpenProjectAPIService
	 */
	private $service;

	/**
	 * @var string
	 */
	private $clientId = 'U3V9_l262pNSENBnsqD2Uwylv5hQWCQ8lFPjCvGPbQc';

	/**
	 * @var string
	 */
	private $clientSecret = 'P5eu43P8YFFM9jeZKWcrpbskAUgHUBGYFQKB_8aeBtU';


	/**
	 * @var string
	 */
	private $workPackagesPath = '/api/v3/work_packages';

	/**
	 * @var string
	 */
	private $notificationsPath = '/api/v3/notifications';

	/**
	 * @var string
	 */
	private $fileLinksPath = '/api/v3/file_links';

	/**
	 * @var IConfig|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $defaultConfigMock;
	/**
	 * @var array<mixed>
	 */
	private $validFileLinkRequestBody = [
		'_type' => 'Collection',
		'_embedded' => [
			'elements' => [
				[
					'originData' => [
						'id' => 5503,
						'name' => 'logo.png',
						'mimeType' => 'image/png',
						'createdAt' => '2021-12-19T09:42:10.000Z',
						'lastModifiedAt' => '2021-12-20T14:00:13.000Z',
						'createdByName' => '',
						'lastModifiedByName' => ''
					],
					'_links' => [
						'storageUrl' => [
							'href' => 'https://nc.my-server.org'
						]
					]
				]
			]
		]
	];

	/**
	 * @var array <mixed>
	 */
	private $validStatusResponseBody = [
		"_type" => "Status",
		"id" => 7,
		"name" => "In progress",
		"isClosed" => false,
		"color" => "#CC5DE8",
		"isDefault" => false,
		"isReadonly" => false,
		"defaultDoneRatio" => null,
		"position" => 7
	];

	/**
	 * @var array <mixed>
	 */
	private $validTypeResponseBody = [
		"_type" => "Type",
		"id" => 3,
		"name" => "Phase",
		"color" => "#CC5DE8",
		"position" => 4,
		"isDefault" => true,
		"isMilestone" => false,
		"createdAt" => "2022-01-12T08:53:15Z",
		"updatedAt" => "2022-01-12T08:53:34Z"
	];

	/**
	 * @return void
	 * @before
	 */
	public function setupMockServer(): void {
		$config = new MockServerEnvConfig();
		$this->builder = new InteractionBuilder($config);
	}

	/**
	 * @return void
	 * @before
	 */
	public function setUpMocks(): void {
		$this->service = $this->getOpenProjectAPIService();
	}

	/**
	 * @param string $nodeClassName \OCP\Files\Node|\OCP\Files\File|\OCP\Files\Folder
	 * @return \OCP\Files\Node
	 */
	private function getNodeMock($nodeClassName = null) {
		if ($nodeClassName === null) {
			$nodeClassName = '\OCP\Files\Node';
		}
		// @phpstan-ignore-next-line
		$fileMock = $this->createMock($nodeClassName);
		$fileMock->method('isReadable')->willReturn(true);
		$fileMock->method('getName')->willReturn('logo.png');
		$fileMock->method('getMimeType')->willReturn('image/png');
		$fileMock->method('getCreationTime')->willReturn(1639906930);
		$fileMock->method('getMTime')->willReturn(1640008813);
		// @phpstan-ignore-next-line
		return $fileMock;
	}

	/**
	 * @param string $nodeClassName \OCP\Files\Node|\OCP\Files\File|\OCP\Files\Folder
	 * @return IRootFolder
	 */
	private function getStorageMock($nodeClassName = null) {
		$nodeMock = $this->getNodeMock($nodeClassName);

		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn([$nodeMock]);

		$storageMock = $this->getMockBuilder('\OCP\Files\IRootFolder')->getMock();
		$storageMock->method('getUserFolder')->willReturn($folderMock);
		return $storageMock;
	}

	/**
	 * @param IRootFolder|null $storageMock
	 * @param string $oAuthToken
	 * @param string $baseUrl
	 * @param string $userId
	 * @return OpenProjectAPIService
	 */
	private function getOpenProjectAPIService(
		$storageMock = null, $oAuthToken = '1234567890', $baseUrl = 'https://nc.my-server.org', $userId = 'testUser'
	) {
		$certificateManager = $this->getMockBuilder('\OCP\ICertificateManager')->getMock();
		$certificateManager->method('getAbsoluteBundlePath')->willReturn('/');

		$client = new GuzzleClient();

		//changed from nextcloud 24
		if (version_compare(OC_Util::getVersionString(), '24') >= 0) {
			// @phpstan-ignore-next-line
			$ocClient = new Client(
				$this->createMock(IConfig::class),                             // @phpstan-ignore-line
				$certificateManager,                                           // @phpstan-ignore-line
				$client,                                                       // @phpstan-ignore-line
				$this->createMock(\OC\Http\Client\LocalAddressChecker::class)  // @phpstan-ignore-line
			);
		} else {
			// @phpstan-ignore-next-line
			$ocClient = new Client(
				$this->createMock(IConfig::class),                             // @phpstan-ignore-line
				$this->createMock(ILogger::class),                             // @phpstan-ignore-line
				$certificateManager,                                           // @phpstan-ignore-line
				$client,                                                       // @phpstan-ignore-line
				$this->createMock(\OC\Http\Client\LocalAddressChecker::class)  // @phpstan-ignore-line
			);
		}

		$clientService = $this->getMockBuilder('\OCP\Http\Client\IClientService')->getMock();
		$clientService->method('newClient')->willReturn($ocClient);

		$avatarManagerMock = $this->getMockBuilder('\OCP\IAvatarManager')
			->getMock();
		$avatarManagerMock
			->method('getGuestAvatar')
			->willReturn(
				new GuestAvatar(
					'test',
					$this->createMock(LoggerInterface::class)
				)
			);
		if ($storageMock === null) {
			$storageMock = $this->createMock(IRootFolder::class);
		}
		$this->defaultConfigMock = $this->getMockBuilder(IConfig::class)->getMock();

		$this->defaultConfigMock
			->method('getUserValue')
			->withConsecutive(
				[$userId, 'integration_openproject', 'token'],
				[$userId, 'integration_openproject', 'refresh_token'],
				[$userId, 'integration_openproject', 'token'],
			)
			->willReturnOnConsecutiveCalls(
				$oAuthToken,
				'oAuthRefreshToken',
				'new-Token'
			);

		$pactMockServerConfig = new MockServerEnvConfig();

		$this->defaultConfigMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url'],

				// for second request after invalid token reply
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url'],
			)
			->willReturnOnConsecutiveCalls(
				$this->clientId,
				$this->clientSecret,
				$pactMockServerConfig->getBaseUri()->__toString(),

				// for second request after invalid token reply
				$this->clientId,
				$this->clientSecret,
				$pactMockServerConfig->getBaseUri()->__toString()
			);

		$urlGeneratorMock = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$urlGeneratorMock
			->method('getBaseUrl')
			->willReturn($baseUrl);

		return new OpenProjectAPIService(
			'integration_openproject',
			$this->createMock(IUserManager::class),
			$avatarManagerMock,
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
			$this->defaultConfigMock,
			$this->createMock(IManager::class),
			$clientService,
			$storageMock,
			$urlGeneratorMock,
			$this->createMock(ICacheFactory::class),
		);
	}

	/**
	 * @param array<string> $onlyMethods
	 * @param ICacheFactory|null $cacheFactoryMock
	 * @return OpenProjectAPIService|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function getServiceMock(
		array $onlyMethods = ['request'], $cacheFactoryMock = null
	): OpenProjectAPIService {
		$onlyMethods[] = 'getBaseUrl';
		if ($cacheFactoryMock === null) {
			$cacheFactoryMock = $this->createMock(ICacheFactory::class);
		}
		$mock = $this->getMockBuilder(OpenProjectAPIService::class)
			->setConstructorArgs(
				[
					'integration_openproject',
					$this->createMock(IUserManager::class),
					$this->createMock(IAvatarManager::class),
					$this->createMock(LoggerInterface::class),
					$this->createMock(IL10N::class),
					$this->createMock(IConfig::class),
					$this->createMock(IManager::class),
					$this->createMock(IClientService::class),
					$this->createMock(IRootFolder::class),
					$this->createMock(IURLGenerator::class),
					$cacheFactoryMock
				])
			->onlyMethods($onlyMethods)
			->getMock();
		$mock->method('getBaseUrl')->willReturn('https://nc.my-server.org');
		return $mock;
	}

	/**
	 * @return array<int, array<int, string|bool>>
	 */
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
	 * @return void
	 */
	public function testValidateOpenProjectURL(string $url, bool $expected) {
		$result = OpenProjectAPIService::validateURL($url);
		$this->assertSame($expected, $result);
	}

	/**
	 * @return array<mixed>
	 */
	public function searchWorkPackageDataProvider() {
		return [
			[
				["_embedded" => ["elements" => [['id' => 1], ['id' => 2], ['id' => 3]]]],
				[['id' => 1], ['id' => 2], ['id' => 3]]
			],
			[   // no search result returned
				[],
				[]
			]
		];
	}

	/**
	 * @param array<mixed> $response
	 * @param array<mixed> $expectedResult
	 * @return void
	 * @dataProvider searchWorkPackageDataProvider
	 */
	public function testSearchWorkPackageOnlyQuery(array $response, array $expectedResult) {
		$service = $this->getServiceMock();
		$service->method('request')
			->with(
					'user', 'work_packages',
					[
						'filters' => '[' .
							'{"typeahead":' .
								'{"operator":"**","values":["search query"]}'.
							'},'.
							'{"linkable_to_storage_url":'.
								'{"operator":"=","values":["https%3A%2F%2Fnc.my-server.org"]}}'.
							']',
						'sortBy' => '[["status","asc"],["updatedAt","desc"]]',
					]
			)
			->willReturn($response);
		$result = $service->searchWorkPackage('user', 'search query');
		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @return void
	 */
	public function testSearchWorkPackageByFileIdOnlyFileId() {
		$service = $this->getServiceMock();
		$service->method('request')
			->withConsecutive(
				[
					'user', 'work_packages',
					[
						'filters' => '[' .
							'{"file_link_origin_id":{"operator":"=","values":["123"]}},'.
							'{"linkable_to_storage_url":'.
								'{"operator":"=","values":["https%3A%2F%2Fnc.my-server.org"]}}'.
							']',
						'sortBy' => '[["status","asc"],["updatedAt","desc"]]',
					]
				],
			)
			->willReturnOnConsecutiveCalls(
				["_embedded" => ["elements" => [['id' => 1], ['id' => 2], ['id' => 3]]]]
			);
		$result = $service->searchWorkPackage('user', null, 123);
		$this->assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $result);
	}

	/**
	 * @return void
	 */
	public function testSearchWorkPackageByQueryAndFileId() {
		$service = $this->getServiceMock();
		$service->method('request')
			->with(
					'user', 'work_packages',
					[
						'filters' => '['.
							'{"file_link_origin_id":{"operator":"=","values":["123"]}},'.
							'{"typeahead":{"operator":"**","values":["search query"]}},'.
							'{"linkable_to_storage_url":'.
								'{"operator":"=","values":["https%3A%2F%2Fnc.my-server.org"]}'.
							'}'.
						']',
						'sortBy' => '[["status","asc"],["updatedAt","desc"]]',
					]
			)
			->willReturn(
				["_embedded" => ["elements" => [['id' => 4], ['id' => 5], ['id' => 6]]]]
			);
		$result = $service->searchWorkPackage('user', 'search query', 123);
		$this->assertSame([['id' => 4], ['id' => 5], ['id' => 6]], $result);
	}

	/**
	 * @return void
	 */
	public function testSearchWorkPackageRequestProblem() {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(['error' => 'some issue', 'statusCode' => 404 ]);
		$result = $service->searchWorkPackage('user', 'search query', 123);
		$this->assertSame(['error' => 'some issue', 'statusCode' => 404 ], $result);
	}

	/**
	 * @return void
	 */
	public function testGetNotificationsRequest() {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath($this->notificationsPath)
			->setQuery("filters=" . \Safe\json_encode([[
				'readIAN' =>
					['operator' => '=', 'values' => ['f']]
			]]))
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody(["_embedded" => ["elements" => [['_links' => 'data']]]]);

		$this->builder
			->uponReceiving('a GET request to /notifications')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->getNotifications(
			'testUser'
		);
		$this->assertSame([['_links' => 'data']], $result);
	}

	/**
	 * @return array<mixed>
	 */
	public function malformedResponsesDataProvider() {
		return [
			[["_embedded" => []]],
			[["_embedded" => ['element']]],
			[["embedded" => ['elements']]],
		];
	}
	/**
	 * @dataProvider malformedResponsesDataProvider
	 * @param array<mixed> $response
	 * @return void
	 */
	public function testGetNotificationsMalformedResponse($response) {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn($response);
		$result = $service->getNotifications('');
		$this->assertSame(["error" => "Malformed response"], $result);
	}

	/**
	 * @return void
	 */
	public function testGetNotificationsErrorResponse() {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(['error' => 'my error']);
		$result = $service->getNotifications('');
		$this->assertSame(["error" => "my error"], $result);
	}

	/**
	 * @return void
	 */
	public function testRequestUsingOAuthToken() {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath($this->workPackagesPath)
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody(["_embedded" => ["elements" => []]]);

		$this->builder
			->uponReceiving('an OAuth GET request to /work_packages')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->request(
			'testUser',
			'work_packages'
		);
		$this->assertSame(["_embedded" => ["elements" => []]], $result);
	}

	/**
	 * @return void
	 */
	public function testRequestRefreshOAuthToken() {
		$consumerRequestInvalidOAuthToken = new ConsumerRequest();
		$consumerRequestInvalidOAuthToken
			->setMethod('GET')
			->setPath($this->workPackagesPath)
			->setHeaders(["Authorization" => "Bearer invalid"]);

		$providerResponseInvalidOAuthToken = new ProviderResponse();
		$providerResponseInvalidOAuthToken
			->setStatus(Http::STATUS_UNAUTHORIZED)
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
				'&grant_type=refresh_token&refresh_token=oAuthRefreshToken'
			);

		$refreshTokenResponse = new ProviderResponse();
		$refreshTokenResponse
			->setStatus(Http::STATUS_OK)
			->setBody([
				"access_token" => "new-Token",
				"refresh_token" => "newRefreshToken"
			]);

		$this->builder->uponReceiving('a POST request to renew token')
			->with($refreshTokenRequest)
			->willRespondWith($refreshTokenResponse);

		$consumerRequestNewOAuthToken = new ConsumerRequest();
		$consumerRequestNewOAuthToken
			->setMethod('GET')
			->setPath($this->workPackagesPath)
			->setHeaders(["Authorization" => "Bearer new-Token"]);

		$providerResponseNewOAuthToken = new ProviderResponse();
		$providerResponseNewOAuthToken
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody(["_embedded" => ["elements" => [['id' => 1], ['id' => 2]]]]);

		$this->builder
			->uponReceiving('an OAuth GET request to /work_packages with new Token')
			->with($consumerRequestNewOAuthToken)
			->willRespondWith($providerResponseNewOAuthToken);

		$service = $this->getOpenProjectAPIService(null, 'invalid');
		$this->defaultConfigMock
			->expects($this->exactly(2))
			->method('setUserValue')
			->withConsecutive(
				['testUser', 'integration_openproject', 'refresh_token', 'newRefreshToken'],
				['testUser', 'integration_openproject', 'token', 'new-Token']
			);

		$result = $service->request(
			'testUser',
			'work_packages'
		);
		$this->assertSame(["_embedded" => ["elements" => [['id' => 1], ['id' => 2]]]], $result);
	}

	/**
	 * @return void
	 */
	public function testRequestToNotExistingPath() {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath('/api/v3/not_existing');

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(404);

		$this->builder
			->uponReceiving('an GET request to /api/v3/not_existing')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->request(
			'testUser',
			'not_existing'
		);
		$this->assertSame([
			'error' => 'Client error: `GET http://localhost:7200/api/v3/not_existing` ' .
						'resulted in a `404 Not Found` response',
			'statusCode' => 404
		], $result);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectAvatar() {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath('/api/v3/users/userWithAvatar/avatar')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->setHeaders(['Content-Type' => 'image/jpeg'])
			//setBody() expects iterable but we want to have raw data here and it seems to work fine
			// @phpstan-ignore-next-line
			->setBody('dataOfTheImage');

		$this->builder
			->uponReceiving('a request to get the avatar of a user that has an avatar')
			->with($consumerRequest)
			->willRespondWith($providerResponse);
		$service = $this->getOpenProjectAPIService(null, '1234567890', 'https://nc.my-server.org', 'userWithAvatar');
		$result = $service->getOpenProjectAvatar(
			'userWithAvatar',
			'Me'
		);
		$this->assertArrayHasKey('avatar', $result);
		$this->assertArrayHasKey('type', $result);
		$this->assertSame('dataOfTheImage', $result['avatar']);
		$this->assertSame('image/jpeg', $result['type']);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectAvatarNoAvatar() {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath('/api/v3/users/testUser/avatar')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_NOT_FOUND);

		$this->builder
			->uponReceiving('a request to get the avatar of a user that does not have one')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->getOpenProjectAvatar(
			'testUser',
			'Me'
		);
		$this->assertArrayHasKey('avatar', $result);
		//make sure its an image, if something else is returned it will throw an exception
		// @phpstan-ignore-next-line
		imagecreatefromstring($result['avatar']);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageStatusRequest(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath('/api/v3/statuses/7')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody($this->validStatusResponseBody);

		$this->builder
			->uponReceiving('a GET request to /statuses ')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->getOpenProjectWorkPackageStatus(
			'testUser',
			'7'
		);
		$this->assertSame($this->validStatusResponseBody, $result);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageStatusCacheHit(): void {
		$cacheMock = $this->getMockBuilder(ICache::class)
			->disableOriginalConstructor()
			->getMock();
		$cacheMock->method('get')->willReturn($this->validStatusResponseBody);
		$cacheFactoryMock = $this->getMockBuilder(ICacheFactory::class)
			->disableOriginalConstructor()
			->getMock();
		$cacheFactoryMock->method('createDistributed')->willReturn($cacheMock);
		$service = $this->getServiceMock(['request'], $cacheFactoryMock);
		$service->expects($this->never())->method('request');
		$result = $service->getOpenProjectWorkPackageStatus('user', 'statusId');
		$this->assertSame($this->validStatusResponseBody, $result);
	}
	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageStatusCacheMiss(): void {
		$cacheMock = $this->getMockBuilder(ICache::class)
			->disableOriginalConstructor()
			->getMock();
		$cacheMock->method('get')->willReturn(null);
		$cacheMock->expects($this->once())->method('set');
		$cacheFactoryMock = $this->getMockBuilder(ICacheFactory::class)
			->disableOriginalConstructor()
			->getMock();
		$cacheFactoryMock->method('createDistributed')->willReturn($cacheMock);
		$service = $this->getServiceMock(['request'], $cacheFactoryMock);
		$service->expects($this->once())
			->method('request')
			->willReturn($this->validStatusResponseBody);
		$result = $service->getOpenProjectWorkPackageStatus('user', 'statusId');
		$this->assertSame($this->validStatusResponseBody, $result);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageStatusMalFormedResponse(): void {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(['error' => 'Malformed response']);
		$result = $service->getOpenProjectWorkPackageStatus('', '');
		$this->assertSame(['error' => 'Malformed response'], $result);
	}



	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageTypeRequest(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath('/api/v3/types/3')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody($this->validTypeResponseBody);

		$this->builder
			->uponReceiving('a GET request to /type ')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->getOpenProjectWorkPackageType(
			'testUser',
			'3'
		);

		$this->assertSame($this->validTypeResponseBody, $result);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageTypeCacheHit(): void {
		$cacheMock = $this->getMockBuilder(ICache::class)
			->disableOriginalConstructor()
			->getMock();
		$cacheMock->method('get')->willReturn($this->validTypeResponseBody);
		$cacheFactoryMock = $this->getMockBuilder(ICacheFactory::class)
			->disableOriginalConstructor()
			->getMock();
		$cacheFactoryMock->method('createDistributed')->willReturn($cacheMock);
		$service = $this->getServiceMock(['request'], $cacheFactoryMock);
		$service->expects($this->never())->method('request');
		$result = $service->getOpenProjectWorkPackageType('user', 'typeId');
		$this->assertSame($this->validTypeResponseBody, $result);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageTypeCacheMiss(): void {
		$cacheMock = $this->getMockBuilder(ICache::class)
			->disableOriginalConstructor()
			->getMock();
		$cacheMock->method('get')->willReturn(null);
		$cacheMock->expects($this->once())->method('set');
		$cacheFactoryMock = $this->getMockBuilder(ICacheFactory::class)
			->disableOriginalConstructor()
			->getMock();
		$cacheFactoryMock->method('createDistributed')->willReturn($cacheMock);
		$service = $this->getServiceMock(['request'], $cacheFactoryMock);
		$service->expects($this->once())
			->method('request')
			->willReturn($this->validTypeResponseBody);
		$result = $service->getOpenProjectWorkPackageType('user', 'typeId');
		$this->assertSame($this->validTypeResponseBody, $result);
	}


	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageTypeMalFormedResponse(): void {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(['error' => 'Malformed response']);
		$result = $service->getOpenProjectWorkPackageType('', '');
		$this->assertSame(['error' => 'Malformed response'], $result);
	}

	/**
	 * @return array<mixed>
	 */
	public function getOpenProjectOauthURLValidDataProvider() {
		return [
			["https://openproject"],
			["https://openproject/"]
		];
	}


	/**
	 * @dataProvider getOpenProjectOauthURLValidDataProvider
	 * @return void
	 */
	public function testGetOpenProjectOauthURL(string $oauthInstanceUrl) {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url'],
				['integration_openproject', 'client_id'],
				['integration_openproject', 'oauth_instance_url'],
			)->willReturnOnConsecutiveCalls('clientID', 'SECRET', $oauthInstanceUrl, 'clientID', $oauthInstanceUrl);

		$url = $this->createMock(IURLGenerator::class);
		$url->expects($this->once())
			->method('getAbsoluteURL')
			->willReturn('http://nextcloud.org/index.php/oauth-redirect');
		$result = $this->service::getOpenProjectOauthURL($configMock, $url);
		$this->assertSame(
			'https://openproject/oauth/authorize?' .
			'client_id=clientID&' .
			'redirect_uri=' . urlencode('http://nextcloud.org/index.php/oauth-redirect') .
			'&response_type=code',
			$result
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function getOpenProjectOauthURLInvalidDataProvider() {
		return [
			[
				'clientId',
				'clientSecret',
				'openproject', // invalid oauth instance url
			],
			[
				'clientId',
				'clientSecret',
				'', // empty oauth instance url
			],
			[
				'clientId',
				'', // empty client secret
				'https://openproject',
			],
			[
				'', // empty client id
				'clientSecret',
				'https://openproject',
			],
		];
	}

	/**
	 * @return void
	 *
	 * @dataProvider getOpenProjectOauthURLInvalidDataProvider
	 */
	public function testGetOpenProjectOauthURLWithInvalidAdminConfig(
		string $clientId, string $clientSecret, string $oauthInstanceUrl
	) {
		$url = $this->createMock(IURLGenerator::class);
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url']
			)->willReturnOnConsecutiveCalls($clientId, $clientSecret, $oauthInstanceUrl);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('OpenProject admin config is not valid!');
		$this->service::getOpenProjectOauthURL($configMock, $url);
	}

	/**
	 * @return array<mixed>
	 */
	public function connectExpectionDataProvider() {
		$requestMock = $this->getMockBuilder('\Psr\Http\Message\RequestInterface')->getMock();
		$responseMock402 = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$responseMock402->method('getStatusCode')->willReturn(402);
		$responseMock403 = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$responseMock403->method('getStatusCode')->willReturn(403);
		$responseMock500 = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$responseMock500->method('getStatusCode')->willReturn(500);
		$responseMock501 = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$responseMock501->method('getStatusCode')->willReturn(501);

		return [
			[
				new ConnectException('a connection problem', $requestMock),
				404,
				'a connection problem'
			],
			[
				new ClientException('some client problem', $requestMock, $responseMock403),
				403,
				'some client problem'
			],
			[
				new ClientException('some client problem', $requestMock, $responseMock402),
				402,
				'some client problem'
			],
			[
				new ServerException('some server issue', $requestMock, $responseMock501),
				501,
				'some server issue'
			],
			[
				new BadResponseException('some issue', $requestMock, $responseMock500),
				500,
				'some issue'
			],
			[
				new \Exception('some issue'),
				500,
				'some issue'
			],

		];
	}

	/**
	 * @return array<array<array<string>>>
	 */
	public function getNodeNotFoundExceptionDataProvider() {
		return [
			[[]],
			[['string']],
		];
	}

	/**
	 * @dataProvider getNodeNotFoundExceptionDataProvider
	 * @param array<array<array<string>>> $expectedReturn
	 * @return void
	 */
	public function testGetNodeNotFoundException($expectedReturn) {
		$folderMock = $this->getMockBuilder('\OCP\Files\Folder')->getMock();
		$folderMock->method('getById')->willReturn($expectedReturn);
		$storageMock = $this->getMockBuilder('\OCP\Files\IRootFolder')->getMock();
		$storageMock->method('getUserFolder')->willReturn($folderMock);
		$service = $this->getOpenProjectAPIService($storageMock);
		$this->expectException(NotFoundException::class);
		$service->getNode('me', 1234);
	}

	/**
	 * @return array<array<string>>
	 */
	public function getNodeDataProvider() {
		return [
			['\OCP\Files\File'],
			['\OCP\Files\Folder'],
		];
	}

	/**
	 * @dataProvider getNodeDataProvider
	 * @param string $nodeClassName
	 * @return void
	 */
	public function testGetNode($nodeClassName) {
		$storageMock = $this->getStorageMock($nodeClassName);
		$service = $this->getOpenProjectAPIService($storageMock);
		$result = $service->getNode('me', 1234);
		$this->assertTrue($result instanceof \OCP\Files\Node);
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileRequest(): void {
		$service = $this->getServiceMock(['request', 'getNode']);

		$service->method('getNode')
			->willReturn($this->getNodeMock());
		$service->method('request')
			->willReturn(['_type' => 'Collection', '_embedded' => ['elements' => [['id' => 2456]]]]);

		$service->expects($this->once())
			->method('request')
			->with(
				'user', 'work_packages/123/file_links',
				['body' => \Safe\json_encode($this->validFileLinkRequestBody)]
			);

		$result = $service->linkWorkPackageToFile(
			123, 5503, 'logo.png', 'user'
		);
		$this->assertSame(2456, $result);
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileFileNotReadable(): void {
		$service = $this->getServiceMock(['request', 'getNode']);

		$fileMock = $this->getMockBuilder('\OCP\Files\File')->getMock();
		$fileMock->method('isReadable')->willReturn(false);

		$service->method('getNode')
			->willReturn($fileMock);

		$service->expects($this->never())
			->method('request');

		$this->expectException(NotPermittedException::class);
		$service->linkWorkPackageToFile(
			123, 5503, 'logo.png', 'user'
		);
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileFileNotFound(): void {
		$service = $this->getServiceMock(['request', 'getNode']);

		$fileMock = $this->getMockBuilder('\OCP\Files\File')->getMock();
		$fileMock->method('isReadable')
			->willThrowException(new NotFoundException());

		$service->method('getNode')
			->willReturn($fileMock);

		$service->expects($this->never())
			->method('request');

		$this->expectException(NotFoundException::class);
		$result = $service->linkWorkPackageToFile(
			123, 5503, 'logo.png', 'user'
		);
	}
	/**
	 * @return void
	 * @param \Exception $exception
	 * @param int $expectedHttpStatusCode
	 * @param string $expectedError
	 * @dataProvider connectExpectionDataProvider
	 *
	 */
	public function testRequestException(
		$exception, $expectedHttpStatusCode, $expectedError
	) {
		$certificateManager = $this->getMockBuilder('\OCP\ICertificateManager')->getMock();
		$certificateManager->method('getAbsoluteBundlePath')->willReturn('/');

		$ocClient = $this->getMockBuilder('\OCP\Http\Client\IClient')->getMock();
		$ocClient->method('get')->willThrowException($exception);
		$clientService = $this->getMockBuilder('\OCP\Http\Client\IClientService')->getMock();
		$clientService->method('newClient')->willReturn($ocClient);

		$configMock = $this->getMockBuilder(IConfig::class)
			->getMock();
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url'],
			)
			->willReturnOnConsecutiveCalls(
				$this->clientId,
				$this->clientSecret,
				'http://openproject.org',
			);
		$configMock
			->method('getUserValue')
			->withConsecutive(
				['','integration_openproject', 'token'],
				['','integration_openproject', 'refresh_token'],
			)
			->willReturnOnConsecutiveCalls(
				'',
				'',
			);

		$service = new OpenProjectAPIService(
			'integration_openproject',
			$this->createMock(IUserManager::class),
			$this->createMock(IAvatarManager::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
			$configMock,
			$this->createMock(IManager::class),
			$clientService,
			$this->createMock(IRootFolder::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(ICacheFactory::class),
		);

		$response = $service->request('', '', []);
		$this->assertSame($expectedError, $response['error']);
		$this->assertSame($expectedHttpStatusCode, $response['statusCode']);
	}

	public function testLinkWorkPackageToFilePact(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('POST')
			->setPath($this->workPackagesPath . '/123/file_links')
			->setHeaders(['Authorization' => 'Bearer 1234567890'])
			->setBody($this->validFileLinkRequestBody);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody(['_type' => 'Collection', '_embedded' => ['elements' => [['id' => 1337]]]]);

		$this->builder
			->uponReceiving('a POST request to /work_packages')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$storageMock = $this->getStorageMock();

		$service = $this->getOpenProjectAPIService($storageMock);

		$result = $service->linkWorkPackageToFile(
			123,
			5503,
			'logo.png',
			'testUser'
		);

		$this->assertSame(1337, $result);
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileEmptyStorageUrlPact(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('POST')
			->setPath($this->workPackagesPath . '/123/file_links')
			->setHeaders(['Authorization' => 'Bearer 1234567890'])
			->setBody([
				'_type' => 'Collection',
				'_embedded' => [
					'elements' => [
						[
							'originData' => $this->validFileLinkRequestBody['_embedded']['elements'][0]['originData'],
							'_links' => [
								'storageUrl' => [
									'href' => ''
								]
							]
						]
					]
				]
			]);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_BAD_REQUEST)
			->addHeader('Content-Type', 'application/json')
			->setBody([
				'_type' => 'Error',
				'errorIdentifier' => 'urn:openproject-org:api:v3:errors:InvalidRequestBody',
				'message' => 'The request body was invalid.'
			]);

		$this->builder
			->uponReceiving('a POST request to /work_packages with empty storage URL')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$storageMock = $this->getStorageMock();
		$service = $this->getOpenProjectAPIService(
			$storageMock,
			'1234567890',
			''
		);

		$this->expectException(OpenprojectErrorException::class);
		$service->linkWorkPackageToFile(
			123,
			5503,
			'logo.png',
			'testUser'
		);
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileNotAvailableStorageUrlPact(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('POST')
			->setPath($this->workPackagesPath . '/123/file_links')
			->setHeaders(['Authorization' => 'Bearer 1234567890'])
			->setBody([
				'_type' => 'Collection',
				'_embedded' => [
					'elements' => [
						[
							'originData' => $this->validFileLinkRequestBody['_embedded']['elements'][0]['originData'],
							'_links' => [
								'storageUrl' => [
									'href' => 'http://not-existing'
								]
							]
						]
					]
				]
			]);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_UNPROCESSABLE_ENTITY)
			->addHeader('Content-Type', 'application/json')
			->setBody([
				'_type' => 'Error',
				'errorIdentifier' => 'urn:openproject-org:api:v3:errors:PropertyConstraintViolation',
				'message' => 'The request was invalid. File Link logo.png - Storage was invalid.'
			]);

		$this->builder
			->uponReceiving('a POST request to /work_packages with a not available storage URL')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$storageMock = $this->getStorageMock();
		$service = $this->getOpenProjectAPIService(
			$storageMock,
			'1234567890',
			'http://not-existing'
			);

		$this->expectException(OpenprojectErrorException::class);
		$service->linkWorkPackageToFile(
			123,
			5503,
			'logo.png',
			'testUser'
		);
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileMissingPermissionPact(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('POST')
			->setPath($this->workPackagesPath . '/123/file_links')
			->setHeaders(['Authorization' => 'Bearer MissingPermission'])
			->setBody($this->validFileLinkRequestBody);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_FORBIDDEN)
			->addHeader('Content-Type', 'application/json')
			->setBody([
				'_type' => 'Error',
				'errorIdentifier' => 'urn:openproject-org:api:v3:errors:MissingPermission',
				'message' => 'You are not authorized to access this resource.'
			]);

		$this->builder
			->uponReceiving('a POST request to /work_packages but missing permission')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$storageMock = $this->getStorageMock();
		$service = $this->getOpenProjectAPIService($storageMock, 'MissingPermission');

		$this->expectException(OpenprojectErrorException::class);
		$service->linkWorkPackageToFile(
			123,
			5503,
			'logo.png',
			'testUser'
		);
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileNotFoundPact(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('POST')
			->setPath($this->workPackagesPath . '/999999/file_links')
			->setHeaders(['Authorization' => 'Bearer 1234567890'])
			->setBody($this->validFileLinkRequestBody);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_NOT_FOUND)
			->addHeader('Content-Type', 'application/json')
			->setBody([
				'_type' => 'Error',
				'errorIdentifier' => 'urn:openproject-org:api:v3:errors:NotFound',
				'message' => 'The requested resource could not be found.'
			]);

		$this->builder
			->uponReceiving('a POST request to /work_packages but not existing workpackage')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$storageMock = $this->getStorageMock();
		$service = $this->getOpenProjectAPIService($storageMock);

		$this->expectException(OpenprojectErrorException::class);
		$service->linkWorkPackageToFile(
			999999,
			5503,
			'logo.png',
			'testUser'
		);
	}

	public function testMarkAllNotificationsOfWorkPackageAsReadPact(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('POST')
			->setPath($this->notificationsPath . '/read_ian')
			->setQuery('filters=' . urlencode('[{"resourceId":{"operator":"=","values":["123"]}}]'))
			->setHeaders(['Authorization' => 'Bearer 1234567890'])
			->setBody(null);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_NO_CONTENT);

		$this->builder
			->uponReceiving('a POST request to mark all notifications of a WP as read')
			->with($consumerRequest)
			->willRespondWith($providerResponse);


		$service = $this->getOpenProjectAPIService();

		$result = $service->markAllNotificationsOfWorkPackageAsRead(
			123,
			'testUser'
		);

		$this->assertSame(['success' => true], $result);
	}

	public function testMarkAllNotificationsOfANotExistingWorkPackageAsReadPact(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('POST')
			->setPath($this->notificationsPath . '/read_ian')
			->setQuery('filters=' . urlencode('[{"resourceId":{"operator":"=","values":["789"]}}]'))
			->setHeaders(['Authorization' => 'Bearer 1234567890'])
			->setBody(null);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_BAD_REQUEST)
			->setBody(["_type" => "Error", "errorIdentifier" => "urn:openproject-org:api:v3:errors:InvalidQuery", "message" => ["Filters Resource filter has invalid values."]]);

		$this->builder
			->uponReceiving('a POST request to mark all notifications of a not-existing WP as read')
			->with($consumerRequest)
			->willRespondWith($providerResponse);


		$service = $this->getOpenProjectAPIService();
		$this->expectException(OpenprojectErrorException::class);
		$result = $service->markAllNotificationsOfWorkPackageAsRead(
			789,
			'testUser'
		);
	}
	/**
	 * @return array<mixed>
	 */
	public function adminConfigStatusProvider(): array {
		return [
			[
				'client_id' => '',
				'client_secret' => '',
				'oauth_instance_url' => '',
				'expected' => false,
			],
			[
				'client_id' => 'clientID',
				'client_secret' => '',
				'oauth_instance_url' => 'https://openproject',
				'expected' => false,
			],
			[
				'client_id' => 'clientID',
				'client_secret' => 'clientSecret',
				'oauth_instance_url' => '',
				'expected' => false,
			],
			[
				'client_id' => 'clientID',
				'client_secret' => 'clientSecret',
				'oauth_instance_url' => 'https://',
				'expected' => false,
			],
			[
				'client_id' => 'clientID',
				'client_secret' => 'clientSecret',
				'oauth_instance_url' => 'openproject.com',
				'expected' => false,
			],
			[
				'client_id' => 'clientID',
				'client_secret' => 'clientSecret',
				'oauth_instance_url' => 'https://openproject',
				'expected' => true,
			],
			[
				'client_id' => 'clientID',
				'client_secret' => 'clientSecret',
				'oauth_instance_url' => 'https://openproject.com/',
				'expected' => true,
			],
			[
				'client_id' => 'clientID',
				'client_secret' => 'clientSecret',
				'oauth_instance_url' => 'https://openproject.com',
				'expected' => true,
			],
		];
	}

	/**
	 * @dataProvider adminConfigStatusProvider
	 * @return void
	 */
	public function testIsAdminConfigOk(
		string $client_id, string $client_secret, string $oauth_instance_url, bool $expected
	) {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url'],
			)->willReturnOnConsecutiveCalls($client_id, $client_secret, $oauth_instance_url);

		$this->assertSame($expected, $this->service::isAdminConfigOk($configMock));
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinksResponse(): void {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn([
				'_type' => 'Collection',
				'_embedded' => [
					'elements' => [
						[
							'id' => 8,
							'_type' => "FileLink",
							'originData' => [
								'id' => 5
							],
						]
					]
				]
			]);
		$result = $service->getWorkPackageFileLinks(7, 'user');
		$this->assertSame([[
			'id' => 8,
			'_type' => "FileLink",
			'originData' => [
				'id' => 5
			]
		]], $result);
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinksErrorResponse(): void {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn([
				'error' => 'something went wrong',
			]);
		$this->expectException(OpenprojectErrorException::class);
		$service->getWorkPackageFileLinks(7, 'user');
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinksMalFormedResponse(): void {
		$service = $this->getServiceMock();
		$service->method('request')
				->willReturn([
					'_type' => '',
				]);
		$this->expectException(OpenprojectResponseException::class);
		$service->getWorkPackageFileLinks(7, 'user');
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinksPact(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath($this->workPackagesPath . '/7/file_links')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody([
				'_type' => 'Collection',
				'_embedded' => [
					'elements' => [
						[
							'id' => 8,
							'_type' => "FileLink",
							'originData' => [
								'id' => 5
							],
						]
					]
				]
			]);

		$this->builder
			->uponReceiving('a GET request to /work_package/{id}/file_links')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$storageMock = $this->getStorageMock();
		$service = $this->getOpenProjectAPIService($storageMock);

		$result = $service->getWorkPackageFileLinks(7, 'testUser');

		$this->assertSame([[
			'id' => 8,
			'_type' => "FileLink",
			'originData' => [
				'id' => 5
			]
		]], $result);
	}
	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinkNotFoundPact(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath($this->workPackagesPath . '/100/file_links')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_NOT_FOUND)
			->addHeader('Content-Type', 'application/json')
			->setBody([
				'_type' => 'Error',
				'errorIdentifier' => 'urn:openproject-org:api:v3:errors:NotFound',
				'message' => 'The requested resource could not be found.'
			]);

		$this->builder
			->uponReceiving('a GET request to /work_package/{id}/file_links to a non-existing work package')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$storageMock = $this->getStorageMock();
		$service = $this->getOpenProjectAPIService($storageMock);
		$this->expectException(OpenprojectErrorException::class);
		$service->getWorkPackageFileLinks(100, 'testUser');
	}

	/**
	 * @return void
	 */
	public function testDeleteFileLinkResponse(): void {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn([
				'success' => true
			]);
		$result = $service->deleteFileLink(7, 'user');
		$this->assertSame([
			'success' => true
		], $result);
	}


	/**
	 * @return void
	 */
	public function testDeleteFileLinkErrorResponse(): void {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn([
				'error' => 'something went wrong',
			]);
		$this->expectException(OpenprojectErrorException::class);
		$service->deleteFileLink(7, 'user');
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileDeleteLinksPact(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('DELETE')
			->setPath($this->fileLinksPath . '/10')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_NO_CONTENT);

		$this->builder
			->uponReceiving('a DELETE request to /file_links')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$storageMock = $this->getStorageMock();
		$service = $this->getOpenProjectAPIService($storageMock);

		$result = $service->deleteFileLink(10, 'testUser');

		$this->assertSame([
			'success' => true
		], $result);
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileDeleteLinkNotFoundPact(): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('DELETE')
			->setPath($this->fileLinksPath . '/12345')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_NOT_FOUND)
			->addHeader('Content-Type', 'application/json')
			->setBody([
				'_type' => 'Error',
				'errorIdentifier' => 'urn:openproject-org:api:v3:errors:NotFound',
				'message' => 'The requested resource could not be found.'
			]);

		$this->builder
			->uponReceiving('a DELETE request to /file_links but not existing file link')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$storageMock = $this->getStorageMock();
		$service = $this->getOpenProjectAPIService($storageMock);

		$this->expectException(OpenprojectErrorException::class);
		$service->deleteFileLink(12345, 'testUser');
	}
}
