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

use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCA\OpenProject\AppInfo\Application;

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
	private $tokenType;
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

	public function __construct(string                $appName,
								IRequest              $request,
								IConfig               $config,
								OpenProjectAPIService $openprojectAPIService,
								?string               $userId) {
		parent::__construct($appName, $request);
		$this->openprojectAPIService = $openprojectAPIService;
		$this->userId = $userId;
		$this->accessToken = $config->getUserValue($userId, Application::APP_ID, 'token');
		$this->tokenType = $config->getUserValue($userId, Application::APP_ID, 'token_type');
		$this->refreshToken = $config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		$this->clientID = $config->getAppValue(Application::APP_ID, 'client_id');
		$this->clientSecret = $config->getAppValue(Application::APP_ID, 'client_secret');
		$this->openprojectUrl = $config->getUserValue($userId, Application::APP_ID, 'url');
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
			$this->openprojectUrl, $this->accessToken, $this->tokenType, $this->refreshToken,
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
			return new DataResponse('', 400);
		}
		$result = $this->openprojectAPIService->getNotifications(
			$this->openprojectUrl, $this->accessToken, $this->tokenType, $this->refreshToken, $this->clientID, $this->clientSecret, $this->userId, $since, 7
		);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}

	/**
	 * get searched work packages
	 *
	 * @NoAdminRequired
	 *
	 * @param ?string $searchQuery
	 *
	 * @return DataResponse
	 */
	public function getSearchedWorkPackages(?string $searchQuery = null): DataResponse {
		if ($this->accessToken === '' || !OpenProjectAPIService::validateOpenProjectURL($this->openprojectUrl)) {
			return new DataResponse('', 400);
		}
		$result = $this->openprojectAPIService->searchWorkPackage(
			$this->openprojectUrl, $this->accessToken, 'oauth', $this->refreshToken, $this->clientID, $this->clientSecret, $this->userId, $searchQuery
		);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
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
			return new DataResponse('', 400);
		}
		$result = $this->openprojectAPIService->getOpenProjectWorkPackageStatus(
			$this->openprojectUrl, $this->accessToken, 'oauth', $this->refreshToken, $this->clientID, $this->clientSecret, $this->userId, $id
		);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
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
			return new DataResponse('', 400);
		}
		$result = $this->openprojectAPIService->getOpenProjectWorkPackageType(
			$this->openprojectUrl, $this->accessToken, 'oauth', $this->refreshToken, $this->clientID, $this->clientSecret, $this->userId, $id
		);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}
}
