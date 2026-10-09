<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Controller;

use \OCP\AppFramework\ApiController;
use DateTime;
use InvalidArgumentException;
use OC\Files\Filesystem;
use OC\Files\Node\Folder;
use OC\ForbiddenException;
use OC\User\NoUserException;
use OCA\OpenProject\Exception\OpenprojectFileNotUploadedException;
use OCA\OpenProject\Exception\OpenprojectUnauthorizedUserException;
use OCA\OpenProject\Service\DatabaseService;
use OCA\OpenProject\Service\DirectUploadService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\ForbiddenException as FileAccessForbiddenException;
use OCP\Files\InvalidCharacterInPathException;
use OCP\Files\InvalidContentException;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Lock\LockedException;
use Sabre\DAV\Exception\Conflict;

class DirectUploadController extends ApiController {
	/**
	 * @var string|null
	 */
	private ?string $userId;

	/**
	 * @var DirectUploadService
	 */
	private DirectUploadService $directUploadService;

	/**
	 * @var DatabaseService
	 */
	private DatabaseService $databaseService;
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
	private IUserManager $userManager;

	/**
	 * @var IUserSession
	 */
	private IUserSession $userSession;

	/**
	 * @var IL10N
	 */

	private $l;

	public function __construct(
		string $appName,
		IRequest $request,
		IRootFolder $rootFolder,
		IUserSession $userSession,
		IUserManager $userManager,
		DirectUploadService $directUploadService,
		DatabaseService $databaseService,
		IL10N  $l,
		?string $userId
	) {
		parent::__construct($appName, $request, 'POST');
		$this->userId = $userId;
		$this->directUploadService = $directUploadService;
		$this->user = $userSession->getUser();
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
		$this->databaseService = $databaseService;
		$this->userSession = $userSession;
		$this->l = $l;
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
					'error' => $this->l->t('folder not found or not enough permissions')
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
					'error' => $this->l->t('folder not found or not enough permissions')
				], Http::STATUS_NOT_FOUND);
			}
		} catch (\Exception $e) {
			return new DataResponse([
				'error' => $this->l->t('folder not found or not enough permissions')
			], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * direct upload
	 *
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @PublicPage
	 *
	 * @param string $token
	 *
	 * @return DataResponse
	 */
	public function directUpload(string $token):DataResponse {
		try {
			if (strlen($token) !== 64 || !preg_match('/^[a-zA-Z0-9]*/', $token)) {
				$this->databaseService->deleteToken($token);
				throw new OpenprojectUnauthorizedUserException('invalid token');
			}

			$postMaxSize = ini_get('post_max_size');
			$postMaxSizeInBytes = (int)\OCP\Util::computerFileSize($postMaxSize);
			$contentLength = (int)$this->request->getHeader('Content-Length');
			if ($postMaxSizeInBytes > 0 && $contentLength > $postMaxSizeInBytes) {
				throw new OpenprojectFileNotUploadedException(
					'File was not uploaded. The request exceeds the maximum post size.',
				);
			}

			$directUploadFile = $this->request->getUploadedFile('file');
			if (empty($directUploadFile)) {
				throw new \InvalidArgumentException(
					'No file is present to upload.'
				);
			}

			$phpFileUploadErrors = [
				UPLOAD_ERR_OK => 'The file was uploaded',
				UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
				UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
				UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded',
				UPLOAD_ERR_NO_FILE => 'No file was uploaded',
				UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
				UPLOAD_ERR_CANT_WRITE => 'Could not write file to disk',
				UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
			];

			if (array_key_exists('error', $directUploadFile) && $directUploadFile['error'] !== UPLOAD_ERR_OK) {
				throw new OpenprojectFileNotUploadedException(
					$phpFileUploadErrors[$directUploadFile['error']],
				);
			}

			$fileName = trim($directUploadFile['name']);
			$this->scanForInvalidCharacters($fileName, "\\/");
			if (Filesystem::isFileBlacklisted($fileName)) {
				throw new ForbiddenException('invalid file name');
			}
			$tmpPath = $directUploadFile['tmp_name'];

			$overwrite = $this->request->getParam('overwrite');
			if (isset($overwrite)) {
				$acceptedOverwriteValues = ['true','false'];
				$overwrite = strtolower($overwrite);
				if (!in_array($overwrite, $acceptedOverwriteValues)) {
					throw new \InvalidArgumentException('invalid overwrite value');
				}
				$overwrite = $overwrite === 'true';
			}

			$tokenInfo = $this->directUploadService->getTokenInfo($token);
			$user = $this->userManager->get($tokenInfo['user_id']);
			$this->userSession->setUser($user);
			$userFolder = $this->rootFolder->getUserFolder($user->getUID());
			$nodes = $userFolder->getById($tokenInfo['folder_id']);
			if (empty($nodes)) {
				throw new NotFoundException('folder not found or not enough permissions');
			}
			/**
			 * @var Folder $folderNode
			 */
			$folderNode = array_shift($nodes);
			if (!$folderNode->isCreatable() && !$overwrite) {
				throw new ForbiddenException('not enough permissions');
			}
			$freeSpace = $folderNode->getFreeSpace();

			// this is also true if we try to overwrite
			// to overwrite a file we need enough free quota for the new data
			// otherwise `putContent()` fails,
			// if freeSpace is smaller than 0 then treat it as a unlimited space
			// and don't throw an error
			if ($directUploadFile['size'] > $freeSpace && $freeSpace >= 0) {
				throw new NotEnoughSpaceException('insufficient quota');
			}
			if ($folderNode->nodeExists($fileName) && $overwrite) {
				/**
				 * @var File $file
				 */
				$file = $folderNode->get($fileName);
				if ($file->getType() === FileInfo::TYPE_FOLDER) {
					throw new Conflict('overwrite is not allowed on non-files');
				}
				if (!$file->isUpdateable()) {
					throw new ForbiddenException('not enough permissions');
				}
				// overwrite the file
				$file->putContent(fopen($tmpPath, 'r'));
				$fileId = $file->getId();
				return new DataResponse([
					'file_name' => $fileName,
					'file_id' => $fileId
				], Http::STATUS_OK);
			} elseif ($folderNode->nodeExists($fileName) && $overwrite === false) {
				// get unique name for duplicate file with number suffix
				$fileName = $folderNode->getNonExistingName($fileName);
			} elseif ($folderNode->nodeExists($fileName)) {
				throw new Conflict('conflict, file name already exists');
			}
			$fileInfo = $folderNode->newFile($fileName, fopen($tmpPath, 'r'));
			$fileId = $fileInfo->getId();
			// setting the creation time for the uploaded file
			$creationTime = (new DateTime())->getTimestamp();
			$fileInfo->getStorage()->getCache()->update($fileId, [
				'creation_time' => $creationTime
			]);
		} catch (OpenprojectUnauthorizedUserException $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage())
			], Http::STATUS_UNAUTHORIZED);
		} catch (NotFoundException | NoUserException $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage())
			], Http::STATUS_NOT_FOUND);
		} catch (InvalidPathException | InvalidArgumentException $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage())
			], Http::STATUS_BAD_REQUEST);
		} catch (ForbiddenException | FileAccessForbiddenException $e) {
			// the FileAccessForbiddenException can occur when we are not allowed to perform certain file operation
			// which is controlled by Nextcloud app File Access Control.
			return new DataResponse([
				'error' => $this->l->t($e->getMessage())
			], Http::STATUS_FORBIDDEN);
		} catch (Conflict $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage()),
			], Http::STATUS_CONFLICT);
		} catch (NotEnoughSpaceException $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage()),
			], Http::STATUS_INSUFFICIENT_STORAGE);
		} catch (OpenprojectFileNotUploadedException $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage())
			], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (InvalidContentException $e) { // files_antivirus throws this exception
			return new DataResponse([
				'error' => $this->l->t($e->getMessage())
			], Http::STATUS_UNSUPPORTED_MEDIA_TYPE);
		} catch (LockedException $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage())
			], Http::STATUS_LOCKED);
		} catch (\Exception $e) {
			return new DataResponse([
				'error' => $this->l->t($e->getMessage())
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse([
			'file_name' => $fileName,
			'file_id' => $fileId
		], Http::STATUS_CREATED);
	}

	/**
	 * @param string $fileName
	 * @param string $invalidChars
	 * @throws InvalidPathException
	 */
	private function scanForInvalidCharacters(string $fileName, string $invalidChars):void {
		if (empty($fileName)) {
			throw new InvalidCharacterInPathException('invalid file name');
		}

		foreach (str_split($invalidChars) as $char) {
			if (strpos($fileName, $char) !== false) {
				throw new InvalidCharacterInPathException('invalid file name');
			}
		}

		$sanitizedFileName = filter_var($fileName, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
		if ($sanitizedFileName !== $fileName) {
			throw new InvalidCharacterInPathException('invalid file name');
		}
	}
}
