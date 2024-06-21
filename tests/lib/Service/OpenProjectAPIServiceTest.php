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
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use OC\Authentication\Events\AppPasswordCreatedEvent;
use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\IToken;
use OC\Avatar\GuestAvatar;
use OC\Http\Client\Client;
use OC_Util;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Exception\OpenprojectErrorException;
use OCA\OpenProject\Exception\OpenprojectResponseException;
use OCA\TermsOfService\Db\Mapper\SignatoryMapper;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Group\ISubAdmin;
use OCP\Http\Client\IClientService;
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
use OCP\Log\ILogFactory;
use OCP\Security\IRemoteHostValidator;
use OCP\Security\ISecureRandom;
use PhpPact\Consumer\InteractionBuilder;
use PhpPact\Consumer\Model\ConsumerRequest;
use PhpPact\Consumer\Model\ProviderResponse;
use PhpPact\Standalone\MockService\MockServerEnvConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * overriding the class_exists method, so that the unit tests always pass,
 * no matter if the activity app is enabled or not
 */
function class_exists(string $className): bool {
	if ($className === '\OCA\GroupFolders\Folder\FolderManager') {
		return true;
	} else {
		return \class_exists($className);
	}
}

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
		$ocClient = null;
		$client = new GuzzleClient();
		$clientConfigMock = $this->getMockBuilder(IConfig::class)->getMock();

		if (version_compare(OC_Util::getVersionString(), '27') >= 0) {
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
			//changed from nextcloud 26
			$ocClient = new Client(
				$clientConfigMock,
				$certificateManager,
				$client,
				$this->createMock(IRemoteHostValidator::class),
				$this->createMock(LoggerInterface::class));
		} elseif (version_compare(OC_Util::getVersionString(), '26') >= 0) {
			$clientConfigMock
			->method('getSystemValueBool')
			->with('allow_local_remote_servers', false)
			->willReturn(true);

			//changed from nextcloud 26
			$ocClient = new Client(
				$clientConfigMock,
				$certificateManager,
				$client,
				$this->createMock(IRemoteHostValidator::class)
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
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url'],

				// for second request after invalid token reply
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url'],
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
			$avatarManagerMock,
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
			$this->defaultConfigMock,
			$clientService,
			$storageMock,
			$urlGeneratorMock,
			$this->createMock(ICacheFactory::class),
			$this->createMock(IUserManager::class),
			$this->createMock(IGroupManager::class),
			$this->createMock(IAppManager::class),
			$this->createMock(IProvider::class),
			$this->createMock(ISecureRandom::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(ISubAdmin::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(ILogFactory::class)
		);
	}

	/**
	 * @param array<string> $onlyMethods
	 * @param IRootFolder|null $rootMock
	 * @param ICacheFactory|null $cacheFactoryMock
	 * @param IUserManager|null $userManagerMock
	 * @param IGroupManager|null $groupManagerMock
	 * @param IAppManager|null $appManagerMock
	 * @param ISubAdmin|null $subAdminManagerMock
	 * @param ISecureRandom|null $iSecureRandomMock
	 * @param IConfig|null $configMock
	 * @param IProvider|null $tokenProviderMock
	 * @param IDBConnection|null $db
	 * @param IURLGenerator|null $iURLGenerator
	 * @return OpenProjectAPIService|MockObject
	 */
	private function getServiceMock(
		array $onlyMethods = ['request'],
		$rootMock = null,
		$cacheFactoryMock = null,
		$userManagerMock = null,
		$groupManagerMock = null,
		$appManagerMock = null,
		$subAdminManagerMock = null,
		$iSecureRandomMock = null,
		$configMock = null,
		$tokenProviderMock = null,
		$db = null,
		$iURLGenerator = null
	): OpenProjectAPIService {
		$onlyMethods[] = 'getBaseUrl';
		if ($rootMock === null) {
			$rootMock = $this->createMock(IRootFolder::class);
		}
		if ($cacheFactoryMock === null) {
			$cacheFactoryMock = $this->createMock(ICacheFactory::class);
		}
		if ($userManagerMock === null) {
			$userManagerMock = $this->createMock(IUserManager::class);
		}
		if ($groupManagerMock === null) {
			$groupManagerMock = $this->createMock(IGroupManager::class);
		}
		if ($appManagerMock === null) {
			$appManagerMock = $this->createMock(IAppManager::class);
		}
		if ($subAdminManagerMock === null) {
			$subAdminManagerMock = $this->getMockBuilder(ISubAdmin::class)->getMock();
		}
		if ($iSecureRandomMock === null) {
			$iSecureRandomMock = $this->createMock(ISecureRandom::class);
		}
		if ($configMock === null) {
			$configMock = $this->createMock(IConfig::class);
		}
		if ($tokenProviderMock === null) {
			$tokenProviderMock = $this->createMock(IProvider::class);
		}
		if ($db === null) {
			$db = $this->createMock(IDBConnection::class);
		}
		if ($iURLGenerator === null) {
			$iURLGenerator = $this->createMock(IURLGenerator::class);
		}
		$mock = $this->getMockBuilder(OpenProjectAPIService::class)
			->setConstructorArgs(
				[
					'integration_openproject',
					$this->createMock(IAvatarManager::class),
					$this->createMock(LoggerInterface::class),
					$this->createMock(IL10N::class),
					$configMock,
					$this->createMock(IClientService::class),
					$rootMock,
					$iURLGenerator,
					$cacheFactoryMock,
					$userManagerMock,
					$groupManagerMock,
					$appManagerMock,
					$tokenProviderMock,
					$iSecureRandomMock,
					$this->createMock(IEventDispatcher::class),
					$subAdminManagerMock,
					$db,
					$iURLGenerator
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
		$service = $this->getServiceMock();
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
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(['error' => 'some issue', 'statusCode' => 404 ]);
		$result = $service->searchWorkPackage('user', 'search query', 123);
		$this->assertSame(['error' => 'some issue', 'statusCode' => 404 ], $result);
	}

	/**
	 * @return void
	 * @throws \JsonException
	 */
	public function testGetNotificationsRequest() {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath($this->notificationsPath)
			->setQuery("pageSize=-1&filters=" . json_encode([[
				'readIAN' =>
					['operator' => '=', 'values' => ['f']]
			]], JSON_THROW_ON_ERROR))
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
		$this->assertSame('Client error: `GET http://localhost:7200/api/v3/not_existing` ' .
			'resulted in a `404 Not Found` response', $result['message']);
		$this->assertSame(404, $result['statusCode']);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectAvatar() {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath('/api/v3/users/openProjectUserWithAvatar/avatar')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_OK)
			->setHeaders(['Content-Type' => 'image/jpeg'])
			//setBody() expects iterable but we want to have raw data here and it seems to work fine
			->setBody('dataOfTheImage');

		$this->builder
			->uponReceiving('a request to get the avatar of a user that has an avatar')
			->with($consumerRequest)
			->willRespondWith($providerResponse);
		$service = $this->getOpenProjectAPIService(null, '1234567890', 'https://nc.my-server.org', 'NCuser');
		$result = $service->getOpenProjectAvatar(
			'openProjectUserWithAvatar',
			'Me',
			'NCuser'
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
			->setPath('/api/v3/users/openProjectUser/avatar')
			->setHeaders(["Authorization" => "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(Http::STATUS_NOT_FOUND);

		$this->builder
			->uponReceiving('a request to get the avatar of a user that does not have one')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->getOpenProjectAvatar(
			'openProjectUser',
			'Me',
			'testUser'
		);
		$this->assertArrayHasKey('avatar', $result);
		//make sure its an image, if something else is returned it will throw an exception
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
		$service = $this->getServiceMock(['request'], null, $cacheFactoryMock);
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
		$service = $this->getServiceMock(['request'], null, $cacheFactoryMock);
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
		$service = $this->getServiceMock(['request'], null, $cacheFactoryMock);
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
		$service = $this->getServiceMock(['request'], null, $cacheFactoryMock);
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
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url'],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_instance_url'],
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
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url']
			)->willReturnOnConsecutiveCalls($clientId, $clientSecret, $oauthInstanceUrl);

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
	 * @throws \JsonException
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
		$service = $this->getServiceMock(['request', 'getNode']);

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
		$service = $this->getServiceMock(['request', 'getNode']);
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
		$service = $this->getServiceMock(['request', 'getNode']);
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
		$service = $this->getServiceMock(['request', 'getNode']);
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
		$service = $this->getServiceMock(['request', 'getNode']);
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
		$service = $this->getServiceMock(['request', 'getNode']);

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
		$service = $this->getServiceMock(['request', 'getNode']);

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
			->withConsecutive(
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url'],
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
			$this->createMock(IAvatarManager::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
			$configMock,
			$clientService,
			$this->createMock(IRootFolder::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(ICacheFactory::class),
			$this->createMock(IUserManager::class),
			$this->createMock(IGroupManager::class),
			$this->createMock(IAppManager::class),
			$this->createMock(IProvider::class),
			$this->createMock(ISecureRandom::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(ISubAdmin::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(ILogFactory::class)

		);

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
			->withConsecutive(
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url'],
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
			$this->createMock(IAvatarManager::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
			$configMock,
			$clientService,
			$this->createMock(IRootFolder::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(ICacheFactory::class),
			$this->createMock(IUserManager::class),
			$this->createMock(IGroupManager::class),
			$this->createMock(IAppManager::class),
			$this->createMock(IProvider::class),
			$this->createMock(ISecureRandom::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(ISubAdmin::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(ILogFactory::class)
		);

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
			->method('getFoldersForGroup')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($getFoldersForGroupResponse);

		$folderManagerMock
			->method('canManageACL')
			->willReturn($canManageACL);

		return $folderManagerMock;
	}

	public function testIsProjectFoldersSetupComplete(): void {
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

		$service = $this->getServiceMock(
			['getGroupFolderManager'],
			null,
			null,
			$userManagerMock,
			$groupManagerMock,
			$appManagerMock,
			$subAdminManagerMock
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

		$service = $this->getServiceMock(
			['getGroupFolderManager'],
			null,
			null,
			$userManagerMock,
			$groupManagerMock,
			$appManagerMock,
			$subAdminManagerMock
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
			->method('generateToken')
			->with($token, Application::OPEN_PROJECT_ENTITIES_NAME, Application::OPEN_PROJECT_ENTITIES_NAME, null, Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($itokenMock);
		$eventDispatcherMock = $this->getMockBuilder(IEventDispatcher::class)->getMock();
		$eventDispatcherMock
			->method('dispatchTyped')
			->with($this->createMock(AppPasswordCreatedEvent::class));
		$service = $this->getServiceMock([], null, null, $userManagerMock, null, null, null, $iSecureRandomMock);
		$result = $service->generateAppPasswordTokenForUser();
		$this->assertSame($token, $result);
	}

	public function testIsSystemReadyForProjectFolderSetUp(): void {
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
		$service = $this->getServiceMock(['getGroupFolderManager'],
			null,
			null,
			$userManagerMock,
			$groupManagerMock,
			$appManagerMock);
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
			[true, true, false, false,'The "Group folders" app is not installed'],
			[true, false, false, false,'The user "'. Application::OPEN_PROJECT_ENTITIES_NAME .'" already exists'],
			[false, true, false, false,'The group "'. Application::OPEN_PROJECT_ENTITIES_NAME .'" already exists'],
			[false, false, false, false,'The "Group folders" app is not installed'],
			[false, false, true, true,'The group folder name "'. Application::OPEN_PROJECT_ENTITIES_NAME .'" already exists'],
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
		$service = $this->getServiceMock(['getGroupFolderManager'],
			null,
			null,
			$userManagerMock,
			$groupManagerMock,
			$appManagerMock);
		$folderManagerMock = $this->getFolderManagerMock();
		$service->method('getGroupFolderManager')
			->willReturn($folderManagerMock);
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage($exception);
		$service->isSystemReadyForProjectFolderSetUp();
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
		$service = $this->getServiceMock([],
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$tokenProviderMock);
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
		$service = $this->getServiceMock([],
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$tokenProviderMock);
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
		$service = $this->getServiceMock([],
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$tokenProviderMock);
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
		$service = $this->getServiceMock(['hasAppPassword'],
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$tokenProviderMock)
		;
		$service->method('hasAppPassword')->willReturn(true);
		$tokenProviderMock->expects($this->exactly(2))
			->method('invalidateTokenById')
			->withConsecutive([Application::OPEN_PROJECT_ENTITIES_NAME, 4], [Application::OPEN_PROJECT_ENTITIES_NAME, 5]);
		$service->deleteAppPassword();
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
		$values = $this->singleFileInformation;
		$result = $service->linkWorkPackageToFile(
			$values,
			'testUser'
		);

		$this->assertSame([1337], $result);
	}


	public function testLinkWorkPackageToMultipleFileRequestPact(): void {
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

		$service = $this->getOpenProjectAPIService($storageMock);
		$values = $this->multipleFileInformation;
		$result = $service->linkWorkPackageToFile(
			$values,
			'testUser'
		);
		$this->assertSame([2456, 2457, 2458], $result);
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

		$values = $this->singleFileInformation;
		$service->linkWorkPackageToFile(
			$values,
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
		$values = $this->singleFileInformation;
		$service->linkWorkPackageToFile(
			$values,
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
		$values = $this->singleFileInformation;
		$service->linkWorkPackageToFile(
			$values,
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
		$values = [
			"workpackageId" => 999999,
			"fileinfo" => [
				[
					"id" => 5503,
					'name' => 'logo.png'
				]
			]
		];
		$service->linkWorkPackageToFile(
			$values,
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
				'openproject_client_id' => '',
				'openproject_client_secret' => '',
				'openproject_instance_url' => '',
				'expected' => false,
			],
			[
				'openproject_client_id' => 'clientID',
				'openproject_client_secret' => '',
				'openproject_instance_url' => 'https://openproject',
				'expected' => false,
			],
			[
				'openproject_client_id' => 'clientID',
				'openproject_client_secret' => 'clientSecret',
				'openproject_instance_url' => '',
				'expected' => false,
			],
			[
				'openproject_client_id' => 'clientID',
				'openproject_client_secret' => 'clientSecret',
				'openproject_instance_url' => 'https://',
				'expected' => false,
			],
			[
				'openproject_client_id' => 'clientID',
				'openproject_client_secret' => 'clientSecret',
				'openproject_instance_url' => 'openproject.com',
				'expected' => false,
			],
			[
				'openproject_client_id' => 'clientID',
				'openproject_client_secret' => 'clientSecret',
				'openproject_instance_url' => 'https://openproject',
				'expected' => true,
			],
			[
				'openproject_client_id' => 'clientID',
				'openproject_client_secret' => 'clientSecret',
				'openproject_instance_url' => 'https://openproject.com/',
				'expected' => true,
			],
			[
				'openproject_client_id' => 'clientID',
				'openproject_client_secret' => 'clientSecret',
				'openproject_instance_url' => 'https://openproject.com',
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
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url'],
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

	/**
	 * @return void
	 */
	public function testGetAvailableOpenProjectProjectsPact(): void {
		$expectedResult = [
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
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath($this->getProjectsPath)
			->setHeaders(["Authorization" => "Bearer 1234567890"]);
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
		$service = $this->getOpenProjectAPIService($storageMock);
		$result = $service->getAvailableOpenProjectProjects('testUser');
		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @dataProvider malformedResponsesDataProvider
	 * @param array<mixed> $response
	 * @return void
	 */
	public function testGetAvailableOpenProjectProjectsMalformedResponse(array $response): void {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn($response);
		$this->expectException(OpenprojectResponseException::class);
		$service->getAvailableOpenProjectProjects('testUser');
	}

	/**
	 * @return void
	 */
	public function testGetAvailableOpenProjectProjectsErrorResponse(): void {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(['error' => 'something went wrong', 'statusCode' => 500]);
		$this->expectException(OpenprojectErrorException::class);
		$service->getAvailableOpenProjectProjects('testUser');
	}

	/**
	 * @return void
	 */
	public function testWorkpackagesFormValidationPact(): void {
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
		$service = $this->getOpenProjectAPIService($storageMock);
		$result = $service->getOpenProjectWorkPackageForm('testUser', '6', $this->validWorkPackageFormValidationBody);
		$this->assertSame($this->validWorkPackageFormValidationResponse['_embedded'], $result);
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
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn($response);
		$this->expectException(OpenprojectResponseException::class);
		$service->getOpenProjectWorkPackageForm('testUser', "6", $this->validWorkPackageFormValidationBody);
	}

	/**
	 * @return void
	 */
	public function testGetOpenProjectWorkPackageFormErrorResponse(): void {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(['error' => 'something went wrong', 'statusCode' => 500]);
		$this->expectException(OpenprojectErrorException::class);
		$service->getOpenProjectWorkPackageForm('testUser', "6", $this->validWorkPackageFormValidationBody);
	}

	/**
	 * @return void
	 */
	public function testGetAvailableAssigneesOfAProjectPact(): void {
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
		$service = $this->getOpenProjectAPIService($storageMock);
		$result = $service->getAvailableAssigneesOfAProject('testUser', '6');
		$this->assertSame($this->validGetProjectAssigneesResponse['_embedded']['elements'], $result);
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
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn($response);
		$this->expectException(OpenprojectResponseException::class);
		$service->getAvailableAssigneesOfAProject('testUser', "6");
	}

	/**
	 * @return void
	 */
	public function testGetAvailableAssigneesOfAProjectErrorResponse(): void {
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn(['error' => 'something went wrong', 'statusCode' => 500]);
		$this->expectException(OpenprojectErrorException::class);
		$service->getAvailableAssigneesOfAProject('testUser', "6");
	}

	/**
	 * @return void
	 */
	public function testCreateWorkpackagePact(): void {
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
		$service = $this->getOpenProjectAPIService($storageMock);
		$result = $service->createWorkPackage('testUser', $this->validCreateWorkpackageBody);
		$this->assertSame($this->createWorkpackageResponse, $result);
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
		$service = $this->getServiceMock();
		$service->method('request')
			->willReturn($response);
		$this->expectException(OpenprojectResponseException::class);
		$service->createWorkPackage('testUser', $this->validCreateWorkpackageBody);
	}

	/**
	 * @return void
	 */
	public function testCreateWorkpackagesErrorResponse(): void {
		$service = $this->getServiceMock();
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
		$service = $this->getServiceMock(
			[],
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$configMock
		);
		$result = $service->getPasswordLength();
		$this->assertEquals($expectedPasswordLength, $result);
	}

	/**
	 * @return array<mixed>
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
		$service = $this->getServiceMock(
			['isTermsOfServiceAppEnabled', 'getAllTermsOfServiceAvailable', 'getAllTermsOfServiceSignedByUserOpenProject'],
			null,
			null,
			$userManagerMock
		);
		$service->method('isTermsOfServiceAppEnabled')->willReturn(true);
		$service->method('getAllTermsOfServiceAvailable')->willReturn($availableTermsOfServices);
		$service->method('getAllTermsOfServiceSignedByUserOpenProject')->with($signatoryMapperMock)->willReturn($alreadySignedTemrsOfServices);
		$result = $service->isAllTermsOfServiceSignedForUserOpenProject($signatoryMapperMock);
		$this->assertSame($expectedResult, $result);
	}

	public function testGetSubline(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getUserValue')->with('testUser', Application::APP_ID, 'token')
			->willReturn("access-token");
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$service = $this->getServiceMock(
			['searchWorkPackage'],
			null,
			null,
			$userManagerMock,
			null,
			null,
			null,
			null,
			$configMock
		);
		$resultTitle = $service->getSubline($this->wpInformationResponse);
		$this->assertSame("#123 [In specification] Scrum project", $resultTitle);
	}

	public function testGetMainText() : void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getUserValue')->with('testUser', Application::APP_ID, 'token')
			->willReturn("access-token");
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$service = $this->getServiceMock(
			['searchWorkPackage'],
			null,
			null,
			$userManagerMock,
			null,
			null,
			null,
			null,
			$configMock
		);
		$resultMainText = $service->getMainText($this->wpInformationResponse);
		$this->assertSame("USER STORY: New login screen", $resultMainText);
	}

	public function testGetWorkPackageInfoForExistentWorkPackage(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getUserValue')->with('testUser', Application::APP_ID, 'token')
			->willReturn("access-token");
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$iULGeneratorMock = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$service = $this->getServiceMock(
			['searchWorkPackage'],
			null,
			null,
			$userManagerMock,
			null,
			null,
			null,
			null,
			$configMock,
			null,
			null,
			$iULGeneratorMock
		);
		$imageURL = 'http://nextcloud/server/index.php/apps/integration_openproject/avatar?userId=3&userName=OpenProject Admin';
		$iULGeneratorMock->method('getAbsoluteURL')->willReturn($imageURL);
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
		$configMock->method('getUserValue')->with('testUser', Application::APP_ID, 'token')
			->willReturn("access-token");
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$service = $this->getServiceMock(
			['searchWorkPackage'],
			null,
			null,
			$userManagerMock,
			null,
			null,
			null,
			null,
			$configMock
		);
		$wpInformationResponse = ["error" => []];
		$service->method('searchWorkPackage')->with('testUser', null, null, false, 123)->willReturn($wpInformationResponse);
		$resultGetWorkPackageInfo = $service->getWorkPackageInfo('testUser', 123);
		$this->assertNull($resultGetWorkPackageInfo);
	}

	public function testGetWorkPackageInfoForNoUserAccessToken(): void {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getUserValue')->with('testUser', Application::APP_ID, 'token')
			->willReturn(null);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)
			->getMock();
		$service = $this->getServiceMock(
			['searchWorkPackage'],
			null,
			null,
			$userManagerMock,
			null,
			null,
			null,
			null,
			$configMock
		);
		$resultGetWorkPackageInfo = $service->getWorkPackageInfo('testUser', 123);
		$this->assertNull($resultGetWorkPackageInfo);
	}
}
