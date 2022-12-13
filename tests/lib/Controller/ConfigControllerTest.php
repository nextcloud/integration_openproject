<?php

namespace OCA\OpenProject\Controller;

use GuzzleHttp\Exception\ConnectException;
use OCA\OAuth2\Controller\SettingsController;
use OCA\OpenProject\Exception\OpenprojectErrorException;
use OCA\OpenProject\Service\OauthService;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUser;
use OCP\AppFramework\Http;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\RequestInterface;

class ConfigControllerTest extends TestCase {

	/**
	 * @var IL10N
	 */
	private $l;

	/**
	 * @var IUser|bool
	 */
	private $user1 = false;

	protected function tearDown(): void {
		if ($this->user1 instanceof IUser) {
			$this->user1->delete();
		}
	}

	/**
	 * @param string $codeVerifier The string that should be used as code_verifier
	 * @param string $clientSecret The string that should be used as openproject_client_secret
	 * @param string $startingPage JSON encoded string that defines the start of the OAuth journey
	 * @return IConfig|MockObject
	 */
	private function getConfigMock(
		$codeVerifier, $clientSecret, $startingPage = '{ page: "files" }'
	) {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url'],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
			)->willReturnOnConsecutiveCalls(
				'clientID', $clientSecret, 'http://openproject.org', 'clientID', 'clientSecret',
			);
		$configMock
			->method('getUserValue')
			->withConsecutive(
				['testUser', 'integration_openproject', 'oauth_state'],
				['testUser', 'integration_openproject', 'code_verifier'],
				['testUser', 'integration_openproject', 'oauth_journey_starting_page'],
				['testUser', 'integration_openproject', 'refresh_token'],
			)
			->willReturnOnConsecutiveCalls(
				'randomString',
				$codeVerifier,
				$startingPage,
				'oAuthRefreshToken',
			);
		return $configMock;
	}

	/**
	 * @return void
	 * @before
	 */
	public function setUpMocks(): void {
		$this->l = $this->createMock(IL10N::class);
		$this->l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($string, $args) {
				return vsprintf($string, $args);
			});
	}

	public function testOauthRedirectSuccess():void {
		$configMock = $this->getConfigMock(
			str_repeat("A", 128), str_repeat("S", 50));
		$configMock
			->method('setUserValue')
			->withConsecutive(
				['testUser', 'integration_openproject', 'token', 'oAuthAccessToken'],
				['testUser', 'integration_openproject', 'refresh_token', 'oAuthRefreshToken'],
				['testUser', 'integration_openproject', 'user_id', 1],
				['testUser', 'integration_openproject', 'user_name', 'Tripathi Himal'],
				['testUser', 'integration_openproject', 'oauth_connection_result', 'success'],
			);
		$urlGeneratorMock = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()
			->getMock();
		$urlGeneratorMock
			->method('linkToRoute')
			->with('files.view.index')
			->willReturn('https://nc.np/apps/files/');
		$apiServiceMock = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$apiServiceMock
			->method('request')
			->with(
				'testUser',
				'users/me'
			)
			->willReturn(['lastName' => 'Himal', 'firstName' => 'Tripathi', 'id' => 1]);

		$apiServiceMock
			->method('requestOAuthAccessToken')
			->willReturn(['access_token' => 'oAuthAccessToken', 'refresh_token' => 'oAuthRefreshToken']);

		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$urlGeneratorMock,
			$this->createMock(IUserManager::class),
			$this->l,
			$apiServiceMock,
			$this->createMock(LoggerInterface::class),
			$this->createMock(OauthService::class),
			$this->createMock(SettingsController::class), 'testUser'
		);
		$result = $configController->oauthRedirect('code', 'randomString');
		$this->assertSame('https://nc.np/apps/files/', $result->getRedirectURL());
	}

	/**
	 * @return array<mixed>
	 */
	public function redirectUrlDataProvider() {
		return [
			[
				'{ "page": "dashboard" }',
				['dashboard.dashboard.index'],
				'https://nc.np/apps/dashboard'
			],
			[
				'{ "page": "settings" }',
				['settings.PersonalSettings.index', ['section' => 'openproject']],
				'https://nc.np/settings/user/openproject'
			],
			[
				'{ "page": "files", "file": {"dir": "/my/data", "name": "secret-data.txt"} }',
				['files.view.index', ['dir' => '/my/data', 'scrollto' => 'secret-data.txt']],
				'https://nc.np/apps/files/?dir=/my/data&scrollto=secret-data.txt'
			],
			[
				'{ "page": "invalid" }',
				['files.view.index'],
				'https://nc.np/apps/files'
			],
		];
	}
	/**
	 * @dataProvider redirectUrlDataProvider
	 * @param string $startingPage
	 * @param array<mixed> $linkToRouteArguments
	 * @param string $redirectUrl
	 * @return void
	 */
	public function testOauthRedirectCorrectRedirectUrl(
		string $startingPage, array $linkToRouteArguments, string $redirectUrl
	):void {
		$configMock = $this->getConfigMock(
			str_repeat("A", 128), str_repeat("S", 50),
			$startingPage
		);
		$urlGeneratorMock = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()
			->getMock();
		$urlGeneratorMock
			->method('linkToRoute')
			->with(...array_values($linkToRouteArguments))
			->willReturn($redirectUrl);
		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$urlGeneratorMock,
			$this->createMock(IUserManager::class),
			$this->l,
			$this->createMock(OpenProjectAPIService::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(OauthService::class),
			$this->createMock(SettingsController::class),
			'testUser'
		);
		$result = $configController->oauthRedirect('code', 'randomString');
		$this->assertSame($redirectUrl, $result->getRedirectURL());
	}

	/**
	 * @return void
	 */
	public function testOauthRedirectWrongState() {
		$configMock = $this->getConfigMock(
			str_repeat("A", 128), str_repeat("S", 50)
		);
		$configMock
			->expects($this->exactly(2))
			->method('setUserValue')
			->withConsecutive(
				['testUser', 'integration_openproject', 'oauth_connection_result', 'error'],
				[
					'testUser', 'integration_openproject', 'oauth_connection_error_message',
					'Error during OAuth exchanges'
				],
			);
		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$this->createMock(IURLGenerator::class),
			$this->createMock(IUserManager::class),
			$this->l,
			$this->createMock(OpenProjectAPIService::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(OauthService::class),
			$this->createMock(SettingsController::class),
			'testUser'
		);
		$configController->oauthRedirect('code', 'stateNotSameAsSaved');
	}

	/**
	 * @return array<mixed>
	 */
	public function codeVerifierDataProvider() {
		return [
			[null, false],
			['', false],
			[false, false],
			[str_repeat('A', 42), false], // too short
			[str_repeat('A', 43), true], // min length
			[str_repeat('A', 128), true], // max length
			[str_repeat('A', 129), false], // too long
			[str_repeat('A', 127) . '*', false], //invalid char
		];
	}

	/**
	 * @dataProvider codeVerifierDataProvider
	 * @param string $codeVerifier
	 * @param bool $valid
	 * @return void
	 */
	public function testOauthRedirectCodeVerifier($codeVerifier, $valid) {
		$loggerMock = $this->createMock(LoggerInterface::class);
		$configMock = $this->getConfigMock($codeVerifier, str_repeat("S", 50));
		if ($valid) {
			$loggerMock->expects($this->never())
				->method('error');
			// even the secret is valid, we get an error because the token request is not mocked
			$configMock->expects($this->exactly(2))
				->method('setUserValue')
				->withConsecutive(
					['testUser', 'integration_openproject', 'oauth_connection_result', 'error'],
					[
						'testUser', 'integration_openproject', 'oauth_connection_error_message',
						'Error getting OAuth access token'
					],
				);
		} else {
			$loggerMock->expects($this->once())
				->method('error');
			$configMock->expects($this->exactly(2))
				->method('setUserValue')
				->withConsecutive(
					['testUser', 'integration_openproject', 'oauth_connection_result', 'error'],
					[
						'testUser', 'integration_openproject', 'oauth_connection_error_message',
						'Error during OAuth exchanges'
					],
				);
		}
		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$this->createMock(IURLGenerator::class),
			$this->createMock(IUserManager::class),
			$this->l,
			$this->createMock(OpenProjectAPIService::class),
			$loggerMock,
			$this->createMock(OauthService::class),
			$this->createMock(SettingsController::class),
			'testUser'
		);
		$configController->oauthRedirect('code', 'randomString');
	}

	/**
	 * @return array<mixed>
	 */
	public function secretDataProvider() {
		return [
			[null, false],
			['', false],
			[false, false],
			[str_repeat('A', 127), true],
			['DRZ5hRGoT6Sm01n3at2Atrbj3sz8hFDHlGJkpzYFT4w', true],
			[str_repeat('A', 9), false] // too short
		];
	}

	/**
	 * @dataProvider secretDataProvider
	 * @param string $clientSecret
	 * @param string $valid
	 * @return void
	 */
	public function testOauthRedirectSecret($clientSecret, $valid) {
		$loggerMock = $this->createMock(LoggerInterface::class);
		$configMock = $this->getConfigMock(
			str_repeat("A", 128), $clientSecret
		);
		if ($valid) {
			$loggerMock->expects($this->never())
				->method('error');
			// even the secret is valid, we get an error because the token request is not mocked
			$configMock->expects($this->exactly(2))
				->method('setUserValue')
				->withConsecutive(
					['testUser', 'integration_openproject', 'oauth_connection_result', 'error'],
					[
						'testUser', 'integration_openproject', 'oauth_connection_error_message',
						'Error getting OAuth access token'
					],
				);
		} else {
			$loggerMock->expects($this->once())
				->method('error');
			$configMock->expects($this->exactly(2))
				->method('setUserValue')
				->withConsecutive(
					['testUser', 'integration_openproject', 'oauth_connection_result', 'error'],
					[
						'testUser', 'integration_openproject', 'oauth_connection_error_message',
						'Error during OAuth exchanges'
					],
				);
		}


		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$this->createMock(IURLGenerator::class),
			$this->createMock(IUserManager::class),
			$this->l,
			$this->createMock(OpenProjectAPIService::class),
			$loggerMock,
			$this->createMock(OauthService::class),
			$this->createMock(SettingsController::class),
			'testUser'
		);
		$configController->oauthRedirect('code', 'randomString');
	}

	/**
	 * @return array<mixed>
	 */
	public function badOAuthResponseDataProvider() {
		return [
			[
				['error' => 'something went wrong'],
				'Error getting OAuth access token. something went wrong'
			],
			[
				[],
				'Error getting OAuth access token'
			],
			[   // access token given but no refresh token
				['access_token' => '123'],
				'Error getting OAuth refresh token'
			],
			[   // access token & error given but no refresh token
				['access_token' => '123', 'error' => 'issue'],
				'Error getting OAuth refresh token. issue'
			],
			[   //refresh token given but no access token
				['refresh_token' => '123'],
				'Error getting OAuth access token'
			],
			[   //refresh token & error given but no access token
				['refresh_token' => '123', 'error' => 'issue'],
				'Error getting OAuth access token. issue'
			]
		];
	}

	/**
	 * @param array<string> $oauthResponse
	 * @param string $expectedErrorMessage
	 * @dataProvider badOAuthResponseDataProvider
	 *@return void
	 */
	public function testOauthNoAccessTokenInResponse($oauthResponse, $expectedErrorMessage) {
		$apiServiceMock = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$apiServiceMock
			->method('requestOAuthAccessToken')
			->willReturn($oauthResponse);
		$configMock = $this->getConfigMock(
			str_repeat("A", 128), str_repeat("S", 50)
		);
		$configMock->expects($this->exactly(2))
			->method('setUserValue')
			->withConsecutive(
				['testUser', 'integration_openproject', 'oauth_connection_result', 'error'],
				['testUser', 'integration_openproject', 'oauth_connection_error_message', $expectedErrorMessage],
			);
		/**
		 * @var ConfigController
		 */
		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$this->createMock(IURLGenerator::class),
			$this->createMock(IUserManager::class),
			$this->l,
			$apiServiceMock,
			$this->createMock(LoggerInterface::class),
			$this->createMock(OauthService::class),
			$this->createMock(SettingsController::class),
			'testUser'
		);
		$configController->oauthRedirect('code', 'randomString');
	}

	/**
	 * @return array<mixed>
	 */
	public function setAdminConfigStatusDataProvider() {
		return [
			[
				[
					'openproject_client_id' => '$client_id',
					'openproject_client_secret' => '$client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				true
			],
			[
				[
					'openproject_client_id' => '',
					'openproject_client_secret' => '$client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				], false
			],
		];
	}

	/**
	 * @param array<string> $credsToUpdate
	 * @param bool $adminConfigStatus
	 *
	 * @return void
	 * @dataProvider setAdminConfigStatusDataProvider
	 */
	public function testSetAdminConfigForDifferentAdminConfigStatus($credsToUpdate, $adminConfigStatus) {
		$userManager = \OC::$server->getUserManager();

		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->expects($this->exactly(3))
			->method('setAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_client_id', $credsToUpdate['openproject_client_id']],
				['integration_openproject', 'openproject_client_secret', $credsToUpdate['openproject_client_secret']],
				['integration_openproject', 'openproject_instance_url', $credsToUpdate['openproject_instance_url']]
			);
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url', ''],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'nc_oauth_client_id', ''],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url']
			)
			->willReturnOnConsecutiveCalls(
				'http://localhost:3000',
				'',
				'',
				'123',
				'',
				$credsToUpdate['openproject_client_id'],
				$credsToUpdate['openproject_client_secret'],
				$credsToUpdate['openproject_instance_url']
			);
		$apiService = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$this->createMock(IURLGenerator::class),
			$userManager,
			$this->l,
			$apiService,
			$this->createMock(LoggerInterface::class),
			$this->createMock(OauthService::class),
			$this->createMock(SettingsController::class),
			'test101'
		);

		$result = $configController->setAdminConfig($credsToUpdate);

		$this->assertSame(
			[
				'status' => $adminConfigStatus,
				'oPOAuthTokenRevokeStatus' => '',
			],
			$result->getData()
		);
	}


	/**
	 * @return array<mixed>
	 */
	public function setAdminConfigClearUserDataChangeNCOauthClientDataProvider() {
		return [
			[ // everything changes so delete user values and change the oAuth Client
				[
					'openproject_client_id' => 'old-openproject_client_id',
					'openproject_client_secret' => 'old-openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				true,
				'change'
			],
			[ // only client id changes so delete user values but don't change the oAuth Client
				[
					'openproject_client_id' => 'old-openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				[
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				true,
				false
			],
			[ // only client secret changes so delete user values but don't change the oAuth Client
				[
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'old-openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				[
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				true,
				false
			],
			[ //only the openproject_instance_url changes so don't delete the user values but change the oAuth Client
				[
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				false,
				'change'
			],
			[ //everything cleared
				[
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'openproject_client_id' => null,
					'openproject_client_secret' => null,
					'openproject_instance_url' => null,
				],
				true,
				'delete'
			],
			[ //everything cleared with empty strings
				[
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'openproject_client_id' => '',
					'openproject_client_secret' => '',
					'openproject_instance_url' => '',
				],
				true,
				'delete'
			],
		];
	}

	/**
	 * @param array<string> $oldCreds
	 * @param array<string> $credsToUpdate
	 * @param bool $deleteUserValues
	 * @param bool|string $updateNCOAuthClient false => don't touch the client, 'change' => update it, 'delete' => remove it
	 * @return void
	 * @dataProvider setAdminConfigClearUserDataChangeNCOauthClientDataProvider
	 */
	public function testSetAdminConfigClearUserDataChangeNCOauthClient(
		$oldCreds, $credsToUpdate, $deleteUserValues, $updateNCOAuthClient
	) {
		$userManager = $this->checkForUsersCountBeforeTest();
		$this->user1 = $userManager->createUser('test101', 'test101');

		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$oauthServiceMock = $this->createMock(OauthService::class);
		$oauthSettingsControllerMock = $this->getMockBuilder(SettingsController::class)
			->disableOriginalConstructor()
			->getMock();
		if ($updateNCOAuthClient) {
			$configMock
				->method('getAppValue')
				->withConsecutive(
					['integration_openproject', 'openproject_instance_url', ''],
					['integration_openproject', 'openproject_client_id'],
					['integration_openproject', 'openproject_client_secret'],
					['integration_openproject', 'nc_oauth_client_id', ''],
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
					['integration_openproject', 'openproject_client_id'],
					['integration_openproject', 'openproject_client_secret'],
					['integration_openproject', 'openproject_instance_url']
				)
				->willReturnOnConsecutiveCalls(
					$oldCreds['openproject_instance_url'],
					$oldCreds['openproject_client_id'],
					$oldCreds['openproject_client_secret'],
					'123',
					'',
					$credsToUpdate['openproject_client_id'],
					$credsToUpdate['openproject_client_secret'],
					$credsToUpdate['openproject_instance_url']
				);
			if ($updateNCOAuthClient === 'change') {
				$oauthServiceMock
					->expects($this->once())
					->method('setClientRedirectUri')
					->with(123, $credsToUpdate['openproject_instance_url']);
				$oauthSettingsControllerMock
					->expects($this->never())
					->method('deleteClient');
			} else { // delete the client
				$oauthServiceMock
					->expects($this->never())
					->method('setClientRedirectUri');
				$oauthSettingsControllerMock
					->expects($this->once())
					->method('deleteClient')
					->with(123);
			}
		} else {
			$configMock
				->method('getAppValue')
				->withConsecutive(
					['integration_openproject', 'openproject_instance_url', ''],
					['integration_openproject', 'openproject_client_id'],
					['integration_openproject', 'openproject_client_secret'],
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
					['integration_openproject', 'openproject_client_id'],
					['integration_openproject', 'openproject_client_secret'],
					['integration_openproject', 'openproject_instance_url']
				)
				->willReturnOnConsecutiveCalls(
					$oldCreds['openproject_instance_url'],
					$oldCreds['openproject_client_id'],
					$oldCreds['openproject_client_secret'],
					'',
					$credsToUpdate['openproject_client_id'],
					$credsToUpdate['openproject_client_secret'],
					$credsToUpdate['openproject_instance_url']
				);
			$oauthServiceMock->expects($this->never())->method('setClientRedirectUri');
		}

		if ($deleteUserValues === true) {
			$configMock
				->expects($this->exactly(10)) // 5 times for each user
				->method('deleteUserValue')
				->withConsecutive(
					['admin', 'integration_openproject', 'token'],
					['admin', 'integration_openproject', 'login'],
					['admin', 'integration_openproject', 'user_id'],
					['admin', 'integration_openproject', 'user_name'],
					['admin', 'integration_openproject', 'refresh_token'],
					[$this->user1->getUID(), 'integration_openproject', 'token'],
					[$this->user1->getUID(), 'integration_openproject', 'login'],
					[$this->user1->getUID(), 'integration_openproject', 'user_id'],
					[$this->user1->getUID(), 'integration_openproject', 'user_name'],
					[$this->user1->getUID(), 'integration_openproject', 'refresh_token'],
				);
		} else {
			$configMock
				->expects($this->never())
				->method('deleteUserValue');
		}

		$apiService = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$this->createMock(IURLGenerator::class),
			$userManager,
			$this->l,
			$apiService,
			$this->createMock(LoggerInterface::class),
			$oauthServiceMock,
			$oauthSettingsControllerMock,
			'test101'
		);

		$configController->setAdminConfig($credsToUpdate);
	}
	/**
	 * @return void
	 */
	public function testSetAdminConfigNotAllowedConfigValues() {
		$apiService = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$userManager = \OC::$server->getUserManager();
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$oauthServiceMock = $this->createMock(OauthService::class);

		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$this->createMock(IURLGenerator::class),
			$userManager,
			$this->l,
			$apiService,
			$this->createMock(LoggerInterface::class),
			$oauthServiceMock,
			$this->createMock(SettingsController::class),
			'test101'
		);

		$response = $configController->setAdminConfig([
			'client_id_top' => 'old-openproject_client_id',
		]);

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertEquals('Invalid key', $response->getData()['error']);
	}

	/**
	 * Runs a check for the users count in the SUT database
	 * Throws an assertion error if the users count is not "1"
	 *
	 * @param int $expectedCount
	 *
	 * @return IUserManager UserManager instance for further user management
	 * @throws \Exception
	 */
	public function checkForUsersCountBeforeTest($expectedCount = 1): IUserManager {
		$actualCount = 1;
		$userManager = \OC::$server->getUserManager();
		$function = function (IUser $user) use (&$count) {
			$count++;
			return null;
		};
		$userManager->callForAllUsers($function);
		$this->assertSame(
			$actualCount, $expectedCount,
			'Expected to have only 1 user in the dB before this test'
		);
		return $userManager;
	}

	/**
	 * @return array<mixed>
	 */
	public function oPOAuthTokenRevokeDataProvider() {
		return [
			[
				[
					'openproject_client_id' => null,
					'openproject_client_secret' => null,
					'openproject_instance_url' => null,
					'default_enable_navigation' => false,
					'default_enable_unified_search' => false,
				],
				false,
				'reset'
			],
			[
				[
					'openproject_client_id' => 'client_id_changed',
					'openproject_client_secret' => 'client_secret_changed',
					'openproject_instance_url' => 'http://localhost:3000',
					'default_enable_navigation' => true,
					'default_enable_unified_search' => true,
				],
				true,
				'change'
			]
		];
	}

	/**
	 * @param array<mixed> $newConfig
	 * @param bool $adminConfigStatus
	 * @param string $mode
	 *
	 * @return void
	 * @throws OpenprojectErrorException
	 * @dataProvider oPOAuthTokenRevokeDataProvider
	 */
	public function testSetAdminConfigForOPOAuthTokenRevoke($newConfig, $adminConfigStatus, $mode) {
		$oldAdminConfig = [
			'openproject_client_id' => 'some_old_client_id',
			'openproject_client_secret' => 'some_old_client_secret',
			'openproject_instance_url' => 'http://localhost:3000',
			'default_enable_navigation' => true,
			'default_enable_unified_search' => true,
		];
		$userTokens = [
			'admin' => 'admin_token',
			'test101' => 'user_token',
		];

		$userManager = $this->checkForUsersCountBeforeTest();
		$this->user1 = $userManager->createUser('test101', 'test101');

		$apiService = $this
			->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$oauthServiceMock = $this->createMock(OauthService::class);
		$oauthSettingsControllerMock = $this->createMock('OCA\OAuth2\Controller\SettingsController');

		if ($mode === 'reset') {
			$configMock
				->method('getAppValue')
				->withConsecutive(
					['integration_openproject', 'openproject_instance_url', ''],
					['integration_openproject', 'openproject_client_id', ''],
					['integration_openproject', 'openproject_client_secret', ''],
					['integration_openproject', 'nc_oauth_client_id', ''],
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''], // for user
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''], // for user
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''], // for the last check
					['integration_openproject', 'openproject_client_id'],
					['integration_openproject', 'openproject_client_secret'],
					['integration_openproject', 'openproject_instance_url'],
				)
				->willReturnOnConsecutiveCalls(
					$oldAdminConfig['openproject_instance_url'],
					$oldAdminConfig['openproject_client_id'],
					$oldAdminConfig['openproject_client_secret'],
					'',
					'',
					'',
					'',
					$newConfig['openproject_client_id'],
					$newConfig['openproject_client_secret'],
					$newConfig['openproject_instance_url'],
				);
		} else {
			$configMock
				->method('getAppValue')
				->withConsecutive(
					['integration_openproject', 'openproject_instance_url', ''],
					['integration_openproject', 'openproject_client_id', ''],
					['integration_openproject', 'openproject_client_secret', ''],
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
					['integration_openproject', 'openproject_client_id'],
					['integration_openproject', 'openproject_client_secret'],
					['integration_openproject', 'openproject_instance_url'],
				)
				->willReturnOnConsecutiveCalls(
					$oldAdminConfig['openproject_instance_url'],
					$oldAdminConfig['openproject_client_id'],
					$oldAdminConfig['openproject_client_secret'],
					'',
					'',
					'',
					$newConfig['openproject_client_id'],
					$newConfig['openproject_client_secret'],
					$newConfig['openproject_instance_url'],
				);
		}
		$configMock
			->method('setAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_client_id', $newConfig['openproject_client_id']],
				['integration_openproject', 'openproject_client_secret', $newConfig['openproject_client_secret']],
				['integration_openproject', 'openproject_instance_url', $newConfig['openproject_instance_url']],
				['integration_openproject', 'default_enable_navigation', $newConfig['default_enable_navigation']],
				['integration_openproject', 'default_enable_unified_search', $newConfig['default_enable_unified_search']],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', 'success']
			);
		$configMock
			->method('getUserValue')
			->withConsecutive(
				['admin', 'integration_openproject', 'token', ''],
				[$this->user1->getUID(), 'integration_openproject', 'token', '']
			)
			->willReturnOnConsecutiveCalls(
				$userTokens['admin'],
				$userTokens[$this->user1->getUID()]
			);

		$apiService
			->expects($this->exactly(2))
			->method('revokeUserOAuthToken')
			->withConsecutive(
				['admin', $oldAdminConfig['openproject_instance_url'], $userTokens['admin'], $oldAdminConfig['openproject_client_id'], $oldAdminConfig['openproject_client_secret']],
				['test101', $oldAdminConfig['openproject_instance_url'], $userTokens['test101'], $oldAdminConfig['openproject_client_id'], $oldAdminConfig['openproject_client_secret']],
			);

		$configMock
			->expects($this->exactly(10))
			->method("deleteUserValue")
			->withConsecutive(
				['admin', 'integration_openproject', 'token'],
				['admin', 'integration_openproject', 'login'],
				['admin', 'integration_openproject', 'user_id'],
				['admin', 'integration_openproject', 'user_name'],
				['admin', 'integration_openproject', 'refresh_token'],
				[$this->user1->getUID(), 'integration_openproject', 'token'],
				[$this->user1->getUID(), 'integration_openproject', 'login'],
				[$this->user1->getUID(), 'integration_openproject', 'user_id'],
				[$this->user1->getUID(), 'integration_openproject', 'user_name'],
				[$this->user1->getUID(), 'integration_openproject', 'refresh_token'],
			);
		$configMock
			->expects($this->exactly(2))
			->method('deleteAppValue')
			->withConsecutive(
				['integration_openproject', 'oPOAuthTokenRevokeStatus'],
				['integration_openproject', 'oPOAuthTokenRevokeStatus'],
			);


		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$this->createMock(IURLGenerator::class),
			$userManager,
			$this->l,
			$apiService,
			$this->createMock(LoggerInterface::class),
			$oauthServiceMock,
			$oauthSettingsControllerMock,
			'test101'
		);

		$result = $configController->setAdminConfig($newConfig);
		$this->assertEquals(Http::STATUS_OK, $result->getStatus());
		$data = $result->getData();
		$this->assertArrayHasKey('status', $data);
		$this->assertEquals($adminConfigStatus, $data['status']);
		$this->assertArrayHasKey('oPOAuthTokenRevokeStatus', $data);
		$this->assertEquals("", $data['oPOAuthTokenRevokeStatus']);
	}

	/**
	 * @return array<mixed>
	 */
	public function oPOAuthTokenRevokeErrorDataProvider() {
		$connectException = new ConnectException('Connection error', $this->createMock(RequestInterface::class));
		$opException = new OpenprojectErrorException('Other error');
		return [
			["connection_error", $connectException, ['Error: Connection error', ['app' => 'integration_openproject']]],
			["other_error", $opException, ['Error: Other error', ['app' => 'integration_openproject']]]
		];
	}


	/**
	 * @param string $errorCode
	 * @param ConnectException|OpenprojectErrorException $exception
	 * @param array<mixed> $errMessage
	 *
	 * @return void
	 * @dataProvider oPOAuthTokenRevokeErrorDataProvider
	 * @throws OpenprojectErrorException
	 */
	public function testOPOAuthTokenRevokeErrors($errorCode, $exception, $errMessage) {
		$oldAdminConfig = [
			'openproject_client_id' => 'some_old_client_id',
			'openproject_client_secret' => 'some_old_client_secret',
			'openproject_instance_url' => 'http://localhost:3000',
		];
		$newAdminConfig = [
			'openproject_client_id' => '',
			'openproject_client_secret' => '',
			'openproject_instance_url' => '',
		];
		$userTokens = [
			'admin' => 'admin_token',
		];
		$userManager = $this->checkForUsersCountBeforeTest();
		$apiService = $this
			->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$oauthServiceMock = $this->createMock(OauthService::class);
		$oauthSettingsControllerMock = $this->createMock('OCA\OAuth2\Controller\SettingsController');
		$loggerInterfaceMock = $this->createMock(LoggerInterface::class);

		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url', ''],
				['integration_openproject', 'openproject_client_id', ''],
				['integration_openproject', 'openproject_client_secret', ''],
				['integration_openproject', 'nc_oauth_client_id', ''],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', ''], // for the last check
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url'],
			)
			->willReturnOnConsecutiveCalls(
				$oldAdminConfig['openproject_instance_url'],
				$oldAdminConfig['openproject_client_id'],
				$oldAdminConfig['openproject_client_secret'],
				'',
				$errorCode,
				$newAdminConfig['openproject_client_id'],
				$newAdminConfig['openproject_client_secret'],
				$newAdminConfig['openproject_instance_url'],
			);
		$configMock
			->method('setAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_client_id', $newAdminConfig['openproject_client_id']],
				['integration_openproject', 'openproject_client_secret', $newAdminConfig['openproject_client_secret']],
				['integration_openproject', 'openproject_instance_url', $newAdminConfig['openproject_instance_url']],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', $errorCode],
			);

		$configMock
			->method('getUserValue')
			->withConsecutive(
				['admin', 'integration_openproject', 'token', ''],
			)
			->willReturnOnConsecutiveCalls(
				$userTokens['admin'],
			);

		$loggerInterfaceMock
			->method("error")
			->withConsecutive(
				$errMessage,
			);

		$apiService
			->expects($this->exactly(1))
			->method('revokeUserOAuthToken')
			->withConsecutive(
				[
					'admin',
					$oldAdminConfig['openproject_instance_url'],
					$userTokens['admin'],
					$oldAdminConfig['openproject_client_id'],
					$oldAdminConfig['openproject_client_secret']
				],
			)
			->willThrowException($exception);

		$configMock
			->expects($this->exactly(5))
			->method("deleteUserValue")
			->withConsecutive(
				['admin', 'integration_openproject', 'token'],
				['admin', 'integration_openproject', 'login'],
				['admin', 'integration_openproject', 'user_id'],
				['admin', 'integration_openproject', 'user_name'],
				['admin', 'integration_openproject', 'refresh_token'],
			);
		$configMock
			->expects($this->exactly(2))
			->method('deleteAppValue')
			->withConsecutive(
				['integration_openproject', 'oPOAuthTokenRevokeStatus'],
				['integration_openproject', 'oPOAuthTokenRevokeStatus'],
			);
		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$this->createMock(IURLGenerator::class),
			$userManager,
			$this->l,
			$apiService,
			$loggerInterfaceMock,
			$oauthServiceMock,
			$oauthSettingsControllerMock,
			'admin'
		);

		$result = $configController->setAdminConfig($newAdminConfig);
		$this->assertEquals(Http::STATUS_OK, $result->getStatus());
		$data = $result->getData();
		$this->assertArrayHasKey('status', $data);
		$this->assertEquals(false, $data['status']);
		$this->assertArrayHasKey('oPOAuthTokenRevokeStatus', $data);
		$this->assertEquals($errorCode, $data['oPOAuthTokenRevokeStatus']);
	}

	/**
	 * @return void
	 */
	public function testOPOAuthTokenRevokeDoesNotOccurIfNoOPOAuthClientHasChanged() {
		$oldAdminConfig = [
			'openproject_client_id' => 'some_old_client_id',
			'openproject_client_secret' => 'some_old_client_secret',
			'openproject_instance_url' => 'http://localhost:3000',
		];
		$newAdminConfig = $oldAdminConfig;
		$userManager = $this->checkForUsersCountBeforeTest();
		$apiService = $this
			->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$oauthServiceMock = $this->createMock(OauthService::class);
		$oauthSettingsControllerMock = $this->createMock('OCA\OAuth2\Controller\SettingsController');
		$loggerInterfaceMock = $this->createMock(LoggerInterface::class);

		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url', ''],
				['integration_openproject', 'openproject_client_id', ''],
				['integration_openproject', 'openproject_client_secret', ''],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', '']
			)
			->willReturnOnConsecutiveCalls(
				$oldAdminConfig['openproject_instance_url'],
				$oldAdminConfig['openproject_client_id'],
				$oldAdminConfig['openproject_client_secret'],
				''
			);
		$configMock
			->expects($this->exactly(2))
			->method('deleteAppValue')
			->withConsecutive(
				['integration_openproject', 'oPOAuthTokenRevokeStatus'],
				['integration_openproject', 'oPOAuthTokenRevokeStatus']
			);

		$apiService
			->expects($this->exactly(0))
			->method('revokeUserOAuthToken');

		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$this->createMock(IURLGenerator::class),
			$userManager,
			$this->l,
			$apiService,
			$loggerInterfaceMock,
			$oauthServiceMock,
			$oauthSettingsControllerMock,
			'admin'
		);

		$result = $configController->setAdminConfig($newAdminConfig);
		$this->assertEquals(Http::STATUS_OK, $result->getStatus());
		$data = $result->getData();
		$this->assertArrayHasKey('status', $data);
		$this->assertEquals(false, $data['status']);
		$this->assertArrayHasKey('oPOAuthTokenRevokeStatus', $data);
		$this->assertEquals("", $data['oPOAuthTokenRevokeStatus']);
	}
}
