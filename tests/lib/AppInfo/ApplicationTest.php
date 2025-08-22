<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\AppInfo;

use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ApplicationTest extends TestCase {
	private IRequest|MockObject $request;
	private IGroupManager|MockObject $groupManager;
	private IUserManager|MockObject $userManager;
	private LoggerInterface|MockObject $logger;

	/***
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	/**
	 * @return array<mixed>
	 */
	public function unrelatedRequestDataProvider(): array {
		return [
			[
				'method' => 'GET',
				'path' => '/cloud/users/testuser/groups',
			],
			[
				'method' => 'DELETE',
				'path' => '/cloud/users/testuser',
			],
		];
	}

	/**
	 * @dataProvider unrelatedRequestDataProvider
	 *
	 * @param string $method
	 * @param string $path
	 *
	 * @return void
	 */
	public function testListenRemoveUserFromGroupRequestUnrelated(string $method, string $path): void {
		$this->request->expects($this->once())->method('getMethod')->willReturn($method);
		$this->request->expects($this->any())->method('getPathInfo')->willReturn($path);
		$this->request->expects($this->never())->method('getParam');
		$this->userManager->expects($this->never())->method('get');

		$app = new Application();
		$app->listenRemoveUserFromGroupRequest(
			$this->request,
			$this->groupManager,
			$this->userManager,
			$this->logger
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function earlyReturnDataProvider(): array {
		return [
			[
				'group' => 'testgroup',
				'user' => 'testuser',
				'hasUser' => true,
				'existsInGroup' => false,
			],
			[
				'group' => Application::OPEN_PROJECT_ENTITIES_NAME,
				'user' => 'testuser',
				'hasUser' => false,
				'existsInGroup' => false,
			],
			[
				'group' => Application::OPEN_PROJECT_ENTITIES_NAME,
				'user' => 'testuser',
				'hasUser' => true,
				'existsInGroup' => true,
			],
		];
	}

	/**
	 * @dataProvider earlyReturnDataProvider
	 *
	 * @param string $groupName
	 * @param string $userName
	 * @param bool $hasUser
	 * @param bool $existsInGroup
	 *
	 * @return void
	 */
	public function testListenRemoveUserFromGroupRequestEarlyReturn(
		string $groupName,
		string $userName,
		bool $hasUser,
		bool $existsInGroup
	): void {
		$userMock = null;
		if ($hasUser) {
			$userMock = $this->createMock(IUser::class);
		}

		$this->request->expects($this->once())->method('getMethod')->willReturn('DELETE');
		$this->request->expects($this->any())->method('getPathInfo')->willReturn("/cloud/users/$userName/groups");
		$this->request->expects($this->once())->method('getParam')->with('groupid')->willReturn($groupName);

		$this->userManager->expects($this->once())->method('get')->with($userName)->willReturn($userMock);

		if ($hasUser && $existsInGroup) {
			$this->groupManager->expects($this->once())->method('isInGroup')
				->with($userName, Application::OPENPROJECT_ALL_GROUP_NAME)
				->willReturn($existsInGroup);
			$this->logger->expects($this->once())
				->method('debug')
				->with($this->stringContains(
					'User already exists in "' . Application::OPENPROJECT_ALL_GROUP_NAME . '" group'
				));
		} else {
			$this->groupManager->expects($this->never())->method('isInGroup');
			$this->logger->expects($this->never())->method('debug');
		}
		$this->logger->expects($this->never())->method('error');

		$app = new Application();
		$app->listenRemoveUserFromGroupRequest(
			$this->request,
			$this->groupManager,
			$this->userManager,
			$this->logger
		);
	}

	/**
	 * @return void
	 */
	public function testListenRemoveUserFromGroupRequestError(): void {
		$userMock = $this->createMock(IUser::class);
		$testUser = 'testuser';

		$this->request->expects($this->once())->method('getMethod')->willReturn('DELETE');
		$this->request->expects($this->any())->method('getPathInfo')->willReturn("/cloud/users/$testUser/groups");
		$this->request->expects($this->once())
			->method('getParam')
			->with('groupid')
			->willReturn(Application::OPEN_PROJECT_ENTITIES_NAME);

		$this->userManager->expects($this->once())->method('get')->with($testUser)->willReturn($userMock);

		$this->groupManager->expects($this->once())->method('isInGroup')
			->with($testUser, Application::OPENPROJECT_ALL_GROUP_NAME)
			->willReturn(false);
		$this->groupManager->expects($this->once())->method('get')
			->with(Application::OPENPROJECT_ALL_GROUP_NAME)
			->willReturn(null);
		$this->logger->expects($this->once())
			->method('debug')
			->with($this->stringContains(
				'User not found in "' . Application::OPENPROJECT_ALL_GROUP_NAME . '" group.'
			));
		$this->logger->expects($this->once())->method('error');
		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessageMatches('/(.*)This group is required before removing users(.*)/');

		$app = new Application();
		$app->listenRemoveUserFromGroupRequest(
			$this->request,
			$this->groupManager,
			$this->userManager,
			$this->logger
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function successDataProvider(): array {
		return [
			[
				'user' => 'testuser',
			],
			[
				'user' => 'Alice\');',
			],
			[
				'user' => '0000',
			],
		];
	}

	/**
	 * @dataProvider successDataProvider
	 *
	 * @param string $testUser
	 *
	 * @return void
	 */
	public function testListenRemoveUserFromGroupRequestSuccess(string $testUser): void {
		$userMock = $this->createMock(IUser::class);
		$groupMock = $this->createMock(IGroup::class);

		$this->request->expects($this->once())->method('getMethod')->willReturn('DELETE');
		$this->request->expects($this->any())->method('getPathInfo')->willReturn("/cloud/users/$testUser/groups");
		$this->request->expects($this->once())
			->method('getParam')
			->with('groupid')
			->willReturn(Application::OPEN_PROJECT_ENTITIES_NAME);

		$this->userManager->expects($this->once())->method('get')->with($testUser)->willReturn($userMock);

		$this->groupManager->expects($this->once())->method('isInGroup')
			->with($testUser, Application::OPENPROJECT_ALL_GROUP_NAME)
			->willReturn(false);
		$groupMock->expects($this->once())->method('addUser')->with($userMock);
		$this->groupManager->expects($this->once())->method('get')
			->with(Application::OPENPROJECT_ALL_GROUP_NAME)
			->willReturn($groupMock);
		$this->logger->expects($this->exactly(2))
			->method('debug');
		$this->logger->expects($this->never())->method('error');

		$app = new Application();
		$app->listenRemoveUserFromGroupRequest(
			$this->request,
			$this->groupManager,
			$this->userManager,
			$this->logger
		);
	}
}
