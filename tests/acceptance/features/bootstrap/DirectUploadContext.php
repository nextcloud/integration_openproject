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
	private function getLastCreatedDirectUploadToken():string {
		return $this->createdDirectUploadTokens[array_key_last($this->createdDirectUploadTokens)];
	}

	/**
	 * @When /^user "([^"]*)" sends a POST request to the direct\-upload endpoint with the ID of "(.*)"$/
	 */
	public function userSendsAGETRequestToTheEndpointWithTheFileIdOf(
		string $user, string $elementName
	): void {
		$elementId = $this->featureContext->getIdOfElement($user, $elementName);
		$data = json_encode(array('folder_id' => $elementId));
		$this->sendRequestToDirectUploadTokenEndpoint($user, $data);
	}

	/**
	 * @When /^user "([^"]*)" sends a POST request to the direct\-upload endpoint with the ID "(.*)"$/
	 */
	public function userSendsAGETRequestToTheEndpointWithTheId(
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
		$this->userSendsAGETRequestToTheEndpointWithTheFileIdOf($user, $elementName);
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
	 * @param string $endpoint
	 * @param TableNode<mixed> $formData
	 * @return void
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function anonymousUserSendsAMultipartFormDataPOSTRequestToTheEndpointWith(
		string $endpoint, TableNode $formData
	): void {
		$endpoint = $this->replaceInlineCodes($endpoint);
		$this->featureContext->verifyTableNodeRows($formData, ['file_name', 'data']);

		$formDataHash = $formData->getRowsHash();
		$data = [
			'name' => 'direct-upload',
			'contents' => $formDataHash['data'],
			'filename' => trim($formDataHash['file_name'], '"')
		];

		$this->featureContext->sendRequestsToAppEndpoint(
			null,
			null,
			'POST',
			$endpoint,
			$data
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

	private function replaceInlineCodes(string $input): string {
		return str_replace(
			"%last-created-direct-upload-token%",
			$this->getLastCreatedDirectUploadToken(),
			$input
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
		/** @phpstan-ignore-next-line */
		$this->featureContext = $environment->getContext('FeatureContext');
	}
}
