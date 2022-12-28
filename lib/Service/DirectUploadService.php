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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\NotPermittedException;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Security\ISecureRandom;
use OCP\IUserManager;
use function PHPUnit\Framework\throwException;

class DirectUploadService {
	/**
	 * @var IDBConnection
	 */
	private IDBConnection $db;

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

	/** @var string table name */
	private string $table = 'directUpload';

	/** @var string time of token expiration */
	private string $expiryTime = '+1 hour';

	public function __construct(
		IDBConnection $db,
		IUserManager $userManager,
		IL10N $l,
		ISecureRandom $secureRandom
	) {
		$this->db = $db;
		$this->l = $l;
		$this->userManager =$userManager;
		$this->secureRandom = $secureRandom;
	}

	/**
	 *
	 * Stores the information in the database and returns token which
	 * is used for the direct upload and the expiration time for token
	 *
	 * @return array<string, int|string>
	 */
	public function getTokenForDirectUpload(int $folderId, string $userId): array {
		$query = $this->db->getQueryBuilder();
		$token = $this->secureRandom->generate(64, ISecureRandom::CHAR_HUMAN_READABLE);
		$date = new DateTime();
		$createdAt = ($date)->getTimestamp();
		$expriesOn = ($date->modify($this->expiryTime))->getTimestamp();
		try {
			$query->insert($this->table)
				->values(
					[
						'token' => $query->createNamedParameter($token),
						'folder_id' => $query->createNamedParameter($folderId),
						'user_id' => $query->createNamedParameter($userId),
						'created_at' => $query->createNamedParameter($createdAt),
						'expires_on' => $query->createNamedParameter($expriesOn),
					]
				)
				->executeStatement();
			return [
				'token' => $token,
				'expires_on' => $expriesOn,
			];
		} catch (Exception $e) {
			return [
				'error' => $this->l->t($e->getMessage())
			];
		}
	}

	/**
	 *
	 * Stores the information in the database and returns token which
	 * is used for the direct upload and the expiration time for token
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
		$tokenInfo = $this->getTokenInfoFromDB($token);
		if($tokenInfo['user_id'] === null || !$this->userManager->userExists($tokenInfo['user_id'])){
			throw new NotPermittedException('unauthorized');
		}
		$currentTime = (new DateTime())->getTimestamp();
		if($currentTime > $tokenInfo['expires_on']){
			throw new NotFoundException('Invalid token.');
		}
		return $tokenInfo;
	}

	/**
	 *
	 * Stores the information in the database and returns token which
	 * is used for the direct upload and the expiration time for token
	 *
	 * @param string $token
	 *
	 * @return array<mixed>
	 *
	 * @throws Exception
	 */
	private function getTokenInfoFromDB(string $token): array {
		$userId = '';
		$expiration = null;
		$folderId = null;
		$query = $this->db->getQueryBuilder();
		$query->select('user_id','created_at','expires_on','folder_id')
			->from('directUpload')
			->where(
				$query->expr()->eq('token', $query->createNamedParameter($token, IQueryBuilder::PARAM_STR))
			);
		$req = $query->executeQuery();
		while ($row = $req->fetch()) {
			$userId =  $row['user_id'];
			$expiration = (int) $row['expires_on'];
			$folderId = (int) $row['folder_id'];
		}
		$req->closeCursor();
		$query->resetQueryParts();
		return [
			'user_id' =>$userId,
			'expires_on'=>$expiration,
			'folder_id'=>$folderId
		];
	}
}
