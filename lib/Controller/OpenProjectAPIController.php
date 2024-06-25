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
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use InvalidArgumentException;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Exception\OpenprojectErrorException;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\LocalServerException;

use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

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

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct(string $appName,
		IRequest $request,
		IConfig $config,
		OpenProjectAPIService $openprojectAPIService,
		IURLGenerator $urlGenerator,
		LoggerInterface $logger,
		?string $userId) {
		parent::__construct($appName, $request);
		$this->openprojectAPIService = $openprojectAPIService;
		$this->userId = $userId;
		$this->accessToken = $config->getUserValue($userId, Application::APP_ID, 'token');
		$this->openprojectUrl = $config->getAppValue(Application::APP_ID, 'openproject_instance_url');
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
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
			$userId, $userName, $this->userId
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
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
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
	 * @param bool $isSmartPicker
	 * @return DataResponse
	 */
	public function getSearchedWorkPackages(
		?string $searchQuery = null,
		?int $fileId = null,
		bool $isSmartPicker = false
	): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		// when the search is done through smart picker we don't want to check if the work package is linkable
		$result = $this->openprojectAPIService->searchWorkPackage(
			$this->userId,
			$searchQuery,
			$fileId,
			!$isSmartPicker
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
	 *
	 * @param array<mixed> $values An array containing the following keys:
	 *        - "workpackageId" (int): The ID of the work package.
	 *        - "fileinfo" (array):  An array of file information with the following keys:
	 *            - "id" (int): File id of the file
	 *            - "name" (string): Name of the file
	 *
	 * @return DataResponse
	 */
	public function linkWorkPackageToFile(array $values): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}

		try {
			$result = $this->openprojectAPIService->linkWorkPackageToFile(
				$values,
				$this->userId,
			);
		} catch (InvalidArgumentException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		} catch (OpenprojectErrorException $e) {
			return new DataResponse($e->getMessage(), $e->getcode());
		} catch (NotPermittedException | NotFoundException $e) {
			return new DataResponse('file not found', Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 * @param int $workpackageId
	 * @return DataResponse
	 */
	public function markNotificationAsRead(int $workpackageId) {
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		try {
			$result = $this->openprojectAPIService->markAllNotificationsOfWorkPackageAsRead(
				$workpackageId,
				$this->userId,
			);
		} catch (OpenprojectErrorException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		} catch (Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		if ($result['success'] !== true) {
			return new DataResponse(
				'could not mark notification as read',
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 * @param int $id
	 * @return DataResponse
	 */
	public function getWorkPackageFileLinks(int $id): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
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
		} catch (\Exception $e) {
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
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
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
		} catch (\Exception $e) {
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
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
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
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
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
	 * @NoAdminRequired
	 */
	public function getAvailableOpenProjectProjects(): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		try {
			$result = $this->openprojectAPIService->getAvailableOpenProjectProjects($this->userId);
		} catch (OpenprojectErrorException $e) {
			return new DataResponse($e->getMessage(), $e->getCode());
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse($result);
	}


	/**
	 * @NoAdminRequired
	 *
	 * @param string $projectId
	 * @param array<mixed> $body body is same in the format that OpenProject api expects the body to be i.e
	 *                             {
	 *                                _links: {
	 *                                    type: {
	 *                                        href: '/api/v3/types/1'
	 *                                        title: 'Task'
	 *                                    },
	 *                                    status: {
	 *                                        href: '/api/v3/statuses/1'
	 *                                        title: 'New'
	 *                                    },
	 *                                    assignee: {
	 *                                        href: ''
	 *                                        title: ''
	 *                                    },
	 *                                    project: {
	 *                                         href: '...'
	 *                                         title: '...'
	 *                                     },
	 *                                },
	 *                                subject: "something",
	 *                                description: {
	 *                                    format: 'markdown',
	 *                                    raw: '',
	 *                                    html: ''
	 *                                }
	 *                                }
	 *                           See POST request for create work package https://www.openproject.org/docs/api/endpoints/work-packages/
	 * 							 Note that this api will send `200` even with empty body and the body content is similar to that of create workpackages
	 * @return DataResponse
	 */
	public function getOpenProjectWorkPackageForm(string $projectId, array $body): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		try {
			$result = $this->openprojectAPIService->getOpenProjectWorkPackageForm($this->userId, $projectId, $body);
		} catch (OpenprojectErrorException $e) {
			return new DataResponse($e->getMessage(), $e->getcode());
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $projectId
	 * @return DataResponse
	 */
	public function getAvailableAssigneesOfAProject(string $projectId): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		try {
			$result = $this->openprojectAPIService->getAvailableAssigneesOfAProject($this->userId, $projectId);
		} catch (OpenprojectErrorException $e) {
			return new DataResponse($e->getMessage(), $e->getcode());
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param array<mixed> $body body is same in the format that OpenProject api expects the body to be i.e
	 *                            {
	 * 								_links: {
	 * 									type: {
	 * 									 	href: '/api/v3/types/1'
	 * 										title: 'Task'
	 * 									},
	 * 									status: {
	 * 									 	href: '/api/v3/statuses/1'
	 * 										title: 'New'
	 * 									},
	 * 									assignee: {
	 * 									 	href: ''
	 * 										title: ''
	 * 									},
	 * 									project: {
	 *                                        href: '...'
	 *                                        title: '...'
	 *                                    },
	 * 								},
	 * 								subject: "something",
	 * 								description: {
	 * 									format: 'markdown',
	 * 									raw: '',
	 * 									html: ''
	 * 								}
	 * 								}
	 *                          See POST request for create work package https://www.openproject.org/docs/api/endpoints/work-packages/
	 * @return DataResponse
	 */
	public function createWorkPackage(array $body): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		// we don't want to check if all the data in the body is set or not because
		// that calculation will be done by the openproject api itself
		// we don't want to duplicate the logic
		if (empty($body)) {
			return new DataResponse('Body cannot be empty', Http::STATUS_BAD_REQUEST);
		}
		try {
			$result = $this->openprojectAPIService->createWorkPackage($this->userId, $body);
		} catch (OpenprojectErrorException $e) {
			return new DataResponse($e->getMessage(), $e->getcode());
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse($result, Http::STATUS_CREATED);
	}

	/**
	 * get OpenProject configuration
	 *
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getOpenProjectConfiguration(): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse('', Http::STATUS_UNAUTHORIZED);
		} elseif (!OpenProjectAPIService::validateURL($this->openprojectUrl)) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		try {
			$result = $this->openprojectAPIService->getOpenProjectConfiguration($this->userId);
		} catch (OpenprojectErrorException $e) {
			return new DataResponse($e->getMessage(), $e->getCode());
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse($result);
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
			$this->logger->error(
				"The OpenProject URL '$url' is invalid",
				['app' => $this->appName]
			);
			return new DataResponse(['result' => 'invalid']);
		}
		try {
			$response = $this->openprojectAPIService->rawRequest(
				'', $url, '', [], 'GET',
				['allow_redirects' => false]
			);
			$statusCode = $response->getStatusCode();
			if ($statusCode >= 300 && $statusCode <= 399) {
				$newLocation = $response->getHeader('Location');
				if ($newLocation !== '') {
					return new DataResponse(
						[
							'result' => 'redirected',
							'details' => str_replace('api/v3/', '', $newLocation)
						]
					);
				}
				$this->logger->error(
					"Could not connect to the URL '$url'",
					[
						'app' => $this->appName,
					]
				);
				return new DataResponse(
					[
						'result' => 'unexpected_error',
						'details' => 'received a redirect status code (' . $statusCode . ') but no "Location" header'
					]
				);
			}
			$body = (string) $response->getBody();
			$decodedBody = json_decode($body, true);
			if (
				$decodedBody &&
				isset($decodedBody['_type']) &&
				isset($decodedBody['instanceName']) &&
				$decodedBody['_type'] === 'Root' &&
				$decodedBody['instanceName'] !== ''
			) {
				// sending admin audit log if admin has changed or added the openproject host url
				$this->openprojectAPIService->logToAuditFile(
					"OpenProject host url has been set to '$url' in application " . Application::APP_ID
				);
				return new DataResponse(['result' => true]);
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
				// sending admin audit log if admin has changed or added the openproject host url
				$this->openprojectAPIService->logToAuditFile(
					"OpenProject host url has been set to '$url' in application " . Application::APP_ID
				);
				return new DataResponse(['result' => true]);
			}
			$this->logger->error(
				"Could not connect to the OpenProject. " .
				"There is no valid OpenProject instance at '$url'",
				['app' => $this->appName, 'exception' => $e]
			);
			return new DataResponse(
				[
					'result' => 'client_exception',
					'details' => $response->getStatusCode() . " " . $response->getReasonPhrase()
				]
			);
		} catch (ServerException $e) {
			$response = $e->getResponse();
			$this->logger->error(
				"Could not connect to the OpenProject URL '$url', " .
				"The server replied with " . $response->getStatusCode() . " " . $response->getReasonPhrase(),
				['app' => $this->appName, 'exception' => $e]
			);
			return new DataResponse(
				[
					'result' => 'server_exception',
					'details' => $response->getStatusCode() . " " . $response->getReasonPhrase()
				]
			);
		} catch (RequestException $e) {
			$this->logger->error(
				"Could not connect to the URL '$url'",
				['app' => $this->appName, 'exception' => $e]
			);
			return new DataResponse(
				[
					'result' => 'request_exception',
					'details' => $e->getMessage()
				]
			);
		} catch (LocalServerException $e) {
			$this->logger->error(
				'Accessing OpenProject servers with local addresses is not allowed. ' .
				'To be able to use an OpenProject server with a local address, ' .
				'enable the `allow_local_remote_servers` setting.',
				['app' => $this->appName, 'exception' => $e]
			);
			return new DataResponse(
				[
					'result' => 'local_remote_servers_not_allowed'
				]
			);
		} catch (ConnectException $e) {
			$this->logger->error(
				"A network error occurred while trying to connect to the OpenProject URL '$url'",
				['app' => $this->appName, 'exception' => $e]
			);
			return new DataResponse(
				[
					'result' => 'network_error',
					'details' => $e->getMessage()
				]
			);
		} catch (Exception $e) {
			$this->logger->error(
				"Could not connect to the URL '$url'",
				['app' => $this->appName, 'exception' => $e]
			);
			return new DataResponse(
				[
					'result' => 'unexpected_error',
					'details' => $e->getMessage()
				]
			);
		}
		$this->logger->error(
			"Could not connect to the OpenProject. " .
			"There is no valid OpenProject instance at '$url'",
			['app' => $this->appName, 'data' => $body]
		);
		return new DataResponse(
			[
				'result' => 'not_valid_body',
				'details' => $body
			]
		);
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

	/**
	 * @NoCSRFRequired
	 *
	 * check if the project folder set up is already setup or not
	 *
	 * @return DataResponse
	 */
	public function getProjectFolderSetupStatus(): DataResponse {
		return new DataResponse(
			[
				'result' => $this->openprojectAPIService->isProjectFoldersSetupComplete()
			]
		);
	}
}
