<?php

namespace OCA\OpenProject\Controller;

use OCA\OpenProject\Service\OauthService;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConfigControllerTest extends TestCase {

	/**
	 * @var IL10N
	 */
	private $l;

	/**
	 * @param string $codeVerifier The string that should be used as code_verifier
	 * @param string $clientSecret The string that should be used as client_secret
	 * @return IConfig|MockObject
	 */
	private function getConfigMock($codeVerifier, $clientSecret) {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url'],
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
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
				'{ page: "files" }',
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

	/**
	 * @param LoggerInterface|null $loggerMock
	 * @param IConfig|null $configMock
	 * @return ConfigController
	 */
	private function getConfigController($loggerMock = null, $configMock = null) {
		if ($configMock === null) {
			$configMock = $this->getConfigMock(str_repeat("A", 128), str_repeat("S", 50));
		}
		if ($loggerMock === null) {
			$loggerMock = $this->createMock(LoggerInterface::class);
		}
		$userManager = $this->createMock(IUserManager::class);

		$apiServiceMock = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$apiServiceMock
			->method('request')
			->with(
				'testUser',
				'users/me'
			)
			->willReturn(['lastName' => 'Himal', 'firstName' => 'Tripatti', 'id' => 1]);

		$apiServiceMock
			->method('requestOAuthAccessToken')
			->willReturn(['access_token' => 'oAuthAccessToken', 'refresh_token' => 'oAuthRefreshToken']);

		return new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$configMock,
			$this->createMock(IURLGenerator::class),
			$userManager,
			$this->l,
			$apiServiceMock,
			$loggerMock,
			$this->createMock(OauthService::class),
			'testUser'
		);
	}
	/**
	 * @return void
	 */
	public function testOauthRedirect() {
		$configMock = $this->getConfigMock(
			str_repeat("A", 128), str_repeat("S", 50)
		);
		$configMock
			->expects($this->exactly(5))
			->method('setUserValue')
			->withConsecutive(
				[$this->anything(),$this->anything(),$this->anything(),$this->anything()],
				[$this->anything(),$this->anything(),$this->anything(),$this->anything()],
				[$this->anything(),$this->anything(),$this->anything(),$this->anything()],
				[$this->anything(),$this->anything(),$this->anything(),$this->anything()],
				['testUser', 'integration_openproject', 'oauth_connection_result', 'success'],
			);
		$configController = $this->getConfigController(null, $configMock);
		$configController->oauthRedirect('code', 'randomString');
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
		$configController = $this->getConfigController(null, $configMock);
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
					'client_id' => '$client_id',
					'client_secret' => '$client_secret',
					'oauth_instance_url' => 'http://openproject.com',
				],
				true
			],
			[
				[
					'client_id' => '',
					'client_secret' => '$client_secret',
					'oauth_instance_url' => 'http://openproject.com',
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
		$count = 0;
		$function = function (IUser $user) use (&$count) {
			$count++;
			return null;
		};
		$userManager->callForAllUsers($function);
		$this->assertSame(1, $count, 'Expected to have only 1 user in the dB before this test');
		$user1 = $userManager->createUser('test101', 'test101');
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock
			->expects($this->exactly(3))
			->method('setAppValue')
			->withConsecutive(
				['integration_openproject', 'client_id', $credsToUpdate['client_id']],
				['integration_openproject', 'client_secret', $credsToUpdate['client_secret']],
				['integration_openproject', 'oauth_instance_url', $credsToUpdate['oauth_instance_url']]
			);
		$configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'oauth_instance_url', ''],
				['integration_openproject', 'nc_oauth_client_id', ''],
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url']
			)
			->willReturnOnConsecutiveCalls(
				'http://localhost:3000',
				'123',
				$credsToUpdate['client_id'],
				$credsToUpdate['client_secret'],
				$credsToUpdate['oauth_instance_url']
			);
		$oauthServiceMock = $this->createMock(OauthService::class);
		$oauthServiceMock->method('setClientRedirectUri')->with(123, 'http://openproject.com');
		$configMock
			->expects($this->exactly(12)) // 6 times for each user
			->method('deleteUserValue')
			->withConsecutive(
				['admin', 'integration_openproject', 'token'],
				['admin', 'integration_openproject', 'login'],
				['admin', 'integration_openproject', 'user_id'],
				['admin', 'integration_openproject', 'user_name'],
				['admin', 'integration_openproject', 'refresh_token'],
				['admin', 'integration_openproject', 'last_notification_check'],
				[$user1->getUID(), 'integration_openproject', 'token'],
				[$user1->getUID(), 'integration_openproject', 'login'],
				[$user1->getUID(), 'integration_openproject', 'user_id'],
				[$user1->getUID(), 'integration_openproject', 'user_name'],
				[$user1->getUID(), 'integration_openproject', 'refresh_token'],
				[$user1->getUID(), 'integration_openproject', 'last_notification_check'],
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
			'test101'
		);

		$result = $configController->setAdminConfig($credsToUpdate);

		$this->assertSame(
			["status" => $adminConfigStatus],
			$result->getData()
		);
		$user1->delete();
	}
}
