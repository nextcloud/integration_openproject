<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use DateTime;
use OCA\OpenProject\Exception\OpenprojectUnauthorizedUserException;
use OCP\DB\Exception;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;

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

	public function __construct(
		IUserManager $userManager,
		IL10N $l,
		ISecureRandom $secureRandom,
		DatabaseService $databaseService
	) {
		$this->l = $l;
		$this->userManager = $userManager;
		$this->secureRandom = $secureRandom;
		$this->databaseService = $databaseService;
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
}
