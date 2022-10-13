<?php
/**
 * Nextcloud - openproject
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

namespace OCA\OpenProject\Notification;

use InvalidArgumentException;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IDismissableNotifier;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCA\OpenProject\AppInfo\Application;

class Notifier implements INotifier, IDismissableNotifier {

	/** @var IFactory */
	protected $factory;

	/** @var IUserManager */
	protected $userManager;

	/** @var INotificationManager */
	protected $notificationManager;

	/** @var IURLGenerator */
	protected $url;

	/**
	 * @param IFactory $factory
	 * @param IUserManager $userManager
	 * @param INotificationManager $notificationManager
	 * @param IURLGenerator $urlGenerator
	 */
	public function __construct(IFactory $factory,
								IUserManager $userManager,
								INotificationManager $notificationManager,
								IURLGenerator $urlGenerator) {
		$this->factory = $factory;
		$this->userManager = $userManager;
		$this->notificationManager = $notificationManager;
		$this->url = $urlGenerator;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'integration_openproject';
	}
	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->factory->get('integration_openproject')->t('OpenProject');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'integration_openproject') {
			// Not my app => throw
			throw new InvalidArgumentException();
		}

		$l = $this->factory->get('integration_openproject', $languageCode);

		switch ($notification->getSubject()) {
		case 'new_open_tickets':
			$p = $notification->getSubjectParameters();
			$nbNotifications = (int) ($p['nbNotifications'] ?? 0);
			$content = $l->t('OpenProject activity');
			$richSubjectInstance = [
				'type' => 'file',
				'id' => 0,
				'name' => $p['link'],
				'path' => '',
				'link' => $p['link'],
			];

			$notification->setParsedSubject($content)
				->setParsedMessage('--')
				->setLink($p['link'] ?? '')
				->setRichMessage(
					$l->n('You have %s new notification in {instance}', 'You have %s new notifications in {instance}', $nbNotifications, [$nbNotifications]),
					[
						'instance' => $richSubjectInstance,
					]
				)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg')));
			return $notification;

		default:
			// Unknown subject => Unknown notification => throw
			throw new InvalidArgumentException();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function dismissNotification(INotification $notification): void {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new \InvalidArgumentException('Unhandled app');
		}

		// do some stuff ^^
	}
}
