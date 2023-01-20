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

use OC\Files\Filesystem;
use OC\Files\Node\Folder;
use OC\User\NoUserException;
use InvalidArgumentException;
use OC\ForbiddenException;
use OCA\OpenProject\Exception\OpenprojectFileNotUploadedException;
use \OCP\AppFramework\ApiController;
use OCP\Files\File;
use OCP\Files\InvalidCharacterInPathException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\NotFoundException;
use OCA\OpenProject\Service\DirectUploadService;
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


	public function __construct(
		string $appName,
		IRequest $request,
		IRootFolder $rootFolder,
		IUserSession $userSession,
		IUserManager $userManager,
		DirectUploadService $directUploadService,
		?string $userId
	) {
		parent::__construct($appName, $request, 'POST');
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
			$fileId = null;
			$directUploadFile = $this->request->getUploadedFile('file');
			if (empty($directUploadFile['tmp_name']) || $directUploadFile['error'] === 1) {
				throw new OpenprojectFileNotUploadedException(
					'File was not uploaded. upload_max_filesize exceeded?'
				);
			}
			$tmpPath = $directUploadFile['tmp_name'];
			$fileName = trim($directUploadFile['name']);
			if (Filesystem::isFileBlacklisted($fileName)) {
				throw new ForbiddenException('invalid file name');
			}
			$overwrite = $this->request->getParam('overwrite');
			if (isset($overwrite)) {
				$acceptedOverwriteValues = ['true','false'];
				$overwrite = strtolower($overwrite);
				if (in_array($overwrite, $acceptedOverwriteValues)) {
					$overwrite = $overwrite === 'true';
				} else {
					throw new InvalidArgumentException('invalid overwrite value');
				}
			} else {
				$overwrite = null;
			}

			if (strlen($token) !== 64 || !preg_match('/^[a-zA-Z0-9]*/', $token)) {
				throw new NotFoundException('invalid token');
			}
			$this->scanForInvalidCharacters($fileName, "\\/");
			$tokenInfo = $this->directUploadService->getTokenInfo($token);
			$user = $this->userManager->get($tokenInfo['user_id']);
			$userFolder = $this->rootFolder->getUserFolder($user->getUID());
			$nodes = $userFolder->getById($tokenInfo['folder_id']);
			if (empty($nodes)) {
				throw new NotFoundException('folder not found or not enough permissions');
			}
			/**
			 * @var Folder $folderNode
			 */
			$folderNode = array_shift($nodes);
			$freeSpace = $folderNode->getFreeSpace();

			// this is also true if we try to overwrite
			// to overwrite a file we need enough free quota for the new data
			// otherwise `putContent()` fails
			if ($directUploadFile['size'] > $freeSpace) {
				throw new NotEnoughSpaceException('insufficient quota');
			}
			if (
				$folderNode->isCreatable()
			) {
				// @phpstan-ignore-next-line
				if ($folderNode->nodeExists($fileName) && $overwrite) {
					/**
					 * @var File $file
					 */
					$file = $folderNode->get($fileName); // @phpstan-ignore-line
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
				} // @phpstan-ignore-next-line
				elseif ($folderNode->nodeExists($fileName) && $overwrite === false) {
					// get unique name for duplicate file with number suffix
					$fileName = $folderNode->getNonExistingName($fileName); // @phpstan-ignore-line
				}
				// @phpstan-ignore-next-line
				elseif ($folderNode->nodeExists($fileName)) {
					throw new Conflict('conflict, file name already exists');
				}
				$fileInfo = $folderNode->newFile($fileName, fopen($tmpPath, 'r')); // @phpstan-ignore-line
				$fileId = $fileInfo->getId();
			}
		} catch (NotPermittedException $e) {
			return new DataResponse([
				'error' => $e->getMessage()
			], Http::STATUS_UNAUTHORIZED);
		} catch (NotFoundException | NoUserException $e) {
			return new DataResponse([
				'error' => $e->getMessage()
			], Http::STATUS_NOT_FOUND);
		} catch (InvalidPathException | InvalidArgumentException $e) {
			return new DataResponse([
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		} catch (ForbiddenException $e) {
			return new DataResponse([
				'error' => $e->getMessage()
			], Http::STATUS_FORBIDDEN);
		} catch (Conflict $e) {
			return new DataResponse([
				'error' => $e->getMessage(),
			], Http::STATUS_CONFLICT);
		} catch (NotEnoughSpaceException $e) {
			return new DataResponse([
				'error' => $e->getMessage(),
			], Http::STATUS_INSUFFICIENT_STORAGE);
		} catch (OpenprojectFileNotUploadedException $e) {
			return new DataResponse([
				'error' => $e->getMessage(),
				'upload_limit' => \OC_Helper::uploadLimit()
			], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (Exception $e) {
			return new DataResponse([
				'error' => $e->getMessage()
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
