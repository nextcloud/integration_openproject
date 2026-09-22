<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Service;

use OC\Files\Node\File;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IParameter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\IUserFolder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class DirectDownloadServiceTest extends TestCase {
	private const USER_ID = 'test';

	/**
	 * @return mixed
	 */
	public function getFolderMock(): mixed {
		if (interface_exists(IUserFolder::class)) {
			return $this->createMock(IUserFolder::class);
		}
		return $this->createMock(Folder::class);
	}

	/**
	 * @param array $rows
	 *
	 * @return \Generator
	 */
	public function dbTableGenerator($rows = []) {
		foreach ($rows as $row) {
			yield $row;
		}
	}

	/**
	 * @return array
	 */
	public function directDownloadDataProvider(): array {
		return [
			"existent file" => [[
				'file_id' => 1,
				'expiration' => time() + 3600,
				'user_id' => self::USER_ID,
			]],
			"non-existent file" => [[]],
		];
	}

	/**
	 * @dataProvider directDownloadDataProvider
	 *
	 * @return void
	 */
	public function testGetDirectDownloadFile($dbRows) {
		$token = '1234abcd';

		$qExpBuilder = $this->createMock(IExpressionBuilder::class);
		$qExpBuilder
			->expects($this->once())
			->method('eq')
			->with('token', $this->isInstanceOf(IParameter::class));

		$tableGenerator = $this->dbTableGenerator([$dbRows]);
		$resultMock = $this->createMock(IResult::class);
		$resultMock
			->method('fetch')
			->willReturnCallback(function () use ($tableGenerator) {
				$row = $tableGenerator->current();
				$tableGenerator->next();
				return $row;
			});
		$resultMock
			->expects($this->once())
			->method('closeCursor');

		$queryBuilderMock = $this->createMock(IQueryBuilder::class);
		$queryBuilderMock
			->expects($this->once())
			->method('select')
			->with('id', 'user_id', 'file_id', 'token', 'expiration')
			->willReturnSelf();
		$queryBuilderMock
			->expects($this->once())
			->method('from')
			->with('directlink')
			->willReturnSelf();
		$queryBuilderMock
			->expects($this->once())
			->method('where')
			->willReturnSelf();
		$queryBuilderMock
			->expects($this->once())
			->method('expr')
			->willReturn($qExpBuilder);
		$queryBuilderMock
			->expects($this->once())
			->method('createNamedParameter')
			->with($token, IQueryBuilder::PARAM_STR)
			->willReturn($this->createMock(IParameter::class));
		$queryBuilderMock
			->expects($this->once())
			->method('executeQuery')
			->willReturn($resultMock);
		$queryBuilderMock
			->expects($this->once())
			->method('resetQueryParts');

		$dbMock = $this->createMock(IDBConnection::class);
		$dbMock->method('getQueryBuilder')
			->willReturn($queryBuilderMock);

		$fileMock = $this->createMock(File::class);
		$folderMock = $this->getFolderMock();
		$folderMock
			->method('getById')
			->with(1)
			->willReturn([$fileMock]);

		$rootFolderMock = $this->createMock(IRootFolder::class);
		$rootFolderMock
			->method('getUserFolder')
			->willReturn($folderMock);

		$directDownloadService = new DirectDownloadService(
			$dbMock,
			$rootFolderMock,
		);

		$result = $directDownloadService->getDirectDownloadFile($token);

		if (isset($dbRows) && !empty($dbRows)) {
			$this->assertInstanceOf(File::class, $result);
		} else {
			$this->assertNull($result);
		}
	}
}
