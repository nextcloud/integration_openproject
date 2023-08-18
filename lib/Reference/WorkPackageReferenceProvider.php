<?php

/**
 * @copyright Copyright (c) 2023 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\OpenProject\Reference;

use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\OpenProject\AppInfo\Application;
use OCP\Collaboration\Reference\IReference;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;

class WorkPackageReferenceProvider extends ADiscoverableReferenceProvider {
	private const RICH_OBJECT_TYPE = Application::APP_ID . '_work_package';

	// as we know we are on NC >= 26, we can use Php 8 syntax for class attributes
	public function __construct(private IConfig $config,
								private IL10N $l10n,
								private IURLGenerator $urlGenerator,
								private ReferenceManager $referenceManager,
								private OpenProjectAPIService $openProjectAPIService,
								private ?string $userId) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'openproject-work-package-ref';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('OpenProject work packages');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	/**
	 * Parse a link to find a work package ID
	 *
	 * @param string $referenceText
	 *
	 * @return int|null
	 */
	private function getWorkPackageIdFromUrl(string $referenceText): ?int {
		$patterns = array('\/projects\/[^\/\?]+\/work_packages(?:\/details)?\/([0-9]+)/',
			'\/wp\/([0-9]+)/',
			'\/(?:work_packages|notifications)\/[^\/\?]+\/([0-9]+)/',);
		// example links
		// https://community.openproject.org/projects/nextcloud-integration/work_packages/40070
		$openProjectUrl = $this->config->getAppValue(Application::APP_ID, 'openproject_instance_url');
		foreach ($patterns as $pattern) {
			$string ='/^' . preg_quote($openProjectUrl, '/') . $pattern;
			preg_match($string, $referenceText, $patternMatches);
			if (count($patternMatches) > 1) {
				return (int) $patternMatches[1];
			}
		}
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		if ($this->userId !== null) {
			$linkPreviewEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'link_preview_enabled', '1') === '1';
			if (!$linkPreviewEnabled) {
				return false;
			}
		}
		$adminLinkPreviewEnabled = $this->config->getAppValue(Application::APP_ID, 'link_preview_enabled', '1') === '1';
		if (!$adminLinkPreviewEnabled) {
			return false;
		}

		return $this->getWorkPackageIdFromUrl($referenceText) !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText) && OpenProjectAPIService::isAdminConfigOk($this->config)) {
			$wpId = $this->getWorkPackageIdFromUrl($referenceText);
			if ($wpId !== null) {
				$wpInfo = $this->openProjectAPIService->getWorkPackageInfo($this->userId, $wpId);

				$reference = new Reference($referenceText);
				// this is used if your custom reference widget cannot be loaded (in mobile/desktop clients for example)
				$reference->setTitle($wpInfo['title']);
				$reference->setDescription($wpInfo['description']);
				$reference->setImageUrl($wpInfo['imageUrl']);
				// this is the data you will get in your custom reference widget
				$reference->setRichObject(
					self::RICH_OBJECT_TYPE,
					$wpInfo['entry']
				);
				return $reference;
			}
		}

		return null;
	}

	/**
	 * We use the userId here because when connecting/disconnecting from the OpenProject account,
	 * we want to invalidate all the user cache and this is only possible with the cache prefix
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	/**
	 * We don't use the userId here but rather a reference unique id
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		$wpId = $this->getWorkPackageIdFromUrl($referenceId);
		return (string) $wpId ?? $referenceId;
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
