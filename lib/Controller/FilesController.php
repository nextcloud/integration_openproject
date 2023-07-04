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

use Exception;
use OCA\Activity\Data;
use OCA\Activity\GroupHelperDisabled;
use OCA\Activity\UserSettings;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\Activity\IManager;
use OCP\App\IAppManager;
use OCP\Files\Config\ICachedMountFileInfo;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\RichObjectStrings\IValidator;
use Throwable;

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
	 * @var IValidator
	 */
	private $richObjectValidator;

	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * @var IL10N
	 */
	private $l;

	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var IAppManager
	 */
	private $appManager;
	/**
	 * @var ITrashManager
	 */
	private $trashManager = null;

	public function __construct(string $appName,
								IRequest $request,
								IRootFolder $rootFolder,
								IUserSession $userSession,
								IMountProviderCollection $mountCollection,
								IManager $activityManager,
								IAppManager $appManager,
								IDBConnection $connection,
								IValidator $richObjectValidator,
								ILogger $logger,
								IL10N $l,
								IConfig $config,
								IUserManager $userManager
	) {
		parent::__construct($appName, $request);
		$this->user = $userSession->getUser();
		$this->rootFolder = $rootFolder;
		$this->mountCollection = $mountCollection;
		$this->activityManager = $activityManager;
		$this->connection = $connection;
		$this->richObjectValidator = $richObjectValidator;
		$this->logger = $logger;
		$this->l = $l;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->appManager = $appManager;
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
	 * Function to make the trash-manager testable
	 * @param ITrashManager $trashManager
	 * @return void
	 */
	public function setTrashManager($trashManager = null) {
		if ($trashManager !== null) {
			$this->trashManager = $trashManager;
		} elseif ($this->trashManager === null) {
			$this->trashManager = \OC::$server->get(ITrashManager::class);
		}
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
		$trashed = false;

		$userFolder = $this->rootFolder->getUserFolder($this->user->getUID());
		$files = $userFolder->getById($fileId);
		if (is_array($files) && count($files) > 0) {
			$file = $files[0];
		} elseif ($this->appManager->isEnabledForUser('files_trashbin')) {
			try {
				$this->setTrashManager();
				$file = $this->trashManager->getTrashNodeById(
					$this->user, $fileId
				);
				$trashed = true;
			} catch (Exception | Throwable $e) {
				$this->logger->error('failed to use the trashManager', ['exception' => $e]);
			}
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
			if ($internalPath === null) {
				$this->logger->error(
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
			if ($file->getMimeType() === FileInfo::MIMETYPE_FOLDER) {
				$mimeType = 'application/x-op-directory';
			} else {
				$mimeType = $file->getMimeType();
			}
			$fullpath = $file->getpath();
			// full path is in format `<user-name>/files/a/b/`
			// since we don't want to send it with username, only get the `files/a/b` and send it
			$path = explode('/', $fullpath, 3);
			$davPermission = $this->getDavPermissions($file);
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
				'trashed' => $trashed,
				'modifier_name' => $modifierName,
				'modifier_id' => $modifierId,
				'dav_permissions' => $davPermission,
				'path' => $path[2]
			];
		}

		if (is_array($mounts) && count($mounts) > 0) {
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
			$activityData = new Data($this->activityManager, $this->connection);
		} else {
			return null;
		}

		// @phpstan-ignore-next-line - make phpstan not complain if activity app does not exist
		$groupHelper = new GroupHelperDisabled(
			$this->l,
			$this->activityManager,
			$this->richObjectValidator,
			$this->logger
		);
		// @phpstan-ignore-next-line - make phpstan not complain if activity app does not exist
		$userSettings = new UserSettings($this->activityManager, $this->config);
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

	// copy of https://github.com/nextcloud/server/blob/cd44ee2053f105f60c668906ed8460605cb1da81/lib/public/Files/DavUtil.php#L58
	// as this is only availabe from V25 and we need it in versions below that
	private function getDavPermissions(FileInfo $info): string {
		$p = '';
		if ($info->isShared()) {
			$p .= 'S';
		}
		if ($info->isShareable()) {
			$p .= 'R';
		}
		if ($info->isMounted()) {
			$p .= 'M';
		}
		if ($info->isReadable()) {
			$p .= 'G';
		}
		if ($info->isDeletable()) {
			$p .= 'D';
		}
		if ($info->isUpdateable()) {
			$p .= 'NV'; // Renameable, Moveable
		}
		if ($info->getType() === FileInfo::TYPE_FILE) {
			if ($info->isUpdateable()) {
				$p .= 'W';
			}
		} else {
			if ($info->isCreatable()) {
				$p .= 'CK';
			}
		}
		return $p;
	}
}
