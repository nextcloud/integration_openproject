<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

class SharingContext implements Context {
	/**
	 *
	 * @var FeatureContext
	 */
	private $featureContext;
	private string $lastCreatedPublicLink;

	private const SHARE_TYPES = [
		'user' => 0,
		'group' => 1,
		'the public' => 3
	];

	public function getLastCreatedPublicLink(): string {
		return $this->lastCreatedPublicLink;
	}

	/**
	 * @Given /^user "([^"]*)" has shared (?:file|folder) "([^"]*)" with (user|group|the public)\s?"?([^"]*)"?$/
	 * @throws \Exception
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function userHasSharedFileWithUser(
		string $sharer, string $path, string $shareType, string $shareWith = ''): void {
		$body['path'] = $path;
		$body['shareType'] = self::SHARE_TYPES[$shareType];
		$body['permissions'] = 31;
		if ($shareType === 'the public') {
			$shareWithForMessage = $shareType;
			$body['publicUpload'] = true;
		} else {
			$body['shareWith'] = $shareWith;
			$shareWithForMessage = $shareWith;
		}
		$response = $this->featureContext->sendOCSRequest(
			'/apps/files_sharing/api/v1/shares',
			'POST',
			$sharer,
			$body
		);
		$this->featureContext->setResponse($response);
		$this->featureContext->theHTTPStatusCodeShouldBe(
			"200",
			"HTTP status code was not 200 while sharing '$path' with '$shareWithForMessage'"
		);

		if ($shareType === 'the public') {
			$fixPublicLinkPermBody['permissions'] = 15;
			$shareData = json_decode(
				$this->featureContext->getResponse()->getBody()->getContents()
			);
			if ($shareData === null) {
				throw new \Exception('could not JSON decode content of share response');
			}
			$shareId = $shareData->ocs->data->id;
			$this->lastCreatedPublicLink = $shareData->ocs->data->token;
			$response = $this->featureContext->sendOCSRequest(
				'/apps/files_sharing/api/v1/shares/' . $shareId,
				'PUT',
				$sharer,
				$fixPublicLinkPermBody
			);
			$this->featureContext->setResponse($response);
			$this->featureContext->theHTTPStatusCodeShouldBe(
				"200",
				"HTTP status code was not 200 while giving upload permissions to public share of '$path'"
			);
		}
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
