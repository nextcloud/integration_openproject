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
use OC\Files\Node\File;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Security\ISecureRandom;

class DirectUploadService {
	/**
	 * @var IDBConnection
	 */
	private $db;
	/**
	 * @var IRootFolder
	 */
	private $root;

	/**
	 * @var ISecureRandom
	 */
	private $secureRandom;

	/** @var string table name */
	private $table = 'directUpload';
	public function __construct(IDBConnection $db,
								IRootFolder $root,
								IL10N $l,
								ISecureRandom $secureRandom) {
		$this->db = $db;
		$this->root = $root;
		$this->l = $l;
		$this->secureRandom = $secureRandom;
	}


	/**
	 * @throws Exception
	 */
	public function setInfoInDB(int $folderId, string $userId){
		$query = $this->db->getQueryBuilder();
		$token = $this->secureRandom->generate(15, ISecureRandom::CHAR_HUMAN_READABLE);
		$date = new DateTime();
		$createdAt = ($date)->getTimestamp();
		$expriesOn = ($date->modify('+1 hour'))->getTimestamp();
		try{
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
		} catch (Exception $e){
			return [
				'error' => $this->l->t($e->getMessage())
			];
		}


	}
}
