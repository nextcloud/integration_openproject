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

use OCP\App\IAppManager;
use OCP\Files\IAppData;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataDownloadResponse;

use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IL10N;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use Psr\Log\LoggerInterface;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\OpenProject\Service\OpenProjectAPIService;
use OCA\OpenProject\AppInfo\Application;

class OpenProjectAPIController extends Controller {


	private $userId;
	private $config;
	private $dbconnection;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IServerContainer $serverContainer,
								IConfig $config,
								IL10N $l10n,
								IAppManager $appManager,
								IAppData $appData,
								LoggerInterface $logger,
								OpenProjectAPIService $openprojectAPIService,
								$userId) {
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->appData = $appData;
		$this->serverContainer = $serverContainer;
		$this->config = $config;
		$this->logger = $logger;
		$this->openprojectAPIService = $openprojectAPIService;
		$this->accessToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'token', '');
		$this->tokenType = $this->config->getUserValue($this->userId, Application::APP_ID, 'token_type', '');
		$this->refreshToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'refresh_token', '');
		$this->clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', '');
		$this->clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret', '');
		$this->openprojectUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', '');
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
	 * @param string $imageId
	 * @return DataDisplayResponse|DataDownloadResponse
	 */
	public function getOpenProjectAvatar(string $userId = '', string $userName = '') {
		$result = $this->openprojectAPIService->getOpenProjectAvatar(
			$this->openprojectUrl, $this->accessToken, $this->tokenType, $this->refreshToken,
			$this->clientID, $this->clientSecret, $userId, $userName
		);
		if (isset($result['error'])) {
			$response = new DataDisplayResponse($result['error'], 404);
		} else {
			$response = new DataDownloadResponse($result['avatar'], 'avatar', $result['type'] ?? '');
			$response->cacheFor(60*60*24);
		}
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
		if ($this->accessToken === '' || !preg_match('/^(https?:\/\/)?[^.]+\.[^.].*/', $this->openprojectUrl)) {
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

}
