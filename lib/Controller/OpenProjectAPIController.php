<?php
/**
 * Nextcloud - openproject
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\OpenProject\Controller;

use Exception;
use GuzzleHttp\Exception\ClientException;
use OCA\OpenProject\Exception\OpenprojectErrorException;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\OpenProject\Service\OpenProjectAPIService;
use OCA\OpenProject\AppInfo\Application;
use OCP\IURLGenerator;

class OpenProjectAPIController extends Controller {

	/**
	 * @var OpenProjectAPIService
	 */
	private $openprojectAPIService;
	/**
	 * @var string|null
	 */
	private $userId;
	/**
	 * @var string
	 */
	private $accessToken;

	/**
	 * @var string
	 */
	private $openprojectUrl;

	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								OpenProjectAPIService $openprojectAPIService,
								IURLGenerator $urlGenerator,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->openprojectAPIService = $openprojectAPIService;
		$this->userId = $userId;
		$this->accessToken = $config->getUserValue($userId, Application::APP_ID, 'token');
		$this->openprojectUrl = $config->getAppValue(Application::APP_ID, 'oauth_instance_url');
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * get openproject instance URL
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getOpenProjectUrl(): DataResponse {
		return new DataResponse($this->openprojectUrl);
	}

	/**
	 * get openproject user avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $userId
	 * @param string $userName
	 * @return DataDisplayResponse|DataDownloadResponse
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\Lock\LockedException
	 */
	public function getOpenProjectAvatar(string $userId = '', string $userName = '') {
		$result = $this->openprojectAPIService->getOpenProjectAvatar(
			$userId, $userName
		);
		$response = new DataDownloadResponse(
			$result['avatar'], 'avatar', $result['type'] ?? ''
		);
		$response->cacheFor(60 * 60 * 24);
		return $response;
	}

	/**
	 * get notifications list
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getNotifications(): DataResponse {
		if ($this->accessToken === '' || !OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		$result = $this->openprojectAPIService->getNotifications($this->userId);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			if (isset($result['statusCode'])) {
				$statusCode = $result['statusCode'];
			} else {
				$statusCode = Http::STATUS_UNAUTHORIZED;
			}
			$response = new DataResponse($result, $statusCode);
		}
		return $response;
	}

	/**
	 * get searched work packages
	 *
	 * @NoAdminRequired
	 *
	 * @param ?string $searchQuery
	 * @param ?int $fileId
	 *
	 * @return DataResponse
	 */
	public function getSearchedWorkPackages(?string $searchQuery = null, ?int $fileId = null): DataResponse {
		if ($this->accessToken === '' || !OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse(
				'invalid open project configuration', Http::STATUS_UNAUTHORIZED
			);
		}
		$result = $this->openprojectAPIService->searchWorkPackage(
			$this->userId,
			$searchQuery,
			$fileId
		);

		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			if (isset($result['statusCode'])) {
				$statusCode = $result['statusCode'];
			} else {
				$statusCode = Http::STATUS_BAD_REQUEST;
			}
			$response = new DataResponse($result, $statusCode);
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @param int $workpackageId
	 * @param int $fileId
	 * @param string $fileName
	 * @return DataResponse
	 */
	public function linkWorkPackageToFile(int $workpackageId, int $fileId, string $fileName) {
		if ($this->accessToken === '' || !OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}

		try {
			$result = $this->openprojectAPIService->linkWorkPackageToFile(
				$workpackageId,
				$fileId,
				$fileName,
				$this->userId,
			);
		} catch (OpenprojectErrorException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		} catch (NotPermittedException | NotFoundException $e) {
			return new DataResponse('file not found', Http::STATUS_NOT_FOUND);
		} catch (Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 * @param int $id
	 * @return DataResponse
	 */
	public function getWorkPackageFileLinks(int $id): DataResponse {
		if ($this->accessToken === '' || !OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}

		try {
			$result = $this->openprojectAPIService->getWorkPackageFileLinks(
				$id,
				$this->userId,
			);
		} catch (OpenprojectErrorException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		} catch (NotFoundException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		} catch (Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 * @param int $id
	 * @return DataResponse
	 */
	public function deleteFileLink(int $id): DataResponse {
		if ($this->accessToken === '' || !OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}

		try {
			$result = $this->openprojectAPIService->deleteFileLink(
				$id,
				$this->userId,
			);
		} catch (OpenprojectErrorException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		} catch (NotFoundException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		} catch (Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse($result);
	}

	/**
	 * get status of work packages
	 *
	 * @NoAdminRequired
	 *
	 * @param string $id
	 *
	 * @return DataResponse
	 */
	public function getOpenProjectWorkPackageStatus(string $id): DataResponse {
		if ($this->accessToken === '' || !OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		$result = $this->openprojectAPIService->getOpenProjectWorkPackageStatus(
			$this->userId, $id
		);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, Http::STATUS_UNAUTHORIZED);
		}
		return $response;
	}

	/**
	 * get type work packages
	 *
	 * @NoAdminRequired
	 *
	 * @param string $id
	 *
	 * @return DataResponse
	 */
	public function getOpenProjectWorkPackageType(string $id): DataResponse {
		if ($this->accessToken === '' || !OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		$result = $this->openprojectAPIService->getOpenProjectWorkPackageType(
			$this->userId, $id
		);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, Http::STATUS_UNAUTHORIZED);
		}
		return $response;
	}

	/**
	 * check if there is a OpenProject behind a certain URL
	 *
	 * @NoAdminRequired
	 *
	 * @param string $url
	 *
	 * @return DataResponse
	 */
	public function isValidOpenProjectInstance(string $url): DataResponse {
		if ($this->openprojectAPIService::validateURL($url) !== true) {
			return new DataResponse('invalid');
		}
		try {
			$response = $this->openprojectAPIService->rawRequest('', $url, '');
			$body = (string) $response->getBody();
			$decodedBody = json_decode($body, true);
			if (
				$decodedBody &&
				isset($decodedBody['_type']) &&
				isset($decodedBody['instanceName']) &&
				$decodedBody['_type'] === 'Root' &&
				$decodedBody['instanceName'] !== ''
			) {
				return new DataResponse(true);
			}
		} catch (ClientException $e) {
			$response = $e->getResponse();
			$body = (string) $response->getBody();
			$decodedBody = json_decode($body, true);
			if (
				$decodedBody &&
				isset($decodedBody['_type']) &&
				isset($decodedBody['errorIdentifier']) &&
				$decodedBody['_type'] === 'Error' &&
				$decodedBody['errorIdentifier'] !== ''
			) {
				return new DataResponse(true);
			}
		} catch (Exception $e) {
			return new DataResponse(false);
		}
		return new DataResponse(false);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getOpenProjectOauthURLWithStateAndPKCE(): DataResponse {
		$url = $this->openprojectAPIService::getOpenProjectOauthURL(
			$this->config, $this->urlGenerator
		);
		$oauthState = bin2hex(random_bytes(5));
		$this->config->setUserValue(
			$this->userId,
			Application::APP_ID,
			'oauth_state',
			$oauthState
		);
		// this results in a random string of 192 char and after packing and encoding a 128 char verifier
		$randomString = bin2hex(random_bytes(96));
		$codeVerifier = $this->base64UrlEncode(pack('H*', $randomString));
		$this->config->setUserValue(
			$this->userId,
			Application::APP_ID,
			'code_verifier',
			$codeVerifier
		);
		$hash = hash('sha256', $codeVerifier);
		$codeChallenge = $this->base64UrlEncode(pack('H*', $hash));
		$url = $url . '&state=' .$oauthState .
				 '&code_challenge=' . $codeChallenge .
				'&code_challenge_method=S256';

		return new DataResponse($url);
	}

	private function base64UrlEncode(string $plainText): string {
		$base64 = base64_encode($plainText);
		$base64 = trim($base64, "=");
		$base64url = strtr($base64, '+/', '-_');
		return ($base64url);
	}
}
