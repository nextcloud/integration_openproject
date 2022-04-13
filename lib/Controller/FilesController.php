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
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IUser;
use OCP\IUserSession;

class FilesController extends OCSController {

	/**
	 * @var IUser|null
	 */
	private $user;
	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * @var ITrashManager
	 */
	private $trashManager;

	/**
	 * @var IMountProviderCollection
	 */
	private $mountCollection;

	public function __construct(string $appName,
								IRequest $request,
								IRootFolder $rootFolder,
								ITrashManager $trashManager,
								IUserSession $userSession,
								IMountProviderCollection $mountCollection) {
		parent::__construct($appName, $request);
		$this->user = $userSession->getUser();
		$this->rootFolder = $rootFolder;
		$this->trashManager = $trashManager;
		$this->mountCollection = $mountCollection;
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
		$fileInfo = $this->compileFileInfo($fileId);
		return new DataResponse($fileInfo, $fileInfo['statuscode']);
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
		$result = [];
		foreach ($fileIds as $fileId) {
			$result[$fileId] = $this->compileFileInfo($fileId);
		}
		return new DataResponse($result);
	}

	/**
	 * @param int $fileId
	 * @return array{'status': string, 'statuscode': int, 'id'?: int, 'name'?:string,
	 *               'mtime'?: int, 'ctime'?: int, 'mimetype'?: string, 'path'?: string,
	 *               'size'?: int, 'owner_name'?: string, 'owner_id'?: string}
	 */
	private function compileFileInfo($fileId) {
		$userFolder = $this->rootFolder->getUserFolder($this->user->getUID());
		$files = $userFolder->getById($fileId);
		if (is_array($files) && count($files) > 0) {
			$file = $files[0];
			$trashed = false;
		} else {
			$file = $this->trashManager->getTrashNodeById(
				$this->user, $fileId
			);
			$trashed = true;
		}

		$mount = $this->mountCollection->getMountCache()->getMountsForFileId($fileId);

		if ($file !== null && is_array($mount) && count($mount) > 0) {
			$owner = $file->getOwner();
			$internalPath = $mount[0]->getInternalPath();
			$path = preg_replace(
				'/(files_trashbin\/)?files\/?/',
				'/',
				$internalPath
			);

			if ($path === '/') {
				$name = $internalPath;
			} else {
				$name = basename($path);
			}
			return [
				'status' => 'OK',
				'statuscode' => 200,
				'id' => $file->getId(),
				'name' => $name,
				'mtime' => $file->getMTime(),
				'ctime' => $file->getCreationTime(),
				'mimetype' => $file->getMimetype(),
				'path' => $path,
				'size' => $file->getSize(),
				'owner_name' => $owner->getDisplayName(),
				'owner_id' => $owner->getUID(),
				'trashed' => $trashed
			];
		}

		if (is_array($mount) && count($mount) > 0) {
			return [
				'status' => 'Forbidden',
				'statuscode' => 403,
			];
		}
		return [
			'status' => 'Not Found',
			'statuscode' => 404,
		];
	}
}
