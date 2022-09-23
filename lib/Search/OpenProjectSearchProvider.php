<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021, Julien Veyssier
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\OpenProject\Search;

use OCA\OpenProject\Service\OpenProjectAPIService;
use OCA\OpenProject\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class OpenProjectSearchProvider implements IProvider {

	/** @var IAppManager */
	private $appManager;

	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;
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
	 * @param IURLGenerator $urlGenerator
	 * @param OpenProjectAPIService $service
	 */
	public function __construct(IAppManager $appManager,
								IL10N $l10n,
								IConfig $config,
								IURLGenerator $urlGenerator,
								OpenProjectAPIService $service) {
		$this->appManager = $appManager;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
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

		$theme = $this->config->getUserValue($user->getUID(), 'accessibility', 'theme');
		$svgUrl = $this->urlGenerator->linkToRoute('core.Svg.getSvgFromApp', [
			'app' => Application::APP_ID,
			'fileName' => 'app',
		]);
		$thumbnailUrl = ($theme === 'dark')
			? $svgUrl . '?color=ffffff'
			: $svgUrl . '?color=000000';

		$openprojectUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url');
		$accessToken = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'token');

		$searchEnabled = $this->config->getUserValue(
			$user->getUID(),
			Application::APP_ID, 'search_enabled',
			$this->config->getAppValue(Application::APP_ID, 'default_enable_unified_search', '0')) === '1';
		if ($accessToken === '' || !$searchEnabled) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$searchResults = $this->service->searchWorkPackage($user->getUID(), $term);
		$searchResults = array_slice($searchResults, $offset, $limit);

		if (isset($searchResults['error'])) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		// @phpstan-ignore-next-line array_map supports also lambda functions
		$formattedResults = array_map(function (array $entry) use ($thumbnailUrl, $openprojectUrl): OpenProjectSearchResultEntry {
			return new OpenProjectSearchResultEntry(
				$thumbnailUrl,
				$this->getMainText($entry),
				$this->getSubline($entry),
				$this->getLinkToOpenProject($entry, $openprojectUrl),
				'',
				false
			);
		}, $searchResults);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$offset + $limit
		);
	}

	/**
	 * @param array<mixed> $entry
	 * @return string
	 */
	protected function getMainText(array $entry): string {
		return $entry['subject'];
	}

	/**
	 * @param array<mixed> $entry
	 * @return string
	 */
	protected function getSubline(array $entry): string {
		$description = isset($entry['description'], $entry['description']['raw'])
			? $entry['description']['raw']
			: '';
		$status = isset($entry['_links'], $entry['_links']['status'], $entry['_links']['status']['title'])
			? '[' . $entry['_links']['status']['title'] . '] '
			: '';
		return $status . $description;
	}

	/**
	 * @param array<mixed> $entry
	 * @param string $url
	 * @return string
	 */
	protected function getLinkToOpenProject(array $entry, string $url): string {
		$projectId = isset($entry['_links'], $entry['_links']['project'], $entry['_links']['project']['href'])
			? preg_replace('/.*\//', '', $entry['_links']['project']['href'])
			: '';
		return ($projectId !== '')
			? $url . '/projects/' . $projectId . '/work_packages/' . $entry['id'] . '/activity'
			: '';
	}

	/**
	 * @param array<mixed> $entry
	 * @param string $thumbnailUrl
	 * @return string
	 */
	protected function getThumbnailUrl(array $entry, string $thumbnailUrl): string {
		return '';
	}
}
