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
use OCA\OpenProject\Service\DirectUploadService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Files\FileInfo;

class DirectUploadController extends Controller {
	/**
	 * @var string|null
	 */
	private $userId;

	/**
	 * @var DirectUploadService
	 */
	private $directUploadService;

	/**
	 * @var IUser|null
	 */
	private $user;

	/**
	 * @var IRootFolder
	 */
	private $rootFolder;
	public function __construct(string $appName,
								IRequest $request,
								IRootFolder $rootFolder,
								IUserSession $userSession,
								DirectUploadService $directUploadService,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->directUploadService = $directUploadService;
		$this->user = $userSession->getUser();
		$this->rootFolder = $rootFolder;
	}

	/**
	 * preparation for the direct upload
	 *
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 *
	 * This can be tested with:
	 * curl -u USER:PASSWD http://my.nc.org/index.php/apps/integration_openproject/direct-upload?folder_id=<folder_id>
	 *
	 * @param int $folder_id
	 * @return DataResponse
	 */
	public function prepareDirectUpload(int $folder_id):DataResponse {
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
		} catch (Exception|NoUserException|NotPermittedException|NotFoundException $e) {
			return new DataResponse([
				'error' => 'folder not found or not enough permissions'
			], Http::STATUS_NOT_FOUND);
		}
	}
}
