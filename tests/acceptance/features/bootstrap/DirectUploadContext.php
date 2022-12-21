<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use PHPUnit\Framework\Assert;

class DirectUploadContext implements Context {
	/**
	 *
	 * @var FeatureContext
	 */
	private $featureContext;
	private string $lastCreatedDirectUploadToken;

	/**
	 * @When /^user "([^"]*)" sends a GET request to the direct\-upload endpoint with the ID of "(.*)"$/
	 */
	public function userSendsAGETRequestToTheEndpointWithTheFileIdOf(
		string $user, string $elementName
	): void {
		$elementId = $this->featureContext->getIdOfElement($user, $elementName);
		$this->sendRequestToDirectUploadEndpoint($user, (string) $elementId);
	}

	/**
	 * @When /^user "([^"]*)" sends a GET request to the direct\-upload endpoint with the ID "(.*)"$/
	 */
	public function userSendsAGETRequestToTheEndpointWithTheId(
		string $user, string $folderId
	): void {
		$this->sendRequestToDirectUploadEndpoint($user, $folderId);
	}

	/**
	 * @Given /^user "([^"]*)" got a direct-upload token for "(.*)"$/
	 */
	public function userGotADirectUploadTokenFor(
		string $user, string $elementName
	): void {
		$elementId = $this->featureContext->getIdOfElement($user, $elementName);
		$this->sendRequestToDirectUploadEndpoint($user, (string) $elementId);
		$this->featureContext->theHttpStatusCodeShouldBe(200);
		$responseAsJson = json_decode(
			$this->featureContext->getResponse()->getBody()->getContents()
		);
		Assert::assertObjectHasAttribute(
			'token', $responseAsJson,
			'cannot find token in response'
		);
		$this->lastCreatedDirectUploadToken = $responseAsJson->token;
	}

	private function sendRequestToDirectUploadEndpoint(
		string $user, string $elementId
	): void {
		$this->featureContext->sendRequestsToAppEndpoint(
			$user,
			$this->featureContext->getRegularUserPassword(),
			'GET',
			'direct-upload?folder_id=' . $elementId
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
