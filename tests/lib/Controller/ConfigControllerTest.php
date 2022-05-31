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
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConfigControllerTest extends TestCase {

	/**
	 * @var IL10N
	 */
	private $l;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var ConfigController
	 */
	private $configController;

	/**
	 * @param string $codeVerifier The string that should be used as code_verifier
	 * @param string $clientSecret The string that should be used as client_secret
	 * @return IConfig|\PHPUnit\Framework\MockObject\MockObject
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
				['testUser', 'integration_openproject', 'refresh_token'],
			)
			->willReturnOnConsecutiveCalls(
				'randomString',
				$codeVerifier,
				'oAuthRefreshToken',
			);
		return $configMock;
	}

	/**
	 * @return void
	 * @before
	 */
	public function setUpMocks(): void {
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

		$this->l = $this->createMock(IL10N::class);
		$this->l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($string, $args) {
				return vsprintf($string, $args);
			});
		$this->userManager = $this->createMock(IUserManager::class);

		$this->configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$this->getConfigMock(str_repeat("A", 128), str_repeat("S", 50)),
			$this->createMock(IURLGenerator::class),
			$this->userManager,
			$this->l,
			$apiServiceMock,
			$this->createMock(LoggerInterface::class),
			$this->createMock(OauthService::class),
			'testUser'
		);
	}

	/**
	 * @return void
	 */
	public function testOauthRedirect() {
		$result = $this->configController->oauthRedirect('code', 'randomString');
		$this->assertSame('?openprojectToken=success', $result->getRedirectURL());
	}

	/**
	 * @return void
	 */
	public function testOauthRedirectWrongState() {
		$result = $this->configController->oauthRedirect('code', 'stateNotSameAsSaved');
		$this->assertSame('?openprojectToken=error&message=Error+during+OAuth+exchanges', $result->getRedirectURL());
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
		if ($valid) {
			$loggerMock->expects($this->never())
				->method('error');
		} else {
			$loggerMock->expects($this->once())
				->method('error');
		}
		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$this->getConfigMock($codeVerifier, str_repeat("S", 50)),
			$this->createMock(IURLGenerator::class),
			$this->createMock(IUserManager::class),
			$this->l,
			$this->createMock(OpenProjectAPIService::class),
			$loggerMock,
			$this->createMock(OauthService::class),
			'testUser'
		);
		$result = $configController->oauthRedirect('code', 'randomString');

		if ($valid) {
			// code verifier the secret is valid, we get an error because the token request is not mocked
			$this->assertSame(
				'?openprojectToken=error&message=Error+getting+OAuth+access+token',
				$result->getRedirectURL()
			);
		} else {
			$this->assertSame(
				'?openprojectToken=error&message=Error+during+OAuth+exchanges',
				$result->getRedirectURL()
			);
		}
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
		if ($valid) {
			$loggerMock->expects($this->never())
				->method('error');
		} else {
			$loggerMock->expects($this->once())
				->method('error');
		}
		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$this->getConfigMock(str_repeat("A", 128), $clientSecret),
			$this->createMock(IURLGenerator::class),
			$this->createMock(IUserManager::class),
			$this->l,
			$this->createMock(OpenProjectAPIService::class),
			$loggerMock,
			$this->createMock(OauthService::class),
			'testUser'
		);
		$result = $configController->oauthRedirect('code', 'randomString');

		if ($valid) {
			// even the secret is valid, we get an error because the token request is not mocked
			$this->assertSame(
				'?openprojectToken=error&message=Error+getting+OAuth+access+token',
				$result->getRedirectURL()
			);
		} else {
			$this->assertSame(
				'?openprojectToken=error&message=Error+during+OAuth+exchanges',
				$result->getRedirectURL()
			);
		}
	}

	/**
	 * @return array<mixed>
	 */
	public function badOAuthResponseDataProvider() {
		return [
			[
				['error' => 'something went wrong'],
				'?openprojectToken=error&message=Error+getting+OAuth+access+token.+something+went+wrong'
			],
			[
				[],
				'?openprojectToken=error&message=Error+getting+OAuth+access+token'
			],
			[   // access token given but no refresh token
				['access_token' => '123'],
				'?openprojectToken=error&message=Error+getting+OAuth+refresh+token'
			],
			[   // access token & error given but no refresh token
				['access_token' => '123', 'error' => 'issue'],
				'?openprojectToken=error&message=Error+getting+OAuth+refresh+token.+issue'
			],
			[   //refresh token given but no access token
				['refresh_token' => '123'],
				'?openprojectToken=error&message=Error+getting+OAuth+access+token'
			],
			[   //refresh token & error given but no access token
				['refresh_token' => '123', 'error' => 'issue'],
				'?openprojectToken=error&message=Error+getting+OAuth+access+token.+issue'
			]
		];
	}

	/**
	 * @return void
	 * @param array<string> $oauthResponse
	 * @param string $expectedRedirect
	 * @dataProvider badOAuthResponseDataProvider
	 */
	public function testOauthNoAccessTokenInResponse($oauthResponse, $expectedRedirect) {
		$apiServiceMock = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$apiServiceMock
			->method('requestOAuthAccessToken')
			->willReturn($oauthResponse);

		/**
		 * @var ConfigController
		 */
		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$this->getConfigMock(str_repeat("A", 128), str_repeat("S", 50)),
			$this->createMock(IURLGenerator::class),
			$this->createMock(IUserManager::class),
			$this->l,
			$apiServiceMock,
			$this->createMock(LoggerInterface::class),
			$this->createMock(OauthService::class),
			'testUser'
		);
		$result = $configController->oauthRedirect('code', 'randomString');
		$this->assertSame($expectedRedirect, $result->getRedirectURL());
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
				['integration_openproject', 'nc_oauth_client_id',''],
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url']
			)
			->willReturnOnConsecutiveCalls(
				'123',
				$credsToUpdate['client_id'],
				$credsToUpdate['client_secret'],
				$credsToUpdate['oauth_instance_url']
			);
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
