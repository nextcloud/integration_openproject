<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Helmich\JsonAssert\JsonAssertions;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context {
	/**
	 * list of users that were created on the local server during test runs
	 * key is the lowercase username, value is an array of user attributes
	 */
	/** @var array<mixed> */
	private array $createdUsers = [];
	/** @var array<mixed> */
	private array $createdgroups = [];
	private string $regularUserPassword = '';
	private string $adminUsername = '';
	private string $adminPassword = '';
	private string $baseUrl = '';
	public int $lastUpLoadTime;
	private SharingContext $sharingContext;
	private DirectUploadContext $directUploadContext;
	/**
	 * @var array<int>
	 */
	private array $createdFiles = [];

	/**
	 * @var array<string>
	 */
	private array $createdAppPasswords = [];

	private ?ResponseInterface $response = null;

	private CookieJar $cookieJar;
	private string $requestToken;

	public function getAdminUsername(): string {
		return $this->adminUsername;
	}

	public function getAdminPassword(): string {
		return $this->adminPassword;
	}

	public function getRegularUserPassword(): string {
		return $this->regularUserPassword;
	}

	public function getBaseUrl(): string {
		return $this->baseUrl;
	}

	/**
	 * @return string
	 */
	public function getRequestToken():string {
		return $this->requestToken;
	}

	/**
	 * @return CookieJar
	 */
	public function getCookieJar():CookieJar {
		return $this->cookieJar;
	}

	/**
	 * @return ResponseInterface|null
	 */
	public function getResponse(): ?ResponseInterface {
		return $this->response;
	}

	/**
	 * @param ResponseInterface|null $response
	 */
	public function setResponse(?ResponseInterface $response): void {
		$this->response = $response;
	}

	public function __construct(
		string $baseUrl,
		string $adminUsername,
		string $adminPassword,
		string $regularUserPassword
	) {
		$this->baseUrl = getenv('NEXTCLOUD_BASE_URL');
		if ($this->baseUrl === false) {
			$this->baseUrl = $baseUrl;
		}
		$this->baseUrl = self::sanitizeUrl($this->baseUrl, true);
		$this->adminUsername = $adminUsername;
		$this->adminPassword = $adminPassword;
		$this->regularUserPassword = $regularUserPassword;
		$this->cookieJar = new CookieJar();
	}

	/**
	 * When we run API tests in CI, the user file system sometime does not get deleted from the data directory.
	 * So user is created with retry in order to comeback the flakiness in CI
	 *
	 * @param string $user
	 * @param array<mixed> $userAttributes
	 *
	 */
	private function createUserWithRetry(string $user, array $userAttributes): void {
		$retryCreate = 1;
		$isUserCreated = false;
		$response = null;
		while ($retryCreate <= 3) {
			$response = $this->sendOCSRequest(
				'/cloud/users', 'POST', $this->getAdminUsername(), $userAttributes
			);
			if ($response->getStatusCode() === 200) {
				$isUserCreated = true;
				break;
			} elseif ($response->getStatusCode() === 400 && getenv('CI')) {
				var_dump("Error: " . $response->getBody()->getContents());
				var_dump('Creating user ' . $user . ' failed!');
				var_dump('Deleting the file system of ' . $user . ' and retrying the user creation again...');
				exec(
					"docker exec nextcloud  /bin/bash -c 'rm -rf data/$user'",
					$output,
					$command_result_code
				);
				if ($command_result_code === 0) {
					var_dump('File system for user ' . $user . ' has been deleted successfully!');
				}
			} else {
				// in case of any other error we just log the response
				var_dump("Status Code: " . $response->getStatusCode());
				var_dump("Error: " . $response->getBody()->getContents());
			}
			sleep(2);
			$retryCreate++;
		}
		Assert::assertTrue($isUserCreated, 'User ' . $user . ' could not be created.' . 'Expected status code 200 but got ' . $response->getStatusCode());
	}

	/**
	 * @Given user :user has been created
	 */
	public function userHasBeenCreated(string $user, string $displayName = null):void {
		// delete the user if it exists
		$this->theAdministratorDeletesTheUser($user);
		$userAttributes['userid'] = $user;
		$userAttributes['password'] = $this->getRegularUserPassword();
		if ($displayName !== null) {
			$userAttributes['displayName'] = $displayName;
		}
		$this->createUserWithRetry($user, $userAttributes);
		$userid = \strtolower((string)$user);
		$this->createdUsers[$userid] = $userAttributes;
		$this->response = $this->makeDavRequest(
			$user,
			$this->regularUserPassword,
			"DELETE",
			"welcome.txt"
		);
	}

	/**
	 * @Given  group :group has been created
	 */
	public function groupHasBeenCreated(string $group):void {
		$this->response = $this->sendOCSRequest(
			'/cloud/groups',
			'POST',
			$this->getAdminUsername(),
			['groupid' => $group]
		);
		$this->theHttpStatusCodeShouldBe(200);
		$this->createdgroups['groupid'] = $group;
	}

	/**
	 * @Given user :user has been added to the group :group
	 */
	public function userHasBeenAddedToGroup(string $user, string $group):void {
		$this->response = $this->sendOCSRequest(
			'/cloud/users/' . $user . '/groups',
			'POST',
			$this->getAdminUsername(),
			['groupid' => $group]
		);
		$this->theHttpStatusCodeShouldBe(200);
	}

	/**
	 * @Given user :user has been disabled
	 */
	public function userHasBeenDisabled(string $user):void {
		$this->theAdministratorDisablesTheUser($user);
		$this->theHttpStatusCodeShouldBe(200);
	}

	/**
	 * @Given user :user has been created with display-name :displayName
	 */
	public function userHasBeenCreatedWithDisplayName(string $user, string $displayName):void {
		$this->userHasBeenCreated($user, $displayName);
	}

	/**
	 * @Given user :user has been deleted
	 */
	public function userHasBeenDeleted(string $user):void {
		$this->theAdministratorDeletesTheUser($user);
		$this->theHttpStatusCodeShouldBe(200);
	}

	/**
	 * @When the administrator deletes the user :user
	 */
	public function theAdministratorDeletesTheUser(string $user):void {
		$this->response = $this->sendOCSRequest(
			'/cloud/users/' . $user, 'DELETE', $this->getAdminUsername()
		);
	}

	/**
	 * @When the administrator deletes the group :group
	 */
	public function theAdministratorDeletesTheGroup(string $group):void {
		$this->response = $this->sendOCSRequest(
			'/cloud/groups/' . $group, 'DELETE', $this->getAdminUsername()
		);
	}

	/**
	 * @When the administrator disables the user :user
	 */
	public function theAdministratorDisablesTheUser(string $user):void {
		$this->response = $this->sendOCSRequest(
			'/cloud/users/' . $user . '/disable', 'PUT', $this->getAdminUsername()
		);
	}

	/**
	 * @Given the administrator has changed the password of :user to the default testing password
	 */
	public function theAdministratorChangesPassword(string $user):void {
		$this->response = $this->sendOCSRequest(
			'/cloud/users/' . $user,
			'PUT',
			$this->getAdminUsername(),
			[
				'key' => 'password',
				'value' => $this->getRegularUserPassword()
			]
		);
		$this->theHttpStatusCodeShouldBe(200);
	}

	/**
	 * @Then user :user should be present in the server
	 */
	public function userShouldBePresentInTheServer(string $user):void {
		$this->response = $this->sendOCSRequest('/cloud/users/'. $user, 'GET', $this->getAdminUsername());
		$this->theHttpStatusCodeShouldBe(200);
	}

	/**
	 * @Then group :group should be present in the server
	 */
	public function groupShouldBePresentInTheServer(string $group):void {
		$this->response = $this->sendOCSRequest('/cloud/groups/'. $group, 'GET', $this->getAdminUsername());
		$this->theHttpStatusCodeShouldBe(200);
	}

	/**
	 * @Then user :user should be the subadmin of the group :group
	 */
	public function userShouldBeTheSubadminOfTheGroup(string $user, string $group):void {
		$response = $this->sendOCSRequest('/cloud/users/'. $user, 'GET', $this->getAdminUsername());
		$responseAsJson = json_decode($response->getBody()->getContents());
		$responseAsJson = $responseAsJson->ocs->data->subadmin;
		Assert::assertNotNull($responseAsJson, 'the response is null');
		Assert::assertContainsEquals(
			$group,
			$responseAsJson,
			"User $user is not the subadmin of group $group"
		);
	}


	/**
	 * @Given user :user has uploaded file with content :content to :destination
	 */
	public function userHasUploadedFileWithContentTo(
		string $user, string $content, string $destination
	):void {
		$fileId = $this->uploadFileWithContent($user, $content, $destination);
		$this->theHTTPStatusCodeShouldBe(
			["201", "204"],
			"HTTP status code was not 201 or 204 while trying to upload file '$destination' for user '$user'"
		);
		$this->createdFiles[] = $fileId;
		$this->lastUpLoadTime = \time();
	}

	/**
	 * @Given the public has uploaded file :destination with content :content to last created public link
	 */
	public function publicHasUploadedFileWithContent(
		string $content, string $destination
	):void {
		$this->response = $this->makeDavRequest(
			$this->sharingContext->getLastCreatedPublicLink(),
			'',
			"PUT",
			$destination,
			[],
			$content,
			'public-link'
		);
		$this->theHTTPStatusCodeShouldBe(
			["201", "204"],
			"HTTP status code was not 201 or 204 while trying to upload file '$destination' as public"
		);
	}

	/**
	 * @Given user :user has created folder :folder
	 */
	public function userHasCreatedFolder(
		string $user, string $folderString
	):void {
		$folders = \explode("/", $folderString);
		$fullFolderString = "";
		foreach ($folders as $folder) {
			if ($folder === '') {
				continue;
			}
			$fullFolderString .= "/" . trim($folder);
			$this->response = $this->makeDavRequest(
				$user,
				$this->regularUserPassword,
				"MKCOL",
				$fullFolderString
			);
			$this->theHTTPStatusCodeShouldBe(
				["201","405"], // 405 is returned if the folder already exists
				"HTTP status code was not 201 while trying to create folder '$fullFolderString' for user '$user'"
			);
		}
	}



	/**
	 * @Given /^user "([^"]*)" has deleted (?:file|folder) "([^"]*)"$/
	 */
	public function userHasDeletedFile(string $user, string $path):void {
		$this->response = $this->makeDavRequest(
			$user,
			$this->regularUserPassword,
			"DELETE",
			$path
		);
		$this->theHTTPStatusCodeShouldBe(
			"204",
			"HTTP status code was not 204 while deleting '$path' as '$user'"
		);
	}

	/**
	 * @When /^user "([^"]*)" deletes (?:file|folder) "([^"]*)"$/
	 */
	public function userDeletesFile(string $user, string $path):void {
		$this->response = $this->makeDavRequest(
			$user,
			$this->regularUserPassword,
			"DELETE",
			$path
		);
	}

	/**
	 * @When /^user "([^"]*)" (?:renames|moves) (?:file|folder) "([^"]*)" to "([^"]*)"$/
	 */
	public function userRenamesFile(string $user, string $src, string $dst):void {
		$davPath = self::getDavPath($user);
		$fullDstUrl = self::sanitizeUrl($this->getBaseUrl() . $davPath . $dst);
		$this->response = $this->makeDavRequest(
			$user,
			$this->regularUserPassword,
			"MOVE",
			$src,
			["Destination" => $fullDstUrl]
		);
	}

	/**
	 * @Given /^user "([^"]*)" has (?:renamed|moved) (?:file|folder) "([^"]*)" to "([^"]*)"$/
	 */
	public function userHasRenamedFile(string $user, string $src, string $dst):void {
		$this->userRenamesFile($user, $src, $dst);
		$this->theHTTPStatusCodeShouldBe(
			"201",
			"HTTP status code was not 201 while moving '$src' to '$dst'"
		);
	}

	/**
	 * @Given /^user "([^"]*)" has emptied the trash-bin$/
	 */
	public function userEmptiedTrashbin(string $user):void {
		$this->response = $this->makeDavRequest(
			$user,
			$this->regularUserPassword,
			"DELETE",
			"$user",
			null,
			null,
			'trash-bin'
		);
		$this->theHTTPStatusCodeShouldBe(
			"204",
			"HTTP status code was not 204 when emptying the trash-bin"
		);
	}

	/**
	 * @When user :user gets the information of last created file
	 */
	public function userGetsTheInformationOfLastCreatedFile(string $user): void {
		$fileId = array_slice($this->createdFiles, -1);
		$this->userGetsTheInformationOfFile($user, "$fileId[0]");
	}

	/**
	 * @When user :user gets the information of the file with the id :id
	 */
	public function userGetsTheInformationOfFile(string $user, string $fileId): void {
		$this->response = $this->sendOCSRequest(
			'/apps/integration_openproject/fileinfo/' . $fileId,
			'GET',
			$user
		);
	}

	/**
	 * @When user :user gets the information of the file :fileName
	 * @When user :user gets the information of the folder :fileName
	 */
	public function userGetsTheInformationOfFileWithName(string $user, string $fileName): void {
		$fileId = $this->getIdOfElement($user, $fileName);
		$this->response = $this->sendOCSRequest(
			'/apps/integration_openproject/fileinfo/' . $fileId,
			'GET',
			$user
		);
	}

	/**
	 * @When user :user gets the information of all files and group folder :groupFolder created in this scenario
	 */
	public function userGetsTheInformationOfAllCreatedFiles(string $user, string $groupFolder): void {
		$this->createdFiles[] = $this->getIdOfElement($user, $groupFolder);
		$body = json_encode(["fileIds" => $this->createdFiles]);
		Assert::assertNotFalse(
			$body,
			"could not encode to JSON"
		);
		$this->response = $this->sendOCSRequest(
			'/apps/integration_openproject/filesinfo',
			'POST',
			$user,
			$body
		);
	}

	/**
	 * @Then the HTTP status code should be :expectedStatusCode
	 *
	 * Check that the status code in the saved response is the expected status
	 * code, or one of the expected status codes.
	 *
	 * @param int|int[]|string|string[] $expectedStatusCode
	 * @param string|null $message
	 * @param ResponseInterface $response
	 *
	 * @return void
	 */
	public function theHTTPStatusCodeShouldBe(
		$expectedStatusCode, ?string $message = "", $response = null
	): void {
		if ($response === null) {
			$response = $this->response;
		}
		$actualStatusCode = $response->getStatusCode();
		if (\is_array($expectedStatusCode)) {
			if ($message === "") {
				$message = "HTTP status code $actualStatusCode is not one of the expected values " .
						\implode(" or ", $expectedStatusCode);
			}

			Assert::assertContainsEquals(
				$actualStatusCode,
				$expectedStatusCode,
				$message
			);
		} else {
			if ($message === "") {
				$message = "HTTP status code $actualStatusCode is not the expected value $expectedStatusCode";
			}

			Assert::assertEquals(
				$expectedStatusCode,
				$actualStatusCode,
				$message
			);
		}
	}

	/**
	 * @param PyStringNode $schemaString
	 * @return mixed
	 */
	private function getJSONSchema(PyStringNode $schemaString) {
		$schemaRawString = $schemaString->getRaw();
		$schemaRawString = $this->replaceInlineCodes($schemaRawString);
		$schema = json_decode($schemaRawString);
		Assert::assertNotNull($schema, 'schema is not valid JSON');
		return $schema;
	}

	/**
	 * @Then the ocs data of the response should match
	 *
	 * @param PyStringNode $schemaString
	 *
	 */
	public function theDataOfTheOCSResponseShouldMatch(
		PyStringNode $schemaString
	): void {
		$responseAsJson = json_decode($this->response->getBody()->getContents());
		$responseAsJson = $responseAsJson->ocs->data;
		JsonAssertions::assertJsonDocumentMatchesSchema(
			$responseAsJson,
			$this->getJSONSchema($schemaString)
		);
	}

	/**
	 * @Then the data of the response should match
	 *
	 * @param PyStringNode $schemaString
	 *
	 */
	public function theDataOfTheResponseShouldMatch(
		PyStringNode $schemaString
	): void {
		$responseAsJson = json_decode($this->response->getBody()->getContents());
		JsonAssertions::assertJsonDocumentMatchesSchema(
			$responseAsJson,
			$this->getJSONSchema($schemaString)
		);
	}

	public function uploadFileWithContent(
		string $user,
		?string $content,
		string $destination
	): int {
		$this->response = $this->makeDavRequest(
			$user,
			$this->regularUserPassword,
			"PUT",
			$destination,
			[],
			$content
		);
		return $this->getIdOfElement($user, $destination);
	}


	/**
	 * @Then the following headers should be set
	 *
	 * @psalm-suppress TooManyTemplateParams
	 *
	 * taken from https://github.com/owncloud/core/blob/3d517563ddddc3e9f22c57e9fd15ba48210553c5/tests/acceptance/features/bootstrap/WebDav.php#L1668-L1708
	 * @param TableNode<mixed> $table
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theFollowingHeadersShouldBeSet(TableNode $table):void {
		$this->verifyTableNodeColumns(
			$table,
			['header', 'value']
		);
		foreach ($table->getColumnsHash() as $header) {
			$headerName = $header['header'];
			$expectedHeaderValue = $header['value'];
			$returnedHeader = $this->response->getHeader($headerName);

			$headerValue = $returnedHeader;
			if (\is_array($returnedHeader)) {
				if (empty($returnedHeader)) {
					throw new Exception(
						\sprintf(
							"Missing expected header '%s'",
							$headerName
						)
					);
				}
				$headerValue = $returnedHeader[0];
			}

			Assert::assertEquals(
				$expectedHeaderValue,
				$headerValue,
				__METHOD__
				. " Expected value for header '$headerName' was '$expectedHeaderValue', but got '$headerValue' instead."
			);
		}
	}

	public function replaceInlineCodes(string $input): string {
		for ($i = 0; $i < count($this->createdFiles); $i++) {
			$input = str_replace(
				"%ids[$i]%", (string)$this->createdFiles[$i], $input
			);
		}

		$input = preg_replace_callback(
			'/%now([+-])(\\d+)s%/',
			function ($matches) {
				$operator = $matches[1];
				$result = ($operator === '+') ? time() + (int)$matches[2] : time() - (int)$matches[2];
				return (string) $result;
			},
			$input
		);

		$input = str_replace(
			"%last-created-direct-upload-token%",
			$this->directUploadContext->getLastCreatedDirectUploadToken(),
			$input
		);
		return $input;
	}

	/**
	 * Verify that the tableNode contains expected headers
	 * taken from https://github.com/owncloud/core/blob/8fa69f84526c7a5a6780b378eeaf9cabb7d46e56/tests/acceptance/features/bootstrap/FeatureContext.php#L3940-L3971
	 *
	 * @psalm-suppress TooManyTemplateParams
	 *
	 * @param TableNode<mixed> $table
	 * @param array<mixed>|null $requiredHeader
	 * @param array<mixed>|null $allowedHeader
	 *
	 * @return void
	 * @throws Exception
	 */
	public function verifyTableNodeColumns(TableNode $table, ?array $requiredHeader = [], ?array $allowedHeader = []):void {
		if (\count($table->getHash()) < 1) {
			throw new Exception("Table should have at least one row.");
		}
		$tableHeaders = $table->getRows()[0];
		$allowedHeader = \array_unique(\array_merge($requiredHeader, $allowedHeader));
		if ($requiredHeader != []) {
			foreach ($requiredHeader as $element) {
				if (!\in_array($element, $tableHeaders)) {
					throw new Exception("Row with header '$element' expected to be in table but not found");
				}
			}
		}

		if ($allowedHeader != []) {
			foreach ($tableHeaders as $element) {
				if (!\in_array($element, $allowedHeader)) {
					throw new Exception("Row with header '$element' is not allowed in table but found");
				}
			}
		}
	}

	public function getIdOfElement(string $user, string $element): int {
		$propfindResponse = $this->makeDavRequest(
			$user,
			$this->regularUserPassword,
			"PROPFIND",
			$element,
			null,
			'<?xml version="1.0"?>
					<d:propfind  xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns" xmlns:nc="http://nextcloud.org/ns" xmlns:ocs="http://open-collaboration-services.org/ns">
					  <d:prop>
						<oc:fileid />
					  </d:prop>
					</d:propfind>'
		);
		$xmlBody = $propfindResponse->getBody()->getContents();
		$responseXmlObject = new SimpleXMLElement($xmlBody);
		$responseXmlObject->registerXPathNamespace(
			'oc',
			'http://owncloud.org/ns'
		);
		return (int)(string)$responseXmlObject->xpath('//oc:fileid')[0];
	}
	/**
	 * @param string $path
	 * @param string $method
	 * @param string $user
	 * @param array<mixed>|string $body
	 * @param int $ocsApiVersion
	 * @return ResponseInterface
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function sendOCSRequest(
		string $path, string $method, string $user, $body = [], int $ocsApiVersion = 2
	): ResponseInterface {
		if ($user === $this->getAdminUsername()) {
			$password = $this->getAdminPassword();
		} else {
			$password = $this->getRegularUserPassword();
		}
		$fullUrl = $this->getBaseUrl();
		$fullUrl .= "ocs/v{$ocsApiVersion}.php" . $path;
		$headers['OCS-APIRequest'] = 'true';
		$headers['Accept'] = 'application/json';
		$headers['Content-Type'] = 'application/json';
		return $this->sendHttpRequest(
			$fullUrl, $user, $password, $method, $headers, $body
		);
	}

	/**
	 * @param string $url
	 * @param string|null $user
	 * @param string|null $password
	 * @param string $method
	 * @param array<mixed>|null $headers
	 * @param array<mixed>|string|null $body
	 * @param array<mixed>|null $options
	 * @return ResponseInterface
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function sendHttpRequest(
		string $url,
		?string $user,
		?string $password,
		string $method = 'GET',
		array  $headers = null,
		$body = null,
		?array $options = []
	): ResponseInterface {
		if ($user !== null && $password !== null) {
			$options['auth'] = [$user, $password];
		}
		$client = new Client($options);
		if ($headers === null) {
			$headers = [];
		}

		if (\is_array($body)) {
			// when creating the client, it is possible to set 'form_params' and
			// the Client constructor sorts out doing this http_build_query stuff.
			// But 'new Request' does not have the flexibility to do that.
			// So we need to do it here.
			$body = \http_build_query($body, '', '&');
			$headers['Content-Type'] = 'application/x-www-form-urlencoded';
		}
		$request = new Request(
			$method,
			$url,
			$headers,
			$body
		);
		try {
			$response = $client->send($request);
		} catch (RequestException $ex) {
			$response = $ex->getResponse();

			//if the response was null for some reason do not return it but re-throw
			if ($response === null) {
				throw $ex;
			}
		}
		return $response;
	}

	/**
	 * @param string|null $user
	 * @param string|null $password
	 * @param string|null $method
	 * @param string|null $path
	 * @param array<mixed>|null $headers
	 * @param array<mixed>|string|null $body
	 * @return ResponseInterface
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function makeDavRequest(
		?string $user,
		?string $password,
		?string $method,
		?string $path,
		?array $headers = null,
		$body = null,
		string $type = 'files'
	): ResponseInterface {
		$davPath = self::getDavPath($user, $type);

		//replace %, # and ? and in the path, Guzzle will not encode them
		$urlSpecialChar = [['%', '#', '?'], ['%25', '%23', '%3F']];
		$path = \str_replace($urlSpecialChar[0], $urlSpecialChar[1], $path);

		if ($type === "trash-bin") {
			$fullUrl = self::sanitizeUrl(
				$this->getBaseUrl() . '/remote.php/dav/trashbin/' . strtolower($user) . '/trash'
			);
		} else {
			$fullUrl = self::sanitizeUrl($this->getBaseUrl() . $davPath . $path);
		}

		if ($headers !== null) {
			foreach ($headers as $key => $value) {
				//? and # need to be encoded in the Destination URL
				if ($key === "Destination") {
					$headers[$key] = \str_replace(
						$urlSpecialChar[0],
						$urlSpecialChar[1],
						$value
					);
					break;
				}
			}
		}
		return $this->sendHttpRequest(
			$fullUrl,
			$user,
			$password,
			$method,
			$headers,
			$body
		);
	}

	private static function getDavPath(string $user, string $type = 'files'): string {
		if ($type === 'public-link') {
			return '/public.php/webdav/';
		}
		return 'remote.php/dav/files/' . strtolower($user) . '/';
	}

	public static function sanitizeUrl(?string $url, ?bool $trailingSlash = false): string {
		if ($trailingSlash === true) {
			$url = $url . "/";
		} else {
			$url = \rtrim($url, "/");
		}
		$url = \preg_replace("/([^:]\/)\/+/", '$1', $url);
		return $url;
	}

	/**
	 * @When /^the administrator sends a (PATCH|POST) request to the "([^"]*)" endpoint with this data:$/
	 *
	 * @return void
	 */
	public function theAdministratorSendsARequestToTheEndpointWithThisData(
		string $method, string $endpoint, PyStringNode $data
	): void {
		$this->sendRequestsToAppEndpoint(
			$this->adminUsername, $this->adminPassword, $method, $endpoint, $data
		);
		if (isset(json_decode($data->getRaw())->values->setup_app_password) && json_decode($data->getRaw())->values->setup_app_password) {
			$responseAsJson = json_decode(
				$this->response->getBody()->getContents()
			);
			if (isset($responseAsJson->openproject_user_app_password)) {
				$this->createdAppPasswords[] = $responseAsJson->openproject_user_app_password;
			}
			$this->response->getBody()->rewind();
		}
	}

	/**
	 * @When /^user "([^"]*)" sends a "([^"]*)" request to "([^"]*)" using (current|old|new) app password$/
	 *
	 * @param string $user
	 * @param string $method
	 * @param string $endpoint
	 * @param string $appPassword
	 * @throws Exception
	 */
	public function theUserSendsRequestTo(string $user, string $method, string $endpoint, string $appPassword) : void {
		if ($appPassword === 'current' || $appPassword === 'old') {
			$appPassword = $this->createdAppPasswords[0];
		} else {
			$appPassword = $this->createdAppPasswords[1];
		}
		$this->response = $this->sendHttpRequest(
			self::sanitizeUrl($this->getBaseUrl() . $endpoint), $user, $appPassword, $method
		);
	}


	/**
	 * @Then the newly generated app password should be different from the previous one
	 *
	 * @return void
	 */
	public function theAppPasswordShouldBeDifferent(): void {
		$uniqueAppPasswordArray = array_unique(
			$this->createdAppPasswords
		);
		Assert::assertEquals(
			count($uniqueAppPasswordArray),
			count($this->createdAppPasswords),
			"App password has the same value:\n" .
			print_r($this->createdAppPasswords, true)
		);
	}

	/**
	 * @When /^the administrator sends a (PATCH|POST|DELETE) request to the "([^"]*)" endpoint$/
	 *
	 * @return void
	 */
	public function theAdministratorSendsARequestToTheEndpoint(
		string $method, string $endpoint
	): void {
		$this->sendRequestsToAppEndpoint(
			$this->adminUsername, $this->adminPassword, $method, $endpoint
		);
	}

	/**
	 * @When /^the user "([^"]*)" sends a (PATCH|POST) request to the "([^"]*)" endpoint with this data:$/
	 *
	 * @return void
	 */
	public function theUserSendsARequestToTheEndpointWithThisData(
		string $user, string $method, string $endpoint, PyStringNode $data
	): void {
		$this->sendRequestsToAppEndpoint(
			$user, $this->regularUserPassword, $method, $endpoint, $data
		);
	}

	/**
	 * @When /^the user "([^"]*)" sends a (PUT|POST|DELETE) request to the "([^"]*)" endpoint$/
	 *
	 * @return void
	 */
	public function theUserSendsARequestToTheEndpoint(
		string $user, string $method, string $endpoint
	): void {
		$this->sendRequestsToAppEndpoint(
			$user, $this->regularUserPassword, $method, $endpoint
		);
	}

	/**
	 * @When the administrator requests the nextcloud capabilities
	 *
	 * @return void
	 */
	public function theAdministratorRequestsCapabilities(): void {
		$this->response = $this->sendOCSRequest(
			'/cloud/capabilities', 'GET', $this->getAdminUsername()
		);
	}

	/**
	 * @Then /^the content of file at "([^"]*)" for user "([^"]*)" should be "([^"]*)"$/
	 *
	 */
	public function theContentOfFileAtForUserShouldBe(
		string $fileName, string $user, string $content
	): void {
		$this->response = $this->makeDavRequest(
			$user, $this->regularUserPassword, 'GET', $fileName
		);
		Assert::assertSame($content, $this->response->getBody()->getContents());
	}

	/**
	 * @When a new browser session for :user starts
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function aNewBrowserSessionForUserStarts(string $user):void {
		$loginUrl = $this->getBaseUrl() . '/index.php/login';
		$options['cookies'] = $this->getCookieJar();
		// Request a new session and extract CSRF token
		$this->setResponse(
			$this->sendHttpRequest(
				$loginUrl,
				null,
				null,
				'GET',
				null,
				null,
				$options
			)
		);
		$this->theHttpStatusCodeShouldBe(200);
		$this->extractRequestTokenFromResponse($this->getResponse());

		// Login and extract new token
		$body = [
			'user' => $user,
			'password' => $this->getRegularUserPassword(),
			'requesttoken' => $this->getRequestToken()
		];
		$options['cookies'] = $this->getCookieJar();
		$this->setResponse(
			$this->sendHttpRequest(
				$loginUrl,
				null,
				null,
				'POST',
				null,
				$body,
				$options
			)
		);
		$this->theHttpStatusCodeShouldBe(200);
		$this->extractRequestTokenFromResponse($this->getResponse());
	}

	/**
	 * @param ResponseInterface $response
	 *
	 * @return void
	 */
	public function extractRequestTokenFromResponse(ResponseInterface $response):void {
		$this->requestToken = \substr(
			\preg_replace(
				'/(.*)data-requesttoken="(.*)">(.*)/sm',
				'\2',
				$response->getBody()->getContents()
			),
			0,
			89
		);
	}

	/**
	 * @param string|null $username
	 * @param string|null $password
	 * @param string $method
	 * @param string $endpoint
	 * @param array<mixed>|PyStringNode|null|string $data //array for multipart data
	 * @param array<mixed>|null $headers //array for multipart data
	 * @return void
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function sendRequestsToAppEndpoint(
		?string            $username,
		?string            $password,
		string             $method,
		string             $endpoint,
		$data = null,
		$headers = null
	) {
		$fullUrl = $this->getBaseUrl();
		$fullUrl .= "index.php/apps/integration_openproject/" . $endpoint;

		// don't set content-type for multipart requests
		if (is_array($data) && $headers === null) {
			$options['multipart'] = $data;
			$data = null;
		} else {
			$headers['Content-Type'] = 'application/json';
			$options = [];
		}

		$this->response = $this->sendHttpRequest(
			$fullUrl, $username, $password, $method, $headers, $data, $options
		);
	}

	/**
	 * Verify that the tableNode contains expected rows
	 *
	 * @psalm-suppress TooManyTemplateParams
	 *
	 * @param TableNode<mixed> $table
	 * @param array<string> $requiredRows
	 * @param array<string> $allowedRows
	 *
	 * taken from https://github.com/owncloud/core/blob/8fa69f84526c7a5a6780b378eeaf9cabb7d46e56/tests/acceptance/features/bootstrap/FeatureContext.php#L3974
	 * @return void
	 * @throws Exception
	 */
	public function verifyTableNodeRows(TableNode $table, array $requiredRows = [], array $allowedRows = []):void {
		if (\count($table->getRows()) < 1) {
			throw new Exception("Table should have at least one row.");
		}
		$tableHeaders = $table->getColumn(0);
		$allowedRows = \array_unique(\array_merge($requiredRows, $allowedRows));
		if ($requiredRows != []) {
			foreach ($requiredRows as $element) {
				if (!\in_array($element, $tableHeaders)) {
					throw new Exception("Row with name '$element' expected to be in table but not found");
				}
			}
		}

		if ($allowedRows != []) {
			foreach ($tableHeaders as $element) {
				if (!\in_array($element, $allowedRows)) {
					throw new Exception("Row with name '$element' is not allowed in table but found");
				}
			}
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
		setlocale(LC_ALL, 'C.utf8');

		// Get the environment
		$environment = $scope->getEnvironment();

		// Get all the contexts you need in this context
		$this->sharingContext = $environment->getContext('SharingContext');
		$this->directUploadContext = $environment->getContext('DirectUploadContext');
	}

	/**
	 * @AfterScenario
	 *
	 * @return void
	 * @throws Exception
	 */
	public function after():void {
		foreach ($this->createdUsers as $userData) {
			$this->theAdministratorDeletesTheUser($userData['userid']);
		}
		foreach ($this->createdgroups as $groups) {
			$this->theAdministratorDeletesTheGroup($groups);
		}
		$this->createdAppPasswords = [];
	}
}
