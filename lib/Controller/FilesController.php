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
use OCA\Activity\GroupHelper;
use OCA\Activity\UserSettings;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\Activity\IManager;
use OCP\Files\Config\IMountProviderCollection;
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

	public function __construct(string $appName,
								IRequest $request,
								IRootFolder $rootFolder,
								ITrashManager $trashManager,
								IUserSession $userSession,
								IMountProviderCollection $mountCollection,
								IManager $activityManager,
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
		$this->trashManager = $trashManager;
		$this->mountCollection = $mountCollection;
		$this->activityManager = $activityManager;
		$this->connection = $connection;
		$this->richObjectValidator = $richObjectValidator;
		$this->logger = $logger;
		$this->l = $l;
		$this->config = $config;
		$this->userManager = $userManager;
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
	 *               'size'?: int, 'owner_name'?: string, 'owner_id'?: string, 'trashed'?: bool,
	 *               'modifier_name'?: string, 'modifier_id'?: string}
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

			$modifier = $this->getLastModifier($owner->getUID(), $file->getId());
			if ($modifier instanceof IUser) {
				$modifierId = $modifier->getUID();
				$modifierName = $modifier->getDisplayName();
			} else {
				$modifierId = null;
				$modifierName = null;
			}
			return [
				'status' => 'OK',
				'statuscode' => 200,
				'id' => $file->getId(),
				'name' => basename($internalPath),
				'mtime' => $file->getMTime(),
				'ctime' => $file->getCreationTime(),
				'mimetype' => $file->getMimetype(),
				'size' => $file->getSize(),
				'owner_name' => $owner->getDisplayName(),
				'owner_id' => $owner->getUID(),
				'trashed' => $trashed,
				'modifier_name' => $modifierName,
				'modifier_id' => $modifierId
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

	private function getLastModifier(string $ownerId, int $fileId, int $since = 0): ?IUser {
		if (class_exists('\OCA\Activity\Data')) {
			$activityData = new Data($this->activityManager, $this->connection);
		} else {
			return null;
		}

		$groupHelper = new GroupHelper(
			$this->l,
			$this->activityManager,
			$this->richObjectValidator,
			$this->logger
		);
		$userSettings = new UserSettings($this->activityManager, $this->config);
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
				if (str_starts_with($activityDetails->getSubject(), 'changed')) {
					return $this->userManager->get($activity['user']);
				}
			}
		}
		if ($activities['has_more'] === true) {
			$lastGiven = (int)$activities['headers']['X-Activity-Last-Given'];
			if ($lastGiven < $since || $since === 0) {
				return $this->getLastModifier($ownerId, $fileId, $lastGiven);
			}
		}
		return null;
	}
}
