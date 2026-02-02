<?php

/**
 * SPDX-FileCopyrightText: 2021-2025 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2021-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use InvalidArgumentException;
use OC\Authentication\Events\AppPasswordCreatedEvent;
use OC\Authentication\Token\IProvider;
use OC\User\NoUserException;
use OCA\AdminAudit\AuditLogger;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\OIDCIdentityProvider\Exceptions\ClientNotFoundException;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Exception\OpenprojectAvatarErrorException;
use OCA\OpenProject\Exception\OpenprojectErrorException;
use OCA\OpenProject\Exception\OpenprojectGroupfolderSetupConflictException;
use OCA\OpenProject\Exception\OpenprojectResponseException;
use OCA\OpenProject\OIDCClientMapper;
use OCA\OpenProject\TokenEventFactory;
use OCA\TermsOfService\Db\Entities\Signatory;
use OCA\TermsOfService\Db\Mapper\SignatoryMapper;
use OCA\TermsOfService\Db\Mapper\TermsMapper;
use OCA\UserOIDC\Db\ProviderMapper;
use OCA\UserOIDC\User\Backend as OIDCBackend;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\Encryption\IManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Group\ISubAdmin;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAvatarManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Log\ILogFactory;
use OCP\PreConditionNotMetException;
use OCP\Security\ISecureRandom;
use OCP\Server;
use Psr\Log\LoggerInterface;

define('CACHE_TTL', 3600);

class OpenProjectAPIService {
	public const AUTH_METHOD_OAUTH = 'oauth2';
	public const AUTH_METHOD_OIDC = 'oidc';
	public const MIN_SUPPORTED_USER_OIDC_APP_VERSION = '7.2.0';
	public const MIN_SUPPORTED_OIDC_APP_VERSION = '1.14.1';
	public const MIN_SUPPORTED_GROUPFOLDERS_APP_VERSION = '1.0.0';
	public const NEXTCLOUD_HUB_PROVIDER = "nextcloud_hub";

	// 1 hour expiration
	public const DEFAULT_ACCESS_TOKEN_EXPIRATION = 3600;

	/**
	 * @var string
	 */
	private $appName;
	/**
	 * @var IAvatarManager
	 */
	private $avatarManager;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var IL10N
	 */
	private $l10n;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var \OCP\Http\Client\IClient
	 */
	private $client;

	/** @var IRootFolder */
	private $storage;

	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	/**
	 * @var ICache
	 */
	private $cache = null;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * @var IAppManager
	 */
	private $appManager;

	/**
	 * @var ISubAdmin
	 */
	private ISubAdmin $subAdminManager;
	private IDBConnection $db;
	private ILogFactory $logFactory;
	private IManager $encryptionManager;

	/**
	 * @var IUserSession
	 */
	private $userSession;


	/**
	 * Service to make requests to OpenProject v3 (JSON) API
	 */


	private IProvider $tokenProvider;
	private ISecureRandom $random;
	private IEventDispatcher $eventDispatcher;
	private AuditLogger $auditLogger;
	private TokenEventFactory $tokenEventFactory;

	public function __construct(
		string $appName,
		IAvatarManager $avatarManager,
		LoggerInterface $logger,
		IL10N $l10n,
		IConfig $config,
		IClientService $clientService,
		IRootFolder $storage,
		IURLGenerator $urlGenerator,
		ICacheFactory $cacheFactory,
		IUserManager $userManager,
		IGroupManager $groupManager,
		IAppManager $appManager,
		IProvider $tokenProvider,
		ISecureRandom $random,
		IEventDispatcher $eventDispatcher,
		ISubAdmin $subAdminManager,
		IDBConnection $db,
		ILogFactory $logFactory,
		IManager $encryptionManager,
		TokenEventFactory $tokenEventFactory,
		IUserSession $userSession,
		private OIDCClientMapper $oidcClientMapper,
	) {
		$this->appName = $appName;
		$this->avatarManager = $avatarManager;
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->client = $clientService->newClient();
		$this->storage = $storage;
		$this->urlGenerator = $urlGenerator;
		$this->cache = $cacheFactory->createDistributed();
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->appManager = $appManager;
		$this->subAdminManager = $subAdminManager;
		$this->tokenProvider = $tokenProvider;
		$this->random = $random;
		$this->eventDispatcher = $eventDispatcher;
		$this->db = $db;
		$this->logFactory = $logFactory;
		$this->encryptionManager = $encryptionManager;
		$this->tokenEventFactory = $tokenEventFactory;
		$this->userSession = $userSession;
	}

	/**
	 * returns the current date and time with the correct format for OpenProject API
	 *
	 * @return string
	 */
	public function now(): string {
		date_default_timezone_set('UTC');
		$utcTimezone = new DateTimeZone('-0000');
		$nowDt = new Datetime();
		$nowDt->setTimezone($utcTimezone);
		return $nowDt->format('Y-m-d\TH:i:s\Z');
	}

	/**
	 * @param string $userId
	 * @return array<mixed>
	 * @throws \JsonException
	 */
	public function getNotifications(string $userId): array {
		$filters[] = [
			'readIAN' =>
				['operator' => '=', 'values' => ['f']]
		];

		$params = [
			'pageSize' => -1,
			'filters' => json_encode($filters, JSON_THROW_ON_ERROR)
		];
		$result = $this->request($userId, 'notifications', $params);
		if (isset($result['error'])) {
			return $result;
		} elseif (
			!isset($result['_embedded']['elements']) ||
			(   // if there is an element, it also has to contain '_links'
				isset($result['_embedded']['elements'][0]) &&
				!isset($result['_embedded']['elements'][0]['_links'])
			)
		) {
			return ['error' => 'Malformed response'];
		}

		$result = $result['_embedded']['elements'];
		return array_values($result);
	}

	/**
	 * wrapper around IURLGenerator::getBaseUrl() to make it easier to mock in tests
	 */
	public function getBaseUrl(): string {
		return $this->config->getSystemValueString('overwrite.cli.url');
	}

	/**
	 * @param string $userId
	 * @param string|null $query
	 * @param int|null $fileId
	 * @param bool $onlyLinkableWorkPackages
	 * @return array<mixed>
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function searchWorkPackage(
		string $userId,
		?string $query = null,
		?int $fileId = null,
		bool $onlyLinkableWorkPackages = true,
		?int $workPackageId = null
	): array {
		$filters = [];

		// search by description
		if ($fileId !== null) {
			$filters[] = ['file_link_origin_id' => ['operator' => '=', 'values' => [(string)$fileId]]];
		}

		if ($query !== null) {
			$filters[] = ['typeahead' => ['operator' => '**', 'values' => [$query]]];
		}

		//search by wpId
		if ($workPackageId !== null) {
			$filters[] = ['id' => ['operator' => '=', 'values' => [(string)$workPackageId]]];
		}

		$resultsById = $this->searchRequest($userId, $filters, $onlyLinkableWorkPackages);
		if (isset($resultsById['error'])) {
			return $resultsById;
		}
		return array_values($resultsById);
	}

	/**
	 * @param string $userId
	 * @param array<mixed> $filters
	 * @param bool $onlyLinkableWorkPackages
	 * @return array<mixed>
	 * @throws \OCP\PreConditionNotMetException|\JsonException
	 */
	private function searchRequest(string $userId, array $filters, bool $onlyLinkableWorkPackages = true): array {
		$resultsById = [];
		$sortBy = [['updatedAt', 'desc']];
		if ($onlyLinkableWorkPackages) {
			$filters[] = [
				'linkable_to_storage_url' =>
					['operator' => '=', 'values' => [urlencode($this->getBaseUrl())]]
			];
		}

		$params = [
			'filters' => json_encode($filters, JSON_THROW_ON_ERROR),
			'sortBy' => json_encode($sortBy, JSON_THROW_ON_ERROR),
		];
		$searchResult = $this->request($userId, 'work_packages', $params);

		if (isset($searchResult['error'])) {
			return $searchResult;
		}

		if (isset($searchResult['_embedded'], $searchResult['_embedded']['elements'])) {
			foreach ($searchResult['_embedded']['elements'] as $wp) {
				$resultsById[$wp['id']] = $wp;
			}
		}
		return $resultsById;
	}
	/**
	 * authenticated request to get an image from openproject
	 *
	 * @param string $openprojectUserId
	 * @param string $openprojectUserName
	 * @param string $nextcloudUserId
	 * @return array{avatar: string, type?: string}
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\Lock\LockedException
	 */
	public function getOpenProjectAvatar(
		string $openprojectUserId,
		string $openprojectUserName,
		string $nextcloudUserId
	): array {
		$accessToken = $this->getAccessToken($nextcloudUserId);
		$openprojectUrl = $this->config->getAppValue(Application::APP_ID, 'openproject_instance_url');
		try {
			$response = $this->rawRequest(
				$accessToken, $openprojectUrl, 'users/'.$openprojectUserId.'/avatar'
			);
			$imageMimeType = $response->getHeader('Content-Type');
			$imageData = $response->getBody();

			// Check if the 'Content-Type' header exists
			if (empty($imageMimeType)) {
				throw new OpenprojectAvatarErrorException(
					'The response does not contain an image content-type.'
				);
			}

			// check if response contains image
			if (!@imagecreatefromstring($imageData)) {
				throw new OpenprojectAvatarErrorException(
					'The response contains invalid image data.'
				);
			}

			// check mimetype of response with content-type value
			// Create a temporary file
			$tempImageFile = tempnam(sys_get_temp_dir(), 'image');
			file_put_contents($tempImageFile, $imageData);

			// Get the MIME type of the temporary file
			$imageMimeTypeFromImageData = mime_content_type($tempImageFile);
			unlink($tempImageFile);
			if ($imageMimeType != $imageMimeTypeFromImageData) {
				throw new OpenprojectAvatarErrorException(
					"The content-type header is '$imageMimeType ' but the mime-type of the image is '$imageMimeTypeFromImageData'."
				);
			}
			return [
				'avatar' => $imageData,
				'type' => $imageMimeType ,
			];
		} catch (ServerException | ClientException | ConnectException | OpenprojectAvatarErrorException | Exception $e) {
			$this->logger->debug('Error while getting OpenProject avatar for user ' . $openprojectUserId . ': ' . $e->getMessage(), ['app' => $this->appName]);
			$avatar = $this->avatarManager->getGuestAvatar($openprojectUserName);
			$avatarContent = $avatar->getFile(64)->getContent();
			return ['avatar' => $avatarContent];
		}
	}

	/**
	 * @param string $accessToken
	 * @param string $openprojectUrl
	 * @param string $endPoint
	 * @param array<mixed> $params
	 * @param string $method
	 * @param array<mixed> $options further options to be given to Guzzle see https://docs.guzzlephp.org/en/stable/request-options.html
	 * @return array{error: string} | IResponse
	 */
	public function rawRequest(
		string $accessToken,
		string $openprojectUrl,
		string $endPoint, array $params = [],
		string $method = 'GET',
		array $options = []
	) {
		$url = $openprojectUrl . '/api/v3/' . $endPoint;
		if (!isset($options['headers']['Authorization'])) {
			$options['headers']['Authorization'] = 'Bearer ' . $accessToken;
		}
		if (!isset($options['headers']['User-Agent'])) {
			$options['headers']['User-Agent'] = 'Nextcloud OpenProject integration';
		}
		if (count($params) > 0) {
			if ($method === 'GET') {
				// manage array parameters
				$paramsContent = '';
				foreach ($params as $key => $value) {
					if (is_array($value)) {
						foreach ($value as $oneArrayValue) {
							$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
						}
						unset($params[$key]);
					}
				}
				$paramsContent .= http_build_query($params);
				$url .= '?' . $paramsContent;
			} else {
				if (isset($params['body'])) {
					$options['body'] = $params['body'];
				}
				if (!isset($options['headers']['Content-Type'])) {
					$options['headers']['Content-Type'] = 'application/json';
				}
			}
		} elseif ($method === 'DELETE') {
			if (!isset($options['headers']['Content-Type'])) {
				$options['headers']['Content-Type'] = 'application/json';
			}
		}

		if ($method === 'GET') {
			$response = $this->client->get($url, $options);
		} elseif ($method === 'POST') {
			$response = $this->client->post($url, $options);
		} elseif ($method === 'PUT') {
			$response = $this->client->put($url, $options);
		} elseif ($method === 'DELETE') {
			$response = $this->client->delete($url, $options);
		} else {
			return ['error' => $this->l10n->t('Bad HTTP method')];
		}
		return $response;
	}

	/**
	 * @param string $userId
	 * @param string $endPoint
	 * @param array<mixed> $params
	 * @param string $method
	 * @return array<mixed>
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function request(
		string $userId,
		string $endPoint,
		array $params = [],
		string $method = 'GET'
	): array {
		$accessToken = $this->getAccessToken($userId);
		$openprojectUrl = $this->config->getAppValue(Application::APP_ID, 'openproject_instance_url');
		if (!$openprojectUrl || !OpenProjectAPIService::validateURL($openprojectUrl)) {
			return ['error' => 'OpenProject URL is invalid', 'statusCode' => 500];
		}
		try {
			$response = $this->rawRequest($accessToken, $openprojectUrl, $endPoint, $params, $method);
			if (($method === 'DELETE' || $method === 'POST') &&
				$response->getStatusCode() === Http::STATUS_NO_CONTENT
			) {
				return ['success' => true];
			}
			return json_decode($response->getBody(), true);
		} catch (ServerException | ClientException $e) {
			$message = $e->getMessage();
			$response = $e->getResponse();
			$body = (string) $response->getBody();
			$decodedBody = json_decode($body, true);
			// try to get the error in the response
			if ($decodedBody && isset($decodedBody['message'])) {
				if (gettype($decodedBody['message']) === 'array') {
					// the OpenProject API sometimes responds with an array as message
					// e.g. when sending a not-existing workpackage as resourceId filter
					// to /api/v3/notifications/read_ian
					// see POST /api/v3/notifications/read_ian in https://community.openproject.org/api/docs
					$message = implode(' ', $decodedBody['message']);
				} else {
					$message = $decodedBody['message'];
				}
			}
			$this->logger->error('OpenProject API error : '. $message, ['app' => $this->appName]);
			return [
				'error' => $body,
				'message' => $message,
				'statusCode' => $response->getStatusCode(),
			];
		} catch (ConnectException $e) {
			$this->logger->error('OpenProject connection error : '.$e->getMessage(), ['app' => $this->appName]);
			return [
				'error' => $e->getMessage(),
				'statusCode' => 404,
			];
		} catch (Exception $e) {
			$this->logger->critical('OpenProject error : '.$e->getMessage(), ['app' => $this->appName]);
			return [
				'error' => $e->getMessage(),
				'statusCode' => 500,
			];
		}
	}

	/**
	 * @param string $userId
	 * @param string $url
	 * @param array<mixed> $params passed to `http_build_query` for GET requests, else send as body
	 * @param string $method
	 * @return array<mixed>
	 */
	public function requestOAuthAccessToken(string $userId, string $url, array $params = [], string $method = 'POST'): array {
		try {
			$url = $url . '/oauth/token';
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud OpenProject integration',
				]
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('OAuth access token refused')];
			}

			$resJson = json_decode($body, true);

			if (isset($resJson['access_token'])) {
				$this->config->setUserValue($userId, Application::APP_ID, 'token', $resJson['access_token']);
				if ($resJson['created_at'] && isset($resJson['expires_in'])) {
					$expiresAt = $resJson['created_at'] + $resJson['expires_in'];
				} else {
					$this->logger->warning('Token response does not contain created_at or expires_in. Using default expiration.', ['app' => $this->appName]);

					if (!isset($resJson['created_at'])) {
						$resJson['created_at'] = time();
					}
					if (!isset($resJson['expires_in'])) {
						$resJson['expires_in'] = self::DEFAULT_ACCESS_TOKEN_EXPIRATION;
					}
					$expiresAt = $resJson['created_at'] + $resJson['expires_in'];
				}
				$this->logger->debug('New token expires at ' . date('Y/m/d H:i:s', $expiresAt), ['app' => $this->appName]);
				$this->config->setUserValue($userId, Application::APP_ID, 'token_expires_at', $expiresAt);
			}
			if (isset($resJson['refresh_token'])) {
				$this->config->setUserValue($userId, Application::APP_ID, 'refresh_token', $resJson['refresh_token']);
			}

			return $resJson;
		} catch (Exception $e) {
			$this->logger->warning('OpenProject OAuth error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}

	public static function validateURL(string $url): bool {
		return filter_var($url, FILTER_VALIDATE_URL) &&
			preg_match('/^https?/', $url);
	}

	/**
	 * authenticated request to get status of a work package
	 *
	 * @param string $userId
	 * @param string $statusId
	 * @return string[]
	 */
	public function getOpenProjectWorkPackageStatus(string $userId, string $statusId): array {
		$result = $this->cache->get(Application::APP_ID . '/statuses/' . $statusId);
		if ($result !== null) {
			return $result;
		}
		$result = $this->request($userId, 'statuses/' . $statusId);
		if (!isset($result['id'])) {
			return ['error' => 'Malformed response'];
		}

		$this->cache->set(
			Application::APP_ID . '/statuses/' . $statusId,
			$result,
			CACHE_TTL
		);
		return $result;
	}

	/**
	 * authenticated request to get status of a work package
	 *
	 * @param string $userId
	 * @param string $typeId
	 * @return string[]
	 */
	public function getOpenProjectWorkPackageType(string $userId, string $typeId): array {
		$result = $this->cache->get(Application::APP_ID . '/types/' . $typeId);
		if ($result !== null) {
			return $result;
		}
		$result = $this->request($userId, 'types/' . $typeId);
		if (!isset($result['id'])) {
			return ['error' => 'Malformed response'];
		}
		$this->cache->set(
			Application::APP_ID . '/types/' . $typeId,
			$result,
			CACHE_TTL
		);

		return $result;
	}

	/**
	 * @param IConfig $config
	 * @param IURLGenerator $urlGenerator
	 * @return string
	 * generates an oauth url to OpenProject containing openproject_client_id & redirect_uri as parameter
	 * please note that the state parameter is still missing, that needs to be generated dynamically
	 * and saved to the DB before calling the OAuth URI
	 * @throws Exception
	 */
	public static function getOpenProjectOauthURL(IConfig $config, IURLGenerator $urlGenerator): string {
		if (!self::isAdminConfigOk($config)) {
			throw new \Exception('OpenProject admin config is not valid!');
		}
		$clientID = $config->getAppValue(Application::APP_ID, 'openproject_client_id');
		$oauthUrl = $config->getAppValue(Application::APP_ID, 'openproject_instance_url');

		// remove trailing slash from the oauthUrl if present
		if (substr($oauthUrl, -1) === '/') {
			$oauthUrl = substr($oauthUrl, 0, -1);
		}

		return $oauthUrl .
			'/oauth/authorize' .
			'?client_id=' . $clientID .
			'&redirect_uri=' . urlencode(self::getOauthRedirectUrl($urlGenerator)) .
			'&response_type=code';
	}

	public static function getOauthRedirectUrl(IURLGenerator $urlGenerator): string {
		return $urlGenerator->getAbsoluteURL(
			'/index.php/apps/' . Application::APP_ID . '/oauth-redirect'
		);
	}

	/**
	 * @param string $userId
	 * @param int $fileId
	 * @return \OCP\Files\Node
	 * @throws NotPermittedException
	 * @throws \OC\User\NoUserException
	 * @throws NotFoundException
	 */
	public function getNode($userId, $fileId) {
		$userFolder = $this->storage->getUserFolder($userId);

		$file = $userFolder->getById($fileId);
		if (isset($file[0]) && $file[0] instanceof Node) {
			return $file[0];
		}
		throw new NotFoundException();
	}

	/**
	 *
	 * @param array<mixed> $values An array containing the following keys:
	 *        - "workpackageId" (int): The ID of the work package.
	 *        - "fileinfo" (array):  An array of file information with the following keys:
	 *            - "id" (int): File id of the file
	 *            - "name" (string): Name of the file
	 * @param string $userId
	 *
	 * @return array<int>
	 * @throws NotFoundException
	 * @throws \OCP\PreConditionNotMetException
	 * @throws NotPermittedException
	 * @throws OpenprojectErrorException
	 * @throws \OC\User\NoUserException
	 * @throws OpenprojectResponseException
	 * @throws InvalidArgumentException
	 * @throws InvalidPathException
	 * @throws \JsonException
	 *
	 */
	public function linkWorkPackageToFile(
		array $values,
		string $userId
	): array {
		$allowedKeys = [
			'workpackageId',
			'fileinfo'
		];
		foreach (array_keys($values) as $key) {
			if (!in_array($key, $allowedKeys)) {
				throw new InvalidArgumentException('invalid key');
			}
		}
		if (!is_int($values['workpackageId']) || !is_array($values['fileinfo']) || empty($values['fileinfo'])) {
			throw new InvalidArgumentException('invalid data');
		}
		$fileInfos = $values['fileinfo'];
		$elements = [];
		// multiple files can also be linked to a single work package
		foreach ($fileInfos as $fileInfo) {
			if (!key_exists('id', $fileInfo) || !key_exists('name', $fileInfo) || !is_int($fileInfo['id']) || $fileInfo['name'] === '' || !is_string($fileInfo['name'])) {
				throw new InvalidArgumentException('invalid data');
			}
			$fileId = $fileInfo["id"];
			$fileName = $fileInfo["name"];
			$file = $this->getNode($userId, $fileId);
			if (!$file->isReadable()) {
				throw new NotPermittedException();
			}
			$element = [
				'originData' => [
					'id' => $fileId,
					'name' => $fileName,
					'mimeType' => $file->getMimeType(),
					'createdAt' => gmdate('Y-m-d\TH:i:s.000\Z', $file->getCreationTime()),
					'lastModifiedAt' => gmdate('Y-m-d\TH:i:s.000\Z', $file->getMTime()),
					'createdByName' => '',
					'lastModifiedByName' => ''
				],
				'_links' => [
					'storageUrl' => [
						'href' => $this->getBaseUrl()
					]
				]
			];
			$elements[] = $element;
		}

		$body = [
			'_type' => 'Collection',
			'_embedded' => [
				'elements' => $elements
			]
		];

		$params['body'] = json_encode($body, JSON_THROW_ON_ERROR);
		$result = $this->request(
			$userId, 'work_packages/' . $values["workpackageId"] . '/file_links', $params, 'POST'
		);

		if (isset($result['error'])) {
			throw new OpenprojectErrorException($result['error'], $result['statusCode']);
		}
		if (
			!isset($result['_type']) ||
			$result['_type'] !== 'Collection' ||
			!isset($result['_embedded']) ||
			!isset($result['_embedded']['elements'])
		) {
			throw new OpenprojectResponseException('Malformed response');
		}
		$fileIds = [];
		for ($i = 0; $i < count($fileInfos); $i++) {
			if (
				!isset($result['_embedded']['elements'][$i]) ||
				!isset($result['_embedded']['elements'][$i]['id'])
			) {
				throw new OpenprojectResponseException('Malformed response');
			}
			$fileIds [] = $result['_embedded']['elements'][$i]['id'];
		}
		return $fileIds;
	}

	/**
	 * @throws OpenprojectErrorException
	 * @throws PreConditionNotMetException
	 * @throws Exception
	 * @return array<mixed>
	 */
	public function markAllNotificationsOfWorkPackageAsRead(
		int $workpackageId, string $userId
	) {
		$filters[] = [
			'resourceId' =>
				['operator' => '=', 'values' => [(string)$workpackageId]]
		];
		$params['body'] = '';
		$fullUrl = 'notifications/read_ian?filters=' . urlencode(json_encode($filters, JSON_THROW_ON_ERROR));

		$result = $this->request(
			$userId, $fullUrl, $params, 'POST'
		);
		if (isset($result['error'])) {
			throw new OpenprojectErrorException($result['error']);
		}
		return $result;
	}
	/**
	 * @param int $workpackageId
	 * @param string $userId
	 * @return array<mixed>
	 * @throws NotFoundException
	 * @throws OpenprojectErrorException
	 * @throws OpenprojectResponseException
	 */
	public function getWorkPackageFileLinks(int $workpackageId, string $userId): array {
		$result = $this->request(
			$userId, 'work_packages/' . $workpackageId. '/file_links'
		);
		if (isset($result['error'])) {
			throw new OpenprojectErrorException($result['error']);
		}
		if (
			!isset($result['_type']) ||
			$result['_type'] !== 'Collection' ||
			!isset($result['_embedded']) ||
			!isset($result['_embedded']['elements'])
		) {
			throw new OpenprojectResponseException('Malformed response');
		}
		return $result['_embedded']['elements'];
	}

	/**
	 * @param int $fileLinkId
	 * @param string $userId
	 * @return array<mixed>
	 * @throws NotFoundException
	 * @throws OpenprojectErrorException|OpenprojectResponseException
	 */
	public function deleteFileLink(int $fileLinkId, string $userId): array {
		$result = $this->request(
			$userId, 'file_links/' . $fileLinkId, [""], 'DELETE'
		);
		if (isset($result['error'])) {
			throw new OpenprojectErrorException($result['error']);
		}
		if (!isset($result['success'])) {
			throw new OpenprojectResponseException('Malformed response');
		}
		return $result;
	}

	/**
	 * check common admin settings
	 * @param IConfig $config
	 *
	 * @return bool
	 */
	public static function isCommonAdminConfigOk(IConfig $config): bool {
		$oauthInstanceUrl = $config->getAppValue(Application::APP_ID, 'openproject_instance_url');
		$freshProjectFolderSetUp = (bool)$config->getAppValue(Application::APP_ID, 'fresh_project_folder_setup');

		if ($freshProjectFolderSetUp === true || empty($oauthInstanceUrl) || !self::validateURL($oauthInstanceUrl)) {
			return false;
		}

		return true;
	}

	/**
	 * checks if every admin config for oauth2 based authorization variables are set
	 *
	 * @param IConfig $config
	 * @return bool
	 */
	public static function isAdminConfigOkForOauth2(IConfig $config):bool {
		if (!self::isCommonAdminConfigOk($config)) {
			return false;
		}

		$authMethod = $config->getAppValue(Application::APP_ID, 'authorization_method');
		// NOTE: For backwards compability, check the auth method only if provided
		// version: 2.8 -> 2.9
		if ($authMethod && $authMethod !== self::AUTH_METHOD_OAUTH) {
			return false;
		}

		$opClientId = $config->getAppValue(Application::APP_ID, 'openproject_client_id');
		$opClientSecret = $config->getAppValue(Application::APP_ID, 'openproject_client_secret');
		$ncClientId = $config->getAppValue(Application::APP_ID, 'nc_oauth_client_id');
		return !(empty($opClientId) || empty($opClientSecret) || empty($ncClientId));
	}

	/**
	 * checks if every admin config for oidc based authorization variables are set
	 *
	 * @param IConfig $config
	 * @return bool
	 */
	public static function isAdminConfigOkForOIDCAuth(IConfig $config):bool {
		if (!self::isCommonAdminConfigOk($config)) {
			return false;
		}

		$authMethod = $config->getAppValue(Application::APP_ID, 'authorization_method');
		if ($authMethod !== self::AUTH_METHOD_OIDC) {
			return false;
		}

		$oidcProvider = $config->getAppValue(Application::APP_ID, 'oidc_provider');
		$ssoProviderType = $config->getAppValue(Application::APP_ID, 'sso_provider_type');
		$targetAudienceClientId = $config->getAppValue(Application::APP_ID, 'targeted_audience_client_id');
		$tokenExchange = (bool)$config->getAppValue(Application::APP_ID, 'token_exchange');

		if (empty($ssoProviderType) || empty($oidcProvider)) {
			return false;
		}

		// check for nextcloud_hub sso
		if ($ssoProviderType === SettingsService::NEXTCLOUDHUB_OIDC_PROVIDER_TYPE) {
			return !empty($targetAudienceClientId);
		}

		// check for external sso without token exchange
		if ($ssoProviderType === SettingsService::EXTERNAL_OIDC_PROVIDER_TYPE && $tokenExchange === false) {
			return true;
		}

		// check for external sso with token exchange
		if ($ssoProviderType === SettingsService::EXTERNAL_OIDC_PROVIDER_TYPE && $tokenExchange === true) {
			return !empty($targetAudienceClientId);
		}

		return false;
	}

	/**
	 * returns overall admin config status whether it be 'oidc' or 'oauth2'
	 *
	 * @return bool
	 */
	public static function isAdminConfigOk(IConfig $config): bool {
		$authMethod = $config->getAppValue(Application::APP_ID, 'authorization_method');

		if ($authMethod === self::AUTH_METHOD_OAUTH) {
			return self::isAdminConfigOkForOauth2($config);
		}

		if ($authMethod === self::AUTH_METHOD_OIDC) {
			return self::isAdminConfigOkForOIDCAuth($config);
		}

		return false;
	}

	/**
	 * makes sure the URL has no extra slashes
	 */
	public static function sanitizeUrl(
		string $url, bool $trailingSlash = false
	): string {
		if ($trailingSlash === true) {
			$url = $url . "/";
		} else {
			$url = \rtrim($url, "/");
		}
		$url = (string)\preg_replace("/([^:]\/)\/+/", '$1', $url);
		return $url;
	}

	/**
	 * Sends a POST request to the OpenProject API server to revoke an OAuth token for the provided client
	 *
	 * @param string $userUID the uid of the user to revoke the token for
	 * @param string $openProjectUrl the url of the openproject instance
	 * @param string $accessToken the refresh token to be revoked
	 * @param string $clientId the client id of the OAuth app
	 * @param string $clientSecret the client secret of the OAuth app
	 *
	 * @return void
	 * @throws OpenprojectErrorException
	 * @throws ConnectException
	 */
	public function revokeUserOAuthToken(
		string $userUID,
		string $openProjectUrl,
		string $accessToken,
		string $clientId,
		string $clientSecret
	): void {
		$options = [
			'headers' => [
				'User-Agent' => 'Nextcloud OpenProject integration',
				'Content-Type' => 'application/x-www-form-urlencoded',
				'Authorization' => 'Basic ' . \base64_encode($clientId . ':' . $clientSecret)
			]
		];
		try {
			$response = $this->client->post(
				rtrim($openProjectUrl, "/") . '/oauth/revoke' . '?token=' . $accessToken,
				$options
			);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();
			if ($respCode !== 200) {
				throw new OpenprojectErrorException('Failed to revoke token in OpenProject for user "'. $userUID . '".\nResponse body: "' . $body . '"');
			}
		} catch (ConnectException $e) {
			throw new ConnectException(
				'Could not revoke token in OpenProject for user "' .
				$userUID . '".\n Message: "' . $e->getMessage() . '"',
				$e->getRequest()
			);
		} catch (ServerException | ClientException | Exception $e) {
			throw new OpenprojectErrorException('Could not revoke token in OpenProject for user "' . $userUID . '".\n Message: "' . $e->getMessage() . '"');
		}
	}

	/**
	 * @throws OpenprojectGroupfolderSetupConflictException
	 */
	public function isSystemReadyForProjectFolderSetUp(): bool {
		if ($this->userManager->userExists(Application::OPEN_PROJECT_ENTITIES_NAME) && $this->groupManager->groupExists(Application::OPEN_PROJECT_ENTITIES_NAME)) {
			if (!$this->isGroupfoldersAppEnabled()) {
				throw new \Exception('The "groupfolders" app is not installed');
			}
		}
		if ($this->userManager->userExists(Application::OPEN_PROJECT_ENTITIES_NAME)) {
			throw new OpenprojectGroupfolderSetupConflictException('The user "'. Application::OPEN_PROJECT_ENTITIES_NAME .'" already exists');
		} elseif ($this->groupManager->groupExists(Application::OPEN_PROJECT_ENTITIES_NAME)) {
			throw new OpenprojectGroupfolderSetupConflictException('The group "'. Application::OPEN_PROJECT_ENTITIES_NAME .'" already exists');
		} elseif (!$this->isGroupfoldersAppEnabled()) {
			throw new \Exception('The "groupfolders" app is not installed');
		} elseif ($this->isOpenProjectGroupfolderCreated()) {
			throw new OpenprojectGroupfolderSetupConflictException(
				'The team folder name "' .
				Application::OPEN_PROJECT_ENTITIES_NAME .
				'" already exists'
			);
		}
		return true;
	}

	/**
	 * checks whether the whole setup of the managed project folders is completed
	 *
	 * @return bool
	 */
	public function isProjectFoldersSetupComplete(): bool {
		return (
			$this->userManager->userExists(Application::OPEN_PROJECT_ENTITIES_NAME) &&
			$this->groupManager->groupExists(Application::OPEN_PROJECT_ENTITIES_NAME) &&
			$this->isUserPartOfAndAdminOfGroup() &&
			$this->isGroupfoldersAppEnabled() &&
			$this->isGroupfolderAppCorrectlySetup()
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function getProjectFolderSetupInformation(): array {
		$status = $this->isProjectFoldersSetupComplete();
		$errorMessage = null;
		if (!$status) {
			try {
				$this->isSystemReadyForProjectFolderSetUp();
			} catch (Exception $e) {
				$errorMessage = $e->getMessage();
			}
		}
		if ($errorMessage !== null) {
			return [
				'status' => $status,
				'errorMessage' => $errorMessage
			];
		}
		return [
			'status' => $status
		];
	}

	/**
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 * @throws NoUserException
	 */
	public function createGroupfolder(): void {
		$groupfoldersFolderManager = Server::get(FolderManager::class);
		$folderId = $groupfoldersFolderManager->createFolder(
			Application::OPEN_PROJECT_ENTITIES_NAME
		);

		// this also works if the group does not exist
		$groupfoldersFolderManager->addApplicableGroup(
			$folderId, Application::OPEN_PROJECT_ENTITIES_NAME
		);

		$groupfoldersFolderManager->setFolderACL($folderId, true);

		// this also works if the user does not exist
		$groupfoldersFolderManager->setManageACL(
			$folderId,
			'user',
			Application::OPEN_PROJECT_ENTITIES_NAME,
			true
		);
	}

	public function getGroupFolderManager(): FolderManager {
		$groupfoldersFolderManager = Server::get(FolderManager::class);
		return $groupfoldersFolderManager;
	}

	/**
	 * @param mixed $folder
	 * @return array<mixed>
	 */
	public function groupFolderToArray(mixed $folder): array {
		// NOTE: groupfolders app has changed the return type in the recent versions
		// for backwards compatibility we check if the new class exists
		// and get the folder as an array
		if (class_exists('\OCA\GroupFolders\Folder\FolderDefinition') && is_object($folder)) {
			$folder = $folder->toArray();
			$folder['folder_id'] = $folder['id'];
		}
		if (!is_array($folder)) {
			throw new InvalidArgumentException(
				'Invalid folder type. Expected array, got: ' . gettype($folder)
			);
		}
		return $folder;
	}

	public function isOpenProjectGroupfolderCreated(): bool {
		$groupfoldersFolderManager = $this->getGroupFolderManager();
		$folders = $groupfoldersFolderManager->getAllFolders();
		foreach ($folders as $folder) {
			$folder = $this->groupFolderToArray($folder);
			if ($folder['mount_point'] === Application::OPEN_PROJECT_ENTITIES_NAME) {
				return true;
			}
		}
		return false;
	}

	public function isGroupfoldersAppEnabled(): bool {
		$user = $this->userManager->get(Application::OPEN_PROJECT_ENTITIES_NAME);
		return (
			class_exists('\OCA\GroupFolders\Folder\FolderManager') &&
			$this->appManager->isEnabledForUser(
				'groupfolders',
				$user
			)
		);
	}

	/**
	 * @return bool
	 */
	public function isGroupfoldersAppSupported(): bool {
		$appVersion = $this->appManager->getAppVersion('groupfolders');
		return (
			$this->isGroupfoldersAppEnabled() &&
			version_compare($appVersion, self::MIN_SUPPORTED_GROUPFOLDERS_APP_VERSION) >= 0
		);
	}

	/**
	 * @param $auditLogMessage
	 * @return void
	 */
	public function logToAuditFile($auditLogMessage): void {
		if ($this->isAdminAuditConfigSetCorrectly()) {
			$this->auditLogger = new AuditLogger($this->logFactory, $this->config);
			$this->auditLogger->info($auditLogMessage,
				['app' => 'admin_audit']
			);
		}
	}

	public function isAdminAuditConfigSetCorrectly(): bool {
		$logLevel = $this->config->getSystemValue('loglevel');
		$configAuditFile = $this->config->getSystemValue('logfile_audit');
		$logCondition = $this->config->getSystemValue('log.condition');
		// All the above config should be satisfied in order for admin audit log for the integration application
		// if any of the config is failed to be set then we are not able to send the admin audit logging in the audit.log file
		return (
			$this->appManager->isInstalled('admin_audit') &&
			$configAuditFile === $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data') . '/audit.log' &&
			(isset($logCondition["apps"]) && in_array('admin_audit', $logCondition["apps"])) &&
			$logLevel >= 1
		);
	}

	public function isTermsOfServiceAppEnabled(): bool {
		return (
			class_exists('\OCA\TermsOfService\Db\Entities\Signatory') &&
			class_exists('\OCA\TermsOfService\Db\Mapper\SignatoryMapper') &&
			class_exists('\OCA\TermsOfService\Db\Mapper\TermsMapper') &&
			$this->appManager->isInstalled(
				'terms_of_service',
			)
		);
	}

	public function isServerSideEncryptionEnabled(): bool {
		$isEncryptionForHomeStorageEnabled = $this->config->getAppValue('encryption', 'encryptHomeStorage', '0') === '1';
		return (
			$this->appManager->isInstalled('encryption') &&
			$this->encryptionManager->isEnabled() &&
			$isEncryptionForHomeStorageEnabled
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function getAllTermsOfServiceAvailable(): array {
		$termsMapper = new TermsMapper($this->db);
		return $termsMapper->getTerms();
	}

	/**
	 * @return array<mixed>
	 */
	public function getAllTermsOfServiceSignedByUserOpenProject($signatoryMapper): array {
		$alreadySignedTermsIdForUserOpenProject = [];
		if ($this->userManager->userExists(Application::OPEN_PROJECT_ENTITIES_NAME)) {
			$user = $this->userManager->get(Application::OPEN_PROJECT_ENTITIES_NAME);
			// get all the signed TOS for user "OpenProject"
			$signatoriesByUserOpenProject = $signatoryMapper->getSignatoriesByUser($user);
			if ($signatoriesByUserOpenProject) {
				foreach ($signatoriesByUserOpenProject as $signature) {
					$alreadySignedTermsIdForUserOpenProject[] = $signature->getTermsId();
				}
			}
		}
		return $alreadySignedTermsIdForUserOpenProject;
	}

	/**
	 * @return bool
	 */
	public function isAllTermsOfServiceSignedForUserOpenProject(?SignatoryMapper $signatoryMapper = null): bool {
		if ($this->isTermsOfServiceAppEnabled() && $this->userManager->userExists(Application::OPEN_PROJECT_ENTITIES_NAME)) {
			if ($signatoryMapper === null) {
				$signatoryMapper = new SignatoryMapper($this->db);
			}
			$terms = $this->getAllTermsOfServiceAvailable();
			$alreadySignedTermsIdForUserOpenProject = $this->getAllTermsOfServiceSignedByUserOpenProject($signatoryMapper);
			foreach ($terms as $term) {
				$termId = $term->id;
				if (!in_array($termId, $alreadySignedTermsIdForUserOpenProject)) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function signTermsOfServiceForUserOpenProject(): void {
		$signatoryMapper = new SignatoryMapper($this->db);
		// get all the available terms of services
		$terms = $this->getAllTermsOfServiceAvailable();
		$alreadySignedTermsIdForUserOpenProject = $this->getAllTermsOfServiceSignedByUserOpenProject($signatoryMapper);
		foreach ($terms as $term) {
			$termId = $term->id;
			// sign only not signed TOS for user "OpenProject"
			if (!in_array($termId, $alreadySignedTermsIdForUserOpenProject)) {
				$signatory = new Signatory();
				$signatory->setUserId(Application::OPEN_PROJECT_ENTITIES_NAME);
				$signatory->setTermsId($termId);
				$signatory->setTimestamp(time());
				$signatoryMapper->insert($signatory);
			}
		}
	}

	public function isUserPartOfAndAdminOfGroup():bool {
		if ($this->groupManager->isInGroup(
			Application::OPEN_PROJECT_ENTITIES_NAME,
			Application::OPEN_PROJECT_ENTITIES_NAME
		) &&
			$this->subAdminManager->isSubAdminOfGroup(
				$this->userManager->get(Application::OPEN_PROJECT_ENTITIES_NAME),
				$this->groupManager->get(Application::OPEN_PROJECT_ENTITIES_NAME)
			)) {
			return true;
		}
		return false;
	}

	/**
	 * checks:
	 * - if the group names OpenProject is managing a folder called OpenProject
	 * - if the permissions are set correctly
	 * - if the ACL is enabled and can be managed by the user OpenProject
	 * @return bool
	 * @throws \OCP\DB\Exception
	 */
	private function isGroupfolderAppCorrectlySetup():bool {
		$groupFolderManager = $this->getGroupFolderManager();
		$folders = $groupFolderManager->getFoldersForGroups([Application::OPEN_PROJECT_ENTITIES_NAME]);
		foreach ($folders as $folder) {
			$folder = $this->groupFolderToArray($folder);
			if (
				$folder['mount_point'] === Application::OPEN_PROJECT_ENTITIES_NAME &&
				$folder['permissions'] === 31 &&
				$folder['acl'] === true
			) {
				if ($groupFolderManager->canManageACL(
					$folder['folder_id'],
					$this->userManager->get(Application::OPEN_PROJECT_ENTITIES_NAME)
				)) {
					return true;
				} else {
					return false;
				}
			}
		}
		return false;
	}

	/**
	 * @return int
	 */
	public function getPasswordLength(): int {
		$passLengthSet = (int) $this->config->getAppValue('password_policy', 'minLength', '0');
		return $passLengthSet === 0 ? 72 : max(72, $passLengthSet);
	}

	/**
	 * @return string
	 */
	public function generateAppPasswordTokenForUser(): string {
		$user = $this->userManager->get(Application::OPEN_PROJECT_ENTITIES_NAME);
		$userID = $user->getUID();
		$token = $this->random->generate(self::getPasswordLength(), ISecureRandom::CHAR_ALPHANUMERIC.ISecureRandom::CHAR_SYMBOLS);
		$generatedToken = $this->tokenProvider->generateToken(
			$token,
			$userID,
			$userID,
			null,
			Application::OPEN_PROJECT_ENTITIES_NAME,
			1 // type 0 => Temporary app password token where as type 1 => Permanent app password token
		);
		$this->eventDispatcher->dispatchTyped(
			new AppPasswordCreatedEvent($generatedToken)
		);
		return $token;
	}

	/**
	 * Deletes the created app password for user OpenProject
	 *
	 * @return void
	 */
	public function deleteAppPassword(): void {
		if ($this->hasAppPassword()) {
			$tokens = $this->tokenProvider->getTokenByUser(Application::OPEN_PROJECT_ENTITIES_NAME);
			foreach ($tokens as $token) {
				if ($token->getName() === Application::OPEN_PROJECT_ENTITIES_NAME) {
					$this->tokenProvider->invalidateTokenById(Application::OPEN_PROJECT_ENTITIES_NAME, $token->getId());
					$this->logToAuditFile(
						"Application password for user 'OpenProject has been deleted' in application " . Application::APP_ID
					);
				}
			}
		}
	}

	/**
	 * check if app password for user OpenProject is already created
	 *
	 * @return bool
	 */
	public function hasAppPassword(): bool {
		$tokens = $this->tokenProvider->getTokenByUser(Application::OPEN_PROJECT_ENTITIES_NAME);
		foreach ($tokens as $token) {
			if ($token->getName() === Application::OPEN_PROJECT_ENTITIES_NAME) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $userId
	 * @param int $wpId
	 *
	 * @return array<mixed>|null
	 */
	public function getWorkPackageInfo(string $userId, int $wpId): ?array {
		$token = $this->getAccessToken($userId);
		if ($token) {
			$searchResult = $this->searchWorkPackage($userId, null, null, false, $wpId);
			if (isset($searchResult['error'])) {
				return null;
			}
			$result = [];
			$result['title'] = $this->getSubline($searchResult[0]);
			$result['description'] = $this->getMainText($searchResult[0]);
			$result['imageUrl'] = $this->getOpenProjectUserAvatarUrl($searchResult[0]);
			$result['entry'] = $searchResult[0];
			return $result;
		}
		return null;
	}

	/**
	 * @param array<mixed> $entry
	 * @return string
	 */
	public function getMainText(array $entry): string {
		$workPackageType = isset($entry['_links'], $entry['_links']['type'], $entry['_links']['type']['title'])
			? strtoupper($entry['_links']['type']['title'])
			: '';
		$subject = $entry['subject'] ?? '';
		return $workPackageType . ": " . $subject;
	}

	/**
	 * @param array<mixed> $entry
	 * @return string
	 */
	public function getOpenProjectUserAvatarUrl(array $entry): string {
		$userIdURL = isset($entry['_links'], $entry['_links']['assignee'], $entry['_links']['assignee']['href'])
			? $entry['_links']['assignee']['href']
			: '';
		$userName = isset($entry['_links'], $entry['_links']['assignee'], $entry['_links']['assignee']['title'])
			? $entry['_links']['assignee']['title']
			: '';
		$userId = preg_replace('/.*\//', "", $userIdURL);
		return $this->urlGenerator->linkToOCSRouteAbsolute(
			Application::APP_ID . '.openProjectAPI.getOpenProjectAvatar',
			[
				'apiVersion' => 'v1',
				'userId' => $userId,
				'userName' => $userName,
			]
		);
	}

	/**
	 * @param array<mixed> $entry
	 * @return string
	 */
	public function getSubline(array $entry): string {
		$workPackageID = $entry['id'] ?? '';
		$status = isset($entry['_links'], $entry['_links']['status'], $entry['_links']['status']['title'])
			? '[' . $entry['_links']['status']['title'] . '] '
			: '';
		$projectTitle = isset($entry['_links'], $entry['_links']['project'], $entry['_links']['project']['title'])
			? $entry['_links']['project']['title']
			: '';
		return "#" . $workPackageID . " " . $status . $projectTitle;
	}

	/**
	 * @param array<mixed> $entry
	 * @param string $url
	 * @return string
	 */
	public function getLinkToOpenProject(array $entry, string $url): string {
		$projectId = isset($entry['_links'], $entry['_links']['project'], $entry['_links']['project']['href'])
			? preg_replace('/.*\//', '', $entry['_links']['project']['href'])
			: '';
		return ($projectId !== '')
			? $url . '/projects/' . $projectId . '/work_packages/' . $entry['id'] . '/activity'
			: '';
	}

	/**
	 * @param string $userId
	 * @param string|null $searchQuery
	 *
	 * @return array<mixed>
	 *
	 * @throws OpenprojectErrorException
	 * @throws OpenprojectResponseException|PreConditionNotMetException|\JsonException
	 */
	public function getAvailableOpenProjectProjects(string $userId, ?string $searchQuery = null): array {
		$resultsById = [];
		$filters = [];
		if ($searchQuery) {
			$filters[] = ['typeahead' => ['operator' => '**', 'values' => [$searchQuery]]];
		}
		$filters[] = [
			'storageUrl' =>
				['operator' => '=', 'values' => [$this->getBaseUrl()]],
			'userAction' =>
				['operator' => '&=', 'values' => ["file_links/manage", "work_packages/create"]]
		];
		$params = [
			'filters' => json_encode($filters, JSON_THROW_ON_ERROR),
			'pageSize' => 100
		];
		$result = $this->request($userId, 'work_packages/available_projects', $params);
		if (isset($result['error'])) {
			throw new OpenprojectErrorException($result['error'], $result['statusCode']);
		}
		if (
			!isset($result['_embedded']) ||
			!isset($result['_embedded']['elements'])
		) {
			throw new OpenprojectResponseException('Malformed response');
		}
		foreach ($result['_embedded']['elements'] as $project) {
			$resultsById[$project['id']] = $project;
		}
		return $resultsById;
	}

	/**
	 * @param string $userId
	 * @param string $projectId
	 * @param array<mixed> $body
	 *
	 * @return array<string,mixed>
	 * @throws OpenprojectResponseException|PreConditionNotMetException|OpenprojectErrorException|\JsonException
	 */
	public function getOpenProjectWorkPackageForm(string $userId, string $projectId, array $body): array {
		$params['body'] = json_encode($body, JSON_THROW_ON_ERROR);
		$result = $this->request($userId, 'projects/'.$projectId.'/work_packages/form', $params, 'POST');
		if (isset($result['error'])) {
			throw new OpenprojectErrorException($result['error'], $result['statusCode']);
		}
		if (
			!isset($result['_type']) ||
			$result['_type'] !== 'Form' ||
			!isset($result['_embedded']) ||
			!isset($result['_embedded']['payload']) ||
			!isset($result['_embedded']['schema'])
		) {
			throw new OpenprojectResponseException('Malformed response');
		}
		return $result['_embedded'];
	}

	/**
	 * @param string $userId
	 * @param string $projectId
	 *
	 * @return array<mixed>
	 * @throws OpenprojectResponseException|PreConditionNotMetException|OpenprojectErrorException
	 */
	public function getAvailableAssigneesOfAProject(string $userId, string $projectId): array {
		$result = $this->request($userId, 'projects/'.$projectId.'/available_assignees');
		if (isset($result['error'])) {
			throw new OpenprojectErrorException($result['error'], $result['statusCode']);
		}
		if (
			!isset($result['_type']) ||
			$result['_type'] !== 'Collection' ||
			!isset($result['_embedded']) ||
			!isset($result['_embedded']['elements'])
		) {
			throw new OpenprojectResponseException('Malformed response');
		}
		return $result['_embedded']['elements'];
	}

	/**
	 * @param string $userId
	 * @param array<mixed> $body
	 *
	 * @return array<mixed>
	 * @throws OpenprojectResponseException|PreConditionNotMetException|OpenprojectErrorException|\JsonException
	 */
	public function createWorkPackage(string $userId, array $body): array {
		$params['body'] = json_encode($body, JSON_THROW_ON_ERROR);
		$result = $this->request($userId, 'work_packages', $params, 'POST');
		if (isset($result['error'])) {
			throw new OpenprojectErrorException($result['error'], $result['statusCode']);
		}
		if (
			!isset($result['_type']) ||
			$result['_type'] !== 'WorkPackage' ||
			!isset($result['_embedded']) ||
			!isset($result['id'])
		) {
			throw new OpenprojectResponseException('Malformed response');
		}
		return $result;
	}

	/**
	 * @param string $userId
	 *
	 * @return array<mixed>
	 * @throws OpenprojectErrorException
	 * @throws OpenprojectResponseException|PreConditionNotMetException
	 */
	public function getOpenProjectConfiguration(string $userId): array {
		$result = $this->request(
			$userId, '/configuration'
		);

		if (isset($result['error'])) {
			throw new OpenprojectErrorException($result['error'], $result['statusCode']);
		}

		if (
			!isset($result['_type']) ||
			$result['_type'] !== 'Configuration'
		) {
			throw new OpenprojectResponseException('Malformed response');
		}
		return $result;
	}

	/**
	 * @param string $userId
	 *
	 * @return string
	 */
	public function getOIDCToken(string $userId): string {
		$authorizationMethod = $this->config->getAppValue(Application::APP_ID, 'authorization_method');
		if ($authorizationMethod !== SettingsService::AUTH_METHOD_OIDC) {
			return '';
		}
		if (!$this->isUserOIDCAppInstalledAndEnabled()) {
			$this->logger->error("The 'user_oidc' app is not enabled or supported");
			return '';
		}

		try {
			$event = $this->tokenEventFactory->getEvent();
			/**
			 * @psalm-suppress InvalidArgument
			 */
			$this->eventDispatcher->dispatchTyped($event);
		} catch (Exception $e) {
			$this->logger->error('Failed to get token: ' . $e->getMessage());
			return '';
		}
		$token = $event->getToken();
		if ($token === null) {
			$this->logger->error("Token event has not been caught by 'user_oidc'");
			return '';
		}

		$SSOProviderType = $this->config->getAppValue(Application::APP_ID, 'sso_provider_type');
		if ($SSOProviderType === self::NEXTCLOUD_HUB_PROVIDER) {
			$oidcClientId = $this->config->getAppValue(Application::APP_ID, 'targeted_audience_client_id');
			$clientTokenType = '';
			try {
				$oidcClient = $this->oidcClientMapper->getClient($oidcClientId);
				$clientTokenType = $oidcClient->getTokenType();
			} catch (ClientNotFoundException) {
				$this->logger->error("Client '$oidcClientId' not found");
			}
			// OpenProject does not support opaque tokens.
			// oidc client MUST be configured to return JWT token
			if ($clientTokenType !== 'jwt') {
				$this->logger->error(
					"JWT access token is not enabled for oidc client '$oidcClientId' in OIDC provider app."
					. " The opaque token is not supported by OpenProject."
				);
				return '';
			}
		}

		// token expiration info
		$tokenExpiresAt = $token->getCreatedAt() + $token->getExpiresIn();
		$this->logger->debug('New token expires at ' . date('Y/m/d H:i:s', $tokenExpiresAt));

		$this->config->setUserValue($userId, Application::APP_ID, 'token', $token->getAccessToken());
		$this->config->setUserValue($userId, Application::APP_ID, 'token_expires_at', $tokenExpiresAt);

		$savedUserId = $this->config->getUserValue($userId, Application::APP_ID, 'user_id');
		$savedUsername = $this->config->getUserValue($userId, Application::APP_ID, 'user_name');
		if (!$savedUserId || !$savedUsername) {
			// get user info
			$this->initUserInfo($userId);
		}

		return $token->getAccessToken();
	}

	/**
	 * @param string $userId
	 *
	 * @return bool
	 */
	public function isAccessTokenExpired(string $userId): bool {
		$expiresAt = $this->config->getUserValue($userId, Application::APP_ID, 'token_expires_at', 0);
		// Consider token expired 60 seconds early
		// to avoid race conditions caused by various factors
		$tokenExpirySafetyMargin = 60;
		$expiresAt = (int)$expiresAt - $tokenExpirySafetyMargin;
		return time() > $expiresAt;
	}

	/**
	 * @param string|null $userId
	 *
	 * @return string
	 */
	public function getAccessToken(?string $userId): string {
		if ($userId === null) {
			$this->logger->debug('User ID is not provided, probably the user is a guest user.', ['app' => $this->appName]);
			return '';
		}
		$token = $this->config->getUserValue($userId, Application::APP_ID, 'token', '');
		if ($token && !$this->isAccessTokenExpired($userId)) {
			return $token;
		}

		if ($token) {
			$this->logger->debug('Token has expired.', ['app' => $this->appName]);
			$this->logger->debug('Refreshing access token.', ['app' => $this->appName]);
		}

		$authMethod = $this->config->getAppValue(Application::APP_ID, 'authorization_method');
		// For OAuth2 setup, only try to refresh the expired token.
		// Token exchange needs to be initiated from the UI.
		if ($authMethod === SettingsService::AUTH_METHOD_OAUTH && $token) {
			$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
			$clientID = $this->config->getAppValue(Application::APP_ID, 'openproject_client_id');
			$clientSecret = $this->config->getAppValue(Application::APP_ID, 'openproject_client_secret');
			$openprojectUrl = $this->config->getAppValue(Application::APP_ID, 'openproject_instance_url');
			$result = $this->requestOAuthAccessToken(
				$userId,
				$openprojectUrl,
				[
					'client_id' => $clientID,
					'client_secret' => $clientSecret,
					'grant_type' => 'refresh_token',
					'refresh_token' => $refreshToken,
				],
			);
			if (isset($result['error'])) {
				$this->logger->error('Failed to refresh token: ' . $result['error'], ['app' => $this->appName]);
				return '';
			}
			return $result['access_token'];
		} elseif ($authMethod === SettingsService::AUTH_METHOD_OIDC) {
			return $this->getOIDCToken($userId);
		}

		return '';
	}

	/**
	 * @param string $userId
	 *
	 * @return array<mixed>
	 * @throws PreConditionNotMetException
	 */
	public function initUserInfo(string $userId): array {
		$info = $this->request($userId, '/users/me');
		if (isset($info['lastName'], $info['firstName'], $info['id'])) {
			$fullName = $info['firstName'] . ' ' . $info['lastName'];
			$this->config->setUserValue($userId, Application::APP_ID, 'user_id', $info['id']);
			$this->config->setUserValue($userId, Application::APP_ID, 'user_name', $fullName);
			return ['user_name' => $fullName];
		}
		if (!isset($info['error'])) {
			$info['error'] = 'Failed to get user profile';
		}
		$this->logger->error($info['error'], ['app' => $this->appName]);
		return $info;
	}

	public function getRegisteredOidcProviders(): array {
		$oidcProviders = [];
		if ($this->isUserOIDCAppInstalledAndEnabled()) {
			$providerMapper = new ProviderMapper($this->db);
			foreach ($providerMapper->getProviders() as $provider) {
				$oidcProviders[] = $provider->getIdentifier();
			}
		}
		return $oidcProviders;
	}

	public function isUserOIDCAppInstalledAndEnabled(): bool {
		return $this->appManager->isInstalled('user_oidc');
	}

	public function isUserOIDCAppSupported(): bool {
		$userOidcVersion = $this->appManager->getAppVersion('user_oidc');
		return (
			$this->isUserOIDCAppInstalledAndEnabled() &&
			class_exists('\OCA\UserOIDC\Db\ProviderMapper') &&
			class_exists('\OCA\UserOIDC\Event\ExchangedTokenRequestedEvent') &&
			class_exists('\OCA\UserOIDC\Event\ExternalTokenRequestedEvent') &&
			class_exists('\OCA\UserOIDC\Event\InternalTokenRequestedEvent') &&
			class_exists('\OCA\UserOIDC\User\Backend') &&
			version_compare($userOidcVersion, self::MIN_SUPPORTED_USER_OIDC_APP_VERSION) >= 0
		);
	}

	public function isOIDCAppEnabled(): bool {
		return $this->appManager->isInstalled('oidc');
	}

	public function isOIDCAppSupported(): bool {
		$appVersion = $this->appManager->getAppVersion('oidc');
		return (
			$this->isOIDCAppEnabled() &&
			version_compare($appVersion, self::MIN_SUPPORTED_OIDC_APP_VERSION) >= 0
		);
	}

	/**
	 * @return bool
	 */
	public function isOIDCUser(): bool {
		$SSOProviderType = $this->config->getAppValue(Application::APP_ID, 'sso_provider_type');
		if ($SSOProviderType === self::NEXTCLOUD_HUB_PROVIDER) {
			return true;
		}

		if (!class_exists(OIDCBackend::class)) {
			return false;
		}

		$user = $this->userSession->getUser();
		if ($user instanceof IUser && $user->getBackend() instanceof OIDCBackend) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $appId
	 *
	 * @return string
	 */
	public function getAppsName(string $appId): string {
		$appInfo = $this->appManager->getAppInfo($appId);

		if ($appInfo === null) {
			$this->logger->debug("App not found using appId: $appId", ['app' => $this->appName]);
			return Application::getDefaultAppName($appId);
		}

		if (!array_key_exists('name', $appInfo)) {
			$this->logger->debug("Missing 'name' property for app: $appId", ['app' => $this->appName]);
			return Application::getDefaultAppName($appId);
		}

		return $appInfo['name'];
	}
}
