<?php

/**
 * SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\AppFramework\Services\IInitialState;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OpenProjectReferenceListenerTest extends TestCase {
	private OpenProjectReferenceListener $listener;
	private MockObject|IInitialState $initialState;
	private MockObject|IAppConfig $appConfig;
	private MockObject|OpenProjectAPIService $openProjectAPIService;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->openProjectAPIService = $this->createMock(OpenProjectAPIService::class);
		$this->listener = new OpenProjectReferenceListener(
			$this->initialState,
			$this->appConfig,
			$this->openProjectAPIService,
			'testUser'
		);
	}

	/**
	 * @return array
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
		$this->appConfig->method('getValueString')
			->willReturnCallback(
				fn (string $app, string $key, string $default = ''):string => $values[$key] ?? $default
			);
		$this->openProjectAPIService->method('getAccessToken')->willReturn('some-token');

		$initStateCalls = [];
		$this->initialState->method('provideInitialState')
			->willReturnCallback(function ($state, $config) use (&$initStateCalls) {
				$initStateCalls[] = [$state, $config];
			});

		$expectedCalls = [
			['admin-config', [
				'isAdminConfigOk' => false,
				'authMethod' => $authMethod,
			],],
			['openproject-url', $openprojectUrl],
		];
		$this->listener->handle($this->createMock(RenderReferenceEvent::class));
		$this->assertEqualsCanonicalizing($expectedCalls, $initStateCalls);
	}

	/**
	 * @return void
	 */
	public function testHandleOidcWithoutAccessToken() {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(
				fn (string $app, string $key, string $default = ''): string => $key === 'authorization_method'
					? Application::AUTH_METHOD_OIDC
					: $default
			);
		$this->openProjectAPIService
			->method('getAccessToken')
			->willReturn('');

		$this->initialState
			->expects($this->never())
			->method('provideInitialState');

		$this->listener->handle($this->createMock(RenderReferenceEvent::class));
	}
}
