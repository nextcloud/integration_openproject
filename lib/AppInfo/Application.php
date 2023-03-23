<?php
/**
 * Nextcloud - OpenProject
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\OpenProject\AppInfo;

use Closure;
use OCA\Files\Event\LoadSidebar;
use OCA\OpenProject\Listener\BeforeNodeInsideOpenProjectGroupfilderChangedListener;
use OCA\OpenProject\Listener\BeforeUserDeletedListener;
use OCA\OpenProject\Listener\BeforeGroupDeletedListener;
use OCA\OpenProject\Listener\LoadSidebarScript;
use OCA\OpenProject\Listener\UserChangedListener;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\IConfig;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\User\Events\UserChangedEvent;
use OCA\OpenProject\Dashboard\OpenProjectWidget;
use OCA\OpenProject\Search\OpenProjectSearchProvider;

/**
 * Class Application
 *
 * @package OCA\OpenProject\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_openproject';
	public const  OPEN_PROJECT_ENTITIES_NAME = 'OpenProject';
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
		$context->registerDashboardWidget(OpenProjectWidget::class);
		$context->registerSearchProvider(OpenProjectSearchProvider::class);

		// register sidebar tab
		$context->registerEventListener(
			LoadSidebar::class,
			LoadSidebarScript::class
		);
		$context->registerEventListener(
			BeforeNodeDeletedEvent::class, BeforeNodeInsideOpenProjectGroupfilderChangedListener::class
		);
		$context->registerEventListener(
			BeforeNodeRenamedEvent::class, BeforeNodeInsideOpenProjectGroupfilderChangedListener::class
		);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerNavigation']));
		/** @var IEventDispatcher $dispatcher */
		$dispatcher = $context->getAppContainer()->get(IEventDispatcher::class);
		$dispatcher->addListener('OCA\Files::loadAdditionalScripts', function () {
			Util::addScript(Application::APP_ID, 'integration_openproject-fileActions');
		});
		$dispatcher->addServiceListener(BeforeUserDeletedEvent::class, BeforeUserDeletedListener::class);
		$dispatcher->addServiceListener(BeforeGroupDeletedEvent::class, BeforeGroupDeletedListener::class);
		$dispatcher->addServiceListener(UserChangedEvent::class, UserChangedListener::class);
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
}
