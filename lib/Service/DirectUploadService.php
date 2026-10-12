<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use DateTime;
use OC\Files\Node\Folder;
use OC\ForbiddenException;
use OCA\OpenProject\Exception\OpenprojectUnauthorizedUserException;
use OCP\DB\Exception;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use Sabre\DAV\Exception\Conflict;

class DirectUploadService {

	/**
	 * @var IL10N
	 */
	private IL10N $l;

	/**
	 * @var ISecureRandom
	 */
	private ISecureRandom $secureRandom;

	/**
	 * @var IUserManager
	 */
	private IUserManager $userManager;

	/**
	 * @var DatabaseService
	 */
	private DatabaseService $databaseService;

	/** @var string time of token expiration */
	private string $expiryTime = '+1 hour';

	/**
	 * @var IUserSession
	 */
	private IUserSession $userSession;

	/**
	 * @var IRootFolder
	 */
	private IRootFolder $rootFolder;

	public function __construct(
		IUserManager    $userManager,
		IL10N           $l,
		ISecureRandom   $secureRandom,
		DatabaseService $databaseService,
		IUserSession $userSession,
		IRootFolder $rootFolder,
	) {
		$this->l = $l;
		$this->userManager = $userManager;
		$this->secureRandom = $secureRandom;
		$this->databaseService = $databaseService;
		$this->userSession = $userSession;
		$this->rootFolder = $rootFolder;
	}

	/**
	 *
	 * gets token which is used for the direct upload and the expiration time for token
	 *
	 * @return array<string, int|string>
	 */
	public function getTokenForDirectUpload(int $folderId, string $userId): array {
		$token = $this->secureRandom->generate(64, ISecureRandom::CHAR_HUMAN_READABLE);
		$date = new DateTime();
		$createdAt = ($date)->getTimestamp();
		$expiresOn = ($date->modify($this->expiryTime))->getTimestamp();
		try {
			$this->databaseService->setInfoForDirectUpload($token, $folderId, $userId, $createdAt, $expiresOn);
			return [
				'token' => $token,
				'expires_on' => $expiresOn,
			];
		} catch (Exception $e) {
			return [
				'error' => $this->l->t($e->getMessage())
			];
		}
	}

	/**
	 *
	 * gets the information regarding a particular token
	 *
	 * @param string $token
	 *
	 * @return array<mixed>
	 *
	 * @throws OpenprojectUnauthorizedUserException
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function getTokenInfo(string $token): ?array {
		$tokenInfo = $this->databaseService->getTokenInfoFromDB($token);
		$userId = $this->userManager->get($tokenInfo['user_id']);
		if ($tokenInfo['user_id'] === null || !$this->userManager->userExists($tokenInfo['user_id']) || !$userId->isEnabled()) {
			throw new OpenprojectUnauthorizedUserException('unauthorized');
		}
		$currentTime = (new DateTime())->getTimestamp();
		if ($currentTime > $tokenInfo['expires_on']) {
			throw new NotFoundException('invalid token');
		}
		return $tokenInfo;
	}

	public function uploadFile(string $token, mixed $directUploadFile, ?bool $overwrite) : array {
		$tokenInfo = $this->getTokenInfo($token);
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
		$fileName = trim($directUploadFile['name']);
		$tmpPath = $directUploadFile['tmp_name'];
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
			return [
				'file_id' => $file->getId(),
				'file_name' => $fileName,
				'overwritten' => true,
			];
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
		return [
			'file_id' => $fileId,
			'file_name' => $fileName,
			'overwritten' => false,
		];
	}
}
