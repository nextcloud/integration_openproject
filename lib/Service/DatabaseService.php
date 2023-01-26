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

use OCP\DB\Exception;
use OCP\IDBConnection;

class DatabaseService {
	/**
	 * @var IDBConnection
	 */
	private IDBConnection $db;


	/** @var string table name */
	private string $table = 'direct_upload';


	public function __construct(
		IDBConnection $db
	) {
		$this->db = $db;
	}

	/**
	 *
	 * Stores the information for direct upload in the database
	 *
	 * @throws Exception
	 */
	public function setInfoForDirectUpload(string $token, int $folderId, string $userId, int $createdAt, int $expiresOn): void {
		$query = $this->db->getQueryBuilder();
		$query->insert($this->table)
			->values(
				[
					'token' => $query->createNamedParameter($token),
					'folder_id' => $query->createNamedParameter($folderId),
					'user_id' => $query->createNamedParameter($userId),
					'created_at' => $query->createNamedParameter($createdAt),
					'expires_on' => $query->createNamedParameter($expiresOn),
				]
			)
			->executeStatement();
	}


	/**
	 *
	 * Gets information about the token from the database
	 *
	 * @param string $token
	 *
	 * @return array<mixed>
	 *
	 * @throws Exception
	 */
	public function getTokenInfoFromDB(string $token): array {
		$userId = '';
		$expiresOn = null;
		$folderId = null;
		$query = $this->db->getQueryBuilder();
		$query->select('user_id', 'created_at', 'expires_on', 'folder_id')
			->from($this->table)
			->where(
				$query->expr()->eq('token', $query->createNamedParameter($token))
			);
		$req = $query->executeQuery();
		while ($row = $req->fetch()) {
			$userId = $row['user_id'];
			$expiresOn = (int) $row['expires_on'];
			$folderId = (int) $row['folder_id'];
		}
		$req->closeCursor();
		$query->resetQueryParts();
		$this->deleteToken($token);
		return [
			'user_id' => $userId,
			'expires_on' => $expiresOn,
			'folder_id' => $folderId
		];
	}

	/**
	 *
	 * @throws Exception
	 */
	public function deleteExpiredTokens(): void {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->table)
			->where(
				$query->expr()->lt('expires_on', $query->createNamedParameter(time()))
			);
		$query->execute();
	}

	/**
	 * deletes the token from the table
	 * @param string $token
	 *
	 * @throws Exception
	 */
	public function deleteToken(string $token): void {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->table)
			->where(
				$query->expr()->eq('token', $query->createNamedParameter($token))
			);

		$query->execute();
	}
}
