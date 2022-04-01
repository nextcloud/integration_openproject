<?php
/**
 * Nextcloud - openproject
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\OpenProject\Controller;

use OC\User\User;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IRequest;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IUserSession;

class FilesController extends OCSController {

	/**
	 * @var string|null
	 */
	private $userId;
	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * @var ITrashManager
	 */
	private $trashManager;

	/**
	 * @var IUserSession
	 */
	private $userSession;

	public function __construct(string $appName,
								IRequest $request,
								IRootFolder $rootFolder,
								ITrashManager $trashManager,
								IUserSession $userSession) {
		parent::__construct($appName, $request);
		$this->userId = $userSession->getUser()->getUID();
		$this->rootFolder = $rootFolder;
		$this->trashManager = $trashManager;
		$this->userSession = $userSession;
	}

	/**
	 * get file info from file ID
	 *
	 * This can be tested with
	 * curl -H "Accept: application/json" -H "OCS-APIRequest: true" -u USER:PASSWD
	 * 		http://my.nc.org/ocs/v1.php/apps/integration_openproject/fileinfo/FILE_ID
	 * @NoAdminRequired
	 *
	 */
	public function getFileInfo(int $fileId): DataResponse {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$files = $userFolder->getById($fileId);

		if (is_array($files) && count($files) > 0) {
			$fileInfo = $this->compileFileInfo($files[0]);
			return new DataResponse($fileInfo);
		} else {
			$trash = $this->trashManager->getTrashNodeById(
				$this->userSession->getUser(), $fileId
			);
			if ($trash !== null) {
				$fileInfo = $this->compileFileInfo($trash);
				return new DataResponse($fileInfo);
			}
		}
		return new DataResponse([], Http::STATUS_NOT_FOUND);
	}

	/**
	 * get file info from file IDs
	 *
	 * This can be tested with:
	 * curl -H "Accept: application/json" -H "Content-Type:application/json" -H "OCS-APIRequest: true"
	 * 		-u USER:PASSWD http://my.nc.org/ocs/v1.php/apps/integration_openproject/filesinfo
	 * 		-X POST -d '{"fileIds":[FILE_ID_1,FILE_ID_2,...]}'
	 *
	 * @param array<int>|null $fileIds
	 * @NoAdminRequired
	 *
	 */
	public function getFilesInfo(?array $fileIds): DataResponse {
		if (!is_array($fileIds)) {
			return new DataResponse('invalid request', Http::STATUS_BAD_REQUEST);
		}
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$result = [];
		foreach ($fileIds as $fileId) {
			$fileId = (int)$fileId;
			$files = $userFolder->getById($fileId);
			if (is_array($files) && count($files) > 0) {
				$result[$fileId] = $this->compileFileInfo($files[0]);
			} else {
				$result[$fileId] = null;
			}
		}
		return new DataResponse($result);
	}

	/**
	 * @param Node $file
	 * @return array{'id': int, 'name':string, 'mtime': int, 'ctime': int,
	 *               'mimetype': string, 'path': string, 'size': int,
	 *               'owner_name': string, 'owner_id': string}
	 */
	private function compileFileInfo($file) {
		$owner = $file->getOwner();

		return [
			'id' => $file->getId(),
			'name' => $file->getName(),
			'mtime' => $file->getMTime(),
			'ctime' => $file->getCreationTime(),
			'mimetype' => $file->getMimetype(),
			'path' => preg_replace('/(files_trashbin\/)?files\/?/', '/', $file->getInternalPath()),
			'size' => $file->getSize(),
			'owner_name' => $owner->getDisplayName(),
			'owner_id' => $owner->getUID(),
		];
	}
}
