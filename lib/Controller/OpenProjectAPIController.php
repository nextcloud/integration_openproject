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
	private $refreshToken;
	/**
	 * @var string
	 */
	private $clientID;
	/**
	 * @var string
	 */
	private $clientSecret;
	/**
	 * @var string
	 */
	private $openprojectUrl;

	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								OpenProjectAPIService $openprojectAPIService,
								IURLGenerator         $urlGenerator,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->openprojectAPIService = $openprojectAPIService;
		$this->userId = $userId;
		$this->accessToken = $config->getUserValue($userId, Application::APP_ID, 'token');
		$this->refreshToken = $config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		$this->clientID = $config->getAppValue(Application::APP_ID, 'client_id');
		$this->clientSecret = $config->getAppValue(Application::APP_ID, 'client_secret');
		$this->openprojectUrl = $config->getAppValue(Application::APP_ID, 'oauth_instance_url');
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
			$this->openprojectUrl, $this->accessToken, $this->refreshToken,
			$this->clientID, $this->clientSecret, $userId, $userName
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
	 * @param ?string $since
	 * @return DataResponse
	 */
	public function getNotifications(?string $since = null): DataResponse {
		if ($this->accessToken === '' || !OpenProjectAPIService::validateOpenProjectURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		$result = $this->openprojectAPIService->getNotifications($this->userId, $since, 7);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, Http::STATUS_UNAUTHORIZED);
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
		if ($this->accessToken === '' || !OpenProjectAPIService::validateOpenProjectURL($this->openprojectUrl)) {
			return new DataResponse(
				'invalid open project configuration', Http::STATUS_UNAUTHORIZED
			);
		}
		$result = $this->openprojectAPIService->searchWorkPackage(
			$this->userId,
			$searchQuery,
			$fileId,
			$this->urlGenerator->getBaseUrl()
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
		if ($this->accessToken === '' || !OpenProjectAPIService::validateOpenProjectURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}

		$storageUrl = $this->urlGenerator->getBaseUrl();

		try {
			$result = $this->openprojectAPIService->linkWorkPackageToFile(
				$workpackageId,
				$fileId,
				$fileName,
				$storageUrl,
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
	 * @param int $workpackageId
	 * @return DataResponse
	 */
	public function getWorkPackageFileLinks(int $id) {
		if ($this->accessToken === '' || !OpenProjectAPIService::validateOpenProjectURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}

		try {
			$result = $this->openprojectAPIService->getWorkPackageFileLinks(
				$id,
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
	public function deleteFileLink(int $id): DataResponse {
		if ($this->accessToken === '' || !OpenProjectAPIService::validateOpenProjectURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}

		try {
			$result = $this->openprojectAPIService->deleteFileLink(
				$id,
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
	 * get status of work packages
	 *
	 * @NoAdminRequired
	 *
	 * @param string $id
	 *
	 * @return DataResponse
	 */
	public function getOpenProjectWorkPackageStatus(string $id): DataResponse {
		if ($this->accessToken === '' || !OpenProjectAPIService::validateOpenProjectURL($this->openprojectUrl)) {
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
		if ($this->accessToken === '' || !OpenProjectAPIService::validateOpenProjectURL($this->openprojectUrl)) {
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
}
