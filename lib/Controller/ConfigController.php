<?php
/**
 * Nextcloud - openproject
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

namespace OCA\OpenProject\Controller;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use OC\User\NoUserException;
use OCA\OAuth2\Controller\SettingsController;
use OCA\OAuth2\Exceptions\ClientNotFoundException;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Exception\OpenprojectErrorException;
use OCA\OpenProject\Exception\OpenprojectGroupfolderSetupConflictException;
use OCA\OpenProject\Service\OauthService;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\DB\Exception as DBException;
use OCP\Group\ISubAdmin;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;

use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\PreConditionNotMetException;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class ConfigController extends Controller {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var OpenProjectAPIService
	 */
	private $openprojectAPIService;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string|null
	 */
	private $userId;
	/**
	 * @var OauthService
	 */
	private $oauthService;

	/**
	 * @var SettingsController
	 */
	private $oauthSettingsController;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * @var ISecureRandom
	 */
	private ISecureRandom $secureRandom;

	/**
	 * @var ISubAdmin
	 */
	private ISubAdmin $subAdminManager;

	public function __construct(string $appName,
		IRequest $request,
		IConfig $config,
		IURLGenerator $urlGenerator,
		IUserManager $userManager,
		IL10N $l,
		OpenProjectAPIService $openprojectAPIService,
		LoggerInterface $logger,
		OauthService $oauthService,
		SettingsController $oauthSettingsController,
		IGroupManager $groupManager,
		ISecureRandom $secureRandom,
		ISubAdmin $subAdminManager,
		?string $userId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
		$this->l = $l;
		$this->openprojectAPIService = $openprojectAPIService;
		$this->logger = $logger;
		$this->userId = $userId;
		$this->oauthService = $oauthService;
		$this->oauthSettingsController = $oauthSettingsController;
		$this->groupManager = $groupManager;
		$this->secureRandom = $secureRandom;
		$this->subAdminManager = $subAdminManager;
	}

	/**
	 * @param string|null $userId
	 * @return void
	 */
	public function clearUserInfo(string $userId = null) {
		if ($userId === null) {
			$userId = $this->userId;
		}
		$this->config->deleteUserValue($userId, Application::APP_ID, 'token');
		$this->config->deleteUserValue($userId, Application::APP_ID, 'login');
		$this->config->deleteUserValue($userId, Application::APP_ID, 'user_id');
		$this->config->deleteUserValue($userId, Application::APP_ID, 'user_name');
		$this->config->deleteUserValue($userId, Application::APP_ID, 'refresh_token');
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array<string, string> $values
	 * @return DataResponse
	 */
	public function setConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, trim($value));
		}
		$result = [];

		if (isset($values['token'])) {
			if ($values['token'] && $values['token'] !== '') {
				$result = $this->storeUserInfo();
			} else {
				$this->clearUserInfo();
				$result = [
					'user_name' => '',
				];
			}
		}
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_UNAUTHORIZED);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * set admin config values
	 *
	 * @param array<string, string|null|bool> $values
	 *
	 * @return array<string, bool|int|string|null>
	 * @throws \Exception
	 * @throws NoUserException | InvalidArgumentException | OpenprojectGroupfolderSetupConflictException
	 */
	private function setIntegrationConfig(array $values): array {
		$allowedKeys = [
			'openproject_instance_url',
			'openproject_client_id',
			'openproject_client_secret',
			'default_enable_navigation',
			'default_enable_unified_search',
			'setup_project_folder',
			'setup_app_password'
		];
		// if values contains a key that is not in the allowedKeys array,
		// return a response with status code 400 and an error message
		foreach ($values as $key => $value) {
			if (!in_array($key, $allowedKeys)) {
				throw new InvalidArgumentException('Invalid key');
			}
		}
		$appPassword = null;

		if (key_exists('setup_project_folder', $values) && $values['setup_project_folder'] === true) {
			$isSystemReady = $this->openprojectAPIService->isSystemReadyForProjectFolderSetUp();
			if ($isSystemReady) {
				$password = $this->secureRandom->generate($this->openprojectAPIService->getPasswordLength(), ISecureRandom::CHAR_ALPHANUMERIC.ISecureRandom::CHAR_SYMBOLS);
				$user = $this->userManager->createUser(Application::OPEN_PROJECT_ENTITIES_NAME, $password);
				$group = $this->groupManager->createGroup(Application::OPEN_PROJECT_ENTITIES_NAME);
				$group->addUser($user);
				$this->subAdminManager->createSubAdmin($user, $group);
				$this->openprojectAPIService->createGroupfolder();
				if ($this->openprojectAPIService->isTermsOfServiceAppEnabled() && $this->userManager->userExists(Application::OPEN_PROJECT_ENTITIES_NAME)) {
					$this->openprojectAPIService->signTermsOfServiceForUserOpenProject();
				}
			}
		}

		// creates or replace the app password
		if (key_exists('setup_app_password', $values) && $values['setup_app_password'] === true) {
			$this->openprojectAPIService->deleteAppPassword();
			if (!$this->userManager->userExists(Application::OPEN_PROJECT_ENTITIES_NAME)) {
				throw new NoUserException('User "' . Application::OPEN_PROJECT_ENTITIES_NAME . '" does not exists to create application password');
			}
			$appPassword = $this->openprojectAPIService->generateAppPasswordTokenForUser();
		}

		$oldOpenProjectOauthUrl = $this->config->getAppValue(
			Application::APP_ID, 'openproject_instance_url', ''
		);
		$oldClientId = $this->config->getAppValue(
			Application::APP_ID, 'openproject_client_id', ''
		);
		$oldClientSecret = $this->config->getAppValue(
			Application::APP_ID, 'openproject_client_secret', ''
		);

		foreach ($values as $key => $value) {
			if ($key === 'setup_project_folder' || $key === 'setup_app_password') {
				continue;
			}
			$this->config->setAppValue(Application::APP_ID, $key, trim($value));
		}

		// if the OpenProject OAuth URL has changed
		if (key_exists('openproject_instance_url', $values)
			&& $oldOpenProjectOauthUrl !== $values['openproject_instance_url']
		) {
			// delete the existing OAuth client if new OAuth URL is passed empty
			if (
				is_null($values['openproject_instance_url']) ||
				$values['openproject_instance_url'] === ''
			) {
				$this->deleteOauthClient();
			} else {
				// otherwise just change the redirect URI for the existing OAuth client
				$oauthClientInternalId = $this->config->getAppValue(
					Application::APP_ID, 'nc_oauth_client_id', ''
				);
				$this->oauthService->setClientRedirectUri(
					(int)$oauthClientInternalId, $values['openproject_instance_url']
				);
			}
		}

		$runningFullReset = (

			key_exists('openproject_instance_url', $values) &&

			key_exists('openproject_client_id', $values) &&

			key_exists('openproject_client_secret', $values) &&

			$values['openproject_instance_url'] === null &&

			$values['openproject_client_id'] === null &&

			$values['openproject_client_secret'] === null

		);

		// resetting and keeping the project folder setup should delete the user app password
		if (key_exists('setup_app_password', $values) && $values['setup_app_password'] === false) {
			$this->openprojectAPIService->deleteAppPassword();
		}

		$this->config->deleteAppValue(Application::APP_ID, 'oPOAuthTokenRevokeStatus');
		if (
			// when the OP client information has changed
			((key_exists('openproject_client_id', $values) && $values['openproject_client_id'] !== $oldClientId) ||
				(key_exists('openproject_client_secret', $values) && $values['openproject_client_secret'] !== $oldClientSecret)) ||
			// when the OP client information is for reset
			$runningFullReset
		) {
			$this->userManager->callForAllUsers(function (IUser $user) use (
				$oldOpenProjectOauthUrl, $oldClientId, $oldClientSecret
			) {
				$userUID = $user->getUID();
				$accessToken = $this->config->getUserValue($userUID, Application::APP_ID, 'token', '');

				// revoke is possible only when the user has the access token stored in the database
				// plus, for a successful revoke, the old OP client information is also needed
				// there may be cases where the software only have host url saved but not the client information
				// in this case, the token is not revoked and just cleared if present in the database
				if ($accessToken && $oldOpenProjectOauthUrl && $oldClientId && $oldClientSecret) {
					try {
						$this->openprojectAPIService->revokeUserOAuthToken(
							$userUID,
							$oldOpenProjectOauthUrl,
							$accessToken,
							$oldClientId,
							$oldClientSecret
						);
						if (
							$this->config->getAppValue(Application::APP_ID, 'oPOAuthTokenRevokeStatus', '') === ''
						) {
							$this->config->setAppValue(Application::APP_ID, 'oPOAuthTokenRevokeStatus', 'success');
						}
					} catch (ConnectException $e) {
						$this->config->setAppValue(Application::APP_ID, 'oPOAuthTokenRevokeStatus', 'connection_error');
						$this->logger->error(
							'Error: ' . $e->getMessage(),
							['app' => Application::APP_ID]
						);
					} catch (OpenprojectErrorException $e) {
						$this->config->setAppValue(Application::APP_ID, 'oPOAuthTokenRevokeStatus', 'other_error');
						$this->logger->error(
							'Error: ' . $e->getMessage(),
							['app' => Application::APP_ID]
						);
					}
				}
				$this->clearUserInfo($userUID);
			});
		}


		// whenever doing full reset we want the project folder switch state to be "on" in the UI
		// so setting `fresh_project_folder_setup` as true
		if ($runningFullReset) {
			$this->config->setAppValue(Application::APP_ID, 'fresh_project_folder_setup', "1");
		} elseif (key_exists('setup_app_password', $values) && key_exists('setup_project_folder', $values)) {
			// for other cases when api has key 'setup_app_password' and 'setup_project_folder' we set it to false
			// assuming user has either fully set the integration or partially without project folder/app password
			$this->config->setAppValue(Application::APP_ID, 'fresh_project_folder_setup', "0");
		}

		// if the revoke has failed at least once, the last status is stored in the database
		// this is not a neat way to give proper information about the revoke status
		// TODO: find way to report every user's revoke status
		$oPOAuthTokenRevokeStatus = $this->config->getAppValue(Application::APP_ID, 'oPOAuthTokenRevokeStatus', '');
		$this->config->deleteAppValue(Application::APP_ID, 'oPOAuthTokenRevokeStatus');
		return [
			"status" => OpenProjectAPIService::isAdminConfigOk($this->config),
			"oPOAuthTokenRevokeStatus" => $oPOAuthTokenRevokeStatus,
			"oPUserAppPassword" => $appPassword,
		];
	}

	/**
	 * set admin config values
	 *
	 * @param array<string, string|null|bool> $values
	 *
	 * @return DataResponse
	 * @throws GuzzleException
	 */
	public function setAdminConfig(array $values): DataResponse {
		try {
			$result = $this->setIntegrationConfig($values);
			return new DataResponse($result);
		} catch (OpenprojectGroupfolderSetupConflictException $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage()),
			], Http::STATUS_CONFLICT);
		} catch (NoUserException $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage())
			], Http::STATUS_NOT_FOUND);
		} catch (InvalidArgumentException $e) {
			return new DataResponse([
				"error" => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			return new DataResponse([
				"error" => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * receive oauth code and get oauth access token
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $code
	 * @param string $state
	 * @return RedirectResponse
	 */
	public function oauthRedirect(string $code = '', string $state = ''): RedirectResponse {
		$configState = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_state');
		$clientID = $this->config->getAppValue(Application::APP_ID, 'openproject_client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'openproject_client_secret');
		$codeVerifier = $this->config->getUserValue(
			$this->userId, Application::APP_ID, 'code_verifier', false
		);
		$oauthJourneyStartingPage = $this->config->getUserValue(
			$this->userId, Application::APP_ID, 'oauth_journey_starting_page'
		);

		try {
			$oauthJourneyStartingPageDecoded = json_decode($oauthJourneyStartingPage);

			if ($oauthJourneyStartingPageDecoded->page === 'dashboard') {
				$newUrl = $this->urlGenerator->linkToRoute('dashboard.dashboard.index');
			} elseif ($oauthJourneyStartingPageDecoded->page === 'settings') {
				$newUrl = $this->urlGenerator->linkToRoute(
					'settings.PersonalSettings.index', ['section' => 'openproject']
				);
			} elseif ($oauthJourneyStartingPageDecoded->page === 'files') {
				$newUrl = $this->urlGenerator->linkToRoute('files.view.index', [
					'dir' => $oauthJourneyStartingPageDecoded->file->dir,
					'scrollto' => $oauthJourneyStartingPageDecoded->file->name
				]);
			} elseif ($oauthJourneyStartingPageDecoded->page === 'spreed') {
				$newUrl = $oauthJourneyStartingPageDecoded->callUrl;
			} else {
				$this->logger->error(
					'could not determine where the OAuth journey ' .
					'to connect to OpenProject started'
				);
				throw new \Exception();
			}
		} catch (\Exception $e) {
			$newUrl = $this->urlGenerator->linkToRoute('files.view.index');
		}

		// anyway, reset state
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'oauth_state');

		$validCodeVerifier = false;
		if (is_string($codeVerifier)) {
			$validCodeVerifier = (preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $codeVerifier) === 1);
		}

		$validClientSecret = false;
		if (is_string($clientSecret)) {
			$validClientSecret = (preg_match('/^.{10,}$/', $clientSecret) === 1);
		}

		if ($clientID && $validClientSecret && $validCodeVerifier && $configState !== '' && $configState === $state) {
			$openprojectUrl = $this->config->getAppValue(Application::APP_ID, 'openproject_instance_url');
			$result = $this->openprojectAPIService->requestOAuthAccessToken($openprojectUrl, [
				'client_id' => $clientID,
				'client_secret' => $clientSecret,
				'code' => $code,
				'redirect_uri' => openprojectAPIService::getOauthRedirectUrl($this->urlGenerator),
				'grant_type' => 'authorization_code',
				'code_verifier' => $codeVerifier
			], 'POST');
			if (isset($result['access_token']) && isset($result['refresh_token'])) {
				$accessToken = $result['access_token'];
				$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
				$refreshToken = $result['refresh_token'];
				$this->config->setUserValue($this->userId, Application::APP_ID, 'refresh_token', $refreshToken);
				// get user info
				// ToDo check response for errors
				$this->storeUserInfo();
				$this->config->setUserValue(
					$this->userId, Application::APP_ID, 'oauth_connection_result', 'success'
				);
				return new RedirectResponse($newUrl);
			}
			$error = '';
			if (!isset($result['access_token'])) {
				$error = $this->l->t('Error getting OAuth access token');
			} elseif (!isset($result['refresh_token'])) {
				$error = $this->l->t('Error getting OAuth refresh token');
			}
			if (isset($result['error'])) {
				$error = $error . '. ' . $result['error'];
			}
			$result = $error;
		} else {
			if (!$validCodeVerifier) {
				$this->logger->error('invalid OAuth code verifier', ['app' => $this->appName]);
			}
			if (!$validClientSecret) {
				$this->logger->error('invalid OAuth client secret', ['app' => $this->appName]);
			}
			$result = $this->l->t('Error during OAuth exchanges');
		}
		$this->config->setUserValue(
			$this->userId, Application::APP_ID, 'oauth_connection_result', 'error'
		);
		$this->config->setUserValue(
			$this->userId, Application::APP_ID, 'oauth_connection_error_message', $result
		);
		return new RedirectResponse($newUrl);
	}

	/**
	 * @return array<mixed>
	 * @throws PreConditionNotMetException
	 */
	private function storeUserInfo(): array {
		$info = $this->openprojectAPIService->request($this->userId, 'users/me');
		if (isset($info['lastName'], $info['firstName'], $info['id'])) {
			$fullName = $info['firstName'] . ' ' . $info['lastName'];
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', $info['id']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $fullName);
			return ['user_name' => $fullName];
		} else {
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_id');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_name');
			if (isset($info['statusCode']) && $info['statusCode'] === 404) {
				$info['error'] = 'Not found';
			} else {
				if (!isset($info['error'])) {
					$info['error'] = 'Invalid token';
				}
			}
			return $info;
		}
	}

	/**
	 * @return DataResponse
	 */
	public function autoOauthCreation(): DataResponse {
		return new DataResponse($this->recreateOauthClientInformation());
	}

	private function deleteOauthClient(): void {
		$oauthClientInternalId = $this->config->getAppValue(
			Application::APP_ID, 'nc_oauth_client_id', ''
		);
		if ($oauthClientInternalId !== '') {
			$id = (int) $oauthClientInternalId;
			try {
				$this->oauthSettingsController->deleteClient($id);
			} catch (ClientNotFoundException $e) {
			}
			$this->config->deleteAppValue(Application::APP_ID, 'nc_oauth_client_id');
		}
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @PublicPage
	 *
	 * @return DataResponse
	 */
	public function checkConfig(): DataResponse {
		return new DataResponse([
			'user_id' => $this->userId ?? '',
			'authorization_header' => $_SERVER['HTTP_AUTHORIZATION'],
		]);
	}

	/**
	 * @NoCSRFRequired
	 * set up integration
	 *  @param array<string, string|null|bool> $values
	 *
	 * @return DataResponse
	 *
	 */
	public function setUpIntegration(?array $values): DataResponse {
		try {
			// for POST all the keys must be mandatory
			OpenProjectAPIService::validateIntegrationSetupInformation($values);
			$status = $this->setIntegrationConfig($values);
			$result = $this->recreateOauthClientInformation();
			if ($status['oPOAuthTokenRevokeStatus'] !== '') {
				$result['openproject_revocation_status'] = $status['oPOAuthTokenRevokeStatus'];
			}
			if ($status['oPUserAppPassword'] !== null) {
				$result['openproject_user_app_password'] = $status['oPUserAppPassword'];
			}
			return new DataResponse($result);
		} catch (OpenprojectGroupfolderSetupConflictException $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage()),
			], Http::STATUS_CONFLICT);
		} catch (NoUserException $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage())
			], Http::STATUS_NOT_FOUND);
		} catch (InvalidArgumentException $e) {
			return new DataResponse([
				"error" => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			return new DataResponse([
				"error" => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoCSRFRequired
	 *
	 * update integration
	 *
	 * @param array<string, string|null|bool> $values
	 *
	 *
	 * @return DataResponse
	 */
	public function updateIntegration(?array $values): DataResponse {
		try {
			// for PUT key information can be optional (not mandatory)
			OpenProjectAPIService::validateIntegrationSetupInformation($values, false);
			$status = $this->setIntegrationConfig($values);
			$oauthClientInternalId = $this->config->getAppValue(Application::APP_ID, 'nc_oauth_client_id', '');
			$result = [];
			if ($status['oPOAuthTokenRevokeStatus'] !== '') {
				$result['openproject_revocation_status'] = $status['oPOAuthTokenRevokeStatus'];
			}
			if ($status['oPUserAppPassword'] !== null) {
				$result['openproject_user_app_password'] = $status['oPUserAppPassword'];
			}
			if ($oauthClientInternalId !== '') {
				$id = (int)$oauthClientInternalId;
				$result = array_merge($this->oauthService->getClientInfo($id), $result);
			} else {
				// we will recreate new oauth when admin has reset it
				$result = array_merge($this->recreateOauthClientInformation(), $result);
			}
			return new DataResponse($result);
		} catch (OpenprojectGroupfolderSetupConflictException $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage()),
			], Http::STATUS_CONFLICT);
		} catch (NoUserException $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage())
			], Http::STATUS_NOT_FOUND);
		} catch (InvalidArgumentException $e) {
			return new DataResponse([
				"error" => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			return new DataResponse([
				"error" => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoCSRFRequired
	 *
	 * reset integration
	 *
	 *
	 * @return DataResponse
	 */
	public function resetIntegration(): DataResponse {
		$values = [
			'openproject_instance_url' => null,
			'openproject_client_id' => null,
			'openproject_client_secret' => null,
			'default_enable_navigation' => null,
			'default_enable_unified_search' => null,
			'setup_app_password' => false
		];
		try {
			$status = $this->setIntegrationConfig($values);
			$result = ["status" => true];
			if ($status['oPOAuthTokenRevokeStatus'] !== '') {
				$result['openproject_revocation_status'] = $status['oPOAuthTokenRevokeStatus'];
			}
			return new DataResponse($result);
		} catch (\Exception $e) {
			return new DataResponse([
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}


	/**
	 * @return array<mixed>
	 */
	private function recreateOauthClientInformation(): array {
		$this->deleteOauthClient();
		$opUrl = $this->config->getAppValue(Application::APP_ID, 'openproject_instance_url', '');
		$clientInfo = $this->oauthService->createNcOauthClient('OpenProject client', rtrim($opUrl, '/') .'/oauth_clients/%s/callback');
		$this->config->setAppValue(Application::APP_ID, 'nc_oauth_client_id', $clientInfo['id']);
		unset($clientInfo['id']);
		return $clientInfo;
	}

	/**
	 * @NoCSRFRequired
	 *
	 * @return DataResponse
	 *
	 */
	public function signTermsOfServiceForUserOpenProject(): DataResponse {
		try {
			$this->openprojectAPIService->signTermsOfServiceForUserOpenProject();
			$result = $this->openprojectAPIService->isAllTermsOfServiceSignedForUserOpenProject();
			return new DataResponse(
				[
					'result' => $result
				]
			);
		} catch (DBException $e) {
			return new DataResponse(
				[
					'error' => $this->l->t($e->getMessage())
				], Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}
}
