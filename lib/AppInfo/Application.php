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
use OCA\OpenProject\Listener\TokenObtainedEventListener;
use OCA\OpenProject\Service\TokenService;
use OCA\TermsOfService\Events\SignaturesResetEvent;
use OCA\TermsOfService\Events\TermsCreatedEvent;
use OCA\UserOIDC\Event\TokenObtainedEvent;
use OCP\App\Events\AppEnableEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\IEventDispatcher;

use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\IConfig;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\UserChangedEvent;

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
		$context->injectFn(Closure::fromCallable([$this, 'registerNavigation']));
		$context->injectFn(Closure::fromCallable([$this, 'tokenRefreshWhenWithAnyBackgroundAction']));
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
        $dispatcher->addServiceListener(TokenObtainedEvent::class, TokenObtainedEventListener::class);
	}

    /**
     * @throws \JsonException
     */
    public function tokenRefreshWhenWithAnyBackgroundAction(IUserSession $userSession, TokenService $tokenService, IConfig $config,): void {
        $user = $userSession->getUser();
        if($user === null) {
            return;
        }
        $userId = $user->getUID();
        $token = $tokenService->getToken();
        if ($token === null) {
            // if we don't have a token but we had one once,
            // it means the session (where we store the token) has died
            // so we need to reauthenticate
            if ($config->getUserValue($userId, self::APP_ID, 'had_token_once', '0') === '1') {
                $tokenService->reauthenticate();
            }
            return;
        }
        // remember that this user had a token once
        $config->setUserValue($userId, self::APP_ID, 'had_token_once', '1');
        if($token->isExpired()) {
            $tokenService->reauthenticate();
        }
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
