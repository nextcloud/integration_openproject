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
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUser;
use OCP\IAvatarManager;
use OCP\Http\Client\IClientService;
use OCP\Notification\IManager as INotificationManager;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;

use OCA\OpenProject\AppInfo\Application;

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
								IClientService $clientService) {
		$this->appName = $appName;
		$this->userManager = $userManager;
		$this->avatarManager = $avatarManager;
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->notificationManager = $notificationManager;
		$this->client = $clientService->newClient();
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
		$notificationEnabled = ($this->config->getUserValue($userId, Application::APP_ID, 'notification_enabled', '0') === '1');
		if ($accessToken && $notificationEnabled) {
			$tokenType = $this->config->getUserValue($userId, Application::APP_ID, 'token_type');
			$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
			$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
			$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
			$openprojectUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url');
			if ($clientID && $clientSecret && $openprojectUrl) {
				$lastNotificationCheck = $this->config->getUserValue($userId, Application::APP_ID, 'last_notification_check');
				$lastNotificationCheck = $lastNotificationCheck === '' ? null : $lastNotificationCheck;
				// get the openproject user ID
				$myOPUserId = $this->config->getUserValue($userId, Application::APP_ID, 'user_id');
				if ($myOPUserId !== '') {
					$myOPUserId = (int) $myOPUserId;
					$notifications = $this->getNotifications(
						$openprojectUrl, $accessToken, $tokenType, $refreshToken, $clientID, $clientSecret, $userId, $lastNotificationCheck
					);
					if (!isset($notifications['error']) && count($notifications) > 0) {
						$newLastNotificationCheck = $notifications[0]['updatedAt'];
						$this->config->setUserValue($userId, Application::APP_ID, 'last_notification_check', $newLastNotificationCheck);
						$nbRelevantNotifications = 0;
						foreach ($notifications as $n) {
							$wpUserId = $this->getWPAssigneeOrAuthorId($n);
							// we avoid the ones with updatedAt === lastNotificationCheck because the request filter is inclusive
							if ($wpUserId === $myOPUserId && $n['updatedAt'] !== $lastNotificationCheck) {
								$nbRelevantNotifications++;
							}
						}
						if ($nbRelevantNotifications > 0) {
							$this->sendNCNotification($userId, 'new_open_tickets', [
								'nbNotifications' => $nbRelevantNotifications,
								'link' => $openprojectUrl
							]);
						}
					}
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
			->setObject('dum', 'dum')
			->setSubject($subject, $params);

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
	 * @param string $url
	 * @param string $accessToken
	 * @param string $authType
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $userId
	 * @param ?string $since
	 * @param ?int $limit
	 * @return array<mixed>
	 */
	public function getNotifications(string $url, string $accessToken, string $authType,
									string $refreshToken, string $clientID, string $clientSecret, string $userId,
									?string $since = null, ?int $limit = null): array {
		if ($since) {
			$filters = '[{"updatedAt":{"operator":"<>d","values":["' . $since . '","' . $this->now() . '"]}},{"status":{"operator":"!","values":["14"]}}]';
		} else {
			$filters = '[{"status":{"operator":"!","values":["14"]}}]';
		}
		$params = [
			// 'filters' => '[%7B%22dueDate%22:%7B%22operator%22:%22=d%22,%22values%22:[%222021-01-03%22,%222021-05-03%22]%7D%7D]',
			// 'filters' => '[{"dueDate":{"operator":"<>d","values":["2021-01-03","2021-05-03"]}}]',
			// 'filters' => '[{"dueDate":{"operator":"=d","values":["2021-04-03"]}}]',
			// 'filters' => '[{"subject":{"operator":"~","values":["conference"]}}]',
			// 'filters' => '[{"description":{"operator":"~","values":["coucou"]}},{"status":{"operator":"!","values":["14"]}}]',
			'filters' => $filters,
			'sortBy' => '[["updatedAt", "desc"]]',
			// 'limit' => $limit,
		];
		$result = $this->request(
			$url, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, 'work_packages', $params
		);
		if (isset($result['error'])) {
			return $result;
		} elseif (!isset($result['_embedded']['elements'])) {
			return ['error' => 'Malformed response'];
		}

		$result = $result['_embedded']['elements'];
		if ($limit) {
			$result = array_slice($result, 0, $limit);
		}
		return array_values($result);
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $authType
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $userId
	 * @param string $query
	 * @param int $offset
	 * @param int $limit
	 * @return array<string>
	 */
	public function searchWorkPackage(string $url, string $accessToken, string $authType,
							string $refreshToken, string $clientID, string $clientSecret, string $userId,
							string $query, int $offset = 0, int $limit = 5): array {
		$resultsById = [];

		// search by description
		$params = [
			'filters' => '[{"description":{"operator":"~","values":["' . $query . '"]}},{"status":{"operator":"!","values":["14"]}}]',
			'sortBy' => '[["updatedAt", "desc"]]',
			// 'limit' => $limit,
		];
		$searchDescResult = $this->request(
			$url, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, 'work_packages', $params
		);

		if (isset($searchDescResult['_embedded'], $searchDescResult['_embedded']['elements'])) {
			foreach ($searchDescResult['_embedded']['elements'] as $wp) {
				$resultsById[$wp['id']] = $wp;
			}
		}
		// search by subject
		$params = [
			'filters' => '[{"subject":{"operator":"~","values":["' . $query . '"]}},{"status":{"operator":"!","values":["14"]}}]',
			'sortBy' => '[["updatedAt", "desc"]]',
			// 'limit' => $limit,
		];
		$searchSubjectResult = $this->request(
			$url, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, 'work_packages', $params
		);

		if (isset($searchSubjectResult['_embedded'], $searchSubjectResult['_embedded']['elements'])) {
			foreach ($searchSubjectResult['_embedded']['elements'] as $wp) {
				$resultsById[$wp['id']] = $wp;
			}
		}

		return array_values($resultsById);
	}

	/**
	 * authenticated request to get an image from openproject
	 *
	 * @param string $url
	 * @param string $accessToken
	 * @param string $authType
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $userId
	 * @param string $userName
	 * @return array{avatar: string, type?: string}
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\Lock\LockedException
	 */
	public function getOpenProjectAvatar(string $url,
									string $accessToken, string $authType, string $refreshToken, string $clientID, string $clientSecret,
									string $userId, string $userName): array {
		$url = $url . '/api/v3/users/' . $userId . '/avatar';
		$authHeader = ($authType === 'access')
			? 'Basic ' . base64_encode('apikey:' . $accessToken)
			: 'Bearer ' . $accessToken;
		$options = [
			'headers' => [
				'Authorization' => $authHeader,
				'User-Agent' => 'Nextcloud OpenProject integration',
			]
		];
		try {
			$response = $this->client->get($url, $options);
			$headers = $response->getHeaders();
			return [
				'avatar' => $response->getBody(),
				'type' => implode(',', $headers['Content-Type']),
			];
		} catch (ServerException | ClientException | ConnectException | Exception $e) {
			$this->logger->warning('Error while getting OpenProject avatar for user ' . $userId . ': ' . $e->getMessage(), ['app' => $this->appName]);
			$avatar = $this->avatarManager->getGuestAvatar($userName);
			$avatarContent = $avatar->getFile(64)->getContent();
			return ['avatar' => $avatarContent];
		}
	}

	/**
	 * @param string $openprojectUrl
	 * @param string $accessToken
	 * @param string $authType
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $userId
	 * @param string $endPoint
	 * @param array<mixed> $params
	 * @param string $method
	 * @return array<mixed>
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function request(string $openprojectUrl, string $accessToken, string $authType, string $refreshToken,
							string $clientID, string $clientSecret, string $userId,
							string $endPoint, array $params = [], string $method = 'GET'): array {
		try {
			$url = $openprojectUrl . '/api/v3/' . $endPoint;
			$authHeader = ($authType === 'access')
				? 'Basic ' . base64_encode('apikey:' . $accessToken)
				: 'Bearer ' . $accessToken;
			$options = [
				'headers' => [
					'Authorization' => $authHeader,
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
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException | ClientException $e) {
			$response = $e->getResponse();
			$body = (string) $response->getBody();
			// refresh token if it's invalid and we are using oauth
			// response can be : 'OAuth2 token is expired!', 'Invalid token!' or 'Not authorized'
			if ($response->getStatusCode() === 401 && $authType === 'oauth') {
				$this->logger->info('Trying to REFRESH the access token', ['app' => $this->appName]);
				// try to refresh the token
				$result = $this->requestOAuthAccessToken($openprojectUrl, [
					'client_id' => $clientID,
					'client_secret' => $clientSecret,
					'grant_type' => 'refresh_token',
					'refresh_token' => $refreshToken,
				], 'POST');
				if (isset($result['access_token'])) {
					$accessToken = $result['access_token'];
					$this->config->setUserValue($userId, Application::APP_ID, 'token', $accessToken);
					// retry the request with new access token
					return $this->request(
						$openprojectUrl, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, $endPoint, $params, $method
					);
				}
			}
			// try to get the error in the response
			$this->logger->warning('OpenProject API error : '.$e->getMessage(), ['app' => $this->appName]);
			$decodedBody = json_decode($body, true);
			if ($decodedBody && isset($decodedBody['message'])) {
				$this->logger->warning('OpenProject API error : '.$decodedBody['message'], ['app' => $this->appName]);
			}
			return [
				'error' => $e->getMessage(),
				'statusCode' => $e->getResponse()->getStatusCode(),
			];
		} catch (ConnectException | Exception $e) {
			return [
				'error' => $e->getMessage(),
				'statusCode' => 404,
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

	public static function validateOpenProjectURL(string $openprojectUrl): bool {
		return filter_var($openprojectUrl, FILTER_VALIDATE_URL) &&
			preg_match('/^https?/', $openprojectUrl);
	}
	
		/**
	 * authenticated request to get status of a work package
	 *
	 * @param string $url
	 * @param string $accessToken
	 * @param string $authType
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $userId
	 * @param string $statusId
	 * @return string[]
	 */
	public function getOpenProjectWorkPackageStatus(
		string $url,
		string $accessToken,
		string $authType,
		string $refreshToken,
		string $clientID,
		string $clientSecret,
		string $userId,
		string $statusId): array {
		$result = $this->request(
			$url, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, 'statuses/' . $statusId);
		if (isset($result['error'])) {
			return $result;
		} elseif (!isset($result['id'])) {
			return ['error' => 'Malformed response'];
		}
		return $result;
	}

	/**
	 * authenticated request to get status of a work package
	 *
	 * @param string $url
	 * @param string $accessToken
	 * @param string $authType
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $userId
	 * @param string $typeId
	 * @return string[]
	 */
	public function getOpenProjectWorkPackageType(
		string $url,
		string $accessToken,
		string $authType,
		string $refreshToken,
		string $clientID,
		string $clientSecret,
		string $userId,
		string $typeId
	): array {
		$result = $this->request(
			$url, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, 'types/' . $typeId);
		if (isset($result['error'])) {
			return $result;
		} elseif (!isset($result['id'])) {
			return ['error' => 'Malformed response'];
		}

		return $result;
	}
}
