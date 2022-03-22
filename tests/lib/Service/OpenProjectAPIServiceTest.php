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
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IURLGenerator;
use PhpPact\Consumer\InteractionBuilder;
use PhpPact\Consumer\Model\ConsumerRequest;
use PhpPact\Consumer\Model\ProviderResponse;
use PhpPact\Standalone\MockService\MockServerEnvConfig;
use PHPUnit\Framework\TestCase;
use OCP\AppFramework\Http;

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
	private $mockServerBaseUri;

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
							'href' => 'http://nextcloud.org'
						]
					]
				]
			]
		]
	];
	/**
	 * @return void
	 * @before
	 */
	public function setupMockServer(): void {
		$config = new MockServerEnvConfig();
		$this->builder = new InteractionBuilder($config);
		$this->mockServerBaseUri = $config->getBaseUri()->__toString();
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
	 * @return OpenProjectAPIService
	 */
	private function getOpenProjectAPIService(
		$storageMock = null, $oAuthToken = '1234567890'
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
					$this->createMock(\Psr\Log\LoggerInterface::class)
				)
			);
		if ($storageMock === null) {
			$storageMock = $this->createMock(\OCP\Files\IRootFolder::class);
		}
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();

		$configMock
			->method('getUserValue')
			->withConsecutive(
				['testUser', 'integration_openproject', 'token'],
				['testUser', 'integration_openproject', 'refresh_token'],
				['testUser', 'integration_openproject', 'token'],
			)
			->willReturnOnConsecutiveCalls(
				$oAuthToken,
				'oAuthRefreshToken',
				'new-Token'
			);

		$pactMockServerConfig = new MockServerEnvConfig();

		$configMock
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

		return new OpenProjectAPIService(
			'integration_openproject',
			$this->createMock(\OCP\IUserManager::class),
			$avatarManagerMock,
			$this->createMock(\Psr\Log\LoggerInterface::class),
			$this->createMock(\OCP\IL10N::class),
			$configMock,
			$this->createMock(\OCP\Notification\IManager::class),
			$clientService,
			$storageMock
		);
	}

	/**
	 * @param array<string> $onlyMethods
	 * @return OpenProjectAPIService|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function getServiceMock(array $onlyMethods = ['request']): OpenProjectAPIService {
		return $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->onlyMethods($onlyMethods)
			->getMock();
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
		$result = OpenProjectAPIService::validateOpenProjectURL($url);
		$this->assertSame($expected, $result);
	}

	/**
	 * @return array<mixed>
	 */
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
	 * @param array<mixed> $descriptionResponse
	 * @param array<mixed> $subjectResponse
	 * @param array<mixed> $expectedResult
	 * @return void
	 * @dataProvider searchWorkPackageDataProvider
	 */
	public function testSearchWorkPackageOnlyQueryDescAndSubjectResponse(
		array $descriptionResponse, array $subjectResponse, array $expectedResult
	) {
		$service = $this->getServiceMock();
		$service->method('request')
			->withConsecutive(
				[
					'user', 'work_packages',
					[
						'filters' => '[{"description":{"operator":"~","values":["search query"]}},{"status":{"operator":"o","values":[]}}]',
						'sortBy' => '[["updatedAt", "desc"]]',
					]
				],
				[
					'user', 'work_packages',
					[
						'filters' => '[{"subject":{"operator":"~","values":["search query"]}},{"status":{"operator":"o","values":[]}}]',
						'sortBy' => '[["updatedAt", "desc"]]',
					]
				]
			)
			->willReturnOnConsecutiveCalls(
				$descriptionResponse,
				$subjectResponse
			);
		$result = $service->searchWorkPackage('user', 'search query');
		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @param array<mixed> $descriptionResponse
	 * @param array<mixed> $subjectResponse
	 * @param array<mixed> $expectedResult
	 * @return void
	 * @dataProvider searchWorkPackageDataProvider
	 */
	public function testSearchWorkPackageQueryAndStorage(
		array $descriptionResponse, array $subjectResponse, array $expectedResult
	) {
		$service = $this->getServiceMock();
		$service->method('request')
			->withConsecutive(
				[
					'user', 'work_packages',
					[
						'filters' => '[' .
										'{"description":' .
											'{"operator":"~","values":["search query"]}'.
										'},'.
										'{"status":{"operator":"o","values":[]}},'.
										'{"linkable_to_storage_url":'.
											'{"operator":"=","values":["https:\/\/nc.my-server.org"]}}'.
									']',
						'sortBy' => '[["updatedAt", "desc"]]',
					]
				],
				[
					'user', 'work_packages',
					[
						'filters' => '[' .
										'{"subject":' .
											'{"operator":"~","values":["search query"]}'.
										'},'.
										'{"status":{"operator":"o","values":[]}},'.
										'{"linkable_to_storage_url":'.
											'{"operator":"=","values":["https:\/\/nc.my-server.org"]}}'.
									']',
						'sortBy' => '[["updatedAt", "desc"]]',
					]
				]
			)
			->willReturnOnConsecutiveCalls(
				$descriptionResponse,
				$subjectResponse
			);
		$result = $service->searchWorkPackage('user', 'search query', null, 'https://nc.my-server.org');
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
						'filters' => '[{"file_link_origin_id":{"operator":"=","values":["123"]}}]',
						'sortBy' => '[["updatedAt", "desc"]]',
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
	public function testSearchWorkPackageByFileIdQueryAndFileId() {
		$service = $this->getServiceMock();
		$service->method('request')
			->withConsecutive(
				[
					'user', 'work_packages',
					[
						'filters' => '[{"file_link_origin_id":{"operator":"=","values":["123"]}},{"description":{"operator":"~","values":["search query"]}},{"status":{"operator":"o","values":[]}}]',
						'sortBy' => '[["updatedAt", "desc"]]',
					]
				],
				[
					'user', 'work_packages',
					[
						'filters' => '[{"subject":{"operator":"~","values":["search query"]}},{"status":{"operator":"o","values":[]}}]',
						'sortBy' => '[["updatedAt", "desc"]]',
					]
				]
			)
			->willReturnOnConsecutiveCalls(
				["_embedded" => ["elements" => [['id' => 1], ['id' => 2], ['id' => 3]]]],
				["_embedded" => ["elements" => [['id' => 4], ['id' => 5], ['id' => 6]]]]
			);
		$result = $service->searchWorkPackage('user', 'search query', 123);
		$this->assertSame([['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5], ['id' => 6]], $result);
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
	public function testSearchWorkPackageSecondRequestProblem() {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturnOnConsecutiveCalls(
				["_embedded" => ["elements" => [['id' => 1], ['id' => 2], ['id' => 3]]]],
				['error' => 'some issue', 'statusCode' => 404 ]
			);
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
			->setPath($this->workPackagesPath)
			->setHeaders(["Authorization" => "Bearer 1234567890"])
			->addQueryParameter('filters', '[{"status":{"operator":"!","values":["14"]}}]')
			->addQueryParameter('sortBy', '[["updatedAt", "desc"]]');

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody(["_embedded" => ["elements" => [['some' => 'data']]]]);

		$this->builder
			->uponReceiving('a GET request to /work_packages with filter and sorting')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->getNotifications(
			'testUser'
		);
		$this->assertSame([['some' => 'data']], $result);
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
		$result = $service->getNotifications('', '');
		$this->assertSame(["error" => "Malformed response"], $result);
	}

	/**
	 * @return void
	 */
	public function testGetNotificationsErrorResponse() {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(['error' => 'my error']);
		$result = $service->getNotifications('', '');
		$this->assertSame(["error" => "my error"], $result);
	}

	/**
	 * @return void
	 */
	public function testGetNotificationsFilters() {
		$service = $this->getServiceMock(['request', 'now']);
		$service->method('now')
			->willReturn("2022-01-27T08:15:48Z");
		$service->expects($this->once())
			->method('request')
			->with(
				'user', 'work_packages',
				[
					'filters' => '[{"updatedAt":{"operator":"<>d","values":["2022-01-01T12:01:01Z","2022-01-27T08:15:48Z"]}},{"status":{"operator":"!","values":["14"]}}]',
					'sortBy' => '[["updatedAt", "desc"]]'
				]);

		$service->getNotifications('user', '2022-01-01T12:01:01Z');
	}

	/**
	 * @return void
	 */
	public function testGetNotificationsLimit() {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(["_embedded" => ["elements" => [['id' => 1], ['id' => 2], ['id' => 3]]]]);
		$result = $service->getNotifications('', '', 2);
		$this->assertSame([['id' => 1], ['id' => 2]], $result);
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
			->setBody(["access_token" => "new-Token"]);

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
			->uponReceiving('a request to get the avatar of a user')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->getOpenProjectAvatar(
			$this->mockServerBaseUri,
			'1234567890',
			'myRefreshToken',
			$this->clientId,
			$this->clientSecret,
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
			->setPath('/api/v3/users/userWithoutAvatar/avatar')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_NOT_FOUND);

		$this->builder
			->uponReceiving('a request to get the avatar of a user that does not have one')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->getOpenProjectAvatar(
			$this->mockServerBaseUri,
			'1234567890',
			'myRefreshToken',
			$this->clientId,
			$this->clientSecret,
			'userWithoutAvatar',
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
			->setBody(["_type" => "Status", "id" => 7, "name" => "In progress",
				"isClosed" => false, "color" => "#CC5DE8", "isDefault" => false, "isReadonly" => false, "defaultDoneRatio" => null, "position" => 7]);

		$this->builder
			->uponReceiving('a GET request to /statuses ')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->getOpenProjectWorkPackageStatus(
			'testUser',
			'7'
		);
		$this->assertSame(["_type" => "Status", "id" => 7, "name" => "In progress",
			"isClosed" => false, "color" => "#CC5DE8", "isDefault" => false, "isReadonly" => false, "defaultDoneRatio" => null, "position" => 7], $result);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageStatusResponse(): void {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(["_type" => "Status", "id" => 7, "name" => "In progress",
				"isClosed" => false, "color" => "#CC5DE8", "isDefault" => false, "isReadonly" => false, "defaultDoneRatio" => null, "position" => 7]);
		$result = $service->getOpenProjectWorkPackageStatus('user', 'statusId');
		$this->assertSame(["_type" => "Status", "id" => 7, "name" => "In progress",
			"isClosed" => false, "color" => "#CC5DE8", "isDefault" => false, "isReadonly" => false, "defaultDoneRatio" => null, "position" => 7], $result);
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
			->setBody(["_type" => "Type", "id" => 3, "name" => "Phase",
				"color" => "#CC5DE8", "position" => 4, "isDefault" => true, "isMilestone" => false, "createdAt" => "2022-01-12T08:53:15Z", "updatedAt" => "2022-01-12T08:53:34Z"]);

		$this->builder
			->uponReceiving('a GET request to /type ')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->getOpenProjectWorkPackageType(
			'testUser',
			'3'
		);

		$this->assertSame(["_type" => "Type", "id" => 3, "name" => "Phase",
			"color" => "#CC5DE8", "position" => 4, "isDefault" => true, "isMilestone" => false, "createdAt" => "2022-01-12T08:53:15Z", "updatedAt" => "2022-01-12T08:53:34Z"], $result);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageTypeResponse(): void {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn([
				"_type" => "Type", "id" => 3, "name" => "Phase",
				"color" => "#CC5DE8", "position" => 4, "isDefault" => true, "isMilestone" => false, "createdAt" => "2022-01-12T08:53:15Z", "updatedAt" => "2022-01-12T08:53:34Z"
			]);
		$result = $service->getOpenProjectWorkPackageType('user', 'typeId');
		$this->assertSame([
			"_type" => "Type", "id" => 3, "name" => "Phase",
			"color" => "#CC5DE8", "position" => 4, "isDefault" => true, "isMilestone" => false, "createdAt" => "2022-01-12T08:53:15Z", "updatedAt" => "2022-01-12T08:53:34Z"
		], $result);
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
	 * @return void
	 */
	public function testGetOpenProjectOauthURL() {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url'],
				['integration_openproject', 'client_id'],
				['integration_openproject', 'oauth_instance_url'],
			)->willReturnOnConsecutiveCalls('clientID', 'SECRET', 'https://openproject', 'clientID', 'https://openproject');

		$url = $this->createMock(IURLGenerator::class);
		$url->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('integration_openproject.config.oauthRedirect')
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
	public function getOpenProjectOauthURLDataProvider() {
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
	 * @dataProvider getOpenProjectOauthURLDataProvider
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
			123, 5503, 'logo.png', 'http://nextcloud.org', 'user'
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
			123, 5503, 'logo.png', 'http://nextcloud.org', 'user'
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
			123, 5503, 'logo.png', 'http://nextcloud.org', 'user'
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

		$service = new OpenProjectAPIService(
			'integration_openproject',
			$this->createMock(\OCP\IUserManager::class),
			$this->createMock(\OCP\IAvatarManager::class),
			$this->createMock(\Psr\Log\LoggerInterface::class),
			$this->createMock(\OCP\IL10N::class),
			$configMock,
			$this->createMock(\OCP\Notification\IManager::class),
			$clientService,
			$this->createMock(\OCP\Files\IRootFolder::class),
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
			'http://nextcloud.org',
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
		$service = $this->getOpenProjectAPIService($storageMock);

		$this->expectException(OpenprojectErrorException::class);
		$service->linkWorkPackageToFile(
			123,
			5503,
			'logo.png',
			'',
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
		$service = $this->getOpenProjectAPIService($storageMock);

		$this->expectException(OpenprojectErrorException::class);
		$service->linkWorkPackageToFile(
			123,
			5503,
			'logo.png',
			'http://not-existing',
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
			'http://nextcloud.org',
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
			'http://nextcloud.org',
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
}
