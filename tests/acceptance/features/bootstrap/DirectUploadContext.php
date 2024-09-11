<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;

class DirectUploadContext implements Context {
	/**
	 *
	 * @var FeatureContext
	 */
	private $featureContext;
	/**
	 * @var array<string>
	 */
	private array $createdDirectUploadTokens = [];
	public function getLastCreatedDirectUploadToken(): ?string {
		$lastKey = array_key_last($this->createdDirectUploadTokens);
		if ($lastKey !== null) {
			return $this->createdDirectUploadTokens[$lastKey];
		}
		return null;
	}

	/**
	 * @When /^user "([^"]*)" sends a POST request to the direct\-upload\-token endpoint with the ID of "(.*)"$/
	 */
	public function userSendsAPOSTRequestToTheEndpointWithTheFileIdOf(
		string $user, string $elementName
	): void {
		$elementId = $this->featureContext->getIdOfFileOrFolder($user, $elementName);
		$data = json_encode(array('folder_id' => $elementId));
		$this->sendRequestToDirectUploadTokenEndpoint($user, $data);
	}

	/**
	 * @When /^user "([^"]*)" sends a POST request to the direct\-upload\-token endpoint with the ID "(.*)"$/
	 */
	public function userSendsAPOSTRequestToTheEndpointWithTheId(
		string $user, string $folderId
	): void {
		$data = json_encode(array('folder_id' => $folderId));
		$this->sendRequestToDirectUploadTokenEndpoint($user, $data);
	}

	/**
	 * @Given /^user "([^"]*)" got a direct-upload token for "(.*)"$/
	 * @When /^user "([^"]*)" gets a direct-upload token for "(.*)"$/
	 */
	public function userGotADirectUploadTokenFor(
		string $user, string $elementName
	): void {
		$this->userSendsAPOSTRequestToTheEndpointWithTheFileIdOf($user, $elementName);
		$this->featureContext->theHttpStatusCodeShouldBe(200);
		$responseAsJson = json_decode(
			$this->featureContext->getResponse()->getBody()->getContents()
		);
		Assert::assertObjectHasAttribute(
			'token', $responseAsJson,
			'cannot find token in response'
		);
		$this->createdDirectUploadTokens[] = $responseAsJson->token;
	}

	/**
	 * @When /^an anonymous user sends a multipart form data POST request to the "([^"]*)" endpoint with:$/
	 *
	 * @psalm-suppress TooManyTemplateParams
	 *
	 * @param string $endpoint
	 * @param TableNode<mixed> $formData
	 * @return void
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function anonymousUserSendsAMultipartFormDataPOSTRequestToTheEndpointWith(
		string $endpoint, TableNode $formData
	): void {
		$endpoint = $this->featureContext->replaceInlineCodes($endpoint);
		$this->featureContext->verifyTableNodeRows(
			$formData,
			['file_name', 'data'],
			['overwrite']
		);

		$formDataHash = $formData->getRowsHash();
		$data = [
			[
				'name' => 'file',
				'contents' => $formDataHash['data'],
				'filename' => trim($formDataHash['file_name'], '"')
			],
		];
		if (isset($formDataHash['overwrite'])) {
			$data[] = [
				'name' => 'overwrite',
				'contents' => $formDataHash['overwrite']
			];
		}
		# 1 second pause is required between the two file uploads in order to create the version of the file
		if (!empty($this->featureContext->lastUpLoadTime) && $this->featureContext->lastUpLoadTime >= time()) {
			sleep(1);
		}
		$this->featureContext->sendRequestsToAppEndpoint(
			null,
			null,
			'POST',
			$endpoint,
			$data
		);
	}

	/**
	 * @Given /^an anonymous user has sent a multipart form data POST request to the "([^"]*)" endpoint with:$/
	 *
	 * @psalm-suppress TooManyTemplateParams
	 *
	 * @param string $endpoint
	 * @param TableNode<mixed> $formData
	 * @return void
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function anAnonymousUserHasSentAMultipartFormDataPostRequestToTheEndpointWith(string $endpoint, TableNode $formData): void {
		$this->anonymousUserSendsAMultipartFormDataPOSTRequestToTheEndpointWith($endpoint, $formData);
		$this->featureContext->theHTTPStatusCodeShouldBe(201);
	}

	/**
	 * @Given /^the quota of user "([^"]*)" has been set to "([^"]*)"$/
	 */
	public function theQuotaOfUserHasBeenSetTo(string $user, string $quota):void {
		$body = [
			'key' => 'quota',
			'value' => $quota,
		];
		$response = $this->featureContext->sendOCSRequest(
			"/cloud/users/$user",
			"PUT",
			$this->featureContext->getAdminUsername(),
			$body
		);
		$this->featureContext->theHttpStatusCodeShouldBe(
			200, "could not set quota", $response
		);
	}

	/**
	 * @When /^an anonymous user sends an OPTIONS request to the "([^"]*)" endpoint with these headers:$/
	 *
	 * @psalm-suppress TooManyTemplateParams
	 *
	 * @param string $endpoint
	 * @param TableNode<mixed> $headersTable
	 * @return void
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 *
	 */
	public function anAnonymousUserSendsAnOptionsRequestToTheEndpointWithTheseHeaders(string $endpoint, TableNode $headersTable) {
		$endpoint = $this->featureContext->replaceInlineCodes($endpoint);
		$this->featureContext->verifyTableNodeColumns($headersTable, ['header', 'value']);
		$headers = [];
		foreach ($headersTable as $row) {
			$headers[$row['header']] = $row ['value'];
		}
		$this->featureContext->sendRequestsToAppEndpoint(
			null,
			null,
			'OPTIONS',
			$endpoint,
			null,
			$headers
		);
	}

	/**
	 * @Then /^all direct\-upload tokens should be different$/
	 */
	public function allDirectUploadTokensShouldBeDifferent():void {
		$uniqueTokensArray = array_unique(
			$this->createdDirectUploadTokens, SORT_STRING
		);
		Assert::assertEquals(
			count($uniqueTokensArray),
			count($this->createdDirectUploadTokens),
			"multiple tokens have the same value:\n" .
			print_r($this->createdDirectUploadTokens, true)
		);
	}


	private function sendRequestToDirectUploadTokenEndpoint(
		string $user, string $data
	): void {
		$this->featureContext->sendRequestsToAppEndpoint(
			$user,
			$this->featureContext->getRegularUserPassword(),
			'POST',
			'direct-upload-token',
			$data
		);
	}

	/**
	 * This will run before EVERY scenario.
	 * It will set the properties for this object.
	 *
	 * @BeforeScenario
	 *
	 * @param BeforeScenarioScope $scope
	 *
	 * @return void
	 */
	public function before(BeforeScenarioScope $scope):void {
		// Get the environment
		$environment = $scope->getEnvironment();

		// Get all the contexts you need in this context
		$this->featureContext = $environment->getContext('FeatureContext');
	}
}
