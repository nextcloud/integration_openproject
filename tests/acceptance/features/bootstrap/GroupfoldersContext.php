<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Assert;

class GroupfoldersContext implements Context {
	/**
	 *
	 * @var FeatureContext
	 */
	private $featureContext;

	/** @var array<mixed> */
	private array $createdGroupFolders = [];


	/**
	 * @Given group folder :folderName has been created
	 */
	public function groupFolderHasBeenCreated(string $folderName): void {
		$fullUrl = $this->featureContext->getBaseUrl() .
			"index.php/apps/groupfolders/folders";

		$headers['OCS-APIRequest'] = 'true';
		$options = [
			'multipart' => [
				[
					'name' => 'mountpoint',
					'contents' => $folderName
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
		$xmlBody = $response->getBody()->getContents();
		$responseXmlObject = new SimpleXMLElement($xmlBody);
		$groupFolderId = (int)$responseXmlObject->data->id;
		$this->createdGroupFolders[$folderName] = $groupFolderId;
	}

	/**
	 * @Given group :group has been added to group folder :groupfolder
	 */
	public function groupHasBeenAddedToGroupFolder(string $group, string $groupfolder):void {
		$groupfolderId = $this->createdGroupFolders[$groupfolder];
		$fullUrl = $this->featureContext->getBaseUrl() .
			"index.php/apps/groupfolders/folders/".$groupfolderId. "/groups";
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
			$folder['groups'],
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
			$folder['acl'],
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
		Assert::assertEquals(
			[
				[
					"type" => "user",
					"id" => $user,
					"displayname" => $user
				]
			],
			$folder['manage'],
			'manager of folder "' . $folderName . '" is not set correctly'
		);
	}

	/**
	 * @Then /^user "([^"]*)" should have a folder called "([^"]*)"$/
	 */
	public function userShouldHaveAFolderCalled(string $user, string $folderName): void {
		Assert::assertIsInt(
			$this->featureContext->getIdOfElement($user, $folderName),
			"folder $folderName does not exist"
		);
	}

	/**
	 * @param string $mountpoint
	 * @return array<mixed>
	 */
	private function getGroupfolderByMountpoint(string $mountpoint): array {
		$body = $this->getAllGroupfolders();
		foreach ($body['ocs']['data'] as $groupfolder) {
			if ($groupfolder['mount_point'] === $mountpoint) {
				return $groupfolder;
			}
		}
		throw new \Exception('could not find "' . $mountpoint . '" in the list of groupfolders' .
			"\n" . print_r($body, true)
		);
	}
	/**
	 * @return array<mixed>
	 * @throws GuzzleException
	 */
	private function getAllGroupfolders() {
		$fullUrl = $this->featureContext->getBaseUrl() .
			"index.php/apps/groupfolders/folders?format=json";

		$headers['Content-Type'] = 'application/json';
		$headers['OCS-APIRequest'] = 'true';

		$response = $this->featureContext->sendHttpRequest(
			$fullUrl,
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			'GET',
			$headers
		);

		$body = json_decode($response->getBody()->getContents(), true);
		Assert::assertNotNull($body, 'could not decode body');
		return $body;
	}

	private function adminDeletesGroupfolder(int $id): void {
		$fullUrl = $this->featureContext->getBaseUrl() .
			"index.php/apps/groupfolders/folders/" . $id;
		$headers['OCS-APIRequest'] = 'true';
		$this->featureContext->sendHttpRequest(
			$fullUrl,
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			'DELETE',
			$headers
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

	/**
	 * @AfterScenario
	 *
	 * @return void
	 * @throws Exception
	 */
	public function after():void {
		foreach ($this->createdGroupFolders as $groupFolder) {
			$this->adminDeletesGroupfolder((int)$groupFolder);
		}
	}
}
