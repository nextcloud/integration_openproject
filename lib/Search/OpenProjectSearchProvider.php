<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2021-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Search;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class OpenProjectSearchProvider implements IProvider {

	/** @var IAppManager */
	private $appManager;

	/** @var IL10N */
	private $l10n;

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var OpenProjectAPIService
	 */
	private $service;

	/**
	 * CospendSearchProvider constructor.
	 *
	 * @param IAppManager $appManager
	 * @param IL10N $l10n
	 * @param IConfig $config
	 * @param OpenProjectAPIService $service
	 */
	public function __construct(IAppManager $appManager,
		IL10N $l10n,
		IConfig $config,
		OpenProjectAPIService $service) {
		$this->appManager = $appManager;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->service = $service;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'openproject-search';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('OpenProject');
	}

	/**
	 * @inheritDoc
	 * @param array<mixed> $routeParameters (unused)
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if (strpos($route, Application::APP_ID . '.') === 0) {
			// Active app, prefer OpenProject results
			return -1;
		}

		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$limit = $query->getLimit();
		$term = $query->getTerm();
		$offset = $query->getCursor();
		$offset = $offset ? intval($offset) : 0;
		$openprojectUrl = OpenProjectAPIService::sanitizeUrl($this->config->getAppValue(Application::APP_ID, 'openproject_instance_url'));

		$authorizationMethod = $this->config->getAppValue(Application::APP_ID, 'authorization_method', '');
		$searchEnabled = $this->config->getUserValue(
			$user->getUID(),
			Application::APP_ID, 'search_enabled',
			$this->config->getAppValue(Application::APP_ID, 'default_enable_unified_search', '0')) === '1';

		$accessToken = $this->service->getAccessToken($user->getUID());
		if (!$accessToken || !$searchEnabled) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$searchResults = $this->service->searchWorkPackage($user->getUID(), $term, null, false);
		$searchResults = array_slice($searchResults, $offset, $limit);

		if (isset($searchResults['error'])) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$formattedResults = array_map(function (array $entry) use ($openprojectUrl): OpenProjectSearchResultEntry {
			return new OpenProjectSearchResultEntry(
				$this->service->getOpenProjectUserAvatarUrl($entry),
				$this->service->getMainText($entry),
				$this->service->getSubline($entry),
				$this->service->getLinkToOpenProject($entry, $openprojectUrl),
				'',
				true
			);
		}, $searchResults);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$offset + $limit
		);
	}
}
