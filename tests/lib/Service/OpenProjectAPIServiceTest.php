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
use OC\Http\Client\Client;
use OCP\ICertificateManager;
use OCP\IConfig;
use OCP\ILogger;
use PhpPact\Consumer\InteractionBuilder;
use PhpPact\Consumer\Model\ConsumerRequest;
use PhpPact\Consumer\Model\ProviderResponse;
use PhpPact\Standalone\MockService\MockServerEnvConfig;
use PHPUnit\Framework\TestCase;

class OpenProjectAPIServiceTest extends TestCase
{
	/**
	 * @var InteractionBuilder
	 */
	private $builder;

	/**
	 * @var OpenProjectAPIService
	 */
	private $service;

	private $clientId = 'U3V9_l262pNSENBnsqD2Uwylv5hQWCQ8lFPjCvGPbQc';
	private $clientSecret = 'P5eu43P8YFFM9jeZKWcrpbskAUgHUBGYFQKB_8aeBtU';
	private $workPackagesPath = '/api/v3/work_packages';
	/**
	 * @return void
	 * @before
	 */
	function setupMockServer(): void {
		$this->builder = new InteractionBuilder(new MockServerEnvConfig());
	}

	/**
	 * @return void
	 * @before
	 */
	function setUpMocks(): void {
		/** @var IConfig $config */
		$config = $this->createMock(IConfig::class);
		/** @var ICertificateManager $certificateManager */
		$certificateManager = $this->getMockBuilder('\OCP\ICertificateManager')->getMock();
		$certificateManager->method('getAbsoluteBundlePath')->willReturn('/');
		$logger = $this->createMock(ILogger::class);

		$client = new GuzzleClient();
		$ocClient = new Client(
			$config,
			$logger,
			$certificateManager,
			$client,
			$this->createMock(\OC\Http\Client\LocalAddressChecker::class)
		);
		$clientService = $this->getMockBuilder('\OCP\Http\Client\IClientService')->getMock();
		$clientService->method('newClient')->willReturn($ocClient);
		$this->service = new OpenProjectAPIService(
			'integration_openproject',
			$this->createMock(\OCP\IUserManager::class),
			$this->createMock(\OCP\IAvatarManager::class),
			$this->createMock(\Psr\Log\LoggerInterface::class),
			$this->createMock(\OCP\IL10N::class),
			$this->createMock(\OCP\IConfig::class),
			$this->createMock(\OCP\Notification\IManager::class),
			$clientService
		);
	}

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
	 */
	public function testValidateOpenProjectURL(string $url, bool $expected) {
		$result = OpenProjectAPIService::validateOpenProjectURL($url);
		$this->assertSame($expected, $result);
	}

	public function testRequestUsingOAuthToken() {
		$consumerRequest = new ConsumerRequest();
		$consumerRequest
			->setMethod('GET')
			->setPath($this->workPackagesPath)
			->setHeaders(["Authorization" =>  "Bearer 1234567890"]);

		$providerResponse = new ProviderResponse();
		$providerResponse
			->setStatus(200)
			->addHeader('Content-Type', 'application/json')
			->setBody(["_embedded" => ["elements" => []]]);

		$this->builder
			->uponReceiving('an OAuth GET request to /work_packages')
			->with($consumerRequest)
			->willRespondWith($providerResponse);

		$result = $this->service->request('http://localhost:7200', '1234567890', 'oauth', '', $this->clientId, $this->clientSecret, 'admin', 'work_packages');
		$this->assertSame(["_embedded" => ["elements" => []]], $result);

	}
	public function testRequestRefreshOAuthToken() {
		$consumerRequestInvalidOAuthToken = new ConsumerRequest();
		$consumerRequestInvalidOAuthToken
			->setMethod('GET')
			->setPath($this->workPackagesPath)
			->setHeaders(["Authorization" =>  "Bearer invalid"]);

		$providerResponseInvalidOAuthToken = new ProviderResponse();
		$providerResponseInvalidOAuthToken
			->setStatus(401)
			->addHeader('Content-Type', 'application/json');

		$this->builder
			->uponReceiving('an OAuth GET request to /work_packages with invalid OAuth Token')
			->with($consumerRequestInvalidOAuthToken)
			->willRespondWith($providerResponseInvalidOAuthToken);

		$refreshTokenRequest = new ConsumerRequest();
		$refreshTokenRequest
			->setMethod('POST')
			->setPath('/oauth/token')
			->setBody('client_id=' . $this->clientId . '&client_secret=' . $this->clientSecret . '&grant_type=refresh_token&refresh_token=myRefreshToken');

		$refreshTokenResponse = new ProviderResponse();
		$refreshTokenResponse
			->setStatus(200)
			->setBody(["access_token" => "new-Token"]);

		$this->builder->uponReceiving('a POST request to renew token')
			->with($refreshTokenRequest)
			->willRespondWith($refreshTokenResponse);

		$consumerRequestNewOAuthToken = new ConsumerRequest();
		$consumerRequestNewOAuthToken
			->setMethod('GET')
			->setPath($this->workPackagesPath)
			->setHeaders(["Authorization" =>  "Bearer new-Token"]);

		$providerResponseNewOAuthToken = new ProviderResponse();
		$providerResponseNewOAuthToken
			->setStatus(200)
			->addHeader('Content-Type', 'application/json')
			->setBody(["_embedded" => ["elements" => [['id' => 1], ['id' => 2]]]]);

		$this->builder
			->uponReceiving('an OAuth GET request to /work_packages with new Token')
			->with($consumerRequestNewOAuthToken)
			->willRespondWith($providerResponseNewOAuthToken);

		$result = $this->service->request('http://localhost:7200', 'invalid', 'oauth', 'myRefreshToken',  $this->clientId, $this->clientSecret, 'admin', 'work_packages');
		$this->assertSame(["_embedded" => ["elements" => [['id' => 1], ['id' => 2]]]], $result);
	}
}
