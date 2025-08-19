<?php

/**
 * SPDX-FileCopyrightText: 2021-2024 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2021-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\AppInfo;

use Closure;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files\Event\LoadSidebar;
use OCA\OpenProject\Capabilities;
use OCA\OpenProject\Dashboard\OpenProjectWidget;
use OCA\OpenProject\Listener\BeforeGroupDeletedListener;
use OCA\OpenProject\Listener\BeforeNodeInsideOpenProjectGroupfilderChangedListener;
use OCA\OpenProject\Listener\BeforeUserDeletedListener;
use OCA\OpenProject\Listener\LoadAdditionalScriptsListener;
use OCA\OpenProject\Listener\LoadSidebarScript;
use OCA\OpenProject\Listener\OpenProjectReferenceListener;
use OCA\OpenProject\Listener\TermsOfServiceEventListener;
use OCA\OpenProject\Listener\UserChangedListener;
use OCA\OpenProject\Reference\WorkPackageReferenceProvider;
use OCA\OpenProject\Search\OpenProjectSearchProvider;
use OCA\TermsOfService\Events\SignaturesResetEvent;
use OCA\TermsOfService\Events\TermsCreatedEvent;
use OCP\App\Events\AppEnableEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\UserChangedEvent;
use Psr\Log\LoggerInterface;

/**
 * Class Application
 *
 * @package OCA\OpenProject\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_openproject';
	public const OPEN_PROJECT_ENTITIES_NAME = 'OpenProject';
	public const OPENPROJECT_ALL_GROUP_NAME = 'OpenProjectAll';
	public const  OPENPROJECT_API_SCOPES = ['api_v3'];

	/**
	 * @var mixed
	 */
	private $config;

	/**
	 * Constructor
	 *
	 * @param array<string> $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->config = $container->get(IConfig::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerCapability(Capabilities::class);
		$context->registerDashboardWidget(OpenProjectWidget::class);
		$context->registerSearchProvider(OpenProjectSearchProvider::class);

		// register sidebar tab
		$context->registerEventListener(
			LoadSidebar::class,
			LoadSidebarScript::class
		);
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);

		$context->registerEventListener(
			BeforeNodeDeletedEvent::class, BeforeNodeInsideOpenProjectGroupfilderChangedListener::class
		);
		$context->registerEventListener(
			BeforeNodeRenamedEvent::class, BeforeNodeInsideOpenProjectGroupfilderChangedListener::class
		);

		if (version_compare($this->config->getSystemValueString('version', '0.0.0'), '26.0.0', '>=')) {
			$context->registerReferenceProvider(WorkPackageReferenceProvider::class);
			// RenderReferenceEvent is dispatched when we know the smart picker or link previews will be used
			// so we need to load our scripts at this moment
			$context->registerEventListener(RenderReferenceEvent::class, OpenProjectReferenceListener::class);
		}
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'listenUserRemoveRequest']));
		$context->injectFn(Closure::fromCallable([$this, 'registerNavigation']));
		/** @var IEventDispatcher $dispatcher */
		$dispatcher = $context->getAppContainer()->get(IEventDispatcher::class);
		$dispatcher->addServiceListener(BeforeUserDeletedEvent::class, BeforeUserDeletedListener::class);
		$dispatcher->addServiceListener(BeforeGroupDeletedEvent::class, BeforeGroupDeletedListener::class);
		$dispatcher->addServiceListener(UserChangedEvent::class, UserChangedListener::class);
		/** @psalm-suppress InvalidArgument AppEnableEvent event is not in stable25 so making psalm not complain*/
		$dispatcher->addServiceListener(AppEnableEvent::class, TermsOfServiceEventListener::class);
		/** @psalm-suppress InvalidArgument TermsCreatedEvent event is not yet registered in terms_of_service app, so making psalm not complain */
		$dispatcher->addServiceListener(TermsCreatedEvent::class, TermsOfServiceEventListener::class);
		/** @psalm-suppress InvalidArgument SignaturesResetEvent event is not yet registered in terms_of_service app, so making psalm not complain*/
		$dispatcher->addServiceListener(SignaturesResetEvent::class, TermsOfServiceEventListener::class);
	}

	public function registerNavigation(IUserSession $userSession): void {
		$user = $userSession->getUser();
		if ($user !== null) {
			$userId = $user->getUID();
			$container = $this->getContainer();

			if ($this->config->getUserValue(
				$userId,
				self::APP_ID,
				'navigation_enabled',
				$this->config->getAppValue(Application::APP_ID, 'default_enable_navigation', '0')) === '1') {
				$openprojectUrl = $this->config->getAppValue(Application::APP_ID, 'openproject_instance_url', '');
				if ($openprojectUrl !== '') {
					$container->get(INavigationManager::class)->add(function () use ($container, $openprojectUrl) {
						$urlGenerator = $container->get(IURLGenerator::class);
						$l10n = $container->get(IL10N::class);
						return [
							'id' => self::APP_ID,

							'order' => 10,

							// the route that will be shown on startup
							'href' => $openprojectUrl,

							// the icon that will be shown in the navigation
							// this file needs to exist in img/
							'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),

							// the title of your application. This will be used in the
							// navigation or on the settings page of your app
							'name' => $l10n->t('OpenProject'),
						];
					});
				}
			}
		}
	}

	/**
	 * Listen to remove user from group API requests
	 * and if the request is to remove user from OpenProject group
	 * check that the user is in OpenProjectAll group and add to it if not
	 * then continue the request
	 *
	 * @param IRequest $request
	 * @param IGroupManager $groupManager
	 * @param IUserManager $userManager
	 * @param LoggerInterface $logger
	 *
	 * @return void
	 * @throws OCSBadRequestException
	 */
	public function listenUserRemoveRequest(
		IRequest $request,
		IGroupManager $groupManager,
		IUserManager $userManager,
		LoggerInterface $logger,
	): void {
		// url path pattern to remove user from group
		// e.g: /cloud/users/{userid}/groups   (path returned by $request->getPathInfo())
		$urlPattern = '/.*\/users\/([^\/]+)\/groups$/';
		if ($request->getMethod() === 'DELETE' && \preg_match($urlPattern, $request->getPathInfo())) {
			$fromGroup = $request->getParam('groupid');
			$userToRemove = \preg_replace($urlPattern, '$1', $request->getPathInfo());
			if (!$fromGroup || !$userToRemove) {
				return;
			}

			$user = $userManager->get($userToRemove);
			if ($user === null) {
				return;
			}

			// Only handle OpenProject group
			if ($fromGroup !== Application::OPEN_PROJECT_ENTITIES_NAME) {
				return;
			}

			if (!$groupManager->isInGroup($userToRemove, Application::OPENPROJECT_ALL_GROUP_NAME)) {
				$logger->debug('User not found in "' . Application::OPENPROJECT_ALL_GROUP_NAME . '" group.');
				$allGroup = $groupManager->get(Application::OPENPROJECT_ALL_GROUP_NAME);

				if ($allGroup === null) {
					$errorMessage = 'Group "' . Application::OPENPROJECT_ALL_GROUP_NAME . '" not found.' .
						' This group is required before removing users from "' . Application::OPEN_PROJECT_ENTITIES_NAME . '" group.';
					$logger->error($errorMessage);
					throw new OCSBadRequestException($errorMessage);
				}

				$allGroup->addUser($user);
				$logger->debug("User '$userToRemove' added to '" . Application::OPENPROJECT_ALL_GROUP_NAME . "' group.");
			} else {
				$logger->debug('User already exists in "' . Application::OPENPROJECT_ALL_GROUP_NAME . '" group');
			}
		}
	}
}
