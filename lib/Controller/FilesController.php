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
use OCP\Files\Node;
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
	 * @param array<int> $fileIds
	 * @NoAdminRequired
	 *
	 */
	public function getFilesInfo(array $fileIds): DataResponse {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$result = [];
		foreach ($fileIds as $fileId) {
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
			'path' => preg_replace('/^files\//', '/', $file->getInternalPath()),
			'size' => $file->getSize(),
			'owner_name' => $owner->getDisplayName(),
			'owner_id' => $owner->getUID(),
		];
	}
}
