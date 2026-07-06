<?php

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Controller;

use GuzzleHttp\Exception\ConnectException;
use OCA\OAuth2\Controller\SettingsController;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Exception\OpenprojectErrorException;
use OCA\OpenProject\Service\OauthService;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCA\OpenProject\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\DB\Exception;
use OCP\Group\ISubAdmin;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class ConfigControllerTest extends TestCase {

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
	 * @param MockObject $mock The mock object on which the method is expected to be called
	 * @param string $method The method name for which the expectations are set
	 * @param array $calls An array of expected argument arrays for each call
	 */
	private function expectMethodCalls(
		$mock,
		string $method,
		array $calls,
		?bool $exactly = false
	): void {
		if ($exactly) {
			$expectedCallsCount = $this->exactly(count($calls));
		} else {
			$expectedCallsCount = $this->any();
		}
		$mock
			->expects($expectedCallsCount)
			->method($method)
			->willReturnCallback(function (...$args) use (&$calls) {
				// if not $returnValue = array_pop($calls);
				[$expectedArgs, $returnValue] = array_shift($calls);
				$this->assertSame($expectedArgs, $args);
				return $returnValue;
			});
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

		$this->expectMethodCalls($configMock, 'getAppValue', [
			[['integration_openproject', 'openproject_client_id', ''], 'clientID'],
			[['integration_openproject', 'openproject_client_secret', ''], $clientSecret],
			[['integration_openproject', 'openproject_instance_url', ''], 'http://openproject.org'],
			[['integration_openproject', 'openproject_client_id', ''], 'clientID'],
			[['integration_openproject', 'openproject_client_secret', ''], 'clientSecret'],
		]);

		// $this->expectMethodCalls($configMock, 'getUserValue', [
		// 	[['testUser', 'integration_openproject', 'oauth_state', ''], 'randomString'],
		// 	[['testUser', 'integration_openproject', 'code_verifier', false], $codeVerifier],
		// 	[['testUser', 'integration_openproject', 'oauth_journey_starting_page', ''], $startingPage],
		// 	[['testUser', 'integration_openproject', 'refresh_token', false], 'oAuthRefreshToken'],
		// ]);
		$configMock
			->method('getUserValue')
			->willReturnMap([
				['testUser', 'integration_openproject', 'oauth_state', '', 'randomString'],
				['testUser', 'integration_openproject', 'code_verifier', false, $codeVerifier],
				['testUser', 'integration_openproject', 'oauth_journey_starting_page', '', $startingPage],
				['testUser', 'integration_openproject', 'refresh_token', false, 'oAuthRefreshToken'],
			]);
		return $configMock;
	}

	/**
	 * @return IL10N
	 */
	public function getL10nMock(): IL10N {
		$l10nMock = $this->createMock(IL10N::class);
		$l10nMock->expects($this->any())
			->method('t')
			->willReturnCallback(function ($string, $args) {
				return vsprintf($string, $args);
			});
		return $l10nMock;
	}

	/**
	 * @return SettingsService
	 */
	public function getSettingsService(): SettingsService {
		return new SettingsService(
			$this->createMock(IUserManager::class),
			$this->createMock(IGroupManager::class),
			$this->createMock(OpenProjectAPIService::class),
			$this->createMock(ISecureRandom::class),
			$this->createMock(ISubAdmin::class),
		);
	}

	/**
	 * Format has to be [<string> => <object|string>] with the first being the constructor parameter name and the second one the mock.
	 * Example: ['config' => $createdMockObject]
	 * @param array<string, object|string> $constructParams specific mocks for the constructor of OpenProjectAPIService
	 *
	 * @return array
	 */
	private function getConfigControllerConstructArgs(array $constructParams = []): array {
		$constructArgs = [
			// order should be the same as in the constructor
			'request' => $this->createMock(IRequest::class),
			'config' => $this->createMock(IConfig::class),
			'urlGenerator' => $this->createMock(IURLGenerator::class),
			'userManager' => $this->createMock(IUserManager::class),
			'l10n' => $this->getL10nMock(),
			'openprojectAPIService' => $this->createMock(OpenProjectAPIService::class),
			'loggerInterface' => $this->createMock(LoggerInterface::class),
			'oauthService' => $this->createMock(OauthService::class),
			'settingsController' => $this->createMock(SettingsController::class),
			'settingsService' => $this->getSettingsService(),
			'userId' => 'testUser'
		];

		// replace default mocks with manually passed in mocks
		foreach ($constructParams as $key => $value) {
			if (!array_key_exists($key, $constructArgs)) {
				throw new \InvalidArgumentException("Invalid construct parameter: $key");
			}

			$constructArgs[$key] = $value;
		}

		return [Application::APP_ID, ...array_values($constructArgs)];
	}

	/**
	 * @param array<string> $mockMethods
	 * @param array<string, object> $constructParams
	 *
	 * @return MockObject
	 */
	private function getConfigControllerMock(
		array $mockMethods = [],
		array $constructParams = [],
	): MockObject {
		$constructArgs = $this->getConfigControllerConstructArgs($constructParams);
		return $this->getMockBuilder(ConfigController::class)
			->setConstructorArgs($constructArgs)
			->onlyMethods($mockMethods)
			->getMock();
	}

	public function testOauthRedirectSuccess():void {
		$configMock = $this->getConfigMock(
			str_repeat("A", 128), str_repeat("S", 50));
		$configMock
			->method('setUserValue')
			->with(
				'testUser', 'integration_openproject', 'oauth_connection_result', 'success'
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
			->method('initUserInfo')
			->with(
				'testUser',
				'oAuthAccessToken'
			)
			->willReturn(['user_name' => 'Tripathi Himal']);

		$apiServiceMock
			->method('requestOAuthAccessToken')
			->willReturn(['access_token' => 'oAuthAccessToken', 'refresh_token' => 'oAuthRefreshToken']);

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'urlGenerator' => $urlGeneratorMock,
			'openprojectAPIService' => $apiServiceMock,
		]);
		$configController = new ConfigController(...$constructArgs);

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

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'urlGenerator' => $urlGeneratorMock,
		]);
		$configController = new ConfigController(...$constructArgs);

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

		$this->expectMethodCalls($configMock, 'setUserValue', [
			[['testUser', 'integration_openproject', 'oauth_connection_result', 'error', null], null],
			[['testUser', 'integration_openproject', 'oauth_connection_error_message', 'Error during OAuth exchanges', null], null],
		]);

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
		]);
		$configController = new ConfigController(...$constructArgs);

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
			$this->expectMethodCalls($configMock, 'setUserValue', [
				[['testUser', 'integration_openproject', 'oauth_connection_result', 'error', null], null],
				[['testUser', 'integration_openproject', 'oauth_connection_error_message', 'Error getting OAuth access token', null], null],
			], true);
		} else {
			$loggerMock->expects($this->once())
				->method('error');
			$this->expectMethodCalls($configMock, 'setUserValue', [
				[['testUser', 'integration_openproject', 'oauth_connection_result', 'error', null], null],
				[['testUser', 'integration_openproject', 'oauth_connection_error_message', 'Error during OAuth exchanges', null], null],
			], true);
		}

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'loggerInterface' => $loggerMock,
		]);
		$configController = new ConfigController(...$constructArgs);

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
				->willReturnMap([
					['testUser', 'integration_openproject', 'oauth_connection_result', 'error', null, null],
					['testUser', 'integration_openproject', 'oauth_connection_error_message', 'Error getting OAuth access token', null, null],
				]);
		} else {
			$loggerMock->expects($this->once())
				->method('error');
			$configMock->expects($this->exactly(2))
				->method('setUserValue')
				->willReturnMap([
					['testUser', 'integration_openproject', 'oauth_connection_result', 'error', null, null],
					['testUser', 'integration_openproject', 'oauth_connection_error_message', 'Error during OAuth exchanges', null, null],
				]);
		}

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'loggerInterface' => $loggerMock,
		]);
		$configController = new ConfigController(...$constructArgs);

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
		$this->expectMethodCalls($configMock, 'setUserValue', [
			[['testUser', 'integration_openproject', 'oauth_connection_result', 'error', null], null],
			[['testUser', 'integration_openproject', 'oauth_connection_error_message', $expectedErrorMessage, null], null],
		], true);

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'openprojectAPIService' => $apiServiceMock,
		]);
		$configController = new ConfigController(...$constructArgs);

		$configController->oauthRedirect('code', 'randomString');
	}

	/**
	 * @return array<mixed>
	 */
	public function setAdminConfigStatusDataProviderForOauth2() {
		return [
			[
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => '$client_id',
					'openproject_client_secret' => '$client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				true
			],
			[
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => '',
					'openproject_client_secret' => '$client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				false
			],
		];
	}

	/**
	 * @param array<string> $credsToUpdate
	 * @param bool $adminConfigStatus
	 *
	 * @return void
	 * @dataProvider setAdminConfigStatusDataProviderForOauth2
	 */
	public function testSetAdminConfigForDifferentAdminConfigStatusForOauth2($credsToUpdate, $adminConfigStatus) {
		$userManager = \OC::$server->getUserManager();

		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('setAppValue')
			->willReturnMap([
				['integration_openproject', 'authorization_method', $credsToUpdate['authorization_method']],
				['integration_openproject', 'openproject_client_id', $credsToUpdate['openproject_client_id']],
				['integration_openproject', 'openproject_client_secret', $credsToUpdate['openproject_client_secret']],
				['integration_openproject', 'openproject_instance_url', $credsToUpdate['openproject_instance_url']]
			]);
		$configMock
			->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'openproject_instance_url', '', $credsToUpdate['openproject_instance_url']],
				[Application::APP_ID, 'authorization_method', '', Application::AUTH_METHOD_OAUTH],
				[Application::APP_ID, 'openproject_client_id', '', $credsToUpdate['openproject_client_id']],
				[Application::APP_ID, 'openproject_client_secret', '', $credsToUpdate['openproject_client_secret']],
				[Application::APP_ID, 'nc_oauth_client_id', '', '123'],
				[Application::APP_ID, 'oPOAuthTokenRevokeStatus', '', ''],
			]);
		$apiService = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManager,
			'openprojectAPIService' => $apiService,
		]);
		$configController = new ConfigController(...$constructArgs);

		$result = $configController->setAdminConfig($credsToUpdate);

		$this->assertSame(
			[
				'status' => $adminConfigStatus,
				'oPOAuthTokenRevokeStatus' => '',
				"oPUserAppPassword" => null
			],
			$result->getData()
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function setAdminConfigStatusDataProviderForOIDC() {
		return [
			[
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => 'test-oidc-provider',
					'targeted_audience_client_id' => 'test-client',
					'openproject_instance_url' => 'http://openproject.com'
				],
				true
			],
			[
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => '',
					'targeted_audience_client_id' => 'test-client',
					'openproject_instance_url' => 'http://openproject.com'
				],
				false
			],
			[
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => '',
					'targeted_audience_client_id' => '',
					'openproject_instance_url' => 'http://openproject.com'
				],
				false
			],
			[
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => 'test-oidc-provider',
					'targeted_audience_client_id' => '',
					'openproject_instance_url' => 'http://openproject.com'
				],
				false
			],
		];
	}

	/**
	 * @param array<string> $credsToUpdate
	 * @param bool $adminConfigStatus
	 *
	 * @return void
	 * @dataProvider setAdminConfigStatusDataProviderForOIDC
	 */
	public function testSetAdminConfigForDifferentAdminConfigStatusForOIDC($credsToUpdate, $adminConfigStatus) {
		$userManager = \OC::$server->getUserManager();

		$configMock = $this->getMockBuilder(IConfig::class)->getMock();

		$this->expectMethodCalls($configMock, 'setAppValue', [
			[['integration_openproject', 'authorization_method', $credsToUpdate['authorization_method']], null],
			[['integration_openproject', 'oidc_provider', $credsToUpdate['oidc_provider']], null],
			[['integration_openproject', 'targeted_audience_client_id', $credsToUpdate['targeted_audience_client_id']], null],
			[['integration_openproject', 'openproject_instance_url', $credsToUpdate['openproject_instance_url']], null],
		], true);

		$configMock
			->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'openproject_instance_url', '', $credsToUpdate['openproject_instance_url']],
				[Application::APP_ID, 'authorization_method', '', Application::AUTH_METHOD_OIDC],
				[Application::APP_ID, 'oidc_provider', '', $credsToUpdate['oidc_provider']],
				[Application::APP_ID, 'sso_provider_type', '', Application::NEXTCLOUD_HUB_OIDC_PROVIDER_TYPE],
				[Application::APP_ID, 'targeted_audience_client_id', '', $credsToUpdate['targeted_audience_client_id']],
				[Application::APP_ID, 'oPOAuthTokenRevokeStatus', '', ''],
			]);

		
		$apiService = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManager,
			'openprojectAPIService' => $apiService,
		]);
		$configController = new ConfigController(...$constructArgs);

		$result = $configController->setAdminConfig($credsToUpdate);

		$this->assertSame(
			[
				'status' => $adminConfigStatus,
				'oPOAuthTokenRevokeStatus' => '',
				"oPUserAppPassword" => null
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
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'old-openproject_client_id',
					'openproject_client_secret' => 'old-openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				true,
				'change'
			],
			[ // only client id changes so delete user values but don't change the oAuth Client
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'old-openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				true,
				false
			],
			[ // only client secret changes so delete user values but don't change the oAuth Client
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'old-openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				true,
				false
			],
			[ //only the openproject_instance_url changes so don't delete the user values but change the oAuth Client
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				false,
				'change'
			],
			[ //everything cleared
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => null,
					'openproject_client_id' => null,
					'openproject_client_secret' => null,
					'openproject_instance_url' => null,
				],
				true,
				'delete'
			],
			[ //everything cleared with empty strings
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => '',
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
		$testUser = 'test101';
		$userManager = $this->checkForUsersCountBeforeTest();
		$this->user1 = $userManager->createUser($testUser, $testUser);

		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$oauthServiceMock = $this->createMock(OauthService::class);
		$oauthSettingsControllerMock = $this->getMockBuilder(SettingsController::class)
			->disableOriginalConstructor()
			->getMock();
		$configMock
			->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'openproject_instance_url', '', $oldCreds['openproject_instance_url']],
				[Application::APP_ID, 'authorization_method', '', $oldCreds['authorization_method']],
				[Application::APP_ID, 'openproject_client_id', '', $oldCreds['openproject_client_id']],
				[Application::APP_ID, 'openproject_client_secret', '', $oldCreds['openproject_client_secret']],
				[Application::APP_ID, 'nc_oauth_client_id', '', '123'],
				[Application::APP_ID, 'oPOAuthTokenRevokeStatus', '', ''],
			]);
		$configMock
			->method('getUserValue')
			->willReturnMap([
				['admin', Application::APP_ID, 'token', '', 'testtoken'],
				[$testUser, Application::APP_ID, 'token', '', 'testtoken'],
			]);

		if ($updateNCOAuthClient) {
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
			$oauthServiceMock->expects($this->never())->method('setClientRedirectUri');
		}

		$expectedCalls = [];
		$deleteCalls = [];
		if ($deleteUserValues === true) {
			$configMock
				->expects($this->exactly(12))
				->method('deleteUserValue')
				->willReturnMap([
					['admin', Application::APP_ID, 'token', null],
					['admin', Application::APP_ID, 'login', null],
					['admin', Application::APP_ID, 'user_id', null],
					['admin', Application::APP_ID, 'user_name', null],
					['admin', Application::APP_ID, 'refresh_token', null],
					['admin', Application::APP_ID, 'token_expires_at', null],
					[$testUser, Application::APP_ID, 'token', null],
					[$testUser, Application::APP_ID, 'login', null],
					[$testUser, Application::APP_ID, 'user_id', null],
					[$testUser, Application::APP_ID, 'user_name', null],
					[$testUser, Application::APP_ID, 'refresh_token', null],
					[$testUser, Application::APP_ID, 'token_expires_at', null],
				]);
		} else {
			$configMock
				->expects($this->never())
				->method('deleteUserValue');
		}

		$apiService = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManager,
			'openprojectAPIService' => $apiService,
			'oauthService' => $oauthServiceMock,
			'settingsController' => $oauthSettingsControllerMock,
			'userId' => 'test101'
		]);
		$configController = new ConfigController(...$constructArgs);

		$configController->setAdminConfig($credsToUpdate);

		$this->assertEqualsCanonicalizing($expectedCalls, $deleteCalls);
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

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManager,
			'openprojectAPIService' => $apiService,
			'oauthService' => $oauthServiceMock,
			'userId' => 'test101'
		]);
		$configController = new ConfigController(...$constructArgs);

		$response = $configController->setAdminConfig([
			'client_id_top' => 'old-openproject_client_id',
		]);

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
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
		$userManager = \OC::$server->getUserManager();

		// Keep tests isolated by removing users created by previous runs.
		$userManager->callForAllUsers(function (IUser $user) {
			if ($user->getUID() !== 'admin') {
				$user->delete();
			}
			return null;
		});

		$actualCount = 0;
		$function = function () use (&$actualCount) {
			$actualCount++;
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
				'oldConfig' => [
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'op_client',
					'openproject_client_secret' => 'op_client_secret',
					'nc_oauth_client_id' => 'nc_client',
				],
				'newConfig' => [
					'authorization_method' => null,
					'openproject_client_id' => null,
					'openproject_client_secret' => null,
					'openproject_instance_url' => null,
					'default_enable_navigation' => false,
					'default_enable_unified_search' => false,
				],
				'configStatus' => false,
				'mode' => 'reset',
			],
			[
				'oldConfig' => [
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'op_client',
					'openproject_client_secret' => 'op_client_secret',
					'nc_oauth_client_id' => 'nc_client',
				],
				'newConfig' => [
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'client_id_changed',
					'openproject_client_secret' => 'client_secret_changed',
					'openproject_instance_url' => 'http://localhost:3000',
					'default_enable_navigation' => true,
					'default_enable_unified_search' => true,
				],
				'configStatus' => true,
				'mode' => 'change',
			]
		];
	}

	/**
	 * @param array<mixed> $oldConfig
	 * @param array<mixed> $newConfig
	 * @param bool $adminConfigStatus
	 * @param string $mode
	 *
	 * @return void
	 * @throws OpenprojectErrorException
	 * @dataProvider oPOAuthTokenRevokeDataProvider
	 */
	public function testSetAdminConfigForOPOAuthTokenRevoke(array $oldConfig, array $newConfig, bool $adminConfigStatus, string $mode) {
		$oldAdminConfig = [
			'openproject_instance_url' => 'http://localhost:3000',
			'default_enable_navigation' => true,
			'default_enable_unified_search' => true,
		];
		$oldAdminConfig = array_merge($oldAdminConfig, $oldConfig);
		$testUser = 'test101';
		$userTokens = [
			'admin' => 'admin_token',
			$testUser => 'user_token',
		];

		$userManager = $this->checkForUsersCountBeforeTest();
		$this->user1 = $userManager->createUser($testUser, $testUser);

		$apiService = $this
			->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$oauthServiceMock = $this->createMock(OauthService::class);
		$oauthSettingsControllerMock = $this->createMock('OCA\OAuth2\Controller\SettingsController');

		if ($mode === "reset") {
			$this->expectMethodCalls($configMock, 'deleteAppValue', [
				[[Application::APP_ID, 'nc_oauth_client_id'], null],
				[[Application::APP_ID, 'oPOAuthTokenRevokeStatus'], null],
				[[Application::APP_ID, 'oPOAuthTokenRevokeStatus'], null],
			], true);
		} else {
			$this->expectMethodCalls($configMock, 'deleteAppValue', [
				[[Application::APP_ID, 'oPOAuthTokenRevokeStatus'], null],
				[[Application::APP_ID, 'oPOAuthTokenRevokeStatus'], null],
			], true);
		}

		$configMock
			->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'openproject_instance_url', '', $oldAdminConfig['openproject_instance_url']],
				[Application::APP_ID, 'authorization_method', '', $oldAdminConfig['authorization_method']],
				[Application::APP_ID, 'openproject_client_id', '', $oldAdminConfig['openproject_client_id']],
				[Application::APP_ID, 'openproject_client_secret', '', $oldAdminConfig['openproject_client_secret']],
				[Application::APP_ID, 'nc_oauth_client_id', '', $oldAdminConfig['nc_oauth_client_id']],
				[Application::APP_ID, 'oPOAuthTokenRevokeStatus', '', ''],
			]);

		$configMock
			->method('setAppValue')
			->willReturnMap([
				[Application::APP_ID, 'authorization_method', $newConfig['authorization_method'], null],
				[Application::APP_ID, 'openproject_client_id', $newConfig['openproject_client_id'], null],
				[Application::APP_ID, 'openproject_client_secret', $newConfig['openproject_client_secret'], null],
				[Application::APP_ID, 'openproject_instance_url', $newConfig['openproject_instance_url'], null],
				[Application::APP_ID, 'default_enable_navigation', $newConfig['default_enable_navigation'], null],
				[Application::APP_ID, 'default_enable_unified_search', $newConfig['default_enable_unified_search'], null],
				[Application::APP_ID, 'oPOAuthTokenRevokeStatus', 'success', null]
			]);

		$configMock
			->method('getUserValue')
			->willReturnMap([
				['admin', Application::APP_ID, 'token', '', $userTokens['admin']],
				[$testUser, Application::APP_ID, 'token', '', $userTokens[$testUser]],
			]);

		$apiService
			->expects($this->exactly(2))
			->method('revokeUserOAuthToken')
			->willReturnMap([
				[$oldAdminConfig['openproject_instance_url'], $userTokens['admin'], $oldAdminConfig['openproject_client_id'], $oldAdminConfig['openproject_client_secret'], true],
				[$oldAdminConfig['openproject_instance_url'], $userTokens[$testUser], $oldAdminConfig['openproject_client_id'], $oldAdminConfig['openproject_client_secret'], true],
			]);

		$configMock
			->expects($this->exactly(12))
			->method("deleteUserValue")
			->willReturnMap([
				['admin', Application::APP_ID, 'token', null],
				['admin', Application::APP_ID, 'login', null],
				['admin', Application::APP_ID, 'user_id', null],
				['admin', Application::APP_ID, 'user_name', null],
				['admin', Application::APP_ID, 'refresh_token', null],
				['admin', Application::APP_ID, 'token_expires_at', null],
				[$testUser, Application::APP_ID, 'token', null],
				[$testUser, Application::APP_ID, 'login', null],
				[$testUser, Application::APP_ID, 'user_id', null],
				[$testUser, Application::APP_ID, 'user_name', null],
				[$testUser, Application::APP_ID, 'refresh_token', null],
				[$testUser, Application::APP_ID, 'token_expires_at', null],
			]);

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManager,
			'openprojectAPIService' => $apiService,
			'oauthService' => $oauthServiceMock,
			'settingsController' => $oauthSettingsControllerMock,
			'userId' => 'test101'
		]);
		$configController = new ConfigController(...$constructArgs);

		$result = $configController->setAdminConfig($newConfig);
		$this->assertEquals(Http::STATUS_OK, $result->getStatus());
		$data = $result->getData();
		$this->assertArrayHasKey('status', $data);
		$this->assertArrayHasKey('oPOAuthTokenRevokeStatus', $data);
		$this->assertArrayHasKey('oPUserAppPassword', $data);
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
			'authorization_method' => Application::AUTH_METHOD_OAUTH,
			'openproject_client_id' => 'some_old_client_id',
			'openproject_client_secret' => 'some_old_client_secret',
			'openproject_instance_url' => 'http://localhost:3000',
		];
		$newAdminConfig = [
			'authorization_method' => '',
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

		$this->expectMethodCalls($configMock, 'getAppValue', [
			[[Application::APP_ID, 'openproject_instance_url', ''], $oldAdminConfig['openproject_instance_url']],
			[[Application::APP_ID, 'authorization_method', ''], $oldAdminConfig['authorization_method']],
			[[Application::APP_ID, 'openproject_client_id', ''], $oldAdminConfig['openproject_client_id']],
			[[Application::APP_ID, 'openproject_client_secret', ''], $oldAdminConfig['openproject_client_secret']],
			[[Application::APP_ID, 'nc_oauth_client_id', ''], ''],
			[[Application::APP_ID, 'oPOAuthTokenRevokeStatus', ''], $errorCode],
			[[Application::APP_ID, 'authorization_method', ''], Application::AUTH_METHOD_OAUTH],
			[[Application::APP_ID, 'openproject_instance_url', ''], $newAdminConfig['openproject_instance_url']],
			[[Application::APP_ID, 'fresh_project_folder_setup', ''], false],
			[[Application::APP_ID, 'openproject_client_id', ''], $newAdminConfig['openproject_client_id']],
			[[Application::APP_ID, 'openproject_client_secret', ''], $newAdminConfig['openproject_client_secret']],
			[[Application::APP_ID, 'openproject_instance_url', ''], $newAdminConfig['openproject_instance_url']],
		]);

		$configMock
			->expects($this->exactly(6))
			->method('setAppValue')
			->willReturnMap([
				[Application::APP_ID, 'authorization_method', $newAdminConfig['authorization_method'], null],
				[Application::APP_ID, 'openproject_client_id', $newAdminConfig['openproject_client_id'], null],
				[Application::APP_ID, 'openproject_client_secret', $newAdminConfig['openproject_client_secret'], null],
				[Application::APP_ID, 'openproject_instance_url', $newAdminConfig['openproject_instance_url'], null],
				[Application::APP_ID, 'oPOAuthTokenRevokeStatus', $errorCode, null],
				[Application::APP_ID, 'fresh_project_folder_setup', false, null],
			]);

		$configMock
			->method('getUserValue')
			->willReturnMap([
				['admin', Application::APP_ID, 'token', '', $userTokens['admin']],
			]);

		$loggerInterfaceMock
			->method("error")
			->willReturn($errMessage);

		$apiService
			->expects($this->exactly(1))
			->method('revokeUserOAuthToken')
			->with(
				'admin',
				$oldAdminConfig['openproject_instance_url'],
				$userTokens['admin'],
				$oldAdminConfig['openproject_client_id'],
				$oldAdminConfig['openproject_client_secret']
			)
			->willThrowException($exception);

		$configMock
			->expects($this->exactly(6))
			->method("deleteUserValue")
			->willReturnMap([
				['admin', Application::APP_ID, 'token', null],
				['admin', Application::APP_ID, 'login', null],
				['admin', Application::APP_ID, 'user_id', null],
				['admin', Application::APP_ID, 'user_name', null],
				['admin', Application::APP_ID, 'refresh_token', null],
				['admin', Application::APP_ID, 'token_expires_at', null],
			]);

		$this->expectMethodCalls($configMock, 'deleteAppValue', [
			[[Application::APP_ID, 'oPOAuthTokenRevokeStatus'], null],
			[[Application::APP_ID, 'oPOAuthTokenRevokeStatus'], null],
		], true);

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManager,
			'openprojectAPIService' => $apiService,
			'loggerInterface' => $loggerInterfaceMock,
			'oauthService' => $oauthServiceMock,
			'settingsController' => $oauthSettingsControllerMock,
			'userId' => 'admin'
		]);
		$configController = new ConfigController(...$constructArgs);

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
			'authorization_method' => Application::AUTH_METHOD_OAUTH,
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
			->willReturnMap([
				[Application::APP_ID, 'openproject_instance_url', '', $oldAdminConfig['openproject_instance_url']],
				[Application::APP_ID, 'authorization_method', '', $oldAdminConfig['authorization_method']],
				[Application::APP_ID, 'openproject_client_id', '', $oldAdminConfig['openproject_client_id']],
				[Application::APP_ID, 'openproject_client_secret', '', $oldAdminConfig['openproject_client_secret']],
				[Application::APP_ID, 'oPOAuthTokenRevokeStatus', '', ''],
			]);
		// $configMock
		// 	->expects($this->exactly(2))
		// 	->method('deleteAppValue')
		// 	->withConsecutive(
		// 		['integration_openproject', 'oPOAuthTokenRevokeStatus'],
		// 		['integration_openproject', 'oPOAuthTokenRevokeStatus']
		// 	);
		// $this->expectMethodCalls($configMock, 'deleteAppValue', [
		// 	[[Application::APP_ID, 'oPOAuthTokenRevokeStatus'], null],
		// 	[[Application::APP_ID, 'oPOAuthTokenRevokeStatus'], null],
		// ], true);

		$configMock
			->expects($this->exactly(2))
			->method('deleteAppValue')
			->with(Application::APP_ID, 'oPOAuthTokenRevokeStatus');

		$apiService
			->expects($this->exactly(0))
			->method('revokeUserOAuthToken');

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManager,
			'openprojectAPIService' => $apiService,
			'loggerInterface' => $loggerInterfaceMock,
			'oauthService' => $oauthServiceMock,
			'settingsController' => $oauthSettingsControllerMock,
			'userId' => 'admin'
		]);
		$configController = new ConfigController(...$constructArgs);

		$result = $configController->setAdminConfig($newAdminConfig);
		$this->assertEquals(Http::STATUS_OK, $result->getStatus());
		$data = $result->getData();
		$this->assertArrayHasKey('status', $data);
		$this->assertEquals(false, $data['status']);
		$this->assertArrayHasKey('oPOAuthTokenRevokeStatus', $data);
		$this->assertEquals("", $data['oPOAuthTokenRevokeStatus']);
	}


	public function testSetupIntegrationProjectFoldersSetUp():void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service
			->method('isSystemReadyForProjectFolderSetUp')
			->willReturn(true);
		$service
			->method('createGroupfolder');
		$service->method('deleteAppPassword');
		$service
			->method('generateAppPasswordTokenForUser')
			->willReturn('gliAcIJ3RwcgpF6ijPramBVzujfSQwJw2AVcz3Uj7bdXqxDbmkSukQhljAUf9HXItQTglvfx');
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'openproject_instance_url', '', 'http://localhost:3000'],
				[Application::APP_ID, 'authorization_method', '', Application::AUTH_METHOD_OAUTH],
				[Application::APP_ID, 'openproject_client_id', '', 'some_cilent_id'],
				[Application::APP_ID, 'openproject_client_secret', '', 'some_cilent_secret'],
			]);
		$service
			->method('getPasswordLength')
			->willReturn(15);
		$userMock = $this->createMock(IUser::class);
		$userManagerMock = $this->getMockBuilder(IUserManager::class)->getMock();
		$userManagerMock
			->method('createUser')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($userMock);
		$userManagerMock
			->method('userExists')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn(true);
		$groupMock = $this->getMockBuilder(IGroup::class)->getMock();
		$groupMock
			->method('addUser')
			->with($userMock);

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManagerMock,
			'openprojectAPIService' => $service,
			'userId' => 'admin'
		]);
		$configController = new ConfigController(...$constructArgs);

		$result = $configController->setAdminConfig([
			"authorization_method" => Application::AUTH_METHOD_OAUTH,
			"setup_project_folder" => true,
			"setup_app_password" => true
		]);
		$this->assertEquals(Http::STATUS_OK, $result->getStatus());
		$data = $result->getData();
		$this->assertArrayHasKey('oPUserAppPassword', $data);
		$this->assertEquals("gliAcIJ3RwcgpF6ijPramBVzujfSQwJw2AVcz3Uj7bdXqxDbmkSukQhljAUf9HXItQTglvfx", $data['oPUserAppPassword']);
	}

	public function testSignTOSForUserOpenProject():void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service->method('signTermsOfServiceForUserOpenProject');
		$service
			->method('isAllTermsOfServiceSignedForUserOpenProject')
			->willReturn(true);
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$userManagerMock = $this->getMockBuilder(IUserManager::class)->getMock();

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManagerMock,
			'openprojectAPIService' => $service,
			'userId' => 'admin'
		]);
		$configController = new ConfigController(...$constructArgs);

		$result = $configController->signTermsOfServiceForUserOpenProject();
		$this->assertEquals(Http::STATUS_OK, $result->getStatus());
		$data = $result->getData();
		$this->assertTrue($data['result']);
	}

	/**
	 * @throws \Exception
	 */
	public function testSignTOSForUserOpenProjectError():void {
		$service = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();
		$service->method('signTermsOfServiceForUserOpenProject')->willThrowException(new Exception("Database Error!"));
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$userManagerMock = $this->getMockBuilder(IUserManager::class)->getMock();

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManagerMock,
			'openprojectAPIService' => $service,
			'userId' => 'admin'
		]);
		$configController = new ConfigController(...$constructArgs);

		$result = $configController->signTermsOfServiceForUserOpenProject();
		$this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $result->getStatus());
		$data = $result->getData();
		$this->assertEquals("Database Error!", $data['error']);
	}

	/**
	 * @return array<mixed>
	 */
	public function setAdminConfigForOIDCAuthSettingProvider() {
		return [
			[ // set info if the authorization settings are changed
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => 'oidc_provider',
					'targeted_audience_client_id' => 'targeted_audience_client_id',
					'openproject_instance_url' => 'http://openproject.com',
				],
				false,
				'change'
			],
			[ // set info even if only 'targeted_audience_client_id' authorization settings are changed
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old_oidc_provider',
					'targeted_audience_client_id' => 'new_targeted_audience_client_id',
					'openproject_instance_url' => 'http://openproject.com',
				],
				false,
				'change'
			],
			[ // setinfo even if only 'oidc_provider' authorization settings are changed
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => 'new_oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://openproject.com',
				]
			],
			[ // set if authorization settings are empty string
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => '',
					'targeted_audience_client_id' => '',
					'openproject_instance_url' => 'http://openproject.com',
				]
			],
			[ // set if authorization settings are null
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => null,
					'targeted_audience_client_id' => null,
					'openproject_instance_url' => 'http://openproject.com',
				]
			],
		];
	}

	/**
	 * @param array<string> $oldCreds
	 * @param array<string> $credsToUpdate
	 * @param bool $deleteUserValues
	 * @param bool|string $updateNCOAuthClient false => don't touch the client, 'change' => update it, 'delete' => remove it
	 * @return void
	 * @dataProvider setAdminConfigForOIDCAuthSettingProvider
	 */
	public function testSetAdminConfigOIDCAuthSetting(
		$oldCreds, $credsToUpdate
	) {
		$userManager = $this->checkForUsersCountBeforeTest();
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$oauthServiceMock = $this->createMock(OauthService::class);
		$oauthSettingsControllerMock = $this->getMockBuilder(SettingsController::class)
			->disableOriginalConstructor()
			->getMock();
		$configMock
			->method('getAppValue')
			->willReturnMap([
				['integration_openproject', 'openproject_instance_url', '', $oldCreds['openproject_instance_url']],
				['integration_openproject', 'authorization_method', '', $oldCreds['authorization_method']],
				['integration_openproject', 'oidc_provider', '', $oldCreds['oidc_provider']],
				['integration_openproject', 'targeted_audience_client_id', '', $oldCreds['targeted_audience_client_id']],
				['integration_openproject', 'nc_oauth_client_id', '', '123'],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', '', ''],
				['integration_openproject', 'authorization_method', '', OpenProjectAPIService::AUTH_METHOD_OIDC],
				['integration_openproject', 'oidc_provider', '', $credsToUpdate['oidc_provider']],
				['integration_openproject', 'targeted_audience_client_id', '', $credsToUpdate['targeted_audience_client_id']],
				['integration_openproject', 'openproject_instance_url', '', $credsToUpdate['openproject_instance_url']],

			]);

		$apiService = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManager,
			'openprojectAPIService' => $apiService,
			'oauthService' => $oauthServiceMock,
			'settingsController' => $oauthSettingsControllerMock,
			'userId' => 'test101'
		]);
		$configController = new ConfigController(...$constructArgs);

		$configController->setAdminConfig($credsToUpdate);
	}


	/**
	 * @return array<mixed>
	 */
	public function setAdminConfigForOAuth2AlreadyConfiguredDataProvider() {
		return [
			[ // when switching from oauth2 to oidc, userdata gets deleted along with the nc client information
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'old-openproject_client_id',
					'openproject_client_secret' => 'old-openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'openproject_client_id' => '',
					'openproject_client_secret' => '',
					'openproject_instance_url' => 'http://old-openproject.com',
				]
			],
			[ // when resetting with OAUTH2 already configured, userdata gets deleted along with the nc client information
				[
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'old-openproject_client_id',
					'openproject_client_secret' => 'old-openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => '',
					'openproject_client_id' => '',
					'openproject_client_secret' => '',
					'openproject_instance_url' => '',
				]
			]
		];
	}

	/**
	 * @param array<string> $oldCreds
	 * @param array<string> $credsToUpdate
	 * @return void
	 * @dataProvider setAdminConfigForOAuth2AlreadyConfiguredDataProvider
	 */
	public function testSetAdminConfigForOAuth2AlreadyConfigured(
		$oldCreds, $credsToUpdate
	) {
		$userManager = $this->checkForUsersCountBeforeTest();
		$this->user1 = $userManager->createUser('test101', 'test101');

		$configMock = $this->createMock(IConfig::class);
		$oauthServiceMock = $this->createMock(OauthService::class);
		$oauthSettingsControllerMock = $this->getMockBuilder(SettingsController::class)
			->disableOriginalConstructor()
			->getMock();
		$this->expectMethodCalls($configMock, 'getAppValue', [
			[['integration_openproject', 'openproject_instance_url', ''], $oldCreds['openproject_instance_url']],
			[['integration_openproject', 'authorization_method', ''], $oldCreds['authorization_method']],
			[['integration_openproject', 'openproject_client_id', ''], $oldCreds['openproject_client_id']],
			[['integration_openproject', 'openproject_client_secret', ''], $oldCreds['openproject_client_secret']],
			[['integration_openproject', 'nc_oauth_client_id', ''], '123'],
			[['integration_openproject', 'oPOAuthTokenRevokeStatus', ''], '123'],
			[['integration_openproject', 'authorization_method', ''], Application::AUTH_METHOD_OAUTH],
			[['integration_openproject', 'openproject_client_id', ''], $credsToUpdate['openproject_client_id']],
			[['integration_openproject', 'openproject_client_secret', ''], $credsToUpdate['openproject_client_secret']],
			[['integration_openproject', 'openproject_instance_url', ''], $credsToUpdate['openproject_instance_url']],
		]);
		$oauthSettingsControllerMock
			->expects($this->once())
			->method('deleteClient')
			->with(123);
		$oauthSettingsControllerMock
			->expects($this->once())
			->method('deleteClient')
			->with(123);
		$configMock
			->expects($this->exactly(12))
			->method('deleteUserValue')
			->willReturnMap([
				['admin', 'integration_openproject', 'token', null],
				['admin', 'integration_openproject', 'login', null],
				['admin', 'integration_openproject', 'user_id', null],
				['admin', 'integration_openproject', 'user_name', null],
				['admin', 'integration_openproject', 'refresh_token', null],
				['admin', 'integration_openproject', 'token_expires_at', null],
				[$this->user1->getUID(), 'integration_openproject', 'token', null],
				[$this->user1->getUID(), 'integration_openproject', 'login', null],
				[$this->user1->getUID(), 'integration_openproject', 'user_id', null],
				[$this->user1->getUID(), 'integration_openproject', 'user_name', null],
				[$this->user1->getUID(), 'integration_openproject', 'refresh_token', null],
				[$this->user1->getUID(), 'integration_openproject', 'token_expires_at', null],
			]);

		$apiService = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManager,
			'openprojectAPIService' => $apiService,
			'oauthService' => $oauthServiceMock,
			'settingsController' => $oauthSettingsControllerMock,
			'userId' => 'test101'
		]);
		$configController = new ConfigController(...$constructArgs);

		$configController->setAdminConfig($credsToUpdate);
	}


	/**
	 * @return array<mixed>
	 */
	public function setAdminConfigForOIDCAlreadyConfigured() {
		return [
			[ // when switching from oidc to oauth2, just the user information get deleted
				'oldConfig' => [
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				'newConfig' => [
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'oidc_provider' => '',
					'targeted_audience_client_id' => '',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
			],
			[
				'oldConfig' => [
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				'newConfig' => [
					'authorization_method' => '',
					'oidc_provider' => '',
					'targeted_audience_client_id' => '',
					'openproject_instance_url' => '',
				],
			],
		];
	}

	/**
	 * @param array<string> $oldConfig
	 * @param array<string> $newConfig
	 *
	 * @return void
	 * @dataProvider setAdminConfigForOIDCAlreadyConfigured
	 */
	public function testSetAdminConfigForOIDCAlreadyConfigured(
		array $oldConfig,
		array $newConfig,
	) {
		$testUser = 'test101';
		$userManager = $this->checkForUsersCountBeforeTest();
		$this->user1 = $userManager->createUser($testUser, $testUser);
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$oauthServiceMock = $this->createMock(OauthService::class);
		$oauthSettingsControllerMock = $this->getMockBuilder(SettingsController::class)
			->disableOriginalConstructor()
			->getMock();
		// $configMock
		// 	->method('getAppValue')
		// 	->withConsecutive(
		// 		['integration_openproject', 'openproject_instance_url', ''],
		// 		['integration_openproject', 'authorization_method', ''],
		// 		['integration_openproject', 'oidc_provider'],
		// 		['integration_openproject', 'targeted_audience_client_id'],
		// 		['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
		// 		['integration_openproject', 'authorization_method', ''],
		// 		['integration_openproject', 'oidc_provider'],
		// 		['integration_openproject', 'targeted_audience_client_id'],
		// 		['integration_openproject', 'openproject_instance_url'],
		// 	)
		// 	->willReturnOnConsecutiveCalls(
		// 		$oldConfig['openproject_instance_url'],
		// 		$oldConfig['authorization_method'],
		// 		$oldConfig['oidc_provider'],
		// 		$oldConfig['targeted_audience_client_id'],
		// 		'',
		// 		$newConfig['authorization_method'],
		// 		$newConfig['oidc_provider'],
		// 		$newConfig['targeted_audience_client_id'],
		// 		$newConfig['openproject_instance_url']
		// 	);
		$this->expectMethodCalls($configMock, 'getAppValue', [
			[['integration_openproject', 'openproject_instance_url', ''], $oldConfig['openproject_instance_url']],
			[['integration_openproject', 'authorization_method', ''], $oldConfig['authorization_method']],
			[['integration_openproject', 'oidc_provider', ''], $oldConfig['oidc_provider']],
			[['integration_openproject', 'targeted_audience_client_id', ''], $oldConfig['targeted_audience_client_id']],
			[['integration_openproject', 'oPOAuthTokenRevokeStatus', ''], ''],
			[['integration_openproject', 'authorization_method', ''], $newConfig['authorization_method']],
			[['integration_openproject', 'oidc_provider', ''], $newConfig['oidc_provider']],
			[['integration_openproject', 'targeted_audience_client_id', ''], $newConfig['targeted_audience_client_id']],
			[['integration_openproject', 'openproject_instance_url', ''], $newConfig['openproject_instance_url']],
		]);
		// $configMock
		// 	->expects($this->exactly(12))
		// 	->method('deleteUserValue')
		// 	->withConsecutive(
		// 		['admin', 'integration_openproject', 'token'],
		// 		['admin', 'integration_openproject', 'login'],
		// 		['admin', 'integration_openproject', 'user_id'],
		// 		['admin', 'integration_openproject', 'user_name'],
		// 		['admin', 'integration_openproject', 'refresh_token'],
		// 		['admin', 'integration_openproject', 'token_expires_at'],
		// 		[$this->user1->getUID(), 'integration_openproject', 'token'],
		// 		[$this->user1->getUID(), 'integration_openproject', 'login'],
		// 		[$this->user1->getUID(), 'integration_openproject', 'user_id'],
		// 		[$this->user1->getUID(), 'integration_openproject', 'user_name'],
		// 		[$this->user1->getUID(), 'integration_openproject', 'refresh_token'],
		// 		[$this->user1->getUID(), 'integration_openproject', 'token_expires_at'],
		// 	);

		// $configMock
		// 	->expects($this->exactly(12))
		// 	->method('deleteUserValue')
		// 	->willReturnMap([
		// 		['admin', 'integration_openproject', 'token', null],
		// 		['admin', 'integration_openproject', 'login', null],
		// 		['admin', 'integration_openproject', 'user_id', null],
		// 		['admin', 'integration_openproject', 'user_name', null],
		// 		['admin', 'integration_openproject', 'refresh_token', null],
		// 		['admin', 'integration_openproject', 'token_expires_at', null],
		// 		[$this->user1->getUID(), 'integration_openproject', 'token', null],
		// 		[$this->user1->getUID(), 'integration_openproject', 'login', null],
		// 		[$this->user1->getUID(), 'integration_openproject', 'user_id', null],
		// 		[$this->user1->getUID(), 'integration_openproject', 'user_name', null],
		// 		[$this->user1->getUID(), 'integration_openproject', 'refresh_token', null],
		// 		[$this->user1->getUID(), 'integration_openproject', 'token_expires_at', null],
		// 	]);

		$this->expectMethodCalls($configMock, 'deleteUserValue', [
			[['admin', 'integration_openproject', 'token'], null],
			[['admin', 'integration_openproject', 'login'], null],
			[['admin', 'integration_openproject', 'user_id'], null],
			[['admin', 'integration_openproject', 'user_name'], null],
			[['admin', 'integration_openproject', 'refresh_token'], null],
			[['admin', 'integration_openproject', 'token_expires_at'], null],
			[[$this->user1->getUID(), 'integration_openproject', 'token'], null],
			[[$this->user1->getUID(), 'integration_openproject', 'login'], null],
			[[$this->user1->getUID(), 'integration_openproject', 'user_id'], null],
			[[$this->user1->getUID(), 'integration_openproject', 'user_name'], null],
			[[$this->user1->getUID(), 'integration_openproject', 'refresh_token'], null],
			[[$this->user1->getUID(), 'integration_openproject', 'token_expires_at'], null],
		], true);

		$apiService = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManager,
			'openprojectAPIService' => $apiService,
			'oauthService' => $oauthServiceMock,
			'settingsController' => $oauthSettingsControllerMock,
			'userId' => $testUser,
		]);

		$configController = new ConfigController(...$constructArgs);
		$configController->setAdminConfig($newConfig);
	}

	/**
	 * @return array<mixed>
	 */
	public function integrationDefaultSettingsProvider() {
		$defaultSettings = [
			'openproject_instance_url' => 'https://test.example.com',
			'default_enable_navigation' => false,
			'default_enable_unified_search' => false,
			'setup_project_folder' => false,
			'setup_app_password' => false,
		];
		return [
			"complete oauth2: without 'authorization_method'" => [
				'settings' => [
					...$defaultSettings,
					'openproject_client_id' => 'test',
					'openproject_client_secret' => 'test',
				],
				'expectedSettings' => [
					...$defaultSettings,
					'openproject_client_id' => 'test',
					'openproject_client_secret' => 'test',
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
				],
			],
			"complete oidc: NC Hub without 'oidc_provider'" => [
				'settings' => [
					...$defaultSettings,
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'sso_provider_type' => Application::NEXTCLOUD_HUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
				],
				'expectedSettings' => [
					...$defaultSettings,
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'sso_provider_type' => Application::NEXTCLOUD_HUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
					'oidc_provider' => Application::NEXTCLOUD_HUB_OIDC_PROVIDER_LABEL,
				],
			],
			"complete oidc: NC Hub with empty 'oidc_provider'" => [
				'settings' => [
					...$defaultSettings,
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'sso_provider_type' => Application::NEXTCLOUD_HUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
					'oidc_provider' => '',
				],
				'expectedSettings' => [
					...$defaultSettings,
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'sso_provider_type' => Application::NEXTCLOUD_HUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
					'oidc_provider' => Application::NEXTCLOUD_HUB_OIDC_PROVIDER_LABEL,
				],
			],
			"complete oidc: NC Hub with 'oidc_provider'" => [
				'settings' => [
					...$defaultSettings,
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'sso_provider_type' => Application::NEXTCLOUD_HUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
					'oidc_provider' => 'test',
				],
				'expectedSettings' => [
					...$defaultSettings,
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'sso_provider_type' => Application::NEXTCLOUD_HUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
					'oidc_provider' => 'test',
				],
			],
		];
	}

	/**
	 * @param array<string, bool|string> $settings
	 * @param array<string, bool|string> $expectedSettings
	 * @return void
	 * @dataProvider integrationDefaultSettingsProvider
	 */
	public function testSetUpIntegrationDefaultSettings(array $settings, array $expectedSettings): void {
		$userManager = $this->checkForUsersCountBeforeTest();
		$oauthMock = $this->createMock(OauthService::class);
		$oauthMock->method('createNcOauthClient')->willReturn(['id' => '1234']);
		$settingsService = $this->createMock(SettingsService::class);

		// check that correct settings are passed
		$settingsService->expects($this->once())
			->method('validateAdminSettingsForm')
			->with($expectedSettings);

		$constructArgs = $this->getConfigControllerConstructArgs([
			'oauthService' => $oauthMock,
			'userManager' => $userManager,
			'settingsService' => $settingsService,
		]);
		$configController = new ConfigController(...$constructArgs);
		$configController->setUpIntegration($settings);
	}

	/**
	 * @return array<mixed>
	 */
	public function setUpIntegrationSuccessProvider(): array {
		$defaultSettings = [
			'openproject_instance_url' => 'https://test.example.com',
			'default_enable_navigation' => false,
			'default_enable_unified_search' => false,
			'setup_project_folder' => false,
			'setup_app_password' => false,
		];
		return [
			"complete oauth2 setup" => [
				"authMethod" => Application::AUTH_METHOD_OAUTH,
				'settings' => [
					...$defaultSettings,
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'test',
					'openproject_client_secret' => 'test',
				],
				'responseProps' => [
					'status',
					'nextcloud_oauth_client_name',
					'nextcloud_client_id',
					'nextcloud_client_secret',
					'openproject_redirect_uri',
				],
			],
			"complete oidc setup" => [
				"authMethod" => Application::AUTH_METHOD_OIDC,
				'settings' => [
					...$defaultSettings,
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'sso_provider_type' => Application::NEXTCLOUD_HUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
				],
				'responseProps' => [
					'status',
				],
			],
			"with team folder" => [
				"authMethod" => Application::AUTH_METHOD_OIDC,
				'settings' => array_merge($defaultSettings, [
					'authorization_method' => Application::AUTH_METHOD_OIDC,
					'sso_provider_type' => Application::NEXTCLOUD_HUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
					'setup_project_folder' => true,
					'setup_app_password' => true,
				]),
				'responseProps' => [
					'status',
					'openproject_user_app_password',
				],
			],
		];
	}

	/**
	 * @param string $authMethod
	 * @param array<string, bool|string> $settings
	 * @param array<string> $responseProps
	 * @return void
	 * @dataProvider setUpIntegrationSuccessProvider
	 */
	public function testSetUpIntegrationSuccess(string $authMethod, array $settings, array $responseProps): void {
		$userManagerMock = $this->createMock(IUserManager::class);
		$userManagerMock->method('userExists')->willReturn(true);
		$oauthMock = $this->createMock(OauthService::class);

		if ($authMethod === Application::AUTH_METHOD_OAUTH) {
			$oauthMock->expects($this->once())
				->method('createNcOauthClient')->willReturn([
					'id' => '1234',
					'nextcloud_oauth_client_name' => 'Openproject Client',
					'openproject_redirect_uri' => 'http://openproject.test/oauth/callback',
					'nextcloud_client_id' => 'client_id',
					'nextcloud_client_secret' => 'client_secret',
				]);
		} else {
			$oauthMock->expects($this->never())
				->method('createNcOauthClient');
		}

		$openprojectAPIServiceMock = $this->createMock(OpenProjectAPIService::class);
		if ($settings['setup_app_password']) {
			$openprojectAPIServiceMock->expects($this->once())
				->method('generateAppPasswordTokenForUser')
				->willReturn('app_pass');
		} else {
			$openprojectAPIServiceMock->expects($this->never())
				->method('generateAppPasswordTokenForUser');
		}
		$settingsServiceMock = $this->createMock(SettingsService::class);
		$settingsServiceMock->expects($this->once())
			->method('validateAdminSettingsForm');

		$constructArgs = [
			'oauthService' => $oauthMock,
			'settingsService' => $settingsServiceMock,
			'openprojectAPIService' => $openprojectAPIServiceMock,
			'userManager' => $userManagerMock,
		];
		$constructArgs = $this->getConfigControllerConstructArgs($constructArgs);
		$configController = new ConfigController(...$constructArgs);

		$response = $configController->setUpIntegration($settings);
		$data = $response->getData();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertArrayHasKey('status', $data);

		foreach ($responseProps as $prop) {
			$this->assertArrayHasKey($prop, $data);
		}
		if ($authMethod === Application::AUTH_METHOD_OIDC) {
			$this->assertArrayNotHasKey('nextcloud_oauth_client_name', $data);
			$this->assertArrayNotHasKey('nextcloud_client_id', $data);
			$this->assertArrayNotHasKey('nextcloud_client_secret', $data);
			$this->assertArrayNotHasKey('openproject_redirect_uri', $data);
		} else {
			$this->assertArrayHasKey('nextcloud_oauth_client_name', $data);
			$this->assertArrayHasKey('nextcloud_client_id', $data);
			$this->assertArrayHasKey('nextcloud_client_secret', $data);
			$this->assertArrayHasKey('openproject_redirect_uri', $data);
		}
		if (!$settings['setup_app_password']) {
			$this->assertArrayNotHasKey('openproject_user_app_password', $data);
		}
	}

	/**
	 * @return array<mixed>
	 */
	public function updateIntegrationSuccessProvider(): array {
		return [
			"oauth2: OpenProject url and existing Nextcloud client" => [
				"authMethod" => Application::AUTH_METHOD_OAUTH,
				"oauthClientId" => 1,
				'settings' => [
					'openproject_instance_url' => 'https://new.test',
				],
				'responseProps' => [
					'status',
					'nextcloud_oauth_client_name',
					'nextcloud_client_id',
					'nextcloud_client_secret',
					'openproject_redirect_uri',
				],
			],
			"oauth2: OpenProject client and non-existing Nextcloud client" => [
				"authMethod" => Application::AUTH_METHOD_OAUTH,
				"oauthClientId" => 0,
				'settings' => [
					'openproject_client_id' => 'test-new',
					'openproject_client_secret' => 'test-new',
				],
				'responseProps' => [
					'status',
					'nextcloud_oauth_client_name',
					'nextcloud_client_id',
					'nextcloud_client_secret',
					'openproject_redirect_uri',
				],
			],
			"oidc: provider type" => [
				"authMethod" => Application::AUTH_METHOD_OIDC,
				"oauthClientId" => 0,
				'settings' => [
					'sso_provider_type' => Application::EXTERNAL_OIDC_PROVIDER_TYPE,
				],
				'responseProps' => [
					'status',
				],
			],
			"oauth to oidc" => [
				"authMethod" => Application::AUTH_METHOD_OAUTH,
				"oauthClientId" => 0,
				'settings' => [
					'authorization_method' => Application::AUTH_METHOD_OIDC,
				],
				'responseProps' => [
					'status',
				],
			],
			"oidc to oauth" => [
				"authMethod" => Application::AUTH_METHOD_OIDC,
				"oauthClientId" => 0,
				'settings' => [
					'authorization_method' => Application::AUTH_METHOD_OAUTH,
				],
				'responseProps' => [
					'status',
					'nextcloud_oauth_client_name',
					'nextcloud_client_id',
					'nextcloud_client_secret',
					'openproject_redirect_uri',
				],
			],
		];
	}

	/**
	 * @param string $authMethod
	 * @param int $oauthClientId
	 * @param array<string, bool|string> $settings
	 * @param array<string> $responseProps
	 * @return void
	 * @dataProvider updateIntegrationSuccessProvider
	 */
	public function testUpdateIntegrationSuccess(string $authMethod, int $oauthClientId, array $settings, array $responseProps): void {
		$userManagerMock = $this->createMock(IUserManager::class);
		$userManagerMock->method('userExists')->willReturn(true);
		$oauthMock = $this->createMock(OauthService::class);

		// change in authorization method
		if (isset($settings['authorization_method'])) {
			$authMethod = $settings['authorization_method'];
		}

		$configMock = $this->createMock(IConfig::class);
		$configMock
			->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'authorization_method', '', $authMethod],
				[Application::APP_ID, 'nc_oauth_client_id', '', $oauthClientId],
			]);

		if ($authMethod === Application::AUTH_METHOD_OAUTH) {
			if ($oauthClientId) {
				$oauthMock->expects($this->never())
					->method('createNcOauthClient');
				$oauthMock->expects($this->once())
					->method('getClientInfo')->willReturn([
						'id' => $oauthClientId,
						'nextcloud_oauth_client_name' => 'Openproject Client',
						'openproject_redirect_uri' => 'https://openproject.test/oauth/callback',
						'nextcloud_client_id' => 'client_id_existing',
						'nextcloud_client_secret' => 'client_secret_existing',
					]);
			} else {
				$oauthMock->expects($this->never())
					->method('getClientInfo');
				$oauthMock->expects($this->once())
					->method('createNcOauthClient')->willReturn([
						'id' => '2',
						'nextcloud_oauth_client_name' => 'Openproject Client',
						'openproject_redirect_uri' => 'https://openproject.test/oauth/callback',
						'nextcloud_client_id' => 'client_id',
						'nextcloud_client_secret' => 'client_secret',
					]);
			}
		} else {
			$oauthMock->expects($this->never())
				->method('createNcOauthClient');
			$oauthMock->expects($this->never())
				->method('getClientInfo');
		}

		$openprojectAPIServiceMock = $this->createMock(OpenProjectAPIService::class);
		$settingsServiceMock = $this->createMock(SettingsService::class);
		$settingsServiceMock->expects($this->once())
			->method('validateAdminSettingsForm');

		$constructArgs = [
			'config' => $configMock,
			'oauthService' => $oauthMock,
			'settingsService' => $settingsServiceMock,
			'openprojectAPIService' => $openprojectAPIServiceMock,
			'userManager' => $userManagerMock,
		];
		$constructArgs = $this->getConfigControllerConstructArgs($constructArgs);
		$configController = new ConfigController(...$constructArgs);

		$response = $configController->updateIntegration($settings);
		$data = $response->getData();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertArrayHasKey('status', $data);

		foreach ($responseProps as $prop) {
			$this->assertArrayHasKey($prop, $data);
		}
		if ($authMethod === Application::AUTH_METHOD_OIDC) {
			$this->assertArrayNotHasKey('nextcloud_oauth_client_name', $data);
			$this->assertArrayNotHasKey('nextcloud_client_id', $data);
			$this->assertArrayNotHasKey('nextcloud_client_secret', $data);
			$this->assertArrayNotHasKey('openproject_redirect_uri', $data);
		} else {
			$this->assertArrayHasKey('nextcloud_oauth_client_name', $data);
			$this->assertArrayHasKey('nextcloud_client_id', $data);
			$this->assertArrayHasKey('nextcloud_client_secret', $data);
			$this->assertArrayHasKey('openproject_redirect_uri', $data);
		}
	}
}
