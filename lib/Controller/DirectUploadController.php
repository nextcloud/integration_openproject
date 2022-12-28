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

use OC\User\NoUserException;
use OCP\Files\NotFoundException;
use OCA\OpenProject\Service\DirectUploadService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Files\FileInfo;

class DirectUploadController extends Controller {
	/**
	 * @var string|null
	 */
	private ?string $userId;

	/**
	 * @var DirectUploadService
	 */
	private DirectUploadService $directUploadService;

	/**
	 * @var IUser|null
	 */
	private ?IUser $user;

	/**
	 * @var IRootFolder
	 */
	private IRootFolder $rootFolder;

	/**
	 * @var IUserManager
	 */
	private $userManager;
	public function __construct(
		string $appName,
		IRequest $request,
		IRootFolder $rootFolder,
		IUserSession $userSession,
		IUserManager $userManager,
		DirectUploadService $directUploadService,
		?string $userId
	) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->directUploadService = $directUploadService;
		$this->user = $userSession->getUser();
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
	}

	/**
	 * preparation for the direct upload
	 *
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 *
	 * @param int $folder_id
	 * @return DataResponse
	 */
	public function prepareDirectUpload(int $folder_id): DataResponse {
		try {
			$userFolder = $this->rootFolder->getUserFolder($this->user->getUID());
			$nodes = $userFolder->getById($folder_id);
			if (empty($nodes)) {
				return new DataResponse([
					'error' => 'folder not found or not enough permissions'
				], Http::STATUS_NOT_FOUND);
			}
			$node = array_shift($nodes);
			$fileType = $node->getType();
			if (
				$node->isCreatable() &&
				$fileType === FileInfo::TYPE_FOLDER
			) {
				$response = $this->directUploadService->getTokenForDirectUpload($folder_id, $this->userId);
				return new DataResponse($response);
			} else {
				return new DataResponse([
					'error' => 'folder not found or not enough permissions'
				], Http::STATUS_NOT_FOUND);
			}
		} catch (Exception $e) {
			return new DataResponse([
				'error' => 'folder not found or not enough permissions'
			], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * preparation for the direct upload
	 *
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @PublicPage
	 *
	 * This can be tested with:
	 * curl -X POST http://my.nc.org/index.php/apps/integration_openproject/direct-upload/<token>
	 *
	 * @param string $token
	 * @param string $file_name
	 * @param string $contents
	 * @return DataResponse
	 * @throws NoUserException
	 */
	public function directUpload(string $token, string $file_name, string $contents): DataResponse {
		$tokenInfo = null;
		if(strlen($token) !== 64){
			return new DataResponse([
				'error' => 'Invalid token. Token should be 64 characters long'
			],Http::STATUS_BAD_REQUEST);
		}
		try{
			$tokenInfo = $this->directUploadService->getTokenInfo($token);
			$user = $this->userManager->get($tokenInfo['user_id']);
			$userFolder = $this->rootFolder->getUserFolder($user->getUID());
			$nodes = $userFolder->getById($tokenInfo['folder_id']);
			if (empty($nodes)) {
				return new DataResponse([
					'error' => 'folder not found or not enough permissions'
				], Http::STATUS_NOT_FOUND);
			}
			$node = array_shift($nodes);
			if (
				$node->isCreatable()
			) {
				if($node->nodeExists($file_name)){
					return new DataResponse([
						'error' => 'Conflict, file with name '. $file_name .' already exists.',
					],Http::STATUS_CONFLICT);
				}
				$test = $node->newFile($file_name,$contents);
				$fileId = $test->getId();
				return new DataResponse([
					'file_name'=> $file_name,
					'file_id'=> $fileId
				],Http::STATUS_CREATED);
			}
		}catch (NotPermittedException $e){
			return new DataResponse([
				'error' => $e->getMessage()
			],Http::STATUS_UNAUTHORIZED);
		} catch (NotFoundException $e){
			return new DataResponse([
				'error' =>  $e->getMessage()
			],Http::STATUS_NOT_FOUND);
		}
		return new DataResponse($tokenInfo);
	}
}
