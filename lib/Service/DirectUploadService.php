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

namespace OCA\OpenProject\Service;

use DateTime;
use OCP\Files\NotFoundException;
use OCP\DB\Exception;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\Security\ISecureRandom;
use OCP\IUserManager;

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
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function getTokenInfo(string $token): ?array {
		$tokenInfo = $this->databaseService->getTokenInfoFromDB($token);
		$userId = $this->userManager->get($tokenInfo['user_id']);
		if ($tokenInfo['user_id'] === null || !$this->userManager->userExists($tokenInfo['user_id']) || !$userId->isEnabled()) {
			throw new NotPermittedException('unauthorized');
		}
		$currentTime = (new DateTime())->getTimestamp();
		if ($currentTime > $tokenInfo['expires_on']) {
			throw new NotFoundException('Invalid token.');
		}
		return $tokenInfo;
	}
}
