<?php

/**
 * SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Assert;

class GroupfoldersContext implements Context {
	/**
	 * @var FeatureContext
	 */
	private $featureContext;

	/** @var array<mixed> */
	private array $createdGroupFolders = [];

	/**
	 * @return string
	 */
	public function getTeamFolderUrl(): string {
		return $this->featureContext->getBaseUrl() . "index.php/apps/groupfolders/folders";
	}

	/**
	 * @Given team folder :folderName has been created
	 */
	public function groupFolderHasBeenCreated(string $folderName): void {
		$headers['OCS-APIRequest'] = 'true';
		$options = [
			'multipart' => [
				[
					'name' => 'mountpoint',
					'contents' => $folderName
				]
			]];
		$response = $this->featureContext->sendHttpRequest(
			$this->getTeamFolderUrl(),
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			'POST',
			$headers,
			null,
			$options
		);
		$this->featureContext->theHttpStatusCodeShouldBe(200, "", $response);
		$xmlBody = $response->getBody()->getContents();
		$responseXmlObject = new SimpleXMLElement($xmlBody);
		$groupFolderId = (int)$responseXmlObject->data->id;
		$this->createdGroupFolders[$folderName] = $groupFolderId;
	}

	/**
	 * @Given group :group has been added to team folder :groupfolder
	 */
	public function groupHasBeenAddedToGroupFolder(string $group, string $groupfolder):void {
		$groupfolderId = $this->createdGroupFolders[$groupfolder];
		$fullUrl = $this->getTeamFolderUrl() . "/" . $groupfolderId . "/groups";
		$headers['OCS-APIRequest'] = 'true';
		$options = [
			'multipart' => [
				[
					'name' => 'group',
					'contents' => $group
				]
			]];
		$response = $this->featureContext->sendHttpRequest(
			$fullUrl,
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			'POST',
			$headers,
			null,
			$options
		);
		$this->featureContext->theHttpStatusCodeShouldBe(200, "", $response);
	}

	/**
	 * @Then /^groupfolder "([^"]*)" should be present in the server$/
	 */
	public function groupfolderShouldBePresentInTheServer(string $folder): void {
		$this->getGroupfolderByMountpoint($folder);
	}

	/**
	 * @Then /^groupfolder "([^"]*)" should be assigned to the group "([^"]*)" with all permissions$/
	 */
	public function groupfolderShouldBeAssignedToTheGroup(string $folderName, string $group): void {
		$folder = $this->getGroupfolderByMountpoint($folderName);
		Assert::assertEquals(
			[ $folderName => 31 ],
			(array)$folder->groups,
			'The group assignment of folder "' . $folderName .
			'" is not correct' .
			"\n" . print_r($folder, true)
		);
	}
	/**
	 * @Then groupfolder :folderName should have advance permissions enabled
	 */
	public function groupfolderShouldHaveAdvancePermissionsEnabled(string $folderName): void {
		$folder = $this->getGroupfolderByMountpoint($folderName);
		Assert::assertEquals(
			1,
			$folder->acl,
			'Folder "' . $folderName .
			'" has no ACLs enabled' .
			"\n" . print_r($folder, true)
		);
	}

	/**
	 * @Then groupfolder :folderName should be managed by the user :user
	 */
	public function groupfolderShouldBeManagedByTheUser(string $folderName, string $user): void {
		$folder = $this->getGroupfolderByMountpoint($folderName);
		$manage = ($folder->manage)[0];
		Assert::assertEquals(
			"user",
			$manage->type,
			'Folder manager misconfigured: type'
		);
		Assert::assertEquals(
			$user,
			$manage->id,
			'Folder manager misconfigured: id'
		);
		Assert::assertEquals(
			$user,
			$manage->displayname,
			'Folder manager misconfigured: displayname'
		);
	}

	/**
	 * @Then /^user "([^"]*)" should have a folder called "([^"]*)"$/
	 */
	public function userShouldHaveAFolderCalled(string $user, string $folderName): void {
		Assert::assertTrue(
			$this->featureContext->fileOrFolderExists($user, $folderName),
			"folder $folderName does not exist"
		);
	}

	/**
	 * @return array<mixed>
	 * @throws GuzzleException
	 */
	private function getAllGroupfolders() {
		$headers['OCS-APIRequest'] = 'true';
		$response = $this->featureContext->sendHttpRequest(
			$this->featureContext->prefixJsonFormat($this->getTeamFolderUrl()),
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			'GET',
			$headers
		);
		$this->featureContext->theHTTPStatusCodeShouldBe(200, "Failed to list team folders.", $response);
		$this->featureContext->theOCSStatusShouldBe("ok", $response);
		$response->getBody()->rewind();
		$body = json_decode($response->getBody()->getContents());
		Assert::assertTrue(
			$body->ocs->meta->status === "ok",
			"Failed to list team folders. Response: " . json_encode($body)
		);
		return $body->ocs->data;
	}

	/**
	 * @param string $mountpoint
	 * @return object
	 */
	private function getGroupfolderByMountpoint(string $mountpoint): object {
		$folders = $this->getAllGroupfolders();
		foreach ($folders as $groupfolder) {
			if ($groupfolder->mount_point === $mountpoint) {
				return $groupfolder;
			}
		}
		throw new \Exception('could not find "' . $mountpoint . '" in the list of groupfolders' .
			"\n" . print_r($folders, true)
		);
	}

	/**
	 * @return int
	 */
	public function getTeamFolderId(string $teamFolder): int {
		$folderId = 0;
		$folders = $this->getAllGroupfolders();
		foreach ($folders as $folder) {
			if ($folder->mount_point === $teamFolder) {
				$folderId = $folder->id;
				break;
			}
		}
		return $folderId;
	}

	/**
	 * @param int $folderId
	 *
	 * @return void
	 */
	private function deleteTeamFolder(int $folderId): void {
		if (!$folderId) {
			return;
		}

		$fullUrl = $this->getTeamFolderUrl() . "/" . $folderId;
		$headers['OCS-APIRequest'] = 'true';
		$response = $this->featureContext->sendHttpRequest(
			$this->featureContext->prefixJsonFormat($fullUrl),
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			"DELETE",
			$headers,
		);
		$this->featureContext->theHTTPStatusCodeShouldBe(200, "Failed to delete team folder", $response);
		$this->featureContext->theOCSStatusShouldBe("ok", $response);
		$response->getBody()->rewind();
		$body = json_decode($response->getBody()->getContents());
		Assert::assertTrue(
			$body->ocs->meta->status === "ok",
			"Failed to delete team folder. Response: " . json_encode($body)
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
		if ($environment instanceof InitializedContextEnvironment) {
			$this->featureContext = $environment->getContext('FeatureContext');
		}
	}

	/**
	 * @AfterScenario @integration-setup
	 *
	 * @return void
	 */
	public function teardownOpenProjectTeamFolder(): void {
		$this->featureContext->enableDisableNextcloudApp(FeatureContext::APP_ID, false);
		$this->deleteTeamFolder($this->getTeamFolderId(FeatureContext::OPENPROJECT_TEAM_FOLDER));

		$this->featureContext->theAdministratorDeletesTheUser(FeatureContext::OPENPROJECT_USER);
		$this->featureContext->theHTTPStatusCodeShouldBe([200, 404]);
		$this->featureContext->setResponse(null);

		foreach (FeatureContext::OPENPROJECT_GROUPS as $group) {
			$this->featureContext->theAdministratorDeletesTheGroup($group);
			// with v2.php, the group deletion may return 200 or 400 if the group does not exist
			$this->featureContext->theHTTPStatusCodeShouldBe([200, 400]);
			$this->featureContext->setResponse(null);
		}

		$this->featureContext->enableDisableNextcloudApp(FeatureContext::APP_ID, true);
	}

	/**
	 * @AfterScenario
	 *
	 * @return void
	 * @throws Exception
	 */
	public function after():void {
		foreach ($this->createdGroupFolders as $groupFolder) {
			$this->deleteTeamFolder((int)$groupFolder);
		}
		$this->createdGroupFolders = [];
	}
}
