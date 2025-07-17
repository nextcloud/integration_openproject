<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\IConfig;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class LoadSidebarScriptTest extends TestCase {
	private LoadSidebarScript $listener;
	private IConfig $config;
	private IInitialState $initialState;
	private OpenProjectAPIService $openProjectService;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$userSession = $this->createMock(IUserSession::class);
		$appManager = $this->createMock(IAppManager::class);
		$this->openProjectService = $this->createMock(OpenProjectAPIService::class);
		$this->listener = new LoadSidebarScript(
			$this->initialState,
			$this->config,
			$userSession,
			$appManager,
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
				'authMethod' => OpenProjectAPIService::AUTH_METHOD_OAUTH,
				'accessToken' => '',
			],
			[
				'authMethod' => OpenProjectAPIService::AUTH_METHOD_OIDC,
				'accessToken' => 'access-token',
			],
			[
				'authMethod' => OpenProjectAPIService::AUTH_METHOD_OIDC,
				'accessToken' => '',
			],
			[
				'authMethod' => '',
				'accessToken' => '',
			],
		];
	}

	/**
	 * @dataProvider dataTestHandle
	 *
	 * @param string $authMethod
	 * @param string $accessToken
	 *
	 * @return void
	 */
	public function testHandle(string $authMethod, string $accessToken) {
		$this->config
			->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'authorization_method', '', $authMethod],
			]);
		$this->openProjectService->method('getOIDCToken')
			->willReturn($accessToken);

		if ($authMethod === OpenProjectAPIService::AUTH_METHOD_OIDC && $accessToken) {
			$this->openProjectService->expects($this->once())
			->method('setUserInfoForOidcBasedAuth')
			->with('testUser');
		} else {
			$this->openProjectService->expects($this->never())
				->method('setUserInfoForOidcBasedAuth');
		}	

		$this->listener->handle($this->createMock(Event::class));
	}
}
