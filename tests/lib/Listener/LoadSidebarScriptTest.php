<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Listener;

use OCA\Files\Event\LoadSidebar;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LoadSidebarScriptTest extends TestCase {
	private LoadSidebarScript $listener;
	private MockObject|IAppConfig $appConfig;
	private MockObject|IUserConfig $userConfig;
	private MockObject|IInitialState $initialState;
	private MockObject|IAppManager $appManager;
	private MockObject|OpenProjectAPIService $openProjectService;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$userSession = $this->createMock(IUserSession::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->openProjectService = $this->createMock(OpenProjectAPIService::class);
		$this->listener = new LoadSidebarScript(
			$this->initialState,
			$this->appConfig,
			$this->userConfig,
			$userSession,
			$this->appManager,
			$this->openProjectService,
			'testUser'
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function dataTestHandle(): array {
		return [
			[
				'authMethod' => Application::AUTH_METHOD_OAUTH,
				'openprojectUrl' => 'http://local.test',
			],
			[
				'authMethod' => Application::AUTH_METHOD_OIDC,
				'openprojectUrl' => 'http://local.test',
			],
			[
				'authMethod' => '',
				'openprojectUrl' => '',
			],
		];
	}

	/**
	 * @dataProvider dataTestHandle
	 *
	 * @param string $authMethod
	 * @param string $openprojectUrl
	 *
	 * @return void
	 */
	public function testHandle(string $authMethod, string $openprojectUrl) {
		$values = [
			'authorization_method' => $authMethod,
			'openproject_instance_url' => $openprojectUrl,
		];
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(
				fn (string $app, string $key, string $default): string => $values[$key] ?? $default
			);
		$this->appManager
			->method('isEnabledForUser')
			->willReturn(true);

		$initStateCalls = [];
		$this->initialState
			->method('provideInitialState')
			->willReturnCallback(function ($state, $config) use (&$initStateCalls) {
				$initStateCalls[] = [$state, $config];
			});

		$expectedCalls = [
			['authorization_method', $authMethod],
			['openproject-url', $openprojectUrl],
			['admin_config_ok', false],
			['oauth-connection-result', null],
			['oauth-connection-error-message', null],
		];

		$this->listener->handle($this->createMock(LoadSidebar::class));
		$this->assertEqualsCanonicalizing($expectedCalls, $initStateCalls);
	}
}
