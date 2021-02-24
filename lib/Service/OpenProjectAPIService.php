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

use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUser;
use OCP\Http\Client\IClientService;
use OCP\Notification\IManager as INotificationManager;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;

use OCA\OpenProject\AppInfo\Application;

class OpenProjectAPIService {

	private $l10n;
	private $logger;

	/**
	 * Service to make requests to OpenProject v3 (JSON) API
	 */
	public function __construct (IUserManager $userManager,
								string $appName,
								LoggerInterface $logger,
								IL10N $l10n,
								IConfig $config,
								INotificationManager $notificationManager,
								IClientService $clientService) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->clientService = $clientService;
		$this->notificationManager = $notificationManager;
		$this->client = $clientService->newClient();
	}

	/**
	 * triggered by a cron job
	 * notifies user of their number of new tickets
	 *
	 * @return void
	 */
	public function checkOpenTickets(): void {
		$this->userManager->callForAllUsers(function (IUser $user) {
			$this->checkOpenTicketsForUser($user->getUID());
		});
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	private function checkOpenTicketsForUser(string $userId): void {
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token', '');
		$notificationEnabled = ($this->config->getUserValue($userId, Application::APP_ID, 'notification_enabled', '0') === '1');
		if ($accessToken && $notificationEnabled) {
			$tokenType = $this->config->getUserValue($userId, Application::APP_ID, 'token_type', '');
			$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token', '');
			$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', '');
			$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret', '');
			$openprojectUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', '');
			if ($clientID && $clientSecret && $openprojectUrl) {
				$lastNotificationCheck = $this->config->getUserValue($userId, Application::APP_ID, 'last_open_check', '');
				$lastNotificationCheck = $lastNotificationCheck === '' ? null : $lastNotificationCheck;
				// get the openproject user ID
				$me = $this->request(
					$openprojectUrl, $accessToken, $tokenType, $refreshToken, $clientID, $clientSecret, $userId, 'users/me'
				);
				if (isset($me['id'])) {
					$my_user_id = $me['id'];

					$notifications = $this->getNotifications(
						$openprojectUrl, $accessToken, $tokenType, $refreshToken, $clientID, $clientSecret, $userId, $lastNotificationCheck
					);
					if (!isset($notifications['error']) && count($notifications) > 0) {
						$lastNotificationCheck = $notifications[0]['updated_at'];
						$this->config->setUserValue($userId, Application::APP_ID, 'last_open_check', $lastNotificationCheck);
						$nbOpen = 0;
						foreach ($notifications as $n) {
							$user_id = $n['user_id'];
							$state_id = $n['state_id'];
							$owner_id = $n['owner_id'];
							// if ($state_id === 1) {
							if ($owner_id === $my_user_id && $state_id === 1) {
								$nbOpen++;
							}
						}
						if ($nbOpen > 0) {
							$this->sendNCNotification($userId, 'new_open_tickets', [
								'nbOpen' => $nbOpen,
								'link' => $openprojectUrl
							]);
						}
					}
				}
			}
		}
	}

	/**
	 * @param string $userId
	 * @param string $subject
	 * @param string $params
	 * @return void
	 */
	private function sendNCNotification(string $userId, string $subject, array $params): void {
		$manager = $this->notificationManager;
		$notification = $manager->createNotification();

		$notification->setApp(Application::APP_ID)
			->setUser($userId)
			->setDateTime(new \DateTime())
			->setObject('dum', 'dum')
			->setSubject($subject, $params);

		$manager->notify($notification);
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
	 * @return array
	 */
	public function getNotifications(string $url, string $accessToken, string $authType,
									string $refreshToken, string $clientID, string $clientSecret, string $userId,
									?string $since = null, ?int $limit = null): array {
		$params = [
			'state' => 'pending',
		];
		$result = $this->request(
			$url, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, 'online_notifications', $params
		);
		if (isset($result['error'])) {
			return $result;
		}
		// filter seen ones
		$result = array_filter($result, function($elem) {
			return !$elem['seen'];
		});
		// filter results by date
		if (!is_null($since)) {
			$sinceDate = new \DateTime($since);
			$sinceTimestamp = $sinceDate->getTimestamp();
			$result = array_filter($result, function($elem) use ($sinceTimestamp) {
				$date = new \Datetime($elem['updated_at']);
				$ts = $date->getTimestamp();
				return $ts > $sinceTimestamp;
			});
		}
		if ($limit) {
			$result = array_slice($result, 0, $limit);
		}
		$result = array_values($result);
		// get details
		foreach ($result as $k => $v) {
			$details = $this->request(
				$url, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, 'tickets/' . $v['o_id']
			);
			if (!isset($details['error'])) {
				$result[$k]['title'] = $details['title'];
				$result[$k]['note'] = $details['note'];
				$result[$k]['state_id'] = $details['state_id'];
				$result[$k]['owner_id'] = $details['owner_id'];
				$result[$k]['type'] = $details['type'];
			}
		}
		// get user details
		$userIds = [];
		foreach ($result as $k => $v) {
			if (!in_array($v['updated_by_id'], $userIds)) {
				array_push($userIds, $v['updated_by_id']);
			}
		}
		$userDetails = [];
		foreach ($userIds as $uid) {
			$user = $this->request(
				$url, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, 'users/' . $uid
			);
			$userDetails[$uid] = [
				'firstname' => $user['firstname'],
				'lastname' => $user['lastname'],
				'organization_id' => $user['organization_id'],
				'image' => $user['image'],
			];
		}
		foreach ($result as $k => $v) {
			$user = $userDetails[$v['updated_by_id']];
			$result[$k]['firstname'] = $user['firstname'];
			$result[$k]['lastname'] = $user['lastname'];
			$result[$k]['organization_id'] = $user['organization_id'];
			$result[$k]['image'] = $user['image'];
		}

		return $result;
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
	 * @return array
	 */
	public function search(string $url, string $accessToken, string $authType,
							string $refreshToken, string $clientID, string $clientSecret, string $userId,
							string $query): array {
		$params = [
			'query' => $query,
			'limit' => 20,
		];
		$searchResult = $this->request(
			$url, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, 'tickets/search', $params
		);

		$result = [];
		if (isset($searchResult['assets']) && isset($searchResult['assets']['Ticket'])) {
			foreach ($searchResult['assets']['Ticket'] as $id => $t) {
				array_push($result, $t);
			}
		}
		// get ticket state names
		$states = $this->request(
			$url, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, 'ticket_states'
		);
		$statesById = [];
		if (!isset($states['error'])) {
			foreach ($states as $state) {
				$id = $state['id'];
				$name = $state['name'];
				if ($id && $name) {
					$statesById[$id] = $name;
				}
			}
		}
		foreach ($result as $k => $v) {
			if (array_key_exists($v['state_id'], $statesById)) {
				$result[$k]['state_name'] = $statesById[$v['state_id']];
			}
		}
		// get ticket priority names
		$prios = $this->request(
			$url, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, 'ticket_priorities'
		);
		$priosById = [];
		if (!isset($prios['error'])) {
			foreach ($prios as $prio) {
				$id = $prio['id'];
				$name = $prio['name'];
				if ($id && $name) {
					$priosById[$id] = $name;
				}
			}
		}
		foreach ($result as $k => $v) {
			if (array_key_exists($v['priority_id'], $priosById)) {
				$result[$k]['priority_name'] = $priosById[$v['priority_id']];
			}
		}
		// add owner information
		$userIds = [];
		$field = 'customer_id';
		foreach ($result as $k => $v) {
			if (!in_array($v[$field], $userIds)) {
				array_push($userIds, $v[$field]);
			}
		}
		$userDetails = [];
		foreach ($userIds as $uid) {
			$user = $this->request(
				$url, $accessToken, $authType, $refreshToken, $clientID, $clientSecret, $userId, 'users/' . $uid
			);
			if (!isset($user['error'])) {
				$userDetails[$uid] = [
					'firstname' => $user['firstname'],
					'lastname' => $user['lastname'],
					'organization_id' => $user['organization_id'],
					'image' => $user['image'],
				];
			}
		}
		foreach ($result as $k => $v) {
			if (array_key_exists($v[$field], $userDetails)) {
				$user = $userDetails[$v[$field]];
				$result[$k]['u_firstname'] = $user['firstname'];
				$result[$k]['u_lastname'] = $user['lastname'];
				$result[$k]['u_organization_id'] = $user['organization_id'];
				$result[$k]['u_image'] = $user['image'];
			}
		}
		return $result;
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
	 * @param string $imageId
	 * @return string
	 */
	public function getOpenProjectAvatar(string $url,
									string $accessToken, string $authType, string $refreshToken, string $clientID, string $clientSecret,
									string $imageId): string {
		$url = $url . '/api/v1/users/image/' . $imageId;
		$authHeader = ($authType === 'access') ? 'Token token=' : 'Bearer ';
		$options = [
			'headers' => [
				'Authorization'  => $authHeader . $accessToken,
				'User-Agent' => 'Nextcloud OpenProject integration',
			]
		];
		return $this->client->get($url, $options)->getBody();
	}

	/**
	 * @param string $openprojectUrl
	 * @param string $accessToken
	 * @param string $authType
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
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
					'Authorization'  => $authHeader,
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
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
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
			$this->logger->warning('OpenProject API error : '.$e->getMessage(), ['app' => $this->appName]);
			return [
				'error' => $e->getMessage(),
				'statusCode' => $e->getResponse()->getStatusCode(),
			];
		} catch (ConnectException $e) {
			return [
				'error' => $e->getMessage(),
				'statusCode' => 404,
			];
		}
	}

	/**
	 * @param string $url
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function requestOAuthAccessToken(string $url, array $params = [], string $method = 'GET'): array {
		try {
			$url = $url . '/oauth/token';
			$options = [
				'headers' => [
					'User-Agent'  => 'Nextcloud OpenProject integration',
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
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('OAuth access token refused')];
			} else {
				return json_decode($body, true);
			}
		} catch (\Exception $e) {
			$this->logger->warning('OpenProject OAuth error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}
}
