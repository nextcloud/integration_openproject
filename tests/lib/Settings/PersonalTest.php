<?php
/**
 * Nextcloud - OpenProject
 *
 *
 * @author Kiran Parajuli <kiran@jankaritech.com>
 * @copyright Kiran Parajuli 2022
 */

namespace OCA\OpenProject\Settings;

use OCP\AppFramework\Services\IInitialState;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PersonalTest extends TestCase {
	/**
	 * @var Personal
	 */
	private $setting;

	/**
	 * @var MockObject | IConfig
	 */
	private $config;

	/**
	 * @var MockObject | IInitialState
	 */
	private $initialState;

	/**
	 * @var MockObject | IURLGenerator
	 */
	private $url;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
		$this->initialState = $this->getMockBuilder(IInitialState::class)->getMock();
		$this->url = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$this->setting = new Personal($this->config, $this->initialState, $this->url, "testUser");
	}

	/**
	 * @return array<mixed>
	 */
	public function dataTestGetForm(): array {
		return [
			[
				// valid dataset
				"clientId" => 'some-client-id',
				"clientSecret" => 'some-client-secret',
				"oauthInstanceUrl" => 'http://some.url',
				"expectedRequestUrl" => 'http://some.url/'
					. 'oauth/authorize'
					. '?client_id=' . 'some-client-id'
					.'&redirect_uri=' . urlencode('http://redirect.url/test/')
					. '&response_type=code',
			],
			[
				// dataset with empty client secret
				"clientId" => 'some-client-id',
				"clientSecret" => '',
				"oauthInstanceUrl" => 'http://some.url',
				"expectedRequestUrl" => '',
			],
			[
				// dataset with invalid oauth instance url
				"clientId" => 'some-client-id',
				"clientSecret" => 'some-secret',
				"oauthInstanceUrl" => 'http:/',
				"expectedRequestUrl" => '',
			],
		];
	}

	/**
	 * @dataProvider dataTestGetForm
	 *
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param string $oauthInstanceUrl
	 * @param string $expectedRequestUrl
	 * @return void
	 */
	public function testGetForm(
		string $clientId, string $clientSecret, string $oauthInstanceUrl, string $expectedRequestUrl
	) {
		$this->config
			->method('getUserValue')
			->withConsecutive(
				['testUser', 'integration_openproject', 'token'],
				['testUser', 'integration_openproject', 'user_name'],
				['testUser', 'integration_openproject', 'search_enabled', '0'],
				['testUser', 'integration_openproject', 'notification_enabled', '0'],
				['testUser', 'integration_openproject', 'navigation_enabled', '0'],
			)
			->willReturnOnConsecutiveCalls(
				'some-token',
				'some-username',
				'0', '0', '0'
			);
		$this->config
			->method('getAppValue')
			->withConsecutive(
				['integration_openproject', 'client_id'],
				['integration_openproject', 'client_secret'],
				['integration_openproject', 'oauth_instance_url'],
				['integration_openproject', 'client_id'],
				['integration_openproject', 'oauth_instance_url'],
			)
			->willReturnOnConsecutiveCalls(
				$clientId,
				$clientSecret,
				$oauthInstanceUrl,
				$clientId,
				$oauthInstanceUrl,
			);

		$this->url
			->method('linkToRouteAbsolute')
			->with('integration_openproject.config.oauthRedirect')
			->willReturn('http://redirect.url/test/');

		$this->initialState
			->method('provideInitialState')
			->with('user-config', [
				'token' => 'some-token',
				'user_name' => 'some-username',
				'search_enabled' => false,
				'notification_enabled' => false,
				'navigation_enabled' => false,
				'request_url' => $expectedRequestUrl === '' ? false : $expectedRequestUrl,
			]);

		$form = $this->setting->getForm();
		$expected = new TemplateResponse('integration_openproject', 'personalSettings');
		$this->assertEquals($expected, $form);
	}
}
