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

use OCA\Activity\Data;
use OCA\Activity\GroupHelperDisabled;
use OCA\Activity\UserSettings;
use OCP\Activity\IManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\Config\ICachedMountFileInfo;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\DavUtil;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;
use Psr\Log\LoggerInterface;

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
	 * @var IMountProviderCollection
	 */
	private $mountCollection;

	/** @var IManager */
	protected $activityManager;

	/** @var IDBConnection */
	protected $connection;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var DavUtil
	 */
	private $davUtils;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IRootFolder $rootFolder
	 * @param IUserSession $userSession
	 * @param IMountProviderCollection $mountCollection
	 * @param IManager $activityManager
	 * @param IDBConnection $connection
	 * @param LoggerInterface $logger
	 * @param IUserManager $userManager
	 * @param DavUtil $davUtils
	 */

	public function __construct(string $appName,
		IRequest $request,
		IRootFolder $rootFolder,
		IUserSession $userSession,
		IMountProviderCollection $mountCollection,
		IManager $activityManager,
		IDBConnection $connection,
		LoggerInterface $logger,
		IUserManager $userManager,
		DavUtil $davUtils
	) {
		parent::__construct($appName, $request);
		$this->user = $userSession->getUser();
		$this->rootFolder = $rootFolder;
		$this->mountCollection = $mountCollection;
		$this->activityManager = $activityManager;
		$this->connection = $connection;
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->davUtils = $davUtils;
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
	 * @param array<mixed>|null $fileIds
	 * @NoAdminRequired
	 *
	 */
	public function getFilesInfo(?array $fileIds): DataResponse {
		if (!is_array($fileIds)) {
			return new DataResponse('invalid request', Http::STATUS_BAD_REQUEST);
		}
		$result = [];
		foreach ($fileIds as $fileId) {
			$result[$fileId] = $this->compileFileInfo((int)$fileId);
		}
		return new DataResponse($result);
	}

	/**
	 * @param int $fileId
	 * @return array<mixed>
	 *
	 *
	 *
	 */
	private function compileFileInfo($fileId) {
		$file = null;

		$userFolder = $this->rootFolder->getUserFolder($this->user->getUID());
		$files = $userFolder->getById($fileId);
		if (is_array($files) && count($files) > 0) {
			$file = $files[0];
		}
		$mounts = $this->mountCollection->getMountCache()->getMountsForFileId($fileId);

		if ($file !== null && is_array($mounts) && count($mounts) > 0) {
			$owner = $file->getOwner();
			$internalPath = null;
			foreach ($mounts as $mount) {
				if ($mount instanceof  ICachedMountFileInfo &&
					$mount->getUser()->getUID() === $owner->getUID()
				) {
					$internalPath = $mount->getInternalPath();
					break;
				}
			}
			// Note: in case of groupfolders the internal path is `__groupfolders/<group-folder-id>` so
			// getInternalPath() functions returns empty string and the internal path fallbacks to the context of requester
			if (!$internalPath) {
				$this->logger->info(
					'could not get the file name in the context of the owner,' .
					' falling back to the context of requester'
				);
				$internalPath = $file->getName();
			}
			$modifier = $this->getLastModifier($owner->getUID(), $file->getId());
			if ($modifier instanceof IUser) {
				$modifierId = $modifier->getUID();
				$modifierName = $modifier->getDisplayName();
			} else {
				$modifierId = null;
				$modifierName = null;
			}
			$fullpath = $file->getpath();
			// full path is in format `<user-name>/files/a/b/`
			// since we don't want to send it with username, only get the `files/a/b` and send it
			$path = explode('/', $fullpath, 3);
			$davPermission = $this->getDavPermissions($file);
			if ($file->getMimeType() === FileInfo::MIMETYPE_FOLDER) {
				$mimeType = 'application/x-op-directory';
				$path = $path[2] . '/';
			} else {
				$mimeType = $file->getMimeType();
				$path = $path[2];
			}
			return [
				'status' => 'OK',
				'statuscode' => 200,
				'id' => $file->getId(),
				'name' => basename($internalPath),
				'mtime' => $file->getMTime(),
				'ctime' => $file->getCreationTime(),
				'mimetype' => $mimeType,
				'size' => $file->getSize(),
				'owner_name' => $owner->getDisplayName(),
				'owner_id' => $owner->getUID(),
				'modifier_name' => $modifierName,
				'modifier_id' => $modifierId,
				'dav_permissions' => $davPermission,
				'path' => $path
			];
		}

		if (is_array($mounts) && count($mounts) > 0) {
			// if the file is in trashbin send 404
			foreach ($mounts as $mount) {
				if (str_starts_with($mount->getInternalPath(), 'files_trashbin')) {
					return [
						'status' => 'Not Found',
						'statuscode' => 404,
					];
				}
			}
			// if the file is of another user send 403
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

	private function getLastModifier(string $ownerId, int $fileId, int $since = 0): ?IUser {
		if (class_exists('\OCA\Activity\Data') &&
			class_exists('\OCA\Activity\GroupHelperDisabled') &&
			class_exists('\OCA\Activity\UserSettings')
		) {
			$activityData = Server::get(Data::class);
		} else {
			return null;
		}

		$groupHelper = Server::get(GroupHelperDisabled::class);
		$userSettings = Server::get(UserSettings::class);
		if (!method_exists($activityData, 'get') ||
			!method_exists($activityData, 'getById')
		) {
			return null;
		}
		$activities = $activityData->get(
			$groupHelper,
			$userSettings,
			$ownerId,
			$since,
			10,
			'DESC',
			'filter',
			'files',
			$fileId
		);
		foreach ($activities['data'] as $activity) {
			if ($activity['type'] === 'file_changed') {
				$activityDetails = $activityData->getById($activity['activity_id']);
				// rename and move events are also of type `file_changed` but don't have `changed_*` in the subject
				// sadly we only get the localized subject from the `get()` request and need to do an other request
				if (!method_exists($activityDetails, 'getSubject')) {
					return null;
				}
				if (str_starts_with($activityDetails->getSubject(), 'changed')) {
					return $this->userManager->get($activity['user']);
				}
			}
		}
		if ($activities['has_more'] === true) {
			$lastGiven = (int)$activities['headers']['X-Activity-Last-Given'];
			if (($lastGiven < $since || $since === 0) && $lastGiven != $since) {
				return $this->getLastModifier($ownerId, $fileId, $lastGiven);
			}
		}
		return null;
	}

	// `davUtils->getDavPermissions` method is static so it cannot be mocked to
	// creating similar function here for testing purposes
	public function getDavPermissions(FileInfo $info): string {
		return $this->davUtils->getDavPermissions($info);
	}
}
