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

	private const PERMISSION_TYPES = [
		'read' => 1,
		'update' => 2,
		'create' => 4,
		'delete' => 8,
		'share' => 16,
	];

	public function getLastCreatedPublicLink(): string {
		return $this->lastCreatedPublicLink;
	}

	/**
	 * @Given /^user "([^"]*)" has shared (?:file|folder) "([^"]*)" with (user|group|the public)\s?"?([^"]*)"?(?: with "([^"]*)" permissions)?$/
	 * @throws \Exception
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function userHasSharedFileWithUser(
		string $sharer,
		string $path,
		string $shareType,
		string $shareWith = '',
		string $permissionsString = 'all'
	): void {
		$body['path'] = $path;
		$body['shareType'] = self::SHARE_TYPES[$shareType];
		$body['permissions'] = $this->getPermissionSum($permissionsString);
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
	 * calculates the permission sum (int) from a given string of permissions (seperated by '+')
	 *
	 * @return int
	 * @throws InvalidArgumentException
	 *
	 */
	private function getPermissionSum(string $permissionsString):int {
		$permissionsString = \trim($permissionsString);
		$permissions = \array_map('trim', \explode('+', $permissionsString));

		/* We use 'all', in feature files for readability.
		Parse into appropriate permissions and return them
		without any duplications.*/
		if (\in_array('all', $permissions, true)) {
			$permissions = \array_keys(self::PERMISSION_TYPES);
		}

		$permissionSum = 0;
		foreach ($permissions as $permission) {
			if (\array_key_exists($permission, self::PERMISSION_TYPES)) {
				$permissionSum += self::PERMISSION_TYPES[$permission];
			} else {
				throw new InvalidArgumentException(
					"invalid permission type ($permission)"
				);
			}
		}
		if ($permissionSum < 1 || $permissionSum > 31) {
			throw new InvalidArgumentException(
				"invalid permission total ($permissionSum)"
			);
		}
		return $permissionSum;
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
