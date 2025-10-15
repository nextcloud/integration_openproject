<?php

/**
 * SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use OCA\OpenProject\AppInfo\Application;
use OCP\DB\Exception;
use PHPUnit\Framework\TestCase;

class DatabaseServiceTest extends TestCase {
	/**
	 * @var DatabaseService
	 */
	private $databaseService;
	private const TABLE_NAME = 'direct_upload';

	/**
	 * createdAt and expiresOn info is not included since it requires current timestamp
	 *
	 * @var array <mixed>
	 */
	private array $unexpiredDirectUploadInfo = [
		[
			"token" => 'unExpiredToken1',
			"folderId" => 1,
			"userId" => 'u1',
		],
		[
			"token" => 'unExpiredToken2',
			"folderId" => 1,
			"userId" => 'u1',
		],
		[
			"token" => 'unExpiredToken3',
			"folderId" => 1,
			"userId" => 'u1',
		],

	];

	/**
	 * createdAt and expiresOn info is not included since it requires current timestamp
	 *
	 * @var array <mixed>
	 */
	private array $expiredDirectUploadInfo = [
		[
			"token" => 'expiredToken1',
			"folderId" => 1,
			"userId" => 'u1',
		],
		[
			"token" => 'expiredToken2',
			"folderId" => 1,
			"userId" => 'u1',
		],
		[
			"token" => 'expiredToken3',
			"folderId" => 1,
			"userId" => 'u1',
		],

	];

	protected function setUp(): void {
		$app = new Application();
		$c = $app->getContainer();

		/** @var DatabaseService $databaseService */
		$databaseService = $c->get(DatabaseService::class);
		$this->databaseService = $databaseService;
	}

	/**
	 * @throws Exception
	 */
	protected function tearDown(): void {
		$query = $this->databaseService->db->getQueryBuilder();
		$query->delete(self::TABLE_NAME);
		$query->executeStatement();
	}

	/**
	 * @throws Exception
	 * @return array<mixed>
	 */
	public function getAllTokensFromTable(): array {
		$tokens = [];
		$query = $this->databaseService->db->getQueryBuilder();
		$query->select('token')
			->from(self::TABLE_NAME);
		$req = $query->executeQuery();
		while ($row = $req->fetch()) {
			$tokens[] = $row['token'];
		}
		return $tokens;
	}


	/**
	 * @return void
	 *
	 * @throws Exception
	 */
	public function testDeleteSingleExpiredToken(): void {
		$this->databaseService->setInfoForDirectUpload("expiredToken", 1, "u1", time(), time() - 1000);
		$this->databaseService->deleteExpiredTokens();
		$token = $this->getAllTokensFromTable();
		self::assertEquals(0, sizeof($token));
	}

	/**
	 * @return void
	 *
	 * @throws Exception
	 */
	public function testDeleteMultipleExpiredTokens(): void {
		foreach ($this->expiredDirectUploadInfo as $info) {
			$this->databaseService->setInfoForDirectUpload($info['token'], $info['folderId'], $info['userId'], time(), time() - 1000);
		}
		$this->databaseService->deleteExpiredTokens();
		$tokens = $this->getAllTokensFromTable();
		self::assertEquals(0, sizeof($tokens));
	}

	/**
	 * @return void
	 *
	 * @throws Exception
	 */
	public function testDeleteSingleUnExpiredToken(): void {
		$this->databaseService->setInfoForDirectUpload("unExpiredToken", 1, "u1", time(), time() + 1000);
		$this->databaseService->deleteExpiredTokens();
		$token = $this->getAllTokensFromTable();
		self::assertSame('unExpiredToken', $token[0]);
	}

	/**
	 * @return void
	 *
	 * @throws Exception
	 */
	public function testDeleteMultipleUnExpiredTokens(): void {
		foreach ($this->unexpiredDirectUploadInfo as $info) {
			$this->databaseService->setInfoForDirectUpload($info['token'], $info['folderId'], $info['userId'], time(), time() + 1000);
		}
		$this->databaseService->deleteExpiredTokens();
		$tokens = $this->getAllTokensFromTable();
		self::assertEquals(sizeof($this->unexpiredDirectUploadInfo), sizeof($tokens));
		foreach ($this->unexpiredDirectUploadInfo as $info) {
			self::assertContains($info['token'], $tokens);
		}
	}

	/**
	 * @return void
	 *
	 * @throws Exception
	 */
	public function testDeleteMultipleExpiredAndUnexpiredTokens(): void {
		foreach ($this->expiredDirectUploadInfo as $info) {
			$this->databaseService->setInfoForDirectUpload($info['token'], $info['folderId'], $info['userId'], time(), time() - 1000);
		}
		foreach ($this->unexpiredDirectUploadInfo as $info) {
			$this->databaseService->setInfoForDirectUpload($info['token'], $info['folderId'], $info['userId'], time(), time() + 1000);
		}
		$this->databaseService->deleteExpiredTokens();
		$tokens = $this->getAllTokensFromTable();
		self::assertEquals(sizeof($this->unexpiredDirectUploadInfo), sizeof($tokens));
		foreach ($this->unexpiredDirectUploadInfo as $info) {
			self::assertContains($info['token'], $tokens);
		}
	}
}
