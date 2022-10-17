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
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCA\OpenProject\AppInfo\Application;

class Notifier implements INotifier {

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
		case 'op_notification':
			$p = $notification->getSubjectParameters();

			// see https://github.com/nextcloud/server/issues/1706 for docs
			$richSubjectInstance = [
				'type' => 'file',
				'id' => 0,
				'name' => $p['link'],
				'path' => '',
				'link' => $p['link'],
			];
			$message = $p['projectTitle'] . ' - ';
			foreach ($p['reasons'] as $reason) {
				$message .= $reason . ',';
			}
			$message = rtrim($message, ',');
			$message .= ' ' . $l->t('by') . ' ';

			foreach ($p['actors'] as $actor) {
				$message .= $actor . ',';
			}
			$message = rtrim($message, ',');
			$markAsReadAction = $notification->createAction();
			$markAsReadAction->setLabel('mark_as_read')
			->setParsedLabel($l->t('Mark as read'))
			->setPrimary(true)
			->setLink($this->url->linkToRouteAbsolute(
				'integration_openproject.openProjectAPI.markNotificationAsRead',
				['workpackageId' => $p['wpId']]),
				'DELETE'
			);

			$notification->setParsedSubject('(' . $p['count']. ') ' . $p['resourceTitle'])
				->setParsedMessage('--')
				->setLink($p['link'] ?? '')
				->setRichMessage(
					$message,
					[
						'instance' => $richSubjectInstance,
					]
				)
				->addParsedAction($markAsReadAction)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg')));
			return $notification;

		default:
			// Unknown subject => Unknown notification => throw
			throw new InvalidArgumentException();
		}
	}
}
