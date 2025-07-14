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
			'groupManager' => $this->createMock(IGroupManager::class),
			'secureRandom' => $this->createMock(ISecureRandom::class),
			'subAdmin' => $this->createMock(ISubAdmin::class),
			'settingsService' => new SettingsService(),
			'userId' => 'testUser'
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
		$mock = $this->getMockBuilder(ConfigController::class)
			->setConstructorArgs($constructArgs)
			->onlyMethods($mockMethods)
			->getMock();
		return $mock;
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
		$configMock->expects($this->exactly(2))
			->method('setUserValue')
			->withConsecutive(
				['testUser', 'integration_openproject', 'oauth_connection_result', 'error'],
				['testUser', 'integration_openproject', 'oauth_connection_error_message', $expectedErrorMessage],
			);

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
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'openproject_client_id' => '$client_id',
					'openproject_client_secret' => '$client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				true
			],
			[
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
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
			->expects($this->exactly(4))
			->method('setAppValue')
			->withConsecutive(
				['integration_openproject', 'authorization_method', $credsToUpdate['authorization_method']],
				['integration_openproject', 'openproject_client_id', $credsToUpdate['openproject_client_id']],
				['integration_openproject', 'openproject_client_secret', $credsToUpdate['openproject_client_secret']],
				['integration_openproject', 'openproject_instance_url', $credsToUpdate['openproject_instance_url']]
			);
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url', ''],
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'nc_oauth_client_id', ''],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
				['integration_openproject', 'authorization_method'],
				['integration_openproject', 'openproject_instance_url'],
				['integration_openproject', 'fresh_project_folder_setup'],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
			)
			->willReturnOnConsecutiveCalls(
				'http://localhost:3000',
				OpenProjectAPIService::AUTH_METHOD_OAUTH,
				'',
				'',
				'123',
				'',
				OpenProjectAPIService::AUTH_METHOD_OAUTH,
				$credsToUpdate['openproject_instance_url'],
				false,
				$credsToUpdate['openproject_client_id'],
				$credsToUpdate['openproject_client_secret'],
			);
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
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'test-oidc-provider',
					'targeted_audience_client_id' => 'test-client',
					'openproject_instance_url' => 'http://openproject.com'
				],
				true
			],
			[
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => '',
					'targeted_audience_client_id' => 'test-client',
					'openproject_instance_url' => 'http://openproject.com'
				],
				false
			],
			[
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => '',
					'targeted_audience_client_id' => '',
					'openproject_instance_url' => 'http://openproject.com'
				],
				false
			],
			[
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
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
		$configMock
			->expects($this->exactly(4))
			->method('setAppValue')
			->withConsecutive(
				['integration_openproject', 'authorization_method', $credsToUpdate['authorization_method']],
				['integration_openproject', 'oidc_provider', $credsToUpdate['oidc_provider']],
				['integration_openproject', 'targeted_audience_client_id', $credsToUpdate['targeted_audience_client_id']],
				['integration_openproject', 'openproject_instance_url', $credsToUpdate['openproject_instance_url']]
			);
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url', ''],
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'oidc_provider'],
				['integration_openproject', 'targeted_audience_client_id'],
				['integration_openproject', 'nc_oauth_client_id', ''],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
				['integration_openproject', 'authorization_method'],
				['integration_openproject', 'openproject_instance_url'],
				['integration_openproject', 'fresh_project_folder_setup'],
				['integration_openproject', 'oidc_provider'],
				['integration_openproject', 'sso_provider_type'],
				['integration_openproject', 'targeted_audience_client_id'],
				['integration_openproject', 'token_exchange'],
				['integration_openproject', 'openproject_instance_url']
			)
			->willReturnOnConsecutiveCalls(
				'http://localhost:3000',
				OpenProjectAPIService::AUTH_METHOD_OAUTH,
				'',
				'',
				'123',
				'',
				OpenProjectAPIService::AUTH_METHOD_OIDC,
				$credsToUpdate['openproject_instance_url'],
				false,
				$credsToUpdate['oidc_provider'],
				SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE,
				$credsToUpdate['targeted_audience_client_id'],
				true,
				$credsToUpdate['openproject_instance_url']

			);
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
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'old-openproject_client_id',
					'openproject_client_secret' => 'old-openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				true,
				'change'
			],
			[ // only client id changes so delete user values but don't change the oAuth Client
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'old-openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				true,
				false
			],
			[ // only client secret changes so delete user values but don't change the oAuth Client
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'old-openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				true,
				false
			],
			[ //only the openproject_instance_url changes so don't delete the user values but change the oAuth Client
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'openproject_client_id',
					'openproject_client_secret' => 'openproject_client_secret',
					'openproject_instance_url' => 'http://openproject.com',
				],
				false,
				'change'
			],
			[ //everything cleared
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
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
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
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
	 * @group ignoreWithPHP8.0
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
					['integration_openproject', 'authorization_method', ''],
					['integration_openproject', 'openproject_client_id'],
					['integration_openproject', 'openproject_client_secret'],
					['integration_openproject', 'nc_oauth_client_id', ''],
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
					['integration_openproject', 'authorization_method', ''],
					['integration_openproject', 'openproject_client_id'],
					['integration_openproject', 'openproject_client_secret'],
					['integration_openproject', 'openproject_instance_url'],
				)
				->willReturnOnConsecutiveCalls(
					$oldCreds['openproject_instance_url'],
					$oldCreds['authorization_method'],
					$oldCreds['openproject_client_id'],
					$oldCreds['openproject_client_secret'],
					'123',
					'',
					OpenProjectAPIService::AUTH_METHOD_OAUTH,
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
					['integration_openproject', 'authorization_method', ''],
					['integration_openproject', 'openproject_client_id'],
					['integration_openproject', 'openproject_client_secret'],
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
					['integration_openproject', 'authorization_method', ''],
					['integration_openproject', 'openproject_client_id'],
					['integration_openproject', 'openproject_client_secret'],
					['integration_openproject', 'openproject_instance_url']
				)
				->willReturnOnConsecutiveCalls(
					$oldCreds['openproject_instance_url'],
					$oldCreds['authorization_method'],
					$oldCreds['openproject_client_id'],
					$oldCreds['openproject_client_secret'],
					'',
					OpenProjectAPIService::AUTH_METHOD_OAUTH,
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
		$actualCount = 1;
		$userManager = \OC::$server->getUserManager();
		$count = 0;
		$function = function () use (&$count) {
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
					'authorization_method' => null,
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
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
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
	 * @group ignoreWithPHP8.0
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
			'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
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
					['integration_openproject', 'authorization_method', ''],
					['integration_openproject', 'openproject_client_id', ''],
					['integration_openproject', 'openproject_client_secret', ''],
					['integration_openproject', 'nc_oauth_client_id', ''],
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''], // for user
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''], // for user
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''], // for the last check
					['integration_openproject', 'authorization_method', ''],
					['integration_openproject', 'openproject_instance_url'],
					['integration_openproject', 'fresh_project_folder_setup'],
					['integration_openproject', 'openproject_client_id'],
					['integration_openproject', 'openproject_client_secret'],
					['integration_openproject', 'openproject_instance_url'],
				)
				->willReturnOnConsecutiveCalls(
					$oldAdminConfig['openproject_instance_url'],
					$oldAdminConfig['authorization_method'],
					$oldAdminConfig['openproject_client_id'],
					$oldAdminConfig['openproject_client_secret'],
					'',
					'',
					'',
					'',
					OpenProjectAPIService::AUTH_METHOD_OAUTH,
					$newConfig['openproject_instance_url'],
					false,
					$newConfig['openproject_client_id'],
					$newConfig['openproject_client_secret'],
					$newConfig['openproject_instance_url'],
				);
		} else {
			$configMock
				->method('getAppValue')
				->withConsecutive(
					['integration_openproject', 'openproject_instance_url', ''],
					['integration_openproject', 'authorization_method', ''],
					['integration_openproject', 'openproject_client_id', ''],
					['integration_openproject', 'openproject_client_secret', ''],
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
					['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
					['integration_openproject', 'authorization_method', ''],
					['integration_openproject', 'openproject_instance_url'],
					['integration_openproject', 'fresh_project_folder_setup'],
					['integration_openproject', 'openproject_client_id'],
					['integration_openproject', 'openproject_client_secret'],
					['integration_openproject', 'openproject_instance_url'],
				)
				->willReturnOnConsecutiveCalls(
					$oldAdminConfig['openproject_instance_url'],
					$oldAdminConfig['authorization_method'],
					$oldAdminConfig['openproject_client_id'],
					$oldAdminConfig['openproject_client_secret'],
					'',
					'',
					'',
					OpenProjectAPIService::AUTH_METHOD_OAUTH,
					$newConfig['openproject_instance_url'],
					false,
					$newConfig['openproject_client_id'],
					$newConfig['openproject_client_secret'],
					$newConfig['openproject_instance_url'],
				);
		}
		$configMock
			->method('setAppValue')
			->withConsecutive(
				['integration_openproject', 'authorization_method', $newConfig['authorization_method']],
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
	 * @group ignoreWithPHP8.0
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
			'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
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

		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url', ''],
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'openproject_client_id', ''],
				['integration_openproject', 'openproject_client_secret', ''],
				['integration_openproject', 'nc_oauth_client_id', ''],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', ''], // for the last check
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'openproject_instance_url'],
				['integration_openproject', 'fresh_project_folder_setup'],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url'],
			)
			->willReturnOnConsecutiveCalls(
				$oldAdminConfig['openproject_instance_url'],
				$oldAdminConfig['authorization_method'],
				$oldAdminConfig['openproject_client_id'],
				$oldAdminConfig['openproject_client_secret'],
				'',
				$errorCode,
				OpenProjectAPIService::AUTH_METHOD_OAUTH,
				$newAdminConfig['openproject_instance_url'],
				false,
				$newAdminConfig['openproject_client_id'],
				$newAdminConfig['openproject_client_secret'],
				$newAdminConfig['openproject_instance_url'],
			);
		$configMock
			->method('setAppValue')
			->withConsecutive(
				['integration_openproject', 'authorization_method', $newAdminConfig['authorization_method']],
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
			'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
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
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'openproject_client_id', ''],
				['integration_openproject', 'openproject_client_secret', ''],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', '']
			)
			->willReturnOnConsecutiveCalls(
				$oldAdminConfig['openproject_instance_url'],
				$oldAdminConfig['authorization_method'],
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
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url', ''],
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'openproject_instance_url'],
				['integration_openproject', 'fresh_project_folder_setup'],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
			)
			->willReturnOnConsecutiveCalls(
				'http://localhost:3000',
				OpenProjectAPIService::AUTH_METHOD_OAUTH,
				'',
				OpenProjectAPIService::AUTH_METHOD_OAUTH,
				'http://localhost:3000',
				false,
				'some_cilent_id',
				'some_cilent_secret',
			);
		$secureRandomMock = $this->getMockBuilder(ISecureRandom::class)->getMock();
		$secureRandomMock
			->method('generate')
			->with(15, ISecureRandom::CHAR_ALPHANUMERIC.ISecureRandom::CHAR_SYMBOLS)
			->willReturn('thisisapassword123');
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
		$groupManagerMock = $this->getMockBuilder(IGroupManager::class)->getMock();
		$groupManagerMock
			->method('createGroup')
			->with(Application::OPEN_PROJECT_ENTITIES_NAME)
			->willReturn($groupMock);
		$subAdminManagerMock = $this->getMockBuilder(ISubAdmin::class)->getMock();
		$subAdminManagerMock
			->method('createSubAdmin')
			->with($userMock, $groupMock);

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManagerMock,
			'openprojectAPIService' => $service,
			'groupManager' => $groupManagerMock,
			'secureRandom' => $secureRandomMock,
			'subAdmin' => $subAdminManagerMock,
			'userId' => 'admin'
		]);
		$configController = new ConfigController(...$constructArgs);

		$result = $configController->setAdminConfig([
			"authorization_method" => OpenProjectAPIService::AUTH_METHOD_OAUTH,
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
		$groupManagerMock = $this->getMockBuilder(IGroupManager::class)->getMock();
		$secureRandomMock = $this->getMockBuilder(ISecureRandom::class)->getMock();
		$subAdminManagerMock = $this->getMockBuilder(ISubAdmin::class)->getMock();

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManagerMock,
			'openprojectAPIService' => $service,
			'groupManager' => $groupManagerMock,
			'secureRandom' => $secureRandomMock,
			'subAdmin' => $subAdminManagerMock,
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
		$groupManagerMock = $this->getMockBuilder(IGroupManager::class)->getMock();
		$secureRandomMock = $this->getMockBuilder(ISecureRandom::class)->getMock();
		$subAdminManagerMock = $this->getMockBuilder(ISubAdmin::class)->getMock();

		$constructArgs = $this->getConfigControllerConstructArgs([
			'config' => $configMock,
			'userManager' => $userManagerMock,
			'openprojectAPIService' => $service,
			'groupManager' => $groupManagerMock,
			'secureRandom' => $secureRandomMock,
			'subAdmin' => $subAdminManagerMock,
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
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'oidc_provider',
					'targeted_audience_client_id' => 'targeted_audience_client_id',
					'openproject_instance_url' => 'http://openproject.com',
				],
				false,
				'change'
			],
			[ // set info even if only 'targeted_audience_client_id' authorization settings are changed
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old_oidc_provider',
					'targeted_audience_client_id' => 'new_targeted_audience_client_id',
					'openproject_instance_url' => 'http://openproject.com',
				],
				false,
				'change'
			],
			[ // setinfo even if only 'oidc_provider' authorization settings are changed
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'new_oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://openproject.com',
				]
			],
			[ // set if authorization settings are empty string
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => '',
					'targeted_audience_client_id' => '',
					'openproject_instance_url' => 'http://openproject.com',
				]
			],
			[ // set if authorization settings are null
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => null,
					'targeted_audience_client_id' => null,
					'openproject_instance_url' => 'http://openproject.com',
				]
			],
		];
	}

	/**
	 * @group ignoreWithPHP8.0
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
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url', ''],
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'oidc_provider'],
				['integration_openproject', 'targeted_audience_client_id'],
				['integration_openproject', 'nc_oauth_client_id', ''],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'oidc_provider'],
				['integration_openproject', 'targeted_audience_client_id'],
				['integration_openproject', 'openproject_instance_url'],
			)
			->willReturnOnConsecutiveCalls(
				$oldCreds['openproject_instance_url'],
				$oldCreds['authorization_method'],
				$oldCreds['oidc_provider'],
				$oldCreds['targeted_audience_client_id'],
				'123',
				'',
				OpenProjectAPIService::AUTH_METHOD_OAUTH,
				$credsToUpdate['oidc_provider'],
				$credsToUpdate['targeted_audience_client_id'],
				$credsToUpdate['openproject_instance_url']
			);

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
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'openproject_client_id' => 'old-openproject_client_id',
					'openproject_client_secret' => 'old-openproject_client_secret',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'openproject_client_id' => '',
					'openproject_client_secret' => '',
					'openproject_instance_url' => 'http://old-openproject.com',
				]
			],
			[ // when resetting with OAUTH2 already configured, userdata gets deleted along with the nc client information
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
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
	 * @group ignoreWithPHP8.0
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

		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$oauthServiceMock = $this->createMock(OauthService::class);
		$oauthSettingsControllerMock = $this->getMockBuilder(SettingsController::class)
			->disableOriginalConstructor()
			->getMock();
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url', ''],
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'nc_oauth_client_id', ''],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'openproject_client_id'],
				['integration_openproject', 'openproject_client_secret'],
				['integration_openproject', 'openproject_instance_url'],
			)
			->willReturnOnConsecutiveCalls(
				$oldCreds['openproject_instance_url'],
				$oldCreds['authorization_method'],
				$oldCreds['openproject_client_id'],
				$oldCreds['openproject_client_secret'],
				'123',
				'123',
				'',
				OpenProjectAPIService::AUTH_METHOD_OAUTH,
				$credsToUpdate['openproject_client_id'],
				$credsToUpdate['openproject_client_secret'],
				$credsToUpdate['openproject_instance_url']
			);
		$oauthSettingsControllerMock
			->expects($this->once())
			->method('deleteClient')
			->with(123);
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
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
					'oidc_provider' => '',
					'targeted_audience_client_id' => '',
					'openproject_instance_url' => 'http://old-openproject.com',
				]
			],
			[ // when switching from oidc to oauth2, just the user information get deleted
				[
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'oidc_provider' => 'old-oidc_provider',
					'targeted_audience_client_id' => 'old-targeted_audience_client_id',
					'openproject_instance_url' => 'http://old-openproject.com',
				],
				[
					'authorization_method' => '',
					'oidc_provider' => '',
					'targeted_audience_client_id' => '',
					'openproject_instance_url' => '',
				]
			],
		];
	}

	/**
	 * @group ignoreWithPHP8.0
	 * @param array<string> $oldCreds
	 * @param array<string> $credsToUpdate
	 * @return void
	 * @dataProvider setAdminConfigForOIDCAlreadyConfigured
	 */
	public function testSetAdminConfigForOIDCAlreadyConfigured(
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
			->withConsecutive(
				['integration_openproject', 'openproject_instance_url', ''],
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'oidc_provider'],
				['integration_openproject', 'targeted_audience_client_id'],
				['integration_openproject', 'oPOAuthTokenRevokeStatus', ''],
				['integration_openproject', 'authorization_method', ''],
				['integration_openproject', 'oidc_provider'],
				['integration_openproject', 'targeted_audience_client_id'],
				['integration_openproject', 'openproject_instance_url'],
			)
			->willReturnOnConsecutiveCalls(
				$oldCreds['openproject_instance_url'],
				$oldCreds['authorization_method'],
				$oldCreds['oidc_provider'],
				$oldCreds['targeted_audience_client_id'],
				'',
				$credsToUpdate['authorization_method'],
				$credsToUpdate['oidc_provider'],
				$credsToUpdate['targeted_audience_client_id'],
				$credsToUpdate['openproject_instance_url']
			);
		$configMock
			->expects($this->exactly(2))
			->method('deleteUserValue')
			->withConsecutive(
				['test101', 'integration_openproject', 'user_id'],
				['test101', 'integration_openproject', 'user_name']
			);

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
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
				],
			],
			"complete oidc: NC Hub without 'oidc_provider'" => [
				'settings' => [
					...$defaultSettings,
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'sso_provider_type' => SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
				],
				'expectedSettings' => [
					...$defaultSettings,
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'sso_provider_type' => SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
					'oidc_provider' => SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_LABEL,
				],
			],
			"complete oidc: NC Hub with empty 'oidc_provider'" => [
				'settings' => [
					...$defaultSettings,
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'sso_provider_type' => SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
					'oidc_provider' => '',
				],
				'expectedSettings' => [
					...$defaultSettings,
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'sso_provider_type' => SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
					'oidc_provider' => SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_LABEL,
				],
			],
			"complete oidc: NC Hub with 'oidc_provider'" => [
				'settings' => [
					...$defaultSettings,
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'sso_provider_type' => SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
					'oidc_provider' => 'test',
				],
				'expectedSettings' => [
					...$defaultSettings,
					'authorization_method' => OpenProjectAPIService::AUTH_METHOD_OIDC,
					'sso_provider_type' => SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE,
					'targeted_audience_client_id' => 'test',
					'oidc_provider' => 'test',
				],
			],
		];
	}

	/**
	 * @group ignoreWithPHP8.0
	 * @param array<string> $oldCreds
	 * @param array<string> $credsToUpdate
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
}
