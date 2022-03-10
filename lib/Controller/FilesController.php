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

use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

class FilesController extends OCSController {

	/**
	 * @var string|null
	 */
	private $userId;
	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	public function __construct(string $appName,
								IRequest $request,
								IRootFolder $rootFolder,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->rootFolder = $rootFolder;
	}

	/**
	 * get file info from file ID
	 * @NoAdminRequired
	 *
	 */
	public function getFileInfo(int $fileId): DataResponse {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$files = $userFolder->getById($fileId);
		if (is_array($files) && count($files) > 0) {
			$file = $files[0];
			$owner = $file->getOwner();
			$fileInfo = [
				'id' => $fileId,
				'name' => $file->getName(),
				'mtime' => $file->getMTime(),
				'ctime' => $file->getCreationTime(),
				'mimetype' => $file->getMimetype(),
				'path' => preg_replace('/^files\//', '/', $file->getInternalPath()),
				'size' => $file->getSize(),
				'owner_name' => $owner->getDisplayName(),
				'owner_id' => $owner->getUID(),
			];
			return new DataResponse($fileInfo);
		}
		return new DataResponse([], Http::STATUS_NOT_FOUND);
	}

	/**
	 * get file info from file IDs
	 * @NoAdminRequired
	 *
	 */
	public function getFilesInfo(array $fileIds): DataResponse {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$result = [];
		foreach ($fileIds as $fileId) {
			$files = $userFolder->getById($fileId);
			if (is_array($files) && count($files) > 0) {
				$file = $files[0];
				$owner = $file->getOwner();
				$result[$fileId] = [
					'id' => $fileId,
					'name' => $file->getName(),
					'mtime' => $file->getMTime(),
					'ctime' => $file->getCreationTime(),
					'mimetype' => $file->getMimetype(),
					'path' => preg_replace('/^files\//', '/', $file->getInternalPath()),
					'size' => $file->getSize(),
					'owner_name' => $owner->getDisplayName(),
					'owner_id' => $owner->getUID(),
				];
			}
		}
		return new DataResponse($result);
	}
}
