<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use PHPUnit\Framework\Assert;

/**
 * Defines application features from the specific context.
 */
class FilesVersionsContext implements Context {
	/**
	 *
	 * @var FeatureContext
	 */
	private $featureContext;

	/**
	 * @Then the version folder of file :path for user :user should contain :count element(s)
	 *
	 * @param string $path
	 * @param string $user
	 * @param int $count
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theVersionFolderOfFileShouldContainElements(
		string $path,
		string $user,
		int $count
	):void {
		$fileId = $this->featureContext->getIdOfElement($user, $path);
		Assert::assertNotNull($fileId, __METHOD__ . " file $path user $user not found (the file may not exist)");
		$this->theVersionFolderOfFileIdShouldContainElements($fileId, $user, $count);
	}

	public function theVersionFolderOfFileIdShouldContainElements(
		string $fileId,
		string $user,
		int $count
	):void {
		$responseXml = $this->listVersionFolder($user, $fileId, 1);
		$xmlPart = $responseXml->xpath("//d:prop/d:getetag");
		Assert::assertEquals(
			$count,
			\count($xmlPart) - 1,
			"could not find $count version element(s) in \n" . $responseXml->asXML()
		);
	}

	/**
	 * returns the result parsed into an SimpleXMLElement
	 * with a registered namespace with 'd' as prefix and 'DAV:' as namespace
	 *
	 * @param string $user
	 * @param string $fileId
	 * @param int $folderDepth
	 * @param string[]|null $properties
	 *
	 * @return SimpleXMLElement
	 * @throws Exception
	 */
	public function listVersionFolder(
		string $user,
		string $fileId,
		int $folderDepth
	):SimpleXMLElement {
		$password = $this->featureContext->getRegularUserPassword();
		$fullUrl = FeatureContext::sanitizeUrl(
			$this->featureContext->getBaseUrl() . '/remote.php/dav/versions/' . strtolower($user) . '/versions/' . $fileId
		);
		$body = '<?xml version="1.0"?>' .
			'<d:propfind xmlns:d="DAV:"' .
			' xmlns:oc="http://owncloud.org/ns"' .
			' xmlns:nc="http://nextcloud.org/ns"' .
			' xmlns:ocs="http://open-collaboration-services.org/ns">' .
			'	<d:prop>' .
			'		<d:getcontentlength />' .
			'		<d:getcontenttype />' .
			'		<d:getlastmodified />' .
			'		<d:getetag />' .
			'		<nc:version-label />' .
			'		<nc:has-preview />' .
			'	</d:prop>' .
			'</d:propfind>';
		$response = $this->featureContext->sendHttpRequest(
			$fullUrl, $user, $password, 'PROPFIND', null, $body
		);
		$xmlBody = $response->getBody()->getContents();
		return new SimpleXMLElement($xmlBody);
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
