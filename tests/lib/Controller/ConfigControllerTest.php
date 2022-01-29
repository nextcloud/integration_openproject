<?php

namespace OCA\OpenProject\Controller;

use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class ConfigControllerTest extends TestCase
{

	/**
	 * @var IL10N
	 */
	private IL10N $l;

	/**
	 * @var IConfig
	 */
	private IConfig $configMock;

	/**
	 * @return void
	 * @before
	 */
	function setUpMocks(): void {
		$apiServiceMock = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$apiServiceMock
			->method('request')
			->with(
				'http://openproject.org',
				'oAuthAccessToken',
				'oauth',
				'oAuthRefreshToken',
				'clientID',
				'clientSecret',
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

		$this->configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$this->configMock
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
			)->willReturnOnConsecutiveCalls('clientID', 'clientSecret', 'clientID', 'clientSecret');
		$this->configMock
			->method('getUserValue')
			->withConsecutive(
				['testUser', 'integration_openproject', 'oauth_state'],
				['testUser', 'integration_openproject', 'redirect_uri'],
				['testUser', 'integration_openproject', 'url'],
				['testUser', 'integration_openproject', 'token_type'],
				['testUser', 'integration_openproject', 'refresh_token'],
				['testUser', 'integration_openproject', 'url'],
			)
			->willReturnOnConsecutiveCalls(
				'randomString',
				'http://redirect.back.to.here/some/url',
				'http://openproject.org',
				'oauth',
				'oAuthRefreshToken',
				'http://openproject.org'
			);

		$this->configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$this->configMock,
			$this->createMock(IURLGenerator::class),
			$this->l,
			$apiServiceMock,
			'testUser'
		);
	}

	public function testOauthRedirect() {
		$result = $this->configController->oauthRedirect('code','randomString');
		$this->assertSame('?openprojectToken=success', $result->getRedirectURL());
	}

	public function testOauthRedirectWrongState() {
		$result = $this->configController->oauthRedirect('code','stateNotSameAsSaved');
		$this->assertSame('?openprojectToken=error&message=Error+during+OAuth+exchanges', $result->getRedirectURL());
	}

	public function badOAuthResponseDataProvider() {
		return [
			[
				['error' => 'something went wrong'],
				'?openprojectToken=error&message=Error+getting+OAuth+access+token.+something+went+wrong'
			],
			[
				[],
				'?openprojectToken=error&message=Error+getting+OAuth+access+token.'
			],
			[   // access token given but no refresh token
				['access_token' => '123'],
				'?openprojectToken=error&message=Error+getting+OAuth+refresh+token.'
			],
			[   // access token & error given but no refresh token
				['access_token' => '123', 'error' => 'issue'],
				'?openprojectToken=error&message=Error+getting+OAuth+refresh+token.+issue'
			],
			[   //refresh token given but no access token
				['refresh_token' => '123'],
				'?openprojectToken=error&message=Error+getting+OAuth+access+token.'
			],
			[   //refresh token & error given but no access token
				['refresh_token' => '123', 'error' => 'issue'],
				'?openprojectToken=error&message=Error+getting+OAuth+access+token.+issue'
			]
		];
	}

	/**
	 * @return void
	 * @dataProvider badOAuthResponseDataProvider
	 */
	public function testOauthNoAccessTokenInResponse($oauthResponse, $expectedRedirect) {
		$apiServiceMock = $this->getMockBuilder(OpenProjectAPIService::class)
			->disableOriginalConstructor()
			->getMock();

		$apiServiceMock
			->method('requestOAuthAccessToken')
			->willReturn($oauthResponse);

		$configController = new ConfigController(
			'integration_openproject',
			$this->createMock(IRequest::class),
			$this->configMock,
			$this->createMock(IURLGenerator::class),
			$this->l,
			$apiServiceMock,
			'testUser'
		);
		$result = $configController->oauthRedirect('code','randomString');
		$this->assertSame($expectedRedirect, $result->getRedirectURL());
	}
}
