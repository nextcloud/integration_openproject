<?php
/**
 * Nextcloud - openproject
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2020
 */

namespace OCA\OpenProject\Service;

use DateTime;
use DateTimeZone;
use Exception;
use OCP\Files\Node;
use OCA\OpenProject\Exception\OpenprojectErrorException;
use OCA\OpenProject\Exception\OpenprojectResponseException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IResponse;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUser;
use OCP\IAvatarManager;
use OCP\Http\Client\IClientService;
use OCP\Notification\IManager as INotificationManager;
use OCP\Files\NotPermittedException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;
use OCP\AppFramework\Http;

use OCA\OpenProject\AppInfo\Application;
use Safe\Exceptions\JsonException;

define('CACHE_TTL', 3600);

class OpenProjectAPIService {
	/**
	 * @var string
	 */
	private $appName;
	/**
	 * @var IUserManager
	 */
	private $userManager;
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
	 * @var INotificationManager
	 */
	private $notificationManager;
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
	 * Service to make requests to OpenProject v3 (JSON) API
	 */
	public function __construct(
								string $appName,
								IUserManager $userManager,
								IAvatarManager $avatarManager,
								LoggerInterface $logger,
								IL10N $l10n,
								IConfig $config,
								INotificationManager $notificationManager,
								IClientService $clientService,
								IRootFolder $storage,
								IURLGenerator $urlGenerator,
								ICacheFactory $cacheFactory) {
		$this->appName = $appName;
		$this->userManager = $userManager;
		$this->avatarManager = $avatarManager;
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->notificationManager = $notificationManager;
		$this->client = $clientService->newClient();
		$this->storage = $storage;
		$this->urlGenerator = $urlGenerator;

		$this->cache = $cacheFactory->createDistributed();
	}

	/**
	 * triggered by a cron job
	 * notifies user of their number of new tickets
	 *
	 * @return void
	 */
	public function checkNotifications(): void {
		$this->userManager->callForAllUsers(function (IUser $user) {
			$this->checkNotificationsForUser($user->getUID());
		});
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	private function checkNotificationsForUser(string $userId): void {
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		$notificationEnabled = ($this->config->getUserValue(
			$userId,
			Application::APP_ID,
			'notification_enabled',
			$this->config->getAppValue(Application::APP_ID, 'default_enable_notifications', '0')) === '1');
		if ($accessToken && $notificationEnabled) {
			$openprojectUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
			$notifications = $this->getNotifications($userId);
			$aggregatedNotifications = [];
			if (!isset($notifications['error']) && count($notifications) > 0) {
				foreach ($notifications as $n) {
					$wpId = preg_replace('/.*\//', '', $n['_links']['resource']['href']);
					if (!array_key_exists($wpId, $aggregatedNotifications)) {
						$aggregatedNotifications[$wpId] = [
							'wpId' => $wpId,
							'resourceTitle' => $n['_links']['resource']['title'],
							'projectTitle' => $n['_links']['project']['title'],
							'count' => 1,
							// TODO according to the docs https://github.com/nextcloud/notifications/blob/master/docs/notification-workflow.md#creating-a-new-notification
							// links should not be set here
							'link' => self::sanitizeUrl(
								$openprojectUrl . '/notifications/details/' . $wpId . '/activity/'
							)
						];
					} else {
						$aggregatedNotifications[$wpId]['count']++;
					}
					$aggregatedNotifications[$wpId]['reasons'][] = $n['reason'];
					$aggregatedNotifications[$wpId]['actors'][] = $n['_links']['actor']['title'];
				}
				$manager = $this->notificationManager;
				$notification = $manager->createNotification();
				$notification->setApp(Application::APP_ID)
					->setUser($userId);
				$manager->markProcessed($notification);

				foreach ($aggregatedNotifications as $n) {
					$n['reasons'] = array_unique($n['reasons']);
					$n['actors'] = array_unique($n['actors']);
					// TODO can we use https://github.com/nextcloud/notifications/blob/master/docs/notification-workflow.md#defer-and-flush ?
					$this->sendNCNotification($userId, 'op_notification', $n);
				}
			}
		}
	}

	/**
	 * @param array<mixed> $workPackage
	 * @return int|null
	 */
	private function getWPAssigneeOrAuthorId(array $workPackage): ?int {
		return isset($workPackage['_links'], $workPackage['_links']['assignee'], $workPackage['_links']['assignee']['href'])
			? (int) preg_replace('/.*\//', '', $workPackage['_links']['assignee']['href'])
			: (isset($workPackage['_links'], $workPackage['_links']['author'], $workPackage['_links']['author']['href'])
				? (int) preg_replace('/.*\//', '', $workPackage['_links']['author']['href'])
				: null);
	}

	/**
	 * @param string $userId
	 * @param string $subject
	 * @param array<mixed> $params
	 * @return void
	 */
	private function sendNCNotification(string $userId, string $subject, array $params): void {
		$manager = $this->notificationManager;
		$notification = $manager->createNotification();

		$notification->setApp(Application::APP_ID)
			->setUser($userId)
			->setDateTime(new DateTime())
			->setObject('dum', 'dum');

		$notification->setSubject($subject, $params);

		$manager->notify($notification);
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
	 */
	public function getNotifications(string $userId): array {
		$filters[] = [
			'readIAN' =>
				['operator' => '=', 'values' => ['f']]
		];

		$params = [
			'filters' => \Safe\json_encode($filters),
		];
		$result = $this->request($userId, 'notifications', $params);
		if (isset($result['error'])) {
			return $result;
		} elseif (
			!isset($result['_embedded']['elements']) ||
			!isset($result['_embedded']['elements'][0]['_links'])
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
		return $this->urlGenerator->getBaseUrl();
	}
	/**
	 * @param string $userId
	 * @param string|null $query
	 * @param int|null $fileId
	 * @param int $offset
	 * @param int $limit
	 * @return array<mixed>
	 * @throws \OCP\PreConditionNotMetException
	 * @throws \Safe\Exceptions\JsonException
	 */
	public function searchWorkPackage(
		string $userId,
		string $query = null,
		int $fileId = null,
		int $offset = 0,
		int $limit = 5
	): array {
		$resultsById = [];
		$filters = [];

		// search by description
		if ($fileId !== null) {
			$filters[] = ['file_link_origin_id' => ['operator' => '=', 'values' => [(string)$fileId]]];
		}
		if ($query !== null) {
			$filters[] = ['typeahead' => ['operator' => '**', 'values' => [$query]]];
		}
		$resultsById = $this->searchRequest($userId, $filters);
		if (isset($resultsById['error'])) {
			return $resultsById;
		}
		return array_values($resultsById);
	}

	/**
	 * @param string $userId
	 * @param array<mixed> $filters
	 * @return array<mixed>
	 * @throws \OCP\PreConditionNotMetException
	 * @throws \Safe\Exceptions\JsonException
	 */
	private function searchRequest(string $userId, array $filters): array {
		$resultsById = [];
		$sortBy = [['status', 'asc'],['updatedAt', 'desc']];
		$filters[] = [
			'linkable_to_storage_url' =>
				['operator' => '=', 'values' => [urlencode($this->getBaseUrl())]]
		];

		$params = [
			'filters' => \Safe\json_encode($filters),
			'sortBy' => \Safe\json_encode($sortBy),
			// 'limit' => $limit,
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
	 * @param string $userId
	 * @param string $userName
	 * @return array{avatar: string, type?: string}
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\Lock\LockedException
	 */
	public function getOpenProjectAvatar(string $userId, string $userName): array {
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		$this->config->getAppValue(Application::APP_ID, 'client_id');
		$this->config->getAppValue(Application::APP_ID, 'client_secret');
		$openprojectUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
		try {
			$response = $this->rawRequest($accessToken, $openprojectUrl, 'users/'.$userId.'/avatar');
			$headers = $response->getHeaders();
			return [
				'avatar' => $response->getBody(),
				'type' => implode(',', $headers['Content-Type']),
			];
		} catch (ServerException | ClientException | ConnectException | Exception $e) {
			$this->logger->debug('Error while getting OpenProject avatar for user ' . $userId . ': ' . $e->getMessage(), ['app' => $this->appName]);
			$avatar = $this->avatarManager->getGuestAvatar($userName);
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
	 * @return array{error: string} | IResponse
	 */
	public function rawRequest(string $accessToken, string $openprojectUrl,
							   string $endPoint, array $params = [], string $method = 'GET') {
		$url = $openprojectUrl . '/api/v3/' . $endPoint;
		$options = [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
				'User-Agent' => 'Nextcloud OpenProject integration',
			]
		];

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
				$options['headers']['Content-Type'] = 'application/json';
			}
		} elseif ($method === 'DELETE') {
			$options['headers']['Content-Type'] = 'application/json';
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
	public function request(string $userId,
							string $endPoint, array $params = [], string $method = 'GET'): array {
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$openprojectUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
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
			$response = $e->getResponse();
			$body = (string) $response->getBody();
			// refresh token if it's invalid and we are using oauth
			// response can be : 'OAuth2 token is expired!', 'Invalid token!' or 'Not authorized'
			if ($response->getStatusCode() === 401) {
				$this->logger->info('Trying to REFRESH the access token', ['app' => $this->appName]);
				// try to refresh the token
				$result = $this->requestOAuthAccessToken($openprojectUrl, [
					'client_id' => $clientID,
					'client_secret' => $clientSecret,
					'grant_type' => 'refresh_token',
					'refresh_token' => $refreshToken,
				], 'POST');
				if (isset($result['refresh_token'])) {
					$refreshToken = $result['refresh_token'];
					$this->config->setUserValue(
						$userId, Application::APP_ID, 'refresh_token', $refreshToken
					);
				}
				if (isset($result['access_token'])) {
					$accessToken = $result['access_token'];
					$this->config->setUserValue($userId, Application::APP_ID, 'token', $accessToken);
					// retry the request with new access token
					return $this->request($userId, $endPoint, $params, $method);
				}
			}
			// try to get the error in the response
			$this->logger->warning('OpenProject API error : '.$e->getMessage(), ['app' => $this->appName]);
			$decodedBody = json_decode($body, true);
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
				$this->logger->warning('OpenProject API error : '. $message, ['app' => $this->appName]);
			}
			return [
				'error' => $e->getMessage(),
				'statusCode' => $response->getStatusCode(),
			];
		} catch (ConnectException $e) {
			$this->logger->warning('OpenProject connection error : '.$e->getMessage(), ['app' => $this->appName]);
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
	 * @param string $url
	 * @param array<mixed> $params passed to `http_build_query` for GET requests, else send as body
	 * @param string $method
	 * @return array<mixed>
	 */
	public function requestOAuthAccessToken(string $url, array $params = [], string $method = 'GET'): array {
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
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('OAuth access token refused')];
			} else {
				return json_decode($body, true);
			}
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
	 * generates an oauth url to OpenProject containing client_id & redirect_uri as parameter
	 * please note that the state parameter is still missing, that needs to be generated dynamically
	 * and saved to the DB before calling the OAuth URI
	 * @throws Exception
	 */
	public static function getOpenProjectOauthURL(IConfig $config, IURLGenerator $urlGenerator): string {
		if (!self::isAdminConfigOk($config)) {
			throw new \Exception('OpenProject admin config is not valid!');
		}
		$clientID = $config->getAppValue(Application::APP_ID, 'client_id');
		$oauthUrl = $config->getAppValue(Application::APP_ID, 'oauth_instance_url');

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
	 * @throws \OCP\Files\InvalidPathException
	 * @throws NotFoundException
	 * @throws \OCP\PreConditionNotMetException
	 * @throws NotPermittedException
	 * @throws OpenprojectErrorException
	 * @throws \OC\User\NoUserException
	 * @throws OpenprojectResponseException
	 * @return int
	 */
	public function linkWorkPackageToFile(
		int $workpackageId,
		int $fileId,
		string $fileName,
		string $userId
	): int {
		$file = $this->getNode($userId, $fileId);
		if (!$file->isReadable()) {
			throw new NotPermittedException();
		}

		$body = [
			'_type' => 'Collection',
			'_embedded' => [
				'elements' => [
					[
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
					]
				]
			]
		];

		$params['body'] = \Safe\json_encode($body);
		$result = $this->request(
			$userId, 'work_packages/' . $workpackageId. '/file_links', $params, 'POST'
		);

		if (isset($result['error'])) {
			throw new OpenprojectErrorException($result['error']);
		}
		if (
			!isset($result['_type']) ||
			$result['_type'] !== 'Collection' ||
			!isset($result['_embedded']) ||
			!isset($result['_embedded']['elements']) ||
			!isset($result['_embedded']['elements'][0]) ||
			!isset($result['_embedded']['elements'][0]['id'])
		) {
			throw new OpenprojectResponseException('Malformed response');
		}
		return $result['_embedded']['elements'][0]['id'];
	}

	/**
	 * @throws OpenprojectErrorException
	 * @throws PreConditionNotMetException
	 * @throws JsonException
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
		$fullUrl = 'notifications/read_ian?filters=' . urlencode(\Safe\json_encode($filters));

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
	 * checks if every admin config variables are set
	 * checks if the oauth instance url is valid
	 *
	 * @param IConfig $config
	 * @return bool
	 */
	public static function isAdminConfigOk(IConfig $config):bool {
		$clientId = $config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $config->getAppValue(Application::APP_ID, 'client_secret');
		$oauthInstanceUrl = $config->getAppValue(Application::APP_ID, 'oauth_instance_url');
		$checkIfConfigIsSet = !!($clientId) && !!($clientSecret) && !!($oauthInstanceUrl);
		if (!$checkIfConfigIsSet) {
			return false;
		} else {
			return self::validateURL($oauthInstanceUrl);
		}
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
		$url = \preg_replace("/([^:]\/)\/+/", '$1', $url);
		return $url;
	}
}
