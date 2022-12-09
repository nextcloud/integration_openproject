<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Helmich\JsonAssert\JsonAssertions;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context {
	private string $regularUserPassword = '';
	private string $adminUsername = '';
	private string $adminPassword = '';
	private string $baseUrl = '';
	private const SHARE_TYPES = [
		'user' => 0,
		'group' => 1,
		'the public' => 3
	];
	/**
	 * @var array<int>
	 */
	private array $createdFiles = [];

	private string $lastCreatedPublicLink;
	private ?ResponseInterface $response = null;

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
	}

	/**
	 * @Given user :user has been created
	 */
	public function userHasBeenCreated(string $user, string $displayName = null):void {
		// delete the user if it exists
		$this->sendOCSRequest(
			'/cloud/users/' . $user, 'DELETE', $this->getAdminUsername()
		);
		$userAttributes['userid'] = $user;
		$userAttributes['password'] = $this->getRegularUserPassword();
		if ($displayName !== null) {
			$userAttributes['displayName'] = $displayName;
		}

		$this->response = $this->sendOCSRequest(
			'/cloud/users', 'POST', $this->getAdminUsername(), $userAttributes
		);
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
		$this->sendOCSRequest(
			'/cloud/users/' . $user, 'DELETE', $this->getAdminUsername()
		);
		$this->theHttpStatusCodeShouldBe(204);
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
	}

	/**
	 * @Given the public has uploaded file :destination with content :content to last created public link
	 */
	public function publicHasUploadedFileWithContent(
		string $content, string $destination
	):void {
		$this->response = $this->makeDavRequest(
			$this->lastCreatedPublicLink,
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
		string $user, string $folder
	):void {
		$this->response = $this->makeDavRequest(
			$user,
			$this->regularUserPassword,
			"MKCOL",
			$folder
		);
		$this->theHTTPStatusCodeShouldBe(
			"201",
			"HTTP status code was not 201 while trying to create folder '$folder' for user '$user'"
		);
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
		$this->response = $this->sendOCSRequest(
			'/apps/files_sharing/api/v1/shares',
			'POST',
			$sharer,
			$body
		);
		$this->theHTTPStatusCodeShouldBe(
			"200",
			"HTTP status code was not 200 while sharing '$path' with '$shareWithForMessage'"
		);

		if ($shareType === 'the public') {
			$fixPublicLinkPermBody['permissions'] = 15;
			$shareData = json_decode($this->response->getBody()->getContents());
			if ($shareData === null) {
				throw new \Exception('could not JSON decode content of share response');
			}
			$shareId = $shareData->ocs->data->id;
			$this->lastCreatedPublicLink = $shareData->ocs->data->token;
			$this->response = $this->sendOCSRequest(
				'/apps/files_sharing/api/v1/shares/' . $shareId,
				'PUT',
				$sharer,
				$fixPublicLinkPermBody
			);
			$this->theHTTPStatusCodeShouldBe(
				"200",
				"HTTP status code was not 200 while giving upload permissions to public share of '$path'"
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
	 * @Given /^user "([^"]*)" has (?:renamed|moved) (?:file|folder) "([^"]*)" to "([^"]*)"$/
	 */
	public function userHasRenamedFile(string $user, string $src, string $dst):void {
		$davPath = self::getDavPath($user);
		$fullDstUrl = self::sanitizeUrl($this->getBaseUrl() . $davPath . $dst);
		$this->response = $this->makeDavRequest(
			$user,
			$this->regularUserPassword,
			"MOVE",
			$src,
			["Destination" => $fullDstUrl]
		);
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
	 * @When user :user gets the information of all files created in this scenario
	 */
	public function userGetsTheInformationOfAllCreatedFiles(string $user): void {
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
	 *
	 * @return void
	 */
	public function theHTTPStatusCodeShouldBe($expectedStatusCode, ?string $message = ""): void {
		$actualStatusCode = $this->response->getStatusCode();
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
	 * @Then the data of the response should match
	 */
	public function theDataOfTheResponseShouldMatch(PyStringNode $schemaString): void {
		$schemaRawString = $schemaString->getRaw();
		for ($i = 0; $i < count($this->createdFiles); $i++) {
			$schemaRawString = str_replace(
				"%ids[$i]%", (string)$this->createdFiles[$i], $schemaRawString
			);
		}
		$schema = json_decode($schemaRawString);
		Assert::assertNotNull($schema, 'schema is not valid JSON');
		$responseAsJson = json_decode($this->response->getBody()->getContents());
		JsonAssertions::assertJsonDocumentMatchesSchema(
			$responseAsJson->ocs->data,
			$schema
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

	private function getIdOfElement(string $user, string $element): int {
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
	 * @param string $user
	 * @param string $password
	 * @param string $method
	 * @param array<mixed>|null $headers
	 * @param array<mixed>|string|null $body
	 * @return ResponseInterface
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function sendHttpRequest(
		string $url,
		string $user,
		string $password,
		string $method = 'GET',
		array  $headers = null,
			   $body = null
	): ResponseInterface {
		$options['auth'] = [$user, $password];
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
	 * @When /^the administrator sends a (PUT|POST) request to the "([^"]*)" endpoint with this data:$/
	 */
	public function theAdministratorSendsARequestToTheEndpointWithThisData(
		string $method, string $endpoint, PyStringNode $data
	) {
		$fullUrl = $this->getBaseUrl();
		$fullUrl .= "index.php/apps/integration_openproject/" . $endpoint;
		$headers['Accept'] = 'application/json';
		$headers['Content-Type'] = 'application/json';
		$this->response = $this->sendHttpRequest(
			$fullUrl, $this->adminUsername, $this->adminPassword, $method, $headers, $data
		);
	}
}
