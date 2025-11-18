<?php

/**
 * SPDX-FileCopyrightText: 2021-2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use OC\Authentication\Events\AppPasswordCreatedEvent;
use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\IToken;
use OC\Avatar\GuestAvatar;
use OC\Http\Client\Client;
use OC\Http\Client\Response;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\OIDCIdentityProvider\Db\Client as OIDCClient;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Exception\OpenprojectErrorException;
use OCA\OpenProject\Exception\OpenprojectResponseException;
use OCA\OpenProject\OIDCClientMapper;
use OCA\OpenProject\TokenEventFactory;
use OCA\TermsOfService\Db\Mapper\SignatoryMapper;
use OCA\UserOIDC\Event\ExchangedTokenRequestedEvent;
use OCA\UserOIDC\Event\InternalTokenRequestedEvent;
use OCA\UserOIDC\Exception\TokenExchangeFailedException;
use OCA\UserOIDC\Model\Token;
use OCA\UserOIDC\User\Backend as OIDCBackend;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\Encryption\IManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Group\ISubAdmin;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAvatarManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Log\ILogFactory;
use OCP\Security\ISecureRandom;
use phpmock\phpunit\PHPMock;
use PhpPact\Consumer\InteractionBuilder;
use PhpPact\Consumer\Model\Body\Binary;
use PhpPact\Consumer\Model\Body\Text;
use PhpPact\Consumer\Model\ConsumerRequest;
use PhpPact\Consumer\Model\ProviderResponse;
use PhpPact\Standalone\MockService\MockServerEnvConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OpenProjectAPIServiceTest extends TestCase {
	use PHPMock;

	/**
	 * @var InteractionBuilder
	 */
	private $builder;

	/**
	 * @var OpenProjectAPIService
	 */
	private $service;

	private MockObject $classExistsMock;

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
	 * @var string
	 */
	private $getProjectsPath = '/api/v3/work_packages/available_projects';

	/**
	 * @var IConfig|MockObject
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
	 * @var array<mixed>
	 */
	private $validFileLinkRequestBodyForMultipleFiles = [
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
				],
				[
					'originData' => [
						'id' => 5504,
						'name' => 'pogo.png',
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
				],
				[
					'originData' => [
						'id' => 5505,
						'name' => 'dogo.png',
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
	 * @var array <mixed>
	 */
	private $singleFileInformation = [
		"workpackageId" => 123,
		"fileinfo" => [
			[
				"id" => 5503,
				"name" => "logo.png"
			]
		]
	];


	/**
	 * @var array <mixed>
	 */
	private $multipleFileInformation = [
		"workpackageId" => 123,
		"fileinfo" => [
			[
				"id" => 5503,
				"name" => "logo.png"
			],
			[
				"id" => 5504,
				"name" => "pogo.png"
			],
			[
				"id" => 5505,
				"name" => "dogo.png"
			]
		]
	];

	/**
	 * @var array<mixed>
	 */
	private $validOpenProjectGetProjectsResponse = [
		"_type" => "Collection",
		"total" => 6,
		"count" => 6,
		"pageSize" => 20,
		"offset" => 1,
		"_embedded" => [
			"elements" => [
				[
					"_type" => "Project",
					"id" => 6,
					"identifier" => "dev-custom-fields",
					"name" => "[dev] Custom fields",
					"_links" => [
						"self" => [
							"href" => "/api/v3/projects/6",
							"title" => "[dev] Custom fields"
						],
						"parent" => [
							"href" => "/api/v3/projects/5",
							"title" => "[dev] Large"
						],
						"storages" => [
							[
								"href" => "/api/v3/storages/37",
								"title" => "nc-26"
							]
						],
					]
				],
				[
					"_type" => "Project",
					"id" => 5,
					"identifier" => "dev-large",
					"name" => "[dev] Large",
					"_links" => [
						"self" => [
							"href" => "/api/v3/projects/5",
							"title" => "[dev] Large"
						],
						"parent" => [
							"href" => null
						],
						"storages" => [
							[
								"href" => "/api/v3/storages/37",
								"title" => "nc-26"
							]
						]
					]
				]
			]
		]
	];

	/**
	 * @var array<mixed>
	 */
	private $expectedValidOpenProjectResponse = [
		6 => [
			"_type" => "Project",
			"id" => 6,
			"identifier" => "dev-custom-fields",
			"name" => "[dev] Custom fields",
			"_links" => [
				"self" => [
					"href" => "/api/v3/projects/6",
					"title" => "[dev] Custom fields"
				],
				"parent" => [
					"href" => "/api/v3/projects/5",
					"title" => "[dev] Large"
				],
				"storages" => [
					[
						"href" => "/api/v3/storages/37",
						"title" => "nc-26"
					]
				],
			]
		],
		5 => [
			"_type" => "Project",
			"id" => 5,
			"identifier" => "dev-large",
			"name" => "[dev] Large",
			"_links" => [
				"self" => [
					"href" => "/api/v3/projects/5",
					"title" => "[dev] Large"
				],
				"parent" => [
					"href" => null
				],
				"storages" => [
					[
						"href" => "/api/v3/storages/37",
						"title" => "nc-26"
					]
				]
			]
		]
	];

	/**
	 * @var array<mixed>
	 */
	private $validWorkPackageFormValidationBody = [
		"_links" => [
			"type" => [
				"href" => "/api/v3/types/2",
				"title" => "Milestone"
			],
			"status" => [
				"href" => "/api/v3/statuses/1",
				"title" => "New"
			],
			"project" => [
				"href" => "/api/v3/projects/6",
				"title" => "Demo project"
			],
			"assignee" => [
				"href" => "/api/v3/users/4",
				"title" => "OpenProject Admin"
			],
		],
		"subject" => "This is a new work package",
		"description" => [
			"format" => "markdown",
			"raw" => "this is a default description for milestone type",
			"html" => null
		]
	];

	/**
	 * @var array<mixed>
	 */
	private $validWorkPackageFormValidationResponse = [
		"_type" => "Form",
		"_embedded" =>
			[
				"payload" => [
					"subject" => "This is a new workpackage",
					"description" => [
						"format" => "markdown",
						"raw" => "this is a default description for task type",
						"html" => "<p class=\"op-uc-p\">this is a default description for task type</p>"
					],
					"_links" => [
						"type" => [
							"href" => "/api/v3/types/2",
							"title" => "Milestone"
						],
						"status" => [
							"href" => "/api/v3/statuses/1",
							"title" => "New"
						],
						"project" => [
							"href" => "/api/v3/projects/6",
							"title" => "Demo project"
						],
						"assignee" => [
							"href" => null
						],
					]
				],
				"schema" => [
					"_type" => "Schema",
					"type" => [
						"type" => "Type",
						"allowedValues" => []
					]
				],
				"validationErrors" => []
			]
	];

	/**
	 * @var array<mixed>
	 */
	private $validGetProjectAssigneesResponse = [
		"_type" => "Collection",
		"total" => 2,
		"count" => 2,
		"_embedded" => [
			"elements" =>
				[
					"_type" => "User",
					"id" => 7,
					"name" => "Project admin DEV user",
					"_links" => [
						"self" => [
							"href" => "/api/v3/users/7",
							"title" => "Project admin DEV user"
						]
					]
				],
			[
				"_type" => "User",
				"id" => 6,
				"name" => "Member DEV user",
				"_links" => [
					"self" => [
						"href" => "/api/v3/users/6",
						"title" => "Member DEV user"
					]
				]
			]
		]
	];

	/**
	 * @var array<mixed>
	 */
	private $validCreateWorkpackageBody = [
		"_links" => [
			"type" => [
				"href" => "/api/v3/types/2",
				"title" => "Milestone"
			],
			"status" => [
				"href" => "/api/v3/statuses/1",
				"title" => "New"
			],
			"project" => [
				"href" => "/api/v3/projects/6",
				"title" => "Demo project"
			],
			"assignee" => [
				"href" => "/api/v3/users/4",
				"title" => "OpenProject Admin"
			],
		],
		"subject" => "This is a new work package",
		"description" => [
			"format" => "markdown",
			"raw" => "this is a default description for milestone type",
			"html" => null
		],
	];

	/**
	 * @var array<mixed>
	 */
	private $createWorkpackageResponse = [
		"_embedded" => [
			"type" => [
				"_type" => "Type",
				"id" => 2,
				"name" => "Milestone",
				"color" => "#FF922B",
			],
			"status" => [
				"_type" => "Status",
				"id" => 1,
				"name" => "New",
				"color" => "#DEE2E6",
			]
		],
		"_type" => "WorkPackage",
		"id" => 12,
		"subject" => "This is a new work package",
		"description" => [
			"format" => "markdown",
			"raw" => "this is a default description for milestone type",
			"html" => "<p class=\"op-uc-p\">this is a default description for milestone type</p>"
		],
		"_links" => [
			"self" => [
				"href" => "/api/v3/work_packages/12",
				"title" => "This is a new work package"
			]
		]
	];

	/**
	 * @var array<mixed>
	 */
	private $wpInformationResponse = [
		"_type" => "WorkPackage",
		"id" => 123,
		"identifier" => "dev-custom-fields",
		"subject" => "New login screen",
		"_links" => [
			"type" => [
				"title" => "User story"
			],
			"status" => [
				"title" => "In specification"
			],
			"project" => [
				"title" => "Scrum project"
			],
			"assignee" => [
				"href" => "/api/v3/users/3",
				"title" => "OpenProject Admin"
			]
		]
	];
	private MockServerEnvConfig $pactMockServerConfig;
	private array $appValues = [];

	/**
	 * [key => value] pairs of custom app values
	 * @param array $withValues
	 *
	 * @return array
	 */
	public function getAppValues(array $withValues = []): array {
		$defaultValues = [
			'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
			'openproject_client_id' => $this->clientId,
			'openproject_client_secret' => $this->clientSecret,
			'openproject_instance_url' => $this->pactMockServerConfig->getBaseUri()->__toString(),
			'oidc_provider' => '',
			'targeted_audience_client_id' => '',
			'sso_provider_type' => 'external',
			'token_exchange' => false,
		];
		$appValues = [];
		foreach ($withValues as $key => $value) {
			$defaultValues[$key] = $value;
		}

		foreach ($defaultValues as $key => $value) {
			$appValues[] = [Application::APP_ID, $key, '', $value];
		}
		return $appValues;
	}
	/**
	 * @return void
	 * @before
	 */
	public function setupMockServer(): void {
		// NOTE: mocking 'class_exists' must be done before anything else
		$this->classExistsMock = $this->getFunctionMock(__NAMESPACE__, "class_exists");

		$this->pactMockServerConfig = new MockServerEnvConfig();

		// find an unused port and use it for the mock server
		// using the same port all the time is not stable
		// sometimes the server fails saying its already used
		$address = $this->pactMockServerConfig->getHost();
		$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_bind($sock, $address);
		socket_getsockname($sock, $address, $port);
		socket_close($sock);

		$this->pactMockServerConfig->setPort($port);
		$this->builder = new InteractionBuilder($this->pactMockServerConfig);

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
		$fileMock = $this->createMock($nodeClassName);
		$fileMock->method('isReadable')->willReturn(true);
		$fileMock->method('getName')->willReturn('logo.png');
		$fileMock->method('getMimeType')->willReturn('image/png');
		$fileMock->method('getCreationTime')->willReturn(1639906930);
		$fileMock->method('getMTime')->willReturn(1640008813);
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
	 * generates a list mocks that can be given as arguments to the constructor of OpenProjectAPIService
	 * by default only empty mocks are generated, but specific mocks can be passed in using the
	 * $constructParams parameter.
	 *
	 * Format has to be [<string> => <object>] with the first being the constructor parameter name and the second one the mock.
	 * Example: ['avatarManager' => $createMockObject]
	 * @param array<string, object> $constructParams specific mocks for the constructor of OpenProjectAPIService
	 *
	 * @return array
	 */
	private function getOpenProjectAPIServiceConstructArgs(array $constructParams = []): array {
		$constructArgs = [
			// order should be the same as in the constructor
			'avatarManager' => $this->createMock(IAvatarManager::class),
			'loggerInterface' => $this->createMock(LoggerInterface::class),
			'l10n' => $this->createMock(IL10N::class),
			'config' => $this->createMock(IConfig::class),
			'clientService' => $this->createMock(IClientService::class),
			'rootFolder' => $this->createMock(IRootFolder::class),
			'urlGenerator' => $this->createMock(IURLGenerator::class),
			'cacheFactory' => $this->createMock(ICacheFactory::class),
			'userManager' => $this->createMock(IUserManager::class),
			'groupManager' => $this->createMock(IGroupManager::class),
			'appManager' => $this->createMock(IAppManager::class),
			'provider' => $this->createMock(IProvider::class),
			'secureRandom' => $this->createMock(ISecureRandom::class),
			'eventDispatcher' => $this->createMock(IEventDispatcher::class),
			'subAdmin' => $this->createMock(ISubAdmin::class),
			'dbConnection' => $this->createMock(IDBConnection::class),
			'logFactory' => $this->createMock(ILogFactory::class),
			'manager' => $this->createMock(IManager::class),
			'tokenEventFactory' => $this->createMock(TokenEventFactory::class),
			'userSession' => $this->createMock(IUserSession::class),
			'oidcClientMapper' => $this->createMock(OIDCClientMapper::class),
		];

		// replace default mocks with manually passed in mocks
		foreach ($constructParams as $key => $value) {
			if (!array_key_exists($key, $constructArgs)) {
				throw new \InvalidArgumentException("Invalid construct parameter: $key");
			}

			$constructArgs[$key] = $value;
		}

		return ['integration_openproject', ...array_values($constructArgs)];
	}

	/**
	 * @param IRootFolder|null $storageMock
	 * @param string $oAuthToken
	 * @param string $baseUrl
	 * @param string $userId
	 * @return OpenProjectAPIService
	 */
	private function getOpenProjectAPIService(
		$authMethod = null,
		$storageMock = null,
		$oAuth2OrOidcToken = '1234567890',
		$baseUrl = 'https://nc.my-server.org',
		$userId = 'testUser',
	) {
		$certificateManager = $this->getMockBuilder('\OCP\ICertificateManager')->getMock();
		$certificateManager->method('getAbsoluteBundlePath')->willReturn('/');
		$clientConfigMock = $this->getMockBuilder(IConfig::class)->getMock();
		$clientConfigMock
			->method('getSystemValueBool')
			->withConsecutive(
				['allow_local_remote_servers', false],
				['installed', false],
				['allow_local_remote_servers', false],
				['allow_local_remote_servers', false],
				['installed', false],
				['allow_local_remote_servers', false],
				['allow_local_remote_servers', false],
				['installed', false],
				['allow_local_remote_servers', false]
			)
			->willReturnOnConsecutiveCalls(
				true,
				true,
				true,
				true,
				true,
				true,
				true,
				true,
				true
			);

		$clientService = $this->getMockBuilder(IClientService::class)->getMock();
		$clientService->method('newClient')->willReturn($this->createMock(Client::class));

		$guestAvatarMock = $this->getMockBuilder(GuestAvatar::class)->disableOriginalConstructor()->getMock();

		$avatarFileMock = $this->createMock(ISimpleFile::class);
		$avatarFileMock->method('getContent')
			->willReturn(\file_get_contents(__DIR__ . "/../fixtures/openproject-icon.jpg"));

		$guestAvatarMock->method('getFile')
			->willReturn($avatarFileMock);

		$avatarManagerMock = $this->getMockBuilder(IAvatarManager::class)
			->getMock();
		$avatarManagerMock
			->method('getGuestAvatar')
			->willReturn($guestAvatarMock);
		if ($storageMock === null) {
			$storageMock = $this->createMock(IRootFolder::class);
		}
		$this->defaultConfigMock = $this->getMockBuilder(IConfig::class)->getMock();
		$appManagerMock = $this->getMockBuilder(IAppManager::class)->disableOriginalConstructor()->getMock();
		$exchangeTokenMock = $this->getMockBuilder(TokenEventFactory::class)->disableOriginalConstructor()->getMock();
		$exchangedTokenRequestedEventMock = $this->getMockBuilder(ExchangedTokenRequestedEvent::class)->disableOriginalConstructor()->getMock();

		$this->defaultConfigMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'authorization_method' => $authMethod ?? OpenProjectAPIService::AUTH_METHOD_OAUTH,
			]));

		$tokenExpiryTime = $oAuth2OrOidcToken === 'expired' ? 0 : time() + 7200;
		$this->defaultConfigMock
			->method('getUserValue')
			->withConsecutive(
				[$userId, 'integration_openproject', 'token'],
				[$userId, 'integration_openproject', 'token_expires_at'],
				[$userId, 'integration_openproject', 'refresh_token'],
				[$userId, 'integration_openproject', 'token'],
			)
			->willReturnOnConsecutiveCalls(
				$oAuth2OrOidcToken,
				$tokenExpiryTime,
				'oAuthRefreshToken',
				'new-Token'
			);
		if ($authMethod === OpenProjectAPIService::AUTH_METHOD_OIDC) {
			$tokenMock = $this->getMockBuilder(Token::class)->disableOriginalConstructor()->getMock();
			$exchangeTokenMock->method('getEvent')->willReturn($exchangedTokenRequestedEventMock);
			$exchangedTokenRequestedEventMock->method('getToken')->willReturn($tokenMock);
			$tokenMock->method('getAccessToken')->willReturn($oAuth2OrOidcToken);
			$appManagerMock->method('isInstalled')->willReturn(true);
		}

		$urlGeneratorMock = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$urlGeneratorMock
			->method('getBaseUrl')
			->willReturn($baseUrl);
		$this->defaultConfigMock
			->method('getSystemValueString')
			->with($this->equalTo('overwrite.cli.url'))
			->willReturn($baseUrl);

		$constructArgs = $this->getOpenProjectAPIServiceConstructArgs([
			'avatarManager' => $avatarManagerMock,
			'config' => $this->defaultConfigMock,
			'clientService' => $clientService,
			'rootFolder' => $storageMock,
			'urlGenerator' => $urlGeneratorMock,
			'appManager' => $appManagerMock,
			'tokenEventFactory' => $exchangeTokenMock,
		]);

		return new OpenProjectAPIService(...$constructArgs);
	}

	/**
	 *  Since our app currently has two authorization methods, the test employing this mock service has no effect on the authorization method.
	 *
	 *
	 * @param array<string> $mockMethods
	 * @param array<string, object> $constructParams
	 *
	 * @return OpenProjectAPIService|MockObject
	 */
	private function getOpenProjectAPIServiceMock(
		array $mockMethods = ['request'],
		array $constructParams = [],
	): OpenProjectAPIService|MockObject {
		$mockMethods[] = 'getBaseUrl';
		$constructArgs = $this->getOpenProjectAPIServiceConstructArgs($constructParams);

		$mock = $this->getMockBuilder(OpenProjectAPIService::class)
			->setConstructorArgs($constructArgs)
			->onlyMethods($mockMethods)
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
		$service = $this->getOpenProjectAPIServiceMock();
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
					'sortBy' => '[["updatedAt","desc"]]',
				]
			)
			->willReturn($response);
		$result = $service->searchWorkPackage('user', 'search query');
		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @param array<mixed> $response
	 * @param array<mixed> $expectedResult
	 * @return void
	 * @dataProvider searchWorkPackageDataProvider
	 */
	public function testSearchWorkPackageNotLinkedToAStorage(array $response, array $expectedResult) {
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->with(
				'user', 'work_packages',
				[
					'filters' => '[' .
						'{"typeahead":' .
						'{"operator":"**","values":["search query"]}'.
						'}]',
					'sortBy' => '[["updatedAt","desc"]]',
				]
			)
			->willReturn($response);
		$result = $service->searchWorkPackage('user', 'search query', null, false);
		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @return void
	 */
	public function testSearchWorkPackageByFileIdOnlyFileId() {
		$service = $this->getOpenProjectAPIServiceMock();
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
						'sortBy' => '[["updatedAt","desc"]]',
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
		$service = $this->getOpenProjectAPIServiceMock();
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
					'sortBy' => '[["updatedAt","desc"]]',
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
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn(['error' => 'some issue', 'statusCode' => 404 ]);
		$result = $service->searchWorkPackage('user', 'search query', 123);
		$this->assertSame(['error' => 'some issue', 'statusCode' => 404 ], $result);
	}

	/**
	 * @return array<mixed>
	 */
	public function getAuthorizationMethodDataProvider() {
		return [
			[OpenProjectAPIService::AUTH_METHOD_OAUTH],
			[OpenProjectAPIService::AUTH_METHOD_OIDC]
		];
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetNotificationsRequest(string $authorizationMethod) {
		$service = $this->getOpenProjectAPIService($authorizationMethod);
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath($this->notificationsPath)
			->setQuery(
				[
					"pageSize" => "-1",
					"filters" => json_encode([[
						'readIAN' =>
							['operator' => '=', 'values' => ['f']]
					]], JSON_THROW_ON_ERROR)
				]
			)
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

		$result = $service->getNotifications(
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
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn($response);
		$result = $service->getNotifications('');
		$this->assertSame(["error" => "Malformed response"], $result);
	}

	/**
	 * @return void
	 */
	public function testGetNotificationsErrorResponse() {
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn(['error' => 'my error']);
		$result = $service->getNotifications('');
		$this->assertSame(["error" => "my error"], $result);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodForOauth2DataProvider
	 * @throws \JsonException
	 */
	public function testRequestUsingOAuthToken(string $authorizationMethod) {
		$service = $this->getOpenProjectAPIService($authorizationMethod);
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

		$result = $service->request(
			'testUser',
			'work_packages'
		);
		$this->assertSame(["_embedded" => ["elements" => []]], $result);
	}

	/**
	 * @return array<mixed>
	 */
	public function getAuthorizationMethodForOauth2DataProvider() {
		return [
			[OpenProjectAPIService::AUTH_METHOD_OAUTH]
		];
	}


	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodForOauth2DataProvider
	 * @throws \JsonException
	 */
	public function testRequestRefreshOAuthToken(string $authorizationMethod) {
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
			->willRespondWith($providerResponseInvalidOAuthToken, false);

		$refreshTokenRequest = new ConsumerRequest();
		$refreshTokenRequest
			->setMethod('POST')
			->setPath('/oauth/token')
			->addHeader('Content-Type', 'application/x-www-form-urlencoded')
			->addHeader('User-Agent', 'Nextcloud OpenProject integration')
			->setBody(new Text(
				'client_id=' . $this->clientId .
				'&client_secret=' . $this->clientSecret .
				'&grant_type=refresh_token&refresh_token=oAuthRefreshToken', 'application/x-www-form-urlencoded')
			);

		$refreshTokenResponse = new ProviderResponse();
		$tokenCreatedAt = time();
		$refreshTokenResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody([
				"access_token" => "new-Token",
				"refresh_token" => "newRefreshToken",
				"created_at" => $tokenCreatedAt,
				'expires_in' => 7200,
			]);

		$this->builder->newInteraction();
		$this->builder
			->uponReceiving('a POST request to renew token')
			->with($refreshTokenRequest)
			->willRespondWith($refreshTokenResponse, false);

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

		$this->builder->newInteraction();
		$this->builder
			->uponReceiving('an OAuth GET request to /work_packages with new Token')
			->with($consumerRequestNewOAuthToken)
			->willRespondWith($providerResponseNewOAuthToken);

		$service = $this->getOpenProjectAPIService($authorizationMethod, null, 'expired');
		$this->defaultConfigMock
			->expects($this->exactly(3))
			->method('setUserValue')
			->withConsecutive(
				['testUser', 'integration_openproject', 'token', 'new-Token'],
				['testUser', 'integration_openproject', 'token_expires_at', $tokenCreatedAt + 7200],
				['testUser', 'integration_openproject', 'refresh_token', 'newRefreshToken'],
			);

		$result = $service->request(
			'testUser',
			'work_packages'
		);
		$this->assertSame(["_embedded" => ["elements" => [['id' => 1], ['id' => 2]]]], $result);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testRequestToNotExistingPath(string $authorizationMethod) {
		$service = $this->getOpenProjectAPIService($authorizationMethod);
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

		$result = $service->request(
			'testUser',
			'not_existing'
		);
		$this->assertSame(
			'Client error: `GET http://localhost:' .
			$this->pactMockServerConfig->getPort() . '/api/v3/not_existing` ' .
			'resulted in a `404 Not Found` response',
			$result['message']);
		$this->assertSame(404, $result['statusCode']);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetOpenProjectAvatar(string $authorizationMethod) {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath('/api/v3/users/openProjectUserWithAvatar/avatar')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();

		$providerResponse
			->setStatus(Http::STATUS_OK)
			->setHeaders(['Content-Type' => 'image/jpeg'])
			->setBody(
				new Binary(
					__DIR__ . "/../fixtures/openproject-icon.jpg",
					'image/jpeg'
				)
			);

		$this->builder
			->uponReceiving('a request to get the avatar of a user that has an avatar')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'openproject_instance_url' => 'http://openprojectUrl.com',
			]));

		$mockResponse = $this->createMock(Response::class);
		$mockResponse->method('getHeader')->willReturn('image/jpeg');
		$mockResponse->method('getBody')->willReturn(
			file_get_contents(__DIR__ . "/../fixtures/openproject-icon.jpg")
		);

		$serviceMock = $this->getOpenProjectAPIServiceMock(['rawRequest'], [ 'config' => $configMock ]);
		$serviceMock->expects($this->once())
			->method('rawRequest')
			->willReturn($mockResponse);

		$result = $serviceMock->getOpenProjectAvatar(
			'openProjectUserWithAvatar',
			'Me',
			'NCuser'
		);
		$this->assertArrayHasKey('avatar', $result);
		$this->assertArrayHasKey('type', $result);
		$this->assertSame(
			\file_get_contents(__DIR__ . "/../fixtures/openproject-icon.jpg"),
			$result['avatar']
		);
		$this->assertSame('image/jpeg', $result['type']);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetOpenProjectAvatarWithNoContentType(string $authorizationMethod) {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath('/api/v3/users/openProjectUserWithAvatar/avatar')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();

		$providerResponse
			->setStatus(Http::STATUS_OK)
			->setBody(null);

		$this->builder
			->uponReceiving('a request to get the avatar of a user that has an avatar')
			->with($consumerRequest)
			->willRespondWith($providerResponse);
		$service = $this->getOpenProjectAPIService($authorizationMethod, null, '1234567890', 'https://nc.my-server.org', 'NCuser');
		$result = $service->getOpenProjectAvatar(
			'openProjectUserWithAvatar',
			'Me',
			'NCuser'
		);
		$this->assertArrayHasKey('avatar', $result);
		// make sure its an image
		$this->assertNotFalse(imagecreatefromstring($result['avatar']));
	}


	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetOpenProjectAvatarWithMisMatchContentType(string $authorizationMethod) {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath('/api/v3/users/openProjectUserWithAvatar/avatar')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();

		$providerResponse
			->setStatus(Http::STATUS_OK)
			->setHeaders(['Content-Type' => 'image/png'])
			->setBody(
				new Binary(
					__DIR__ . "/../fixtures/openproject-icon.jpg",
					'image/jpeg'
				)
			);

		$this->builder
			->uponReceiving('a request to get the avatar of a user that has an avatar')
			->with($consumerRequest)
			->willRespondWith($providerResponse);
		$service = $this->getOpenProjectAPIService($authorizationMethod, null, '1234567890', 'https://nc.my-server.org', 'NCuser');
		$result = $service->getOpenProjectAvatar(
			'openProjectUserWithAvatar',
			'Me',
			'NCuser'
		);
		$this->assertArrayHasKey('avatar', $result);
		// make sure its an image
		$this->assertNotFalse(imagecreatefromstring($result['avatar']));
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetOpenProjectAvatarWithInvalidImageData(string $authorizationMethod) {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath('/api/v3/users/openProjectUserWithAvatar/avatar')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();

		$providerResponse
			->setStatus(Http::STATUS_OK)
			->setHeaders(['Content-Type' => 'text/plain'])
			->setBody("Something in text form");

		$this->builder
			->uponReceiving('a request to get the avatar of a user that has an avatar')
			->with($consumerRequest)
			->willRespondWith($providerResponse);
		$service = $this->getOpenProjectAPIService($authorizationMethod, null, '1234567890', 'https://nc.my-server.org', 'NCuser');
		$result = $service->getOpenProjectAvatar(
			'openProjectUserWithAvatar',
			'Me',
			'NCuser'
		);
		$this->assertArrayHasKey('avatar', $result);
		// make sure its an image
		$this->assertNotFalse(imagecreatefromstring($result['avatar']));
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetOpenProjectAvatarNoAvatar(string $authorizationMethod) {
		$service = $this->getOpenProjectAPIService($authorizationMethod);
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath('/api/v3/users/openProjectUser/avatar')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_NOT_FOUND);

		$this->builder
			->uponReceiving('a request to get the avatar of a user that does not have one')
			->with($consumerRequest)
			->willRespondWith($providerResponse);
		$result = $service->getOpenProjectAvatar(
			'openProjectUser',
			'Me',
			'testUser'
		);
		$this->assertArrayHasKey('avatar', $result);
		// make sure its an image
		$this->assertNotFalse(imagecreatefromstring($result['avatar']));
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetOpenProjectWorkPackageStatusRequest(string $authorizationMethod): void {
		$service = $this->getOpenProjectAPIService($authorizationMethod);
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

		$result = $service->getOpenProjectWorkPackageStatus(
			'testUser',
			'7'
		);
		$this->assertSame(sort($this->validStatusResponseBody), sort($result));
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
		$service = $this->getOpenProjectAPIServiceMock(['request'], ['cacheFactory' => $cacheFactoryMock]);
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
		$service = $this->getOpenProjectAPIServiceMock(['request'], ['cacheFactory' => $cacheFactoryMock]);
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
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn(['error' => 'Malformed response']);
		$result = $service->getOpenProjectWorkPackageStatus('', '');
		$this->assertSame(['error' => 'Malformed response'], $result);
	}



	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetOpenProjectWorkPackageTypeRequest(string $authorizationMethod): void {
		$service = $this->getOpenProjectAPIService($authorizationMethod);
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

		$result = $service->getOpenProjectWorkPackageType(
			'testUser',
			'3'
		);

		$this->assertSame(sort($this->validTypeResponseBody), sort($result));
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
		$service = $this->getOpenProjectAPIServiceMock(['request'], ['cacheFactory' => $cacheFactoryMock]);
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
		$service = $this->getOpenProjectAPIServiceMock(['request'], ['cacheFactory' => $cacheFactoryMock]);
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
		$service = $this->getOpenProjectAPIServiceMock();
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
			->willReturnMap($this->getAppValues([
				'openproject_client_id' => 'clientID',
				'openproject_client_secret' => 'SECRET',
				'openproject_instance_url' => $oauthInstanceUrl,
				'nc_oauth_client_id' => 'nc-client',
				'fresh_project_folder_setup' => false,
			]));
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
			->willReturnMap($this->getAppValues([
				'openproject_client_id' => $clientId,
				'openproject_client_secret' => $clientSecret,
				'openproject_instance_url' => $oauthInstanceUrl
			]));
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('OpenProject admin config is not valid!');
		$this->service::getOpenProjectOauthURL($configMock, $url);
	}

	/**
	 * @return array<mixed>
	 */
	public function connectExpectionDataProvider(): array {
		$requestMock = $this->getMockBuilder('\Psr\Http\Message\RequestInterface')->getMock();
		$responseMock500 = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$responseMock500->method('getStatusCode')->willReturn(500);

		return [
			[
				new ConnectException('a connection problem', $requestMock),
				404,
				'a connection problem'
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
	 * @return array<mixed>
	 */
	public function clientExpectionDataProvider(): array {
		$requestMock = $this->getMockBuilder('\Psr\Http\Message\RequestInterface')->getMock();
		$responseMock402 = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$responseMock402->method('getStatusCode')->willReturn(402);
		$responseMock403 = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$responseMock403->method('getStatusCode')->willReturn(403);
		$responseMock501 = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')->getMock();
		$responseMock501->method('getStatusCode')->willReturn(501);

		return [
			[
				new ClientException('some client problem', $requestMock, $responseMock403),
				403,
				'some client problem'
			],
			[
				new ServerException('some server issue', $requestMock, $responseMock501),
				501,
				'some server issue'
			],
			[
				new ClientException('some client problem', $requestMock, $responseMock402),
				402,
				'some client problem'
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
		// here authorization method is of no use, just putting 'oauth2' since its the default
		$service = $this->getOpenProjectAPIService(OpenProjectAPIService::AUTH_METHOD_OAUTH, $storageMock);
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
		// here authorization method is of no use, just putting 'oauth2' since its the default
		$service = $this->getOpenProjectAPIService(OpenProjectAPIService::AUTH_METHOD_OAUTH, $storageMock);
		$result = $service->getNode('me', 1234);
		$this->assertTrue($result instanceof \OCP\Files\Node);
	}

	/**
	 * @return void
	 * @throws \JsonException
	 */
	public function testLinkWorkPackageToFileRequest(): void {
		$service = $this->getOpenProjectAPIServiceMock(['request', 'getNode']);

		$service->method('getNode')
			->willReturn($this->getNodeMock());
		$service->method('request')
			->willReturn(['_type' => 'Collection', '_embedded' => ['elements' => [['id' => 2456]]]]);

		$service->expects($this->once())
			->method('request')
			->with(
				'user', 'work_packages/123/file_links',
				['body' => json_encode($this->validFileLinkRequestBody, JSON_THROW_ON_ERROR)]
			);
		$values = $this->singleFileInformation;
		$result = $service->linkWorkPackageToFile(
			$values, 'user'
		);
		$this->assertSame([2456], $result);
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileFileNotReadable(): void {
		$service = $this->getOpenProjectAPIServiceMock(['request', 'getNode']);

		$fileMock = $this->getMockBuilder('\OCP\Files\File')->getMock();
		$fileMock->method('isReadable')->willReturn(false);

		$service->method('getNode')
			->willReturn($fileMock);

		$service->expects($this->never())
			->method('request');

		$this->expectException(NotPermittedException::class);

		$values = $this->singleFileInformation;
		$service->linkWorkPackageToFile(
			$values, 'user'
		);
	}

	/**
	 * @return array<array<string>>
	 */
	public function getInvalidKeyInformation(): array {
		return [
			['workpackageiD', 'fileinfo'],
			['workpackageId', 'fileINfo'],
			['workpackageiD', 'fileINfo']
		];
	}

	/**
	 * @param string $keyWorkPackageId
	 * @param string $keyFileInfo
	 * @dataProvider getInvalidKeyInformation
	 * @return void
	 */
	public function testLinkWorkPackageToFileFileInvalidKey(string $keyWorkPackageId, string $keyFileInfo): void {
		$service = $this->getOpenProjectAPIServiceMock(['request', 'getNode']);
		$fileMock = $this->getMockBuilder('\OCP\Files\File')->getMock();
		$service->method('getNode')
			->willReturn($fileMock);
		$service->expects($this->never())
			->method('request');
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/^invalid key$/');
		$values = [
			$keyWorkPackageId => 123,
			$keyFileInfo => [
				[
					"id" => 5503,
					"name" => "logo.png"
				]
			]
		];
		$service->linkWorkPackageToFile(
			$values, 'user'
		);
	}


	/**
	 * @return array<mixed>
	 */
	public function getInvalidKeyWithCorrectKeyInformation(): array {
		return [
			["", ""],
			[null, ""],
			[false, ""],
			[true, ""],
			["invalid-data", ""],
			["", null],
			["", false],
			["", true],
			[1, null],
			[1, false],
			[1, true],
			[null, [["id" => 5503, "name" => "logo.png"]]],
			[false, [["id" => 5503, "name" => "logo.png"]]],
			[true, [["id" => 5503, "name" => "logo.png"]]],
			["", "invalid-data"],
			["invalid-data", "invalid-data"],
			[1, "invalid-data"],
			[1, []],
			["invalid-data", [["id" => 5503, "name" => "logo.png"]]],
		];
	}

	/**
	 * @param array<mixed> $workpackageIdValue
	 * @param array<mixed> $fileInfoValue
	 * @dataProvider getInvalidKeyWithCorrectKeyInformation
	 * @return void
	 */
	public function testLinkWorkPackageToFileFileInvalidDataWithValidKey($workpackageIdValue, $fileInfoValue): void {
		$service = $this->getOpenProjectAPIServiceMock(['request', 'getNode']);
		$fileMock = $this->getMockBuilder('\OCP\Files\File')->getMock();
		$service->method('getNode')
			->willReturn($fileMock);
		$service->expects($this->never())
			->method('request');
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/^invalid data$/');
		$values = [
			"workpackageId" => $workpackageIdValue,
			"fileinfo" => $fileInfoValue
		];
		$service->linkWorkPackageToFile(
			$values, 'user'
		);
	}


	/**
	 * @return array<array<string>>
	 */
	public function getInvalidKeyWithValidFileInfokeys(): array {
		return [
			["i", "name"],
			["id", "nme"],
			["i", "nme"]
		];
	}

	/**
	 * @param string $invalidFileIdKey
	 * @param string $invalidFileNameKey
	 * @dataProvider getInvalidKeyWithValidFileInfokeys
	 * @return void
	 */
	public function testLinkWorkPackageToFileFileInvalidDataWithValidFileInfoKeys(string $invalidFileIdKey, string $invalidFileNameKey): void {
		$service = $this->getOpenProjectAPIServiceMock(['request', 'getNode']);
		$fileMock = $this->getMockBuilder('\OCP\Files\File')->getMock();
		$service->method('getNode')
			->willReturn($fileMock);
		$service->expects($this->never())
			->method('request');
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/^invalid data$/');
		$values = [
			"workpackageId" => 1,
			"fileinfo" => [
				[
					$invalidFileIdKey => 5503,
					$invalidFileNameKey => "logo.png"
				]
			]
		];
		$service->linkWorkPackageToFile(
			$values, 'user'
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function getInvalidKeyWithValidFileInfoValues(): array {
		return [
			["", ""],
			[null, ""],
			[false, ""],
			[true, ""],
			["invalid-data", ""],
			["", null],
			["", false],
			["", true],
			["", "logo.png"],
			["invalid-data", "logo.png"],
			[[], []],
			[1, null],
			[1, false],
			[1, true],
			[null, "logo.png"],
			[false, "logo.png"],
			[true, "logo.png"],
		];
	}

	/**
	 * @param array<mixed> $invalidFileIdValue
	 * @param array<mixed> $invalidFileNameValue
	 * @dataProvider getInvalidKeyWithValidFileInfoValues
	 * @return void
	 */
	public function testLinkWorkPackageToFileFileInvalidDataWithValidFileInfoValues($invalidFileIdValue, $invalidFileNameValue): void {
		$service = $this->getOpenProjectAPIServiceMock(['request', 'getNode']);
		$fileMock = $this->getMockBuilder('\OCP\Files\File')->getMock();
		$service->method('getNode')
			->willReturn($fileMock);
		$service->expects($this->never())
			->method('request');
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/^invalid data$/');
		$values = [
			"workpackageId" => 1,
			"fileinfo" => [
				[
					"id" => $invalidFileIdValue,
					"name" => $invalidFileNameValue
				]
			]
		];
		$service->linkWorkPackageToFile(
			$values, 'user'
		);
	}

	/**
	 * @return void
	 */
	public function testLinkWorkPackageToFileFileNotFound(): void {
		$service = $this->getOpenProjectAPIServiceMock(['request', 'getNode']);

		$fileMock = $this->getMockBuilder('\OCP\Files\File')->getMock();
		$fileMock->method('isReadable')
			->willThrowException(new NotFoundException());

		$service->method('getNode')
			->willReturn($fileMock);

		$service->expects($this->never())
			->method('request');

		$this->expectException(NotFoundException::class);
		$values = $this->singleFileInformation;
		$service->linkWorkPackageToFile(
			$values, 'user'
		);
	}


	/**
	 * @return void
	 * @throws \JsonException
	 */
	public function testLinkWorkPackageToMultipleFileRequest(): void {
		$service = $this->getOpenProjectAPIServiceMock(['request', 'getNode']);

		$service->method('getNode')
			->willReturn($this->getNodeMock());
		$service->method('request')
			->willReturn(['_type' => 'Collection', '_embedded' => ['elements' => [['id' => 2456], ['id' => 2457], ['id' => 2458]]]]);

		$service->expects($this->once())
			->method('request')
			->with(
				'user', 'work_packages/123/file_links',
				['body' => json_encode($this->validFileLinkRequestBodyForMultipleFiles, JSON_THROW_ON_ERROR)]
			);
		$values = $this->multipleFileInformation;
		$result = $service->linkWorkPackageToFile(
			$values, 'user'
		);
		$this->assertSame([2456, 2457, 2458], $result);
	}


	/**
	 * @return void
	 * @param \Exception $exception
	 * @param int $expectedHttpStatusCode
	 * @param string $expectedError
	 * @dataProvider connectExpectionDataProvider
	 *
	 */
	public function testRequestConnectException(
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
			->willReturnMap($this->getAppValues([
				'openproject_instance_url' => 'http://openproject.org',
			]));
		$configMock
			->method('getUserValue')
			->withConsecutive(
				['','integration_openproject', 'token'],
				['','integration_openproject', 'token_expires_at'],
				['','integration_openproject', 'refresh_token'],
			)
			->willReturnOnConsecutiveCalls(
				'',
				'',
			);

		$constructArgs = $this->getOpenProjectAPIServiceConstructArgs([
			'config' => $configMock,
			'clientService' => $clientService,
		]);

		$service = new OpenProjectAPIService(...$constructArgs);

		$response = $service->request('', '', []);
		$this->assertSame($expectedError, $response['error']);
		$this->assertSame($expectedHttpStatusCode, $response['statusCode']);
	}

	/**
	 * @return void
	 * @param \Exception $exception
	 * @param int $expectedHttpStatusCode
	 * @param string $expectedError
	 * @dataProvider clientExpectionDataProvider
	 *
	 */
	public function testRequestClientServerException(
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
			->willReturnMap($this->getAppValues([
				'openproject_instance_url' => 'http://openproject.org',
			]));
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

		$constructArgs = $this->getOpenProjectAPIServiceConstructArgs([
			'config' => $configMock,
			'clientService' => $clientService,
		]);

		$service = new OpenProjectAPIService(...$constructArgs);

		$response = $service->request('', '', []);
		$this->assertSame($expectedError, $response['message']);
		$this->assertSame($expectedHttpStatusCode, $response['statusCode']);
	}

	/**
	 * @param string $mountPoint
	 * @param bool $canManageACL
	 * @param array<mixed>|null $getFoldersForGroupResponse
	 * @return MockObject
	 */
	public function getFolderManagerMock(
		string $mountPoint = Application::OPEN_PROJECT_ENTITIES_NAME,
		bool $canManageACL = true,
		array $getFoldersForGroupResponse = null): MockObject {
		$folderManagerMock = $this->getMockBuilder(FolderManager::class)->disableOriginalConstructor()->getMock();
		$folderManagerMock
			->method('getAllFolders')
			->willReturn([ 0 => [
				'id' => 123,
				'mount_point' => $mountPoint,
				'groups' => Application::OPEN_PROJECT_ENTITIES_NAME,
				'quota' => 1234,
				'size' => 0,
				'acl' => $canManageACL
			]]);

		if ($getFoldersForGroupResponse === null) {
			$getFoldersForGroupResponse = [ 0 => [
				'folder_id' => 123,
				'mount_point' => $mountPoint,
				'permissions' => 31,
				'acl' => true
			]];
		}
		$folderManagerMock
			->method('getFoldersForGroups')
			->with([Application::OPEN_PROJECT_ENTITIES_NAME])
			->willReturn($getFoldersForGroupResponse);

		$folderManagerMock
			->method('canManageACL')
			->willReturn($canManageACL);

		return $folderManagerMock;
	}

	public function testIsProjectFoldersSetupComplete(): void {
		$this->classExistsMock->expects($this->any())->willReturn(true);
		$userMock = $this->createMock(IUser::class);
		$groupMock = $this->createMock(IGroup::class);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$userManagerMock
			->method('userExists')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn(true);
		$userManagerMock
			->method('get')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($userMock);

		$groupManagerMock = $this->getMockBuilder(IGroupManager::class)
			->getMock();
		$groupManagerMock
			->method('groupExists')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn(true);
		$groupManagerMock
			->method('get')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($groupMock);
		$groupManagerMock
			->method('isInGroup')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME, Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn(true);

		$subAdminManagerMock = $this->getMockBuilder(ISubAdmin::class)->getMock();
		$subAdminManagerMock
			->method('isSubAdminOfGroup')
			->with($userMock, $groupMock)
			->willReturn(true);

		$appManagerMock = $this->getMockBuilder(IAppManager::class)
			->getMock();
		$appManagerMock
			->method('isEnabledForUser')
			->with('groupfolders', $userMock)
			->willReturn(true);

		$service = $this->getOpenProjectAPIServiceMock(
			['getGroupFolderManager'],
			[
				'userManager' => $userManagerMock,
				'groupManager' => $groupManagerMock,
				'appManager' => $appManagerMock,
				'subAdmin' => $subAdminManagerMock,
			],
		);
		$folderManagerMock = $this->getFolderManagerMock();
		$service->method('getGroupFolderManager')
			->willReturn($folderManagerMock);
		$this->assertTrue($service->isProjectFoldersSetupComplete());
	}


	/**
	 * @return array<mixed>
	 */
	public function groupFolderNotSetUpDataProvider(): array {
		return [
			[false,true,true,true,true,Application::OPEN_PROJECT_ENTITIES_NAME,null,true],
			[true,false,true,true,true,Application::OPEN_PROJECT_ENTITIES_NAME,null,true],
			[true,true,false,true,true,Application::OPEN_PROJECT_ENTITIES_NAME,null,true],
			[true,true,true,false,true,Application::OPEN_PROJECT_ENTITIES_NAME,null,true],
			[true,true,true,true,false,Application::OPEN_PROJECT_ENTITIES_NAME,null,true],
			[true,true,true,true,true,"test_path",null,true],
			[true,true,true,true,true,Application::OPEN_PROJECT_ENTITIES_NAME,[],true], // no folders assigned to the OpenProject group
			[true, true, true, true, true, Application::OPEN_PROJECT_ENTITIES_NAME,
				[0 => [
					'folder_id' => 123,
					'mount_point' => Application::OPEN_PROJECT_ENTITIES_NAME,
					'permissions' => 15,
					'acl' => true
				]],
				true], // the folder assigned to the OpenProject group has wrong permission
			[true, true, true, true, true, Application::OPEN_PROJECT_ENTITIES_NAME,
				[0 => [
					'folder_id' => 123,
					'mount_point' => 'someOtherFolder',
					'permissions' => 31,
					'acl' => true
				]],
				true], // there is an OpenProject folder, and also a folder assigned to the OpenProject group but they are not the same
			[true, true, true, true, true, Application::OPEN_PROJECT_ENTITIES_NAME,
				[0 => [
					'folder_id' => 123,
					'mount_point' => Application::OPEN_PROJECT_ENTITIES_NAME,
					'permissions' => 31,
					'acl' => false
				]],
				true], // the folder assigned to the OpenProject has no acl set
			[true,true,true,true,true,Application::OPEN_PROJECT_ENTITIES_NAME,null,false]
		];
	}

	/**
	 * @return void
	 * @param bool $userExists
	 * @param bool $groupExists
	 * @param bool $userIsMemberOfGroup
	 * @param bool $userIsAdminOfGroup
	 * @param bool $groupFolderAppEnabled
	 * @param string $groupFolderPath
	 * @param ?array<mixed> $getFoldersForGroupResponse // null means a good default response
	 * @param bool $canUserManageACL
	 * @dataProvider  groupFolderNotSetUpDataProvider
	 *
	 */
	public function testIsGroupFolderNotSetup(
		bool $userExists,
		bool $groupExists,
		bool $userIsMemberOfGroup,
		bool $userIsAdminOfGroup,
		bool $groupFolderAppEnabled,
		string $groupFolderPath,
		?array $getFoldersForGroupResponse,
		bool $canUserManageACL
	): void {
		$userMock = $this->createMock(IUser::class);
		$groupMock = $this->createMock(IGroup::class);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$userManagerMock
			->method('userExists')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($userExists);
		$userManagerMock
			->method('get')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($userMock);

		$groupManagerMock = $this->getMockBuilder(IGroupManager::class)
			->getMock();
		$groupManagerMock
			->method('groupExists')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($groupExists);
		$groupManagerMock
			->method('get')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($groupMock);
		$groupManagerMock
			->method('isInGroup')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME, Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($userIsMemberOfGroup);

		$subAdminManagerMock = $this->getMockBuilder(ISubAdmin::class)->getMock();
		$subAdminManagerMock
			->method('isSubAdminOfGroup')
			->with($userMock, $groupMock)
			->willReturn($userIsAdminOfGroup);

		$appManagerMock = $this->getMockBuilder(IAppManager::class)
			->getMock();
		$appManagerMock
			->method('isEnabledForUser')
			->with('groupfolders', $userMock)
			->willReturn($groupFolderAppEnabled);

		$service = $this->getOpenProjectAPIServiceMock(
			['getGroupFolderManager'],
			[
				'userManager' => $userManagerMock,
				'groupManager' => $groupManagerMock,
				'appManager' => $appManagerMock,
				'subAdmin' => $subAdminManagerMock,
			],
		);
		$folderManagerMock = $this->getFolderManagerMock(
			$groupFolderPath, $canUserManageACL, $getFoldersForGroupResponse
		);
		$service->method('getGroupFolderManager')
			->willReturn($folderManagerMock);
		$this->assertFalse($service->isProjectFoldersSetupComplete());
	}

	public function testGenerateAppPasswordTokenForUser(): void {
		$userMock = $this->getMockBuilder(IUser::class)->getMock();
		$userMock
			->method('getUID')
			->willReturn(Application::OPEN_PROJECT_ENTITIES_NAME);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$userManagerMock
			->method('get')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($userMock);
		$token = "gliAcIJ3RwcgpF6ijPramBVzujfSQwJw2AVcz3Uj7bdXqxDbmkSukQhljAUf9HXItQTglvfx";
		$iSecureRandomMock = $this->getMockBuilder(ISecureRandom::class)
			->getMock();
		$iSecureRandomMock
			->method('generate')
			->willReturn($token);
		$itokenMock = $this->createMock(IToken::class);
		$tokenProviderMock = $this->getMockBuilder(IProvider::class)->getMock();
		$tokenProviderMock
			->expects($this->once())
			->method('generateToken')
			->with($token, Application::OPEN_PROJECT_ENTITIES_NAME, Application::OPEN_PROJECT_ENTITIES_NAME, null, Application::OPEN_PROJECT_ENTITIES_NAME, $this->equalTo(1))
			->willReturn($itokenMock);
		$eventDispatcherMock = $this->getMockBuilder(IEventDispatcher::class)->getMock();
		$eventDispatcherMock
			->method('dispatchTyped')
			->with($this->createMock(AppPasswordCreatedEvent::class));
		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'userManager' => $userManagerMock,
				'secureRandom' => $iSecureRandomMock,
				'provider' => $tokenProviderMock,
			],
		);
		$result = $service->generateAppPasswordTokenForUser();
		$this->assertSame($token, $result);
	}

	public function testIsSystemReadyForProjectFolderSetUp(): void {
		$this->classExistsMock->expects($this->any())->willReturn(true);
		$userMock = $this->createMock(IUser::class);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$userManagerMock
			->method('userExists')
			->withConsecutive([Application::OPEN_PROJECT_ENTITIES_NAME], [Application::OPEN_PROJECT_ENTITIES_NAME])
			->willReturnOnConsecutiveCalls(false, false);
		$userManagerMock
			->method('get')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($userMock);

		$groupManagerMock = $this->getMockBuilder(IGroupManager::class)
			->getMock();
		$groupManagerMock
			->method('groupExists')
			->withConsecutive([Application::OPEN_PROJECT_ENTITIES_NAME], [Application::OPEN_PROJECT_ENTITIES_NAME])
			->willReturnOnConsecutiveCalls(false, false);
		$appManagerMock = $this->getMockBuilder(IAppManager::class)
			->getMock();
		$appManagerMock
			->method('isEnabledForUser')
			->with('groupfolders', $userMock)
			->willReturn(true);
		$service = $this->getOpenProjectAPIServiceMock(
			['getGroupFolderManager'],
			[
				'userManager' => $userManagerMock,
				'groupManager' => $groupManagerMock,
				'appManager' => $appManagerMock,
			],
		);
		$folderManagerMock = $this->getFolderManagerMock('', false, [ 0 => [
			'folder_id' => 123,
			'mount_point' => '',
			'permissions' => 31,
			'acl' => true
		]]);
		$service->method('getGroupFolderManager')
			->willReturn($folderManagerMock);
		$result = $service->isSystemReadyForProjectFolderSetUp();
		$this->assertTrue($result);
	}

	/**
	 * @return array<mixed>
	 */
	public function isSystemReadyForGroupFolderSetUpUserOrGroupExistsExceptionDataProvider(): array {
		return [
			[true, true, false, false,'The "groupfolders" app is not installed'],
			[true, false, false, false,'The user "'. Application::OPEN_PROJECT_ENTITIES_NAME .'" already exists'],
			[false, true, false, false,'The group "'. Application::OPEN_PROJECT_ENTITIES_NAME .'" already exists'],
			[false, false, false, false,'The "groupfolders" app is not installed'],
			[false, false, true, true,'The team folder name "'. Application::OPEN_PROJECT_ENTITIES_NAME .'" already exists'],
		];
	}

	/**
	 * @param bool $userExists
	 * @param bool $groupExists
	 * @param bool $appEnabled
	 * @param bool $groupFolderExists
	 * @param string $exception
	 * @return void
	 * @dataProvider isSystemReadyForGroupFolderSetUpUserOrGroupExistsExceptionDataProvider
	 */
	public function testIsSystemReadyForGroupFolderSetUpUserOrGroupExistsException(
		bool $userExists,
		bool $groupExists,
		bool $appEnabled,
		bool $groupFolderExists,
		string $exception
	): void {
		$this->classExistsMock->expects($this->any())->with('\OCA\GroupFolders\Folder\FolderManager')->willReturn($appEnabled);
		$userMock = $this->createMock(IUser::class);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$userManagerMock
			->method('get')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($userMock);
		$userManagerMock
			->method('userExists')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($userExists);
		$groupManagerMock = $this->getMockBuilder(IGroupManager::class)
			->getMock();
		$groupManagerMock
			->method('groupExists')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($groupExists);
		$appManagerMock = $this->getMockBuilder(IAppManager::class)
			->getMock();
		$appManagerMock
			->method('isEnabledForUser')
			->with('groupfolders', $userMock)
			->willReturn($appEnabled);
		$service = $this->getOpenProjectAPIServiceMock(
			['getGroupFolderManager', 'groupFolderToArray'],
			[
				'userManager' => $userManagerMock,
				'groupManager' => $groupManagerMock,
				'appManager' => $appManagerMock,
			],
		);
		$folderManagerMock = $this->getFolderManagerMock();
		$service->method('getGroupFolderManager')
			->willReturn($folderManagerMock);
		$service->method('groupFolderToArray')
			->willReturn([
				'id' => 123,
				'folder_id' => 123,
				'mount_point' => Application::OPEN_PROJECT_ENTITIES_NAME,
				'groups' => Application::OPEN_PROJECT_ENTITIES_NAME,
				'quota' => 1234,
				'size' => 0,
				'acl' => true,
				'permissions' => 31,
			]);
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage($exception);
		$service->isSystemReadyForProjectFolderSetUp();
	}

	/**
	 * @return array<mixed>
	 */
	public function groupFolderToArrayDataProvider(): array {
		$folder = new class {
			public function toArray(): array {
				return ['id' => 123];
			}
		};
		return [
			[true, $folder, ['id' => 123, 'folder_id' => 123]],
			[false, ['id' => 123], ['id' => 123]],
			[true, null, "Invalid folder type. Expected array, got: NULL"],
		];
	}

	/**
	 * @param bool $classExists
	 * @param mixed $folder
	 * @return void
	 * @dataProvider groupFolderToArrayDataProvider
	 */
	public function testGroupFolderToArray(
		bool $classExists,
		mixed $folder,
		array|string $expectedResult,
	): void {
		$this->classExistsMock->expects($this->any())->willReturn($classExists);
		$service = $this->getOpenProjectAPIServiceMock();
		if ($folder === null) {
			$this->expectException(\InvalidArgumentException::class);
			$this->expectExceptionMessage($expectedResult);
		}
		$result = $service->groupFolderToArray($folder);
		$this->assertSame($expectedResult, $result);
	}

	public function testProjectFolderHasAppPassword(): void {
		$tokenProviderMock = $this->getMockBuilder(IProvider::class)->disableOriginalConstructor()
			->getMock();
		$tokenMock = $this->getMockBuilder(IToken::class)->getMock();
		$tokenMock
			->method('getName')
			->willReturn('OpenProject');
		$tokenProviderMock
			->method('getTokenByUser')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn([$tokenMock]);
		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'provider' => $tokenProviderMock,
			]
		);
		$this->assertTrue($service->hasAppPassword());
	}

	public function testProjectFolderHasMultipleAppPassword(): void {
		$tokenProviderMock = $this->getMockBuilder(IProvider::class)->disableOriginalConstructor()
			->getMock();
		$tokenMock1 = $this->getMockBuilder(IToken::class)->getMock();
		$tokenMock1
			->method('getName')
			->willReturn('session');
		$tokenMock2 = $this->getMockBuilder(IToken::class)->getMock();
		$tokenMock2
			->method('getName')
			->willReturn('test');
		$tokenMock3 = $this->getMockBuilder(IToken::class)->getMock();
		$tokenMock3
			->method('getName')
			->willReturn('new-token');
		$tokenMock4 = $this->getMockBuilder(IToken::class)->getMock();
		$tokenMock4
			->method('getName')
			->willReturn('OpenProject');
		$tokenProviderMock
			->method('getTokenByUser')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn([$tokenMock1,$tokenMock2,$tokenMock3,$tokenMock4]);
		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'provider' => $tokenProviderMock,
			],
		);
		$this->assertTrue($service->hasAppPassword());
	}

	public function testProjectFolderHasAppPasswordNegativeCondition(): void {
		$tokenProviderMock = $this->getMockBuilder(IProvider::class)->disableOriginalConstructor()
			->getMock();
		$tokenMock = $this->getMockBuilder(IToken::class)->getMock();
		$tokenMock
			->method('getName')
			->willReturn('session');
		$tokenProviderMock
			->method('getTokenByUser')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn([$tokenMock]);
		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'provider' => $tokenProviderMock,
			],
		);
		$this->assertFalse($service->hasAppPassword());
	}

	public function testProjectFolderDeleteAppPassword(): void {
		$tokenProviderMock = $this->getMockBuilder(IProvider::class)->disableOriginalConstructor()
			->getMock();
		$tokenMock1 = $this->getMockBuilder(IToken::class)->getMock();
		$tokenMock1
			->method('getName')
			->willReturn('session');
		$tokenMock1
			->method('getId')
			->willReturn(1);
		$tokenMock2 = $this->getMockBuilder(IToken::class)->getMock();
		$tokenMock2
			->method('getName')
			->willReturn('test');
		$tokenMock2
			->method('getId')
			->willReturn(2);
		$tokenMock3 = $this->getMockBuilder(IToken::class)->getMock();
		$tokenMock3
			->method('getName')
			->willReturn('new-token');
		$tokenMock3
			->method('getId')
			->willReturn(3);
		$tokenMock4 = $this->getMockBuilder(IToken::class)->getMock();
		$tokenMock4
			->method('getName')
			->willReturn('OpenProject');
		$tokenMock4
			->method('getId')
			->willReturn(4);
		$tokenMock5 = $this->getMockBuilder(IToken::class)->getMock();
		$tokenMock5
			->method('getName')
			->willReturn('OpenProject');
		$tokenMock5
			->method('getId')
			->willReturn(5);
		$tokenProviderMock
			->method('getTokenByUser')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn([$tokenMock1,$tokenMock2,$tokenMock3,$tokenMock4,$tokenMock5]);
		$service = $this->getOpenProjectAPIServiceMock(
			['hasAppPassword'],
			[
				'provider' => $tokenProviderMock,
			],
		);
		$service->method('hasAppPassword')->willReturn(true);
		$tokenProviderMock->expects($this->exactly(2))
			->method('invalidateTokenById')
			->withConsecutive([Application::OPEN_PROJECT_ENTITIES_NAME, 4], [Application::OPEN_PROJECT_ENTITIES_NAME, 5]);
		$service->deleteAppPassword();
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testLinkWorkPackageToFilePact(string $authorizationMethod): void {
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

		$service = $this->getOpenProjectAPIService($authorizationMethod, $storageMock);
		$values = $this->singleFileInformation;
		$result = $service->linkWorkPackageToFile(
			$values,
			'testUser'
		);

		$this->assertSame([1337], $result);
	}


	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testLinkWorkPackageToMultipleFileRequestPact(string $authorizationMethod): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('POST')
			->setPath($this->workPackagesPath . '/123/file_links')
			->setHeaders(['Authorization' => 'Bearer 1234567890'])
			->setBody($this->validFileLinkRequestBodyForMultipleFiles);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody(['_type' => 'Collection', '_embedded' => ['elements' => [['id' => 2456], ['id' => 2457], ['id' => 2458]]]]);

		$this->builder
			->uponReceiving('a POST request to /work_packages with multiple files')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$storageMock = $this->getStorageMock();

		$service = $this->getOpenProjectAPIService($authorizationMethod, $storageMock);
		$values = $this->multipleFileInformation;
		$result = $service->linkWorkPackageToFile(
			$values,
			'testUser'
		);
		$this->assertSame([2456, 2457, 2458], $result);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testLinkWorkPackageToFileEmptyStorageUrlPact(string $authorizationMethod): void {
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

		$configMock = $this->createMock(IConfig::class);
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'openproject_instance_url' => 'http://openprojectUrl.com',
			]));

		$serviceMock = $this->getOpenProjectAPIServiceMock(['request'], [ 'config' => $configMock, 'rootFolder' => $this->getStorageMock() ]);
		$serviceMock->expects($this->once())
			->method('request')
			->willReturn([
				'error' => 'some string',
				'statusCode' => Http::STATUS_UNPROCESSABLE_ENTITY,
				'message' => "The request body was invalid."
			]);

		$this->expectException(OpenprojectErrorException::class);

		$values = $this->singleFileInformation;
		$serviceMock->linkWorkPackageToFile(
			$values,
			'testUser'
		);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testLinkWorkPackageToFileNotAvailableStorageUrlPact(string $authorizationMethod): void {
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

		$configMock = $this->createMock(IConfig::class);
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'openproject_instance_url' => 'http://openprojectUrl.com',
			]));

		$serviceMock = $this->getOpenProjectAPIServiceMock(['request'], [ 'config' => $configMock, 'rootFolder' => $this->getStorageMock() ]);
		$serviceMock->expects($this->once())
			->method('request')
			->willReturn([
				'error' => 'some string',
				'statusCode' => Http::STATUS_UNPROCESSABLE_ENTITY,
				'message' => "The request was invalid. File Link logo.png - Storage was invalid."
			]);

		$this->expectException(OpenprojectErrorException::class);
		$values = $this->singleFileInformation;
		$serviceMock->linkWorkPackageToFile(
			$values,
			'testUser'
		);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testLinkWorkPackageToFileMissingPermissionPact(string $authorizationMethod): void {
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

		$configMock = $this->createMock(IConfig::class);
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'openproject_instance_url' => 'http://openprojectUrl.com',
			]));

		$serviceMock = $this->getOpenProjectAPIServiceMock(['request'], [ 'config' => $configMock, 'rootFolder' => $this->getStorageMock() ]);
		$serviceMock->expects($this->once())
			->method('request')
			->willReturn([
				'error' => 'some string',
				'statusCode' => Http::STATUS_FORBIDDEN,
				'message' => "You are not authorized to access this resource."
			]);

		$this->expectException(OpenprojectErrorException::class);
		$values = $this->singleFileInformation;
		$serviceMock->linkWorkPackageToFile(
			$values,
			'testUser'
		);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testLinkWorkPackageToFileNotFoundPact(string $authorizationMethod): void {
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


		$configMock = $this->createMock(IConfig::class);
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'openproject_instance_url' => 'http://openprojectUrl.com',
			]));

		$serviceMock = $this->getOpenProjectAPIServiceMock(['request'], [ 'config' => $configMock, 'rootFolder' => $this->getStorageMock() ]);
		$serviceMock->expects($this->once())
			->method('request')
			->willReturn([
				'error' => 'some string',
				'statusCode' => Http::STATUS_NOT_FOUND,
				'message' => "The requested resource could not be found."
			]);

		$this->expectException(OpenprojectErrorException::class);
		$values = [
			"workpackageId" => 999999,
			"fileinfo" => [
				[
					"id" => 5503,
					'name' => 'logo.png'
				]
			]
		];

		$serviceMock->linkWorkPackageToFile(
			$values,
			'testUser'
		);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testMarkAllNotificationsOfWorkPackageAsReadPact(string $authorizationMethod): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('POST')
			->setPath($this->notificationsPath . '/read_ian')
			->setQuery(['filters' => '[{"resourceId":{"operator":"=","values":["123"]}}]'])
			->setHeaders(['Authorization' => 'Bearer 1234567890'])
			->setBody(null);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_NO_CONTENT);

		$this->builder
			->uponReceiving('a POST request to mark all notifications of a WP as read')
			->with($consumerRequest)
			->willRespondWith($providerResponse);


		$service = $this->getOpenProjectAPIService($authorizationMethod);

		$result = $service->markAllNotificationsOfWorkPackageAsRead(
			123,
			'testUser'
		);

		$this->assertSame(['success' => true], $result);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testMarkAllNotificationsOfANotExistingWorkPackageAsReadPact(string $authorizationMethod): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('POST')
			->setPath($this->notificationsPath . '/read_ian')
			->setQuery(['filters' => '[{"resourceId":{"operator":"=","values":["789"]}}]'])
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

		$configMock = $this->createMock(IConfig::class);
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'openproject_instance_url' => 'http://openprojectUrl.com',
			]));

		$serviceMock = $this->getOpenProjectAPIServiceMock(['request'], [ 'config' => $configMock, 'rootFolder' => $this->getStorageMock() ]);
		$serviceMock->expects($this->once())
			->method('request')
			->willReturn([
				'error' => 'some string',
				'statusCode' => Http::STATUS_BAD_REQUEST,
				'message' => "Filters Resource filter has invalid values."
			]);

		$this->expectException(OpenprojectErrorException::class);
		$serviceMock->markAllNotificationsOfWorkPackageAsRead(
			789,
			'testUser'
		);
	}
	/**
	 * @return array<mixed>
	 */
	public function adminConfigStatusProviderForOauth(): array {
		return [
			[
				'config' => [
					'openproject_client_id' => '',
					'openproject_client_secret' => '',
					'fresh_project_folder_setup', true,
				],
				'expected' => false,
			],
			[
				'config' => [
					'openproject_client_id' => 'clientID',
					'openproject_client_secret' => '',
					'fresh_project_folder_setup', true,
				],
				'expected' => false,
			],
			[
				'config' => [
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'openproject_client_id' => 'clientID',
					'openproject_client_secret' => 'clientSecret',
					'fresh_project_folder_setup', true,
				],
				'expected' => false,
			],
			[
				'config' => [
					'nc_oauth_client_id' => '',
					'openproject_client_id' => 'clientID',
					'openproject_client_secret' => 'clientSecret',
					'fresh_project_folder_setup', true,
				],
				'expected' => false,
			],
			[
				'config' => [
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'clientID',
					'openproject_client_secret' => 'clientSecret',
					'fresh_project_folder_setup', false,
					'nc_oauth_client_id' => 'ncClientID',
				],
				'expected' => true,
			],
			[
				'config' => [
					'authorization_method' => '',
					'openproject_client_id' => 'clientID',
					'openproject_client_secret' => 'clientSecret',
					'fresh_project_folder_setup', false,
					'nc_oauth_client_id' => 'ncClientID',
				],
				'expected' => true,
			],
		];
	}

	/**
	 * @dataProvider adminConfigStatusProviderForOauth
	 * @return void
	 */
	public function testIsAdminConfigOkForOauth2(
		array $config,
		bool $expected,
	) {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues($config));

		$this->assertSame($expected, $this->service::isAdminConfigOkForOauth2($configMock));
	}

	/**
	 * @return array<mixed>
	 */
	public function adminConfigStatusProviderForOIDC(): array {
		return [
			[
				'config' => [
					'openproject_instance_url' => '',
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => '',
					'targeted_audience_client_id' => '',
					'sso_provider_type' => '',
					'token_exchange' => false,
					'fresh_project_folder_setup', true,
				],
				'expected' => false,
			],
			[
				'config' => [
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'Keycloak',
					'targeted_audience_client_id' => '',
					'sso_provider_type' => '',
					'token_exchange' => false,
					'fresh_project_folder_setup', true,
				],
				'expected' => false,
			],
			[
				'config' => [
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => '',
					'targeted_audience_client_id' => 'targetClientID',
					'sso_provider_type' => 'external',
					'token_exchange' => true,
					'fresh_project_folder_setup', false,
				],
				'expected' => false,
			],
			[
				'config' => [
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'oidc_provider' => 'Keycloak',
					'targeted_audience_client_id' => 'targetClientID',
					'sso_provider_type' => 'external',
					'token_exchange' => false,
					'fresh_project_folder_setup', false,
				],
				'expected' => false,
			],
			[
				'config' => [
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'oidcProvider',
					'targeted_audience_client_id' => '',
					'sso_provider_type' => 'external',
					'token_exchange' => true,
					'fresh_project_folder_setup', false,
				],
				'expected' => false,
			],
			[
				'config' => [
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'oidcProvider',
					'targeted_audience_client_id' => 'targetClientID',
					'sso_provider_type' => '',
					'token_exchange' => true,
					'fresh_project_folder_setup', true,
				],
				'expected' => false,
			],
			[
				'config' => [
					'openproject_instance_url' => '',
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'oidcProvider',
					'targeted_audience_client_id' => 'targetClientID',
					'sso_provider_type' => '',
					'token_exchange' => true,
					'fresh_project_folder_setup', true,
				],
				'expected' => false,
			],
			[
				'config' => [
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'oidcProvider',
					'targeted_audience_client_id' => 'targetClientID',
					'sso_provider_type' => 'external',
					'token_exchange' => true,
					'fresh_project_folder_setup', false,
				],
				'expected' => true,
			],
			[
				'config' => [
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'oidcProvider',
					'targeted_audience_client_id' => '',
					'sso_provider_type' => 'external',
					'token_exchange' => false,
					'fresh_project_folder_setup', false,
				],
				'expected' => true,
			],
			[
				'config' => [
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'NC Provider',
					'targeted_audience_client_id' => 'test-client',
					'sso_provider_type' => 'nextcloud_hub',
					'token_exchange' => false,
					'fresh_project_folder_setup', false,
				],
				'expected' => true,
			],
		];
	}

	/**
	 * @dataProvider adminConfigStatusProviderForOIDC
	 * @return void
	 */
	public function testIsAdminConfigOkForOIDCAuth(
		array $config,
		bool $expected,
	) {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues($config));

		$this->assertSame($expected, $this->service::isAdminConfigOkForOIDCAuth($configMock));
	}

	/**
	 * @return array<array>
	 */
	public function adminConfigOkDataProvider(): array {
		return [
			[
				'config' => [
					'openproject_instance_url' => 'http://op.local',
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'clientID',
					'openproject_client_secret' => 'clientSecret',
					'fresh_project_folder_setup' => false,
					'nc_oauth_client_id' => 'ncClientID',
				],
				'completeSetup' => true,
			],
			[
				'config' => [
					'openproject_instance_url' => 'http://op.local',
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'sso_provider_type' => OpenProjectAPIService::NEXTCLOUD_HUB_PROVIDER,
					'oidc_provider' => 'Nextcloud',
					'targeted_audience_client_id' => 'openproject',
					'fresh_project_folder_setup' => false,
				],
				'completeSetup' => true,
			],
			[
				'config' => [
					'authorization_method' => '',
				],
				'completeSetup' => false,],
		];
	}

	/**
	 * @dataProvider adminConfigOkDataProvider
	 * @return void
	 */
	public function testIsAdminConfigOk(array $config, bool $completeSetup) {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues($config));

		$result = OpenProjectAPIService::isAdminConfigOk($configMock);
		$this->assertSame($completeSetup, $result);
	}

	/**
	 * @return void
	 */
	public function testGetWorkPackageFileLinksResponse(): void {
		$service = $this->getOpenProjectAPIServiceMock();
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
		$service = $this->getOpenProjectAPIServiceMock();
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
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
				->willReturn([
					'_type' => '',
				]);
		$this->expectException(OpenprojectResponseException::class);
		$service->getWorkPackageFileLinks(7, 'user');
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetWorkPackageFileLinksPact(string $authorizationMethod): void {
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
		$service = $this->getOpenProjectAPIService($authorizationMethod, $storageMock);

		$result = $service->getWorkPackageFileLinks(7, 'testUser');

		$expected = [[
			'id' => 8,
			'_type' => "FileLink",
			'originData' => [
				'id' => 5
			]
		]];
		$this->assertSame(
			sort($expected),
			sort($result));
	}
	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetWorkPackageFileLinkNotFoundPact(string $authorizationMethod): void {
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

		$configMock = $this->createMock(IConfig::class);
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'openproject_instance_url' => 'http://openprojectUrl.com',
			]));

		$serviceMock = $this->getOpenProjectAPIServiceMock(['request'], [ 'config' => $configMock, 'rootFolder' => $this->getStorageMock() ]);
		$serviceMock->expects($this->once())
			->method('request')
			->willReturn([
				'error' => 'some string',
				'statusCode' => Http::STATUS_NOT_FOUND,
				'message' => "The requested resource could not be found."
			]);

		$this->expectException(OpenprojectErrorException::class);
		$serviceMock->getWorkPackageFileLinks(100, 'testUser');
	}

	/**
	 * @return void
	 */
	public function testDeleteFileLinkResponse(): void {
		$service = $this->getOpenProjectAPIServiceMock();
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
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn([
				'error' => 'something went wrong',
			]);
		$this->expectException(OpenprojectErrorException::class);
		$service->deleteFileLink(7, 'user');
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetWorkPackageFileDeleteLinksPact(string $authorizationMethod): void {
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
		$service = $this->getOpenProjectAPIService($authorizationMethod, $storageMock);

		$result = $service->deleteFileLink(10, 'testUser');

		$this->assertSame([
			'success' => true
		], $result);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetWorkPackageFileDeleteLinkNotFoundPact(string $authorizationMethod): void {
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

		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'openproject_instance_url' => 'http://openprojectUrl.com',
			]));

		$serviceMock = $this->getOpenProjectAPIServiceMock(['request'], [ 'config' => $configMock, 'rootFolder' => $this->getStorageMock() ]);
		$serviceMock->expects($this->once())
			->method('request')
			->willReturn([
				'error' => 'some string',
				'statusCode' => Http::STATUS_NOT_FOUND,
				'message' => "The requested resource could not be found."
			]);

		$this->expectException(OpenprojectErrorException::class);
		$serviceMock->deleteFileLink(12345, 'testUser');
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetAvailableOpenProjectProjectsPact(string $authorizationMethod): void {
		$filters[] = [
			'storageUrl' =>
				['operator' => '=', 'values' => ['https://nc.my-server.org']],
			'userAction' =>
				['operator' => '&=', 'values' => ["file_links/manage", "work_packages/create"]]
		];
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath($this->getProjectsPath)
			->setHeaders(["Authorization" => "Bearer 1234567890"])
			->setQuery(
				[
					'filters' => json_encode($filters, JSON_THROW_ON_ERROR),
					'pageSize' => (string) 100
				]
			);
		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody($this->validOpenProjectGetProjectsResponse);
		$this->builder
			->uponReceiving('a GET request to /work_packages/available_projects')
			->with($consumerRequest)
			->willRespondWith($providerResponse);
		$storageMock = $this->getStorageMock();
		$service = $this->getOpenProjectAPIService($authorizationMethod, $storageMock);
		$result = $service->getAvailableOpenProjectProjects('testUser');
		$this->assertSame(sort($this->expectedValidOpenProjectResponse), sort($result));
	}

	/**
	 * @dataProvider malformedResponsesDataProvider
	 * @param array<mixed> $response
	 * @return void
	 */
	public function testGetAvailableOpenProjectProjectsMalformedResponse(array $response): void {
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn($response);
		$this->expectException(OpenprojectResponseException::class);
		$service->getAvailableOpenProjectProjects('testUser');
	}

	/**
	 * @return void
	 */
	public function testGetAvailableOpenProjectProjectsErrorResponse(): void {
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn(['error' => 'something went wrong', 'statusCode' => 500]);
		$this->expectException(OpenprojectErrorException::class);
		$service->getAvailableOpenProjectProjects('testUser');
	}

	/**
	 * @return void
	 */
	public function testGetAvailableOpenProjectProjectsQueryOnly() {
		$iUrlGeneratorMock = $this->getMockBuilder(IURLGenerator::class)->disableOriginalConstructor()->getMock();
		$iUrlGeneratorMock->method('getBaseUrl')->willReturn('https%3A%2F%2Fnc.my-server.org');
		$service = $this->getOpenProjectAPIServiceMock(
			['request'],
			[
				'urlGenerator' => $iUrlGeneratorMock,
			],
		);
		$service->method('request')
			->with(
				'user', 'work_packages/available_projects',
				[
					'filters' => '[' .
						'{"typeahead":' .
						'{"operator":"**","values":["search query"]}'.
						'},'.
						'{"storageUrl":'.
						'{"operator":"=","values":["https:\/\/nc.my-server.org"]},'.
						'"userAction":'.
						'{"operator":"&=","values":["file_links\/manage","work_packages\/create"]}}'.
						']',
					'pageSize' => 100
				],
			)
			->willReturn($this->validOpenProjectGetProjectsResponse);
		$result = $service->getAvailableOpenProjectProjects('user', 'search query');
		$this->assertSame($this->expectedValidOpenProjectResponse, $result);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testWorkpackagesFormValidationPact(string $authorizationMethod): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('POST')
			->setPath('/api/v3/projects/6/work_packages/form')
			->setHeaders(["Authorization" => "Bearer 1234567890"])
			->setBody($this->validWorkPackageFormValidationBody);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody($this->validWorkPackageFormValidationResponse);

		$this->builder
			->uponReceiving('a POST request to /projects/6/work_packages/form')
			->with($consumerRequest)
			->willRespondWith($providerResponse);
		$storageMock = $this->getStorageMock();
		$service = $this->getOpenProjectAPIService($authorizationMethod, $storageMock);
		$result = $service->getOpenProjectWorkPackageForm('testUser', '6', $this->validWorkPackageFormValidationBody);
		$this->assertSame(
			sort($this->validWorkPackageFormValidationResponse['_embedded']),
			sort($result)
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function workpackageFormMalformedResponsesDataProvider() {
		return [
			[["_type" => "workpackage"]],
			[["_embedded" => []]],
			[["_embedded" => ['payloa']]],
			[["_embedded" => ['schemas']]],
			[["embedded" => ['payload']]],
			[["embedded" => ['schema']]],
		];
	}

	/**
	 * @dataProvider workpackageFormMalformedResponsesDataProvider
	 * @param array<mixed> $response
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageFormMalformedResponse(array $response): void {
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn($response);
		$this->expectException(OpenprojectResponseException::class);
		$service->getOpenProjectWorkPackageForm('testUser', "6", $this->validWorkPackageFormValidationBody);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageFormErrorResponse(): void {
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn(['error' => 'something went wrong', 'statusCode' => 500]);
		$this->expectException(OpenprojectErrorException::class);
		$service->getOpenProjectWorkPackageForm('testUser', "6", $this->validWorkPackageFormValidationBody);
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testGetAvailableAssigneesOfAProjectPact(string $authorizationMethod): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath('/api/v3/projects/6/available_assignees')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody($this->validGetProjectAssigneesResponse);

		$this->builder
			->uponReceiving('a GET request to /projects/6/available_assignees')
			->with($consumerRequest)
			->willRespondWith($providerResponse);
		$storageMock = $this->getStorageMock();
		$service = $this->getOpenProjectAPIService($authorizationMethod, $storageMock);
		$result = $service->getAvailableAssigneesOfAProject('testUser', '6');
		$this->assertSame(
			sort($this->validGetProjectAssigneesResponse['_embedded']['elements']),
			sort($result)
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function getAvailableAssigneesOfAProjectMalformedResponsesDataProvider() {
		return [
			[["_type" => "workpackage"]],
			[["_embedded" => []]],
			[["_embedded" => ['element']]],
			[["embedded" => ['elements']]],
		];
	}

	/**
	 * @dataProvider getAvailableAssigneesOfAProjectMalformedResponsesDataProvider
	 * @param array<mixed> $response
	 * @return void
	 */
	public function testGetAvailableAssigneesOfAProjectMalformedResponse(array $response) {
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn($response);
		$this->expectException(OpenprojectResponseException::class);
		$service->getAvailableAssigneesOfAProject('testUser', "6");
	}

	/**
	 * @return void
	 */
	public function testGetAvailableAssigneesOfAProjectErrorResponse(): void {
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn(['error' => 'something went wrong', 'statusCode' => 500]);
		$this->expectException(OpenprojectErrorException::class);
		$service->getAvailableAssigneesOfAProject('testUser', "6");
	}

	/**
	 * @param string $authorizationMethod
	 * @return void
	 * @dataProvider getAuthorizationMethodDataProvider
	 * @throws \JsonException
	 */
	public function testCreateWorkpackagePact(string $authorizationMethod): void {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('POST')
			->setPath('/api/v3/work_packages')
			->setHeaders(["Authorization" => "Bearer 1234567890"])
			->setBody($this->validCreateWorkpackageBody);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->addHeader('Content-Type', 'application/json')
			->setBody($this->createWorkpackageResponse);

		$this->builder
			->uponReceiving('a POST request to /work_packages to create a new work package')
			->with($consumerRequest)
			->willRespondWith($providerResponse);
		$storageMock = $this->getStorageMock();
		$service = $this->getOpenProjectAPIService($authorizationMethod, $storageMock);
		$result = $service->createWorkPackage('testUser', $this->validCreateWorkpackageBody);
		$this->assertSame(sort($this->createWorkpackageResponse), sort($result));
	}

	/**
	 * @return array<mixed>
	 */
	public function createWorkpackagesMalformedResponsesDataProvider() {
		return [
			[["_type" => "collection"]],
			[["id" => null]],
			[["_embedded" => []]],
		];
	}
	/**
	 * @dataProvider createWorkpackagesMalformedResponsesDataProvider
	 * @param array<mixed> $response
	 * @return void
	 */
	public function testCreateWorkpackagesMalformedResponse($response) {
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn($response);
		$this->expectException(OpenprojectResponseException::class);
		$service->createWorkPackage('testUser', $this->validCreateWorkpackageBody);
	}

	/**
	 * @return void
	 */
	public function testCreateWorkpackagesErrorResponse(): void {
		$service = $this->getOpenProjectAPIServiceMock();
		$service->method('request')
			->willReturn(['error' => 'something went wrong', 'statusCode' => 500]);
		$this->expectException(OpenprojectErrorException::class);
		$service->createWorkPackage('testUser', $this->validCreateWorkpackageBody);
	}
	/**
	 * @return array<int, array<int, int|string>>
	 */
	public function passwordLengthProvider(): array {
		return [
			['10', 72],
			['100', 100]
		];
	}

	/**
	 * @dataProvider passwordLengthProvider
	 * @return void
	 */
	public function testGetPasswordLength(string $passwordLength, int $expectedPasswordLength): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->with(
				'password_policy', 'minLength'
			)
			->willReturn(
				$passwordLength
			);
		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'config' => $configMock,
			],
		);
		$result = $service->getPasswordLength();
		$this->assertEquals($expectedPasswordLength, $result);
	}

	/**
	 * @return array
	 */
	public function termsOfServicesDataProvider(): array {
		return [
			[
				[ (object)['id' => 1], (object)['id' => 2]],
				[1,2],
				true
			],
			[
				[ (object)['id' => 1],  (object)['id' => 2]],
				[1],
				false
			],
			[
				[ (object)['id' => 1],  (object)['id' => 2]],
				[],
				false
			],
		];
	}


	/**
	 * @dataProvider termsOfServicesDataProvider
	 * @param array<mixed> $availableTermsOfServices
	 * @param array<mixed> $alreadySignedTemrsOfServices
	 * @param bool $expectedResult
	 *
	 * @return void
	 */
	public function testIsAllTermsOfServiceSignedForUserOpenProject(array $availableTermsOfServices, array $alreadySignedTemrsOfServices, bool $expectedResult): void {
		$userMock = $this->createMock(IUser::class);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$userManagerMock
			->method('userExists')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn(true);
		$userManagerMock
			->method('get')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($userMock);
		$signatoryMapperMock = $this->getMockBuilder(SignatoryMapper::class)->disableOriginalConstructor()->getMock();
		$service = $this->getOpenProjectAPIServiceMock(
			['isTermsOfServiceAppEnabled', 'getAllTermsOfServiceAvailable', 'getAllTermsOfServiceSignedByUserOpenProject'],
			[
				'userManager' => $userManagerMock,
			],
		);
		$service->method('isTermsOfServiceAppEnabled')->willReturn(true);
		$service->method('getAllTermsOfServiceAvailable')->willReturn($availableTermsOfServices);
		$service->method('getAllTermsOfServiceSignedByUserOpenProject')->with($signatoryMapperMock)->willReturn($alreadySignedTemrsOfServices);
		$result = $service->isAllTermsOfServiceSignedForUserOpenProject($signatoryMapperMock);
		$this->assertSame($expectedResult, $result);
	}

	public function testGetSubline(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getUserValue')
			->willReturnMap([
				['testUser', Application::APP_ID, 'token', '', 'test_token'],
				['testUser', Application::APP_ID, 'token_expires_at', 0, time() + 7200],
			]);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$service = $this->getOpenProjectAPIServiceMock(
			['searchWorkPackage'],
			[
				'userManager' => $userManagerMock,
				'config' => $configMock,
			],
		);
		$resultTitle = $service->getSubline($this->wpInformationResponse);
		$this->assertSame("#123 [In specification] Scrum project", $resultTitle);
	}

	public function testGetMainText() : void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getUserValue')
			->willReturnMap([
				['testUser', Application::APP_ID, 'token', '', 'test_token'],
				['testUser', Application::APP_ID, 'token_expires_at', 0, time() + 7200],
			]);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$service = $this->getOpenProjectAPIServiceMock(
			['searchWorkPackage'],
			[
				'userManager' => $userManagerMock,
				'config' => $configMock,
			],
		);
		$resultMainText = $service->getMainText($this->wpInformationResponse);
		$this->assertSame("USER STORY: New login screen", $resultMainText);
	}

	public function testGetWorkPackageInfoForExistentWorkPackage(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getUserValue')
			->willReturnMap([
				['testUser', Application::APP_ID, 'token', '', 'test_token'],
				['testUser', Application::APP_ID, 'token_expires_at', 0, time() + 7200],
			]);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$iULGeneratorMock = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$service = $this->getOpenProjectAPIServiceMock(
			['searchWorkPackage'],
			[
				'userManager' => $userManagerMock,
				'config' => $configMock,
				'urlGenerator' => $iULGeneratorMock,
			],
		);
		$imageURL = 'http://nextcloud/server/ocs/v2.php/apps/integration_openproject/api/v1/avatar?userId=3&userName=OpenProject Admin';
		$iULGeneratorMock->method('linkToOCSRouteAbsolute')->willReturn($imageURL);
		$testUser = 'testUser';
		$workPackageId = 123;
		$service->method('searchWorkPackage')->with($testUser, null, null, false, $workPackageId)->willReturn([$this->wpInformationResponse]);
		$resultGetWorkPackageInfo = $service->getWorkPackageInfo($testUser, $workPackageId);
		$expectedGetWorkPackageInfo = [
			"title" => '#123 [In specification] Scrum project',
			"description" => 'USER STORY: New login screen',
			"imageUrl" => $imageURL,
			"entry" => $this->wpInformationResponse,
		];
		self::assertSame($expectedGetWorkPackageInfo, $resultGetWorkPackageInfo);
	}

	public function testGetWorkPackageInfoForNonExistentWorkPackage(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getUserValue')
			->willReturnMap([
				['testUser', Application::APP_ID, 'token', '', 'test_token'],
				['testUser', Application::APP_ID, 'token_expires_at', 0, time() + 7200],
			]);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$service = $this->getOpenProjectAPIServiceMock(
			['searchWorkPackage'],
			[
				'userManager' => $userManagerMock,
				'config' => $configMock,
			],
		);
		$wpInformationResponse = ["error" => []];
		$service->method('searchWorkPackage')->with('testUser', null, null, false, 123)->willReturn($wpInformationResponse);
		$resultGetWorkPackageInfo = $service->getWorkPackageInfo('testUser', 123);
		$this->assertNull($resultGetWorkPackageInfo);
	}

	public function testGetWorkPackageInfoForNoUserAccessToken(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getUserValue')
			->willReturnMap([
				['testUser', Application::APP_ID, 'token', '', null],
			]);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$service = $this->getOpenProjectAPIServiceMock(
			['searchWorkPackage'],
			[
				'userManager' => $userManagerMock,
				'config' => $configMock,
			],
		);
		$resultGetWorkPackageInfo = $service->getWorkPackageInfo('testUser', 123);
		$this->assertNull($resultGetWorkPackageInfo);
	}

	public function auditLogDataProvider(): array {
		return [
			[
				// log level less than 1
				0,
				\OC::$SERVERROOT . '/data/audit.log',
				[],
				true,
				false
			],
			[
				// wrong path to audit file
				1,
				'wrong-path-to-audit-log/audit.log',
				[],
				true,
				false
			],
			[
				// no audit apps in apps section
				1,
				'/audit.log',
				['apps' => ['no_audit_app']],
				true,
				false
			],
			[
				// multiple values including apps with no audit apps in apps section
				1,
				'/audit.log',
				['apps' => ['no_audit_app'], 'others' => []],
				true,
				false
			],
			[
				// all values configured correctly
				1,
				'/audit.log',
				['apps' => ['admin_audit']],
				true,
				true
			],
			[
				// admin_audit app not installed
				1,
				'/audit.log',
				['apps' => ['admin_audit']],
				false,
				false
			],
		];
	}

	/**
	 * @dataProvider auditLogDataProvider
	 * @param int $logLevel
	 * @param string $pathToAuditLog
	 * @param array<mixed> $logCondition
	 * @param bool $isAdminAuditAppInstalled
	 * @param bool $expectedResult
	 *
	 * @return void
	 */

	public function testIsAdminAuditConfigSetCorrectly(
		int $logLevel,
		string $pathToAuditLog,
		array $logCondition,
		bool $isAdminAuditAppInstalled,
		bool $expectedResult
	): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$iAppManagerMock = $this->getMockBuilder(IAppManager::class)->getMock();
		$configMock
			->method('getSystemValue')
			->withConsecutive(
				['loglevel'],
				['logfile_audit'],
				['log.condition']
			)->willReturnOnConsecutiveCalls($logLevel, $pathToAuditLog, $logCondition);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$iAppManagerMock->method('isInstalled')->with('admin_audit')
			->willReturn($isAdminAuditAppInstalled);
		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'userManager' => $userManagerMock,
				'appManager' => $iAppManagerMock,
				'config' => $configMock,
			],
		);
		$actualResult = $service->isAdminAuditConfigSetCorrectly();
		$this->assertEquals($expectedResult, $actualResult);
	}

	public function serverSideEncryptionEnabledDataProvider(): array {
		return [
			[
				'1',
				true,
				true,
				true
			],
			[
				'0',
				true,
				true,
				false
			],
			[
				'1',
				false,
				true,
				false
			],
			[
				'1',
				true,
				false,
				false
			],
			[
				'1',
				false,
				false,
				false
			],
			[
				'0',
				false,
				false,
				false
			]
		];
	}

	/**
	 * @dataProvider serverSideEncryptionEnabledDataProvider
	 * @param string $encryptionForHomeStorageEnabled
	 * @param bool $isEncryptionAppInstalled
	 * @param bool $isEncryptionAppEnabled
	 * @param bool $expectedResult
	 *
	 * @return void
	 */

	public function testIsServerSideEncryptionEnabled(
		string $encryptionForHomeStorageEnabled,
		bool $isEncryptionAppInstalled,
		bool $isEncryptionAppEnabled,
		bool $expectedResult
	): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$iManagerMock = $this->getMockBuilder(IManager::class)->getMock();
		$iAppManagerMock = $this->getMockBuilder(IAppManager::class)->getMock();
		$configMock
			->method('getAppValue')
			->with(
				'encryption', 'encryptHomeStorage'
			)
			->willReturn(
				$encryptionForHomeStorageEnabled
			);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$iAppManagerMock->method('isInstalled')->with('encryption')
			->willReturn($isEncryptionAppInstalled);
		$iManagerMock->method('isEnabled')->willReturn($isEncryptionAppEnabled);
		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'userManager' => $userManagerMock,
				'appManager' => $iAppManagerMock,
				'config' => $configMock,
				'manager' => $iManagerMock,
			],
		);
		$actualResult = $service->isServerSideEncryptionEnabled();
		$this->assertEquals($expectedResult, $actualResult);
	}



	/**
	 * @return void
	 */

	public function testGetOIDCTokenSuccess(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC
			]));
		$configMock
			->method('getUserValue')
			->willReturnMap([
				['testUser', Application::APP_ID, 'user_id', null],
				['testUser', Application::APP_ID, 'user_name', null],
			]);
		$calls = [];
		$configMock
			->method('setUserValue')
			->willReturnCallback(function ($uid, $app, $key, $value) use (&$calls) {
				$calls[] = [$uid, $app, $key, $value];
			});
		$iManagerMock = $this->getMockBuilder(IManager::class)->getMock();
		$iAppManagerMock = $this->getMockBuilder(IAppManager::class)->getMock();
		$iAppManagerMock->method('isInstalled')->willReturn(true);
		$exchangeTokenEvent = $this->getMockBuilder(TokenEventFactory::class)->disableOriginalConstructor()->getMock();
		$eventMock = $this->getMockBuilder(ExchangedTokenRequestedEvent::class)->disableOriginalConstructor()->getMock();
		$tokenMock = $this->getMockBuilder(Token::class)->disableOriginalConstructor()->getMock();
		$exchangeTokenEvent->method('getEvent')->willReturn($eventMock);
		$eventMock->method('getToken')->willReturn($tokenMock);
		$tokenMock->method('getAccessToken')->willReturn('exchanged-access-token');
		$service = $this->getOpenProjectAPIServiceMock(
			['initUserInfo'],
			[
				'appManager' => $iAppManagerMock,
				'config' => $configMock,
				'manager' => $iManagerMock,
				'tokenEventFactory' => $exchangeTokenEvent,
			],
		);
		$service->expects($this->once())->method('initUserInfo')->with('testUser');
		$result = $service->getOIDCToken('testUser');
		$this->assertEquals('exchanged-access-token', $result);
		$expectedCalls = [
			['testUser', Application::APP_ID, 'token', 'exchanged-access-token'],
			['testUser', Application::APP_ID, 'token_expires_at', '0'],
		];
		$this->assertEqualsCanonicalizing($expectedCalls, $calls);
	}

	public function testGetOIDCTokenReturnsNullIfNotOIDC(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH
			]));
		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'config' => $configMock,
			],
		);
		$result = $service->getOIDCToken('testUser');
		$this->assertEquals('', $result);
	}

	/**
	 * @return void
	 */

	public function testGetOIDCTokenUserOIDCAppNotInstalled(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$iManagerMock = $this->getMockBuilder(IManager::class)->getMock();
		$iAppManagerMock = $this->getMockBuilder(IAppManager::class)->getMock();
		$iAppManagerMock->method('isInstalled')->willReturn(false);
		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'appManager' => $iAppManagerMock,
				'config' => $configMock,
				'manager' => $iManagerMock,
			],
		);
		$result = $service->getOIDCToken('testUser');
		$this->assertEquals(null, $result);
	}

	/**
	 * @return void
	 */

	public function testGetOIDCTokenExchangeFailedException(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$iManagerMock = $this->getMockBuilder(IManager::class)->getMock();
		$iAppManagerMock = $this->getMockBuilder(IAppManager::class)->getMock();
		$iAppManagerMock->method('isInstalled')->willReturn(true);
		$exchangeTokenEvent = $this->getMockBuilder(TokenEventFactory::class)->disableOriginalConstructor()->getMock();
		/** @psalm-suppress InvalidArgument for getEvent
		 * get event can throw TokenExchangeFailedException in case there is failure in token exchange from the user_oidc app
		 */
		$exchangeTokenEvent->method('getEvent')->willThrowException(new TokenExchangeFailedException('Token exchanged failed'));
		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'appManager' => $iAppManagerMock,
				'config' => $configMock,
				'manager' => $iManagerMock,
				'tokenEventFactory' => $exchangeTokenEvent,
			],
		);
		$result = $service->getOIDCToken('testUser');
		$this->assertEquals(null, $result);
	}

	/**
	 * @return void
	 */

	public function testGetOIDCTokenNoToken(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$iManagerMock = $this->getMockBuilder(IManager::class)->getMock();
		$iAppManagerMock = $this->getMockBuilder(IAppManager::class)->getMock();
		$iAppManagerMock->method('isInstalled')->willReturn(true);
		$exchangeTokenEvent = $this->getMockBuilder(TokenEventFactory::class)->disableOriginalConstructor()->getMock();
		$eventMock = $this->getMockBuilder(ExchangedTokenRequestedEvent::class)->disableOriginalConstructor()->getMock();
		$exchangeTokenEvent->method('getEvent')->willReturn($eventMock);
		$eventMock->method('getToken')->willReturn(null);
		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'appManager' => $iAppManagerMock,
				'config' => $configMock,
				'manager' => $iManagerMock,
				'tokenEventFactory' => $exchangeTokenEvent,
			],
		);
		$result = $service->getOIDCToken('testUser');
		$this->assertEquals(null, $result);
	}

	/**
	 * @return void
	 */
	public function testGetOIDCTokenClientTokenType(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'targeted_audience_client_id' => 'testclient',
				'sso_provider_type' => OpenProjectAPIService::NEXTCLOUD_HUB_PROVIDER,
			]));
		$iManagerMock = $this->getMockBuilder(IManager::class)->getMock();
		$iAppManagerMock = $this->getMockBuilder(IAppManager::class)->getMock();
		$iAppManagerMock->method('isInstalled')->willReturn(true);
		$tokenEventMock = $this->getMockBuilder(TokenEventFactory::class)->disableOriginalConstructor()->getMock();
		$eventMock = $this->getMockBuilder(InternalTokenRequestedEvent::class)->disableOriginalConstructor()->getMock();
		$tokenMock = $this->getMockBuilder(Token::class)->disableOriginalConstructor()->getMock();
		$tokenEventMock->method('getEvent')->willReturn($eventMock);
		$eventMock->method('getToken')->willReturn($tokenMock);
		$tokenMock->method('getAccessToken')->willReturn('opaque-access-token');

		$clientMock = $this->getMockBuilder(OIDCClient::class)
			->disableOriginalConstructor()
			->addMethods(["getTokenType"])
			->getMock();
		$clientMock->method('getTokenType')->willReturn('opaque');
		$clientMapperMock = $this->createMock(OIDCClientMapper::class);
		$clientMapperMock->method('getClient')->willReturn($clientMock);

		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'appManager' => $iAppManagerMock,
				'config' => $configMock,
				'manager' => $iManagerMock,
				'tokenEventFactory' => $tokenEventMock,
				'oidcClientMapper' => $clientMapperMock,
			],
		);
		$result = $service->getOIDCToken('testUser');
		$this->assertEquals(null, $result);
	}

	public function getAccessTokenDataProvider(): array {
		return [
			'no token' => [
				'token' => null,
				'expired' => false,
				'authMethod' => '',
				'tokenRefreshFailed' => false,
				'expected' => null,
			],
			'has oauth token' => [
				'token' => 'test_token',
				'expired' => false,
				'authMethod' => SettingsService::AUTH_METHOD_OAUTH,
				'tokenRefreshFailed' => false,
				'expected' => 'test_token',
			],
			'has expired oauth token' => [
				'token' => 'test_token',
				'expired' => true,
				'authMethod' => SettingsService::AUTH_METHOD_OAUTH,
				'tokenRefreshFailed' => false,
				'expected' => 'new_token',
			],
			'has expired oauth token and refresh fails' => [
				'token' => 'test_token',
				'expired' => true,
				'authMethod' => SettingsService::AUTH_METHOD_OAUTH,
				'tokenRefreshFailed' => true,
				'expected' => null,
			],
			'has oidc token' => [
				'token' => 'test_token',
				'expired' => false,
				'authMethod' => SettingsService::AUTH_METHOD_OIDC,
				'tokenRefreshFailed' => false,
				'expected' => 'test_token',
			],
			'has expired oidc token' => [
				'token' => 'test_token',
				'expired' => true,
				'authMethod' => SettingsService::AUTH_METHOD_OIDC,
				'tokenRefreshFailed' => false,
				'expected' => 'new_token',
			],
		];
	}
	/**
	 * @dataProvider getAccessTokenDataProvider
	 * @param string|null $token
	 * @param bool $expired
	 * @param string $authMethod
	 * @param bool $tokenRefreshFailed
	 * @param string|null $expectedToken
	 *
	 * @return void
	 */
	public function testGetAccessToken(
		?string $token,
		bool $expired,
		string $authMethod,
		bool $tokenRefreshFailed,
		?string $expectedToken,
	): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getAppValue')
			->willReturnMap($this->getAppValues([
				'openproject_instance_url' => 'http://test.local',
				'authorization_method' => $authMethod,
				'openproject_client_id' => 'client-id',
				'openproject_client_secret' => 'client-secret',
			]));
		$configMock->method('getUserValue')
			->willReturnMap([
				['testUser', Application::APP_ID, 'token', '', $token],
				['testUser', Application::APP_ID, 'refresh_token', '', 'refresh-token'],
			]);
		$service = $this->getOpenProjectAPIServiceMock(
			['isAccessTokenExpired', 'getOIDCToken', 'requestOAuthAccessToken'],
			[
				'config' => $configMock,
			],
		);
		if ($token) {
			$service->expects($this->once())
			->method('isAccessTokenExpired')
			->with('testUser')
			->willReturn($expired);
		}

		if ($authMethod === SettingsService::AUTH_METHOD_OAUTH && $expired) {
			if ($tokenRefreshFailed) {
				$service->expects($this->once())
					->method('requestOAuthAccessToken')
					->with(
						'testUser',
						'http://test.local',
						[
							'client_id' => 'client-id',
							'client_secret' => 'client-secret',
							'grant_type' => 'refresh_token',
							'refresh_token' => 'refresh-token',
						],
					)
					->willReturn([
						'error' => 'some-error',
					]);
			} else {
				$service->expects($this->once())
					->method('requestOAuthAccessToken')
					->with(
						'testUser',
						'http://test.local',
						[
							'client_id' => 'client-id',
							'client_secret' => 'client-secret',
							'grant_type' => 'refresh_token',
							'refresh_token' => 'refresh-token',
						],
					)
					->willReturn([
						'access_token' => $expectedToken,
					]);
			}
		} else {
			$service->expects($this->never())->method('requestOAuthAccessToken');
		}
		if ($authMethod === SettingsService::AUTH_METHOD_OIDC && $expired) {
			$service->expects($this->once())
				->method('getOIDCToken')
				->with('testUser')
				->willReturn($expectedToken);
		} else {
			$service->expects($this->never())->method('getOIDCToken');
		}

		$result = $service->getAccessToken('testUser');
		$this->assertEquals($expectedToken, $result);
	}

	public function dataProviderForIsOIDCUser(): array {
		$backendMock = $this->getMockBuilder(OIDCBackend::class)->disableOriginalConstructor()->getMock();
		return [
			'has OIDCBackend class and OIDC user' => [
				'hasOIDCBackend' => true,
				'userBackend' => $backendMock,
				'SSOProviderType' => 'external',
				'expected' => true,
			],
			'has OIDCBackend class but not OIDC user' => [
				'hasOIDCBackend' => true,
				'userBackend' => new \stdClass(),
				'SSOProviderType' => 'external',
				'expected' => false,
			],
			'missing OIDCBackend class' => [
				'hasOIDCBackend' => false,
				'userBackend' => $backendMock,
				'SSOProviderType' => '',
				'expected' => false,
			],
			'configure with Nextcloud Hub' => [
				'hasOIDCBackend' => true,
				'userBackend' => new \stdClass(),
				'SSOProviderType' => OpenProjectAPIService::NEXTCLOUD_HUB_PROVIDER,
				'expected' => true,
			],
		];
	}

	/**
	 * @dataProvider dataProviderForIsOIDCUser
	 *
	 * @return void
	 */
	public function testIsOIDCUser($hasOIDCBackend, $userBackend, $SSOProviderType, $expected): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getAppValue')->willReturn($SSOProviderType);

		if ($SSOProviderType !== OpenProjectAPIService::NEXTCLOUD_HUB_PROVIDER) {
			$this->classExistsMock->expects($this->once())->willReturn($hasOIDCBackend);
		}

		$userSessionMock = $this->createMock(IUserSession::class);
		$userMock = $this->createMock(IUser::class);

		$userMock->method('getBackend')->willReturn($userBackend);
		$userSessionMock->method('getUser')->willReturn($userMock);

		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'userSession' => $userSessionMock,
				'config' => $configMock,
			],
		);

		$result = $service->isOIDCUser();
		$this->assertEquals($expected, $result);
	}

	/**
	 * @param string $version
	 *
	 * @return string
	 */
	public function getHigherVersionThanSupported(string $version): string {
		$versionArray = explode('.', $version);
		$versionArray[1] = (int)$versionArray[1] + 1;
		return implode('.', $versionArray);
	}

	/**
	 * Data provider for testIsUserOIDCAppSupported
	 */
	public function dataProviderForIsUserOIDCAppSupported(): array {
		$supportedVersion = OpenProjectAPIService::MIN_SUPPORTED_USER_OIDC_APP_VERSION;
		return [
			'has installed supported user_oidc apps and all classes exist' => [
				'appInstalledAndEnabled' => true,
				'classesExist' => true,
				'version' => $supportedVersion,
				'expected' => true,
			],
			'has installed user_oidc apps but one of the class does not exist' => [
				'appInstalledAndEnabled' => true,
				'classesExist' => false,
				'version' => $supportedVersion,
				'expected' => false,
			],
			'has user_oidc apps not enabled' => [
				'appInstalledAndEnabled' => false,
				'classesExist' => true,
				'version' => $supportedVersion,
				'expected' => false,
			],
			'has installed unsupported user_oidc apps version' => [
				'appInstalledAndEnabled' => true,
				'classesExist' => true,
				'version' => '6.1.2',
				'expected' => false,
			],
			'has installed user_oidc apps higher version and all classes exist' => [
				'appInstalledAndEnabled' => true,
				'classesExist' => true,
				'version' => $this->getHigherVersionThanSupported($supportedVersion),
				'expected' => true,
			],
			'has no user_oidc app' => [
				'appInstalledAndEnabled' => true,
				'classesExist' => true,
				'version' => '0',
				'expected' => false,
			],
		];
	}

	/**
	 * @dataProvider dataProviderForIsUserOIDCAppSupported
	 */
	public function testIsUserOIDCAppSupported($appInstalledAndEnabled, $classesExist, $version, $expected): void {
		$this->classExistsMock->expects($this->any())->willReturn($classesExist);

		$iAppManagerMock = $this->getMockBuilder(IAppManager::class)->getMock();
		$iAppManagerMock->method('getAppVersion')->with('user_oidc')->willReturn($version);

		$service = $this->getOpenProjectAPIServiceMock(
			['isUserOIDCAppInstalledAndEnabled'],
			[
				'appManager' => $iAppManagerMock,
			],
		);
		$service->method('isUserOIDCAppInstalledAndEnabled')->willReturn($appInstalledAndEnabled);
		$actualResult = $service->isUserOIDCAppSupported();
		$this->assertEquals($expected, $actualResult);
	}

	/**
	 * Data provider for testIsOIDCAppSupported
	 */
	public function dataProviderForIsOIDCAppSupported(): array {
		$supportedVersion = OpenProjectAPIService::MIN_SUPPORTED_OIDC_APP_VERSION;
		return [
			'supported app enabled' => [
				'appEnabled' => true,
				'version' => $supportedVersion,
				'expected' => true,
			],
			'higher app version enabled' => [
				'appEnabled' => true,
				'version' => $this->getHigherVersionThanSupported($supportedVersion),
				'expected' => true,
			],
			'supported app disabled' => [
				'appEnabled' => false,
				'version' => $supportedVersion,
				'expected' => false,
			],
			'unsupported app enabled' => [
				'appEnabled' => true,
				'version' => '1.3.0',
				'expected' => false,
			],
			'app not installed' => [
				'appEnabled' => true,
				'version' => '0',
				'expected' => false,
			],
		];
	}

	/**
	 * @dataProvider dataProviderForIsOIDCAppSupported
	 */
	public function testIsOIDCAppSupported($appEnabled, $version, $expected): void {
		$appManagerMock = $this->getMockBuilder(IAppManager::class)->getMock();
		$appManagerMock->method('getAppVersion')->with('oidc')->willReturn($version);

		$service = $this->getOpenProjectAPIServiceMock(
			['isOIDCAppEnabled'],
			[
				'appManager' => $appManagerMock,
			],
		);
		$service->method('isOIDCAppEnabled')->willReturn($appEnabled);
		$actualResult = $service->isOIDCAppSupported();
		$this->assertEquals($expected, $actualResult);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function dataProviderForRequestOAuthAccessToken(): array {
		return [
			'has expiry time' => [
				'createdAt' => time(),
				'expiresIn' => 7200,
			],
			'no expiry time' => [
				'createdAt' => null,
				'expiresIn' => null,
			],
		];
	}

	/**
	 * @dataProvider dataProviderForRequestOAuthAccessToken
	 *
	 * @param int|null $createdAt
	 * @param int|null $expiresIn
	 *
	 * @return void
	 */
	public function testRequestOAuthAccessToken(?int $createdAt, ?int $expiresIn): void {
		$responseBody = [
			'access_token' => 'testtoken',
			'refresh_token' => 'testrefreshtoken',
		];
		if ($createdAt !== null && $expiresIn !== null) {
			$responseBody['created_at'] = $createdAt;
			$responseBody['expires_in'] = $expiresIn;
		} else {
			$responseBody['created_at'] = time();
			$responseBody['expires_in'] = OpenProjectAPIService::DEFAULT_ACCESS_TOKEN_EXPIRATION;
		}
		$responseMock = $this->createMock(IResponse::class);
		$responseMock
			->expects($this->once())
			->method('getBody')
			->willReturn(json_encode($responseBody));
		$responseMock
			->expects($this->once())
			->method('getStatusCode')
			->willReturn(200);
		$clientMock = $this->createMock(IClient::class);
		$clientMock
			->expects($this->once())
			->method('post')
			->willReturn($responseMock);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($clientMock);
		$config = $this->createMock(IConfig::class);
		$config->expects($this->exactly(3))->method('setUserValue');
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('debug');

		$service = $this->getOpenProjectAPIServiceMock(
			['isOIDCAppEnabled'],
			[
				'config' => $config,
				'loggerInterface' => $logger,
				'clientService' => $clientService,
			],
		);
		$result = $service->requestOAuthAccessToken('testuser', 'http://op.local.test');
		$this->assertEquals($responseBody, $result);
	}

	/**
	 * Data provider for testGetAppsName
	 */
	public function getAppsNameDataProvider(): array {
		return [
			'enabled app' => [
				'appId' => 'groupfolders',
				'appInfo' => ['name' => 'Group folders'],
				'expected' => 'Group folders',
			],
			'disabled existing app' => [
				'appId' => 'oidc',
				'appInfo' => null,
				'expected' => Application::getDefaultAppName('oidc'),
			],
			'non-existing app' => [
				'appId' => 'nonexistent_app',
				'appInfo' => null,
				'expected' => 'nonexistent_app',
			],
			'app info missing name field' => [
				'appId' => 'user_oidc',
				'appInfo' => ['description' => 'An app without name'],
				'expected' => Application::getDefaultAppName('user_oidc'),
			],
		];
	}

	/**
	 * @dataProvider getAppsNameDataProvider
	 *
	 * @param string $appId
	 * @param array<string, mixed>|null $appInfo
	 * @param string $expected
	 *
	 * @return void
	 */
	public function testGetAppsName(string $appId, ?array $appInfo, string $expected): void {
		$appManagerMock = $this->getMockBuilder(IAppManager::class)->getMock();
		$appManagerMock
			->expects($this->once())
			->method('getAppInfo')
			->with($appId)
			->willReturn($appInfo);

		$service = $this->getOpenProjectAPIServiceMock(
			[],
			[
				'appManager' => $appManagerMock,
			]
		);

		$result = $service->getAppsName($appId);
		$this->assertSame($expected, $result);
	}

}
