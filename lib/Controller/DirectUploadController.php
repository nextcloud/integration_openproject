<?php

/**
 * @copyright Copyright (c) 2022 Swikriti Tripathi <swikriti@jankaritech.com>
 *
 * @author Your name <swikriti@jankaritech.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OpenProject\Controller;

use OC\Files\Node\File;
use OCA\OAuth2\Controller\SettingsController;
use OCA\OpenProject\Service\DirectUploadService;
use OCA\OpenProject\Service\OauthService;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use OCP\Constants;
use OCP\Files\FileInfo;

class DirectUploadController extends Controller{
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
	 * @var DirectUploadService
	 */
	private $directUploadService;

	/**
	 * @var IUser|null
	 */
	private $user;
	/**
	/**
	 * @var IRootFolder
	 */
	private $rootFolder;
	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IRootFolder $rootFolder,
								IUserSession $userSession,
								IURLGenerator $urlGenerator,
								IUserManager $userManager,
								LoggerInterface $logger,
								OauthService $oauthService,
								IL10N $l,
								DirectUploadService $directUploadService,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
		$this->l = $l;
		$this->logger = $logger;
		$this->userId = $userId;
		$this->oauthService = $oauthService;
		$this->directUploadService = $directUploadService;
		$this->user = $userSession->getUser();
		$this->rootFolder = $rootFolder;
	}

	/**
	 * receive oauth code and get oauth access token
	 *
	 * @NoCSRFRequired
	 *
	 * @param int $folder_id
	 * @return DataResponse
	 * @throws Exception
	 */
	public function prepareDirectUpload(int $folder_id):DataResponse {
		$userFolder = $this->rootFolder->getUserFolder($this->user->getUID());
		$files = $userFolder->getById($folder_id);
		if (
			is_array($files) &&
			count($files) > 0 &&
			$userFolder->getType($files[0]) === FileInfo::TYPE_FOLDER
		) {
			$permissions = $userFolder->getPermissions($files[0]);
			if($permissions !== Constants::PERMISSION_DELETE && $permissions !== Constants::PERMISSION_UPDATE){
				try{
					$response = $this->directUploadService->setInfoInDB($folder_id,$this->userId);
					return new DataResponse($response);
				} catch (\Exception $e) {
					return new DataResponse([
						'error' => $this->l->t($e->getMessage())
					], Http::STATUS_BAD_REQUEST);
				}
			}
			else {
				return new DataResponse([
					'error' => 'not enough permissions'
				], Http::STATUS_FORBIDDEN);
			}

		}
		else {
			return new DataResponse([
				'error' => 'Folder not found'
			], Http::STATUS_BAD_REQUEST);
		}

	}
}
