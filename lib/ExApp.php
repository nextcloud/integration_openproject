<?php
/**
 * Nextcloud - openproject
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2022
 */

namespace OCA\OpenProject;

use OCA\AppAPI\Service\ExAppService;

class ExApp {
	public const  APP_ID_PROXY_OPENPROJECT = 'openproject-nextcloud-app';
	private ExAppService $exAppService;

	/**
	 * Service to manipulate Nextcloud oauth clients
	 */
	public function __construct(
		ExAppService $exAppService,
	) {
		$this->exAppService = $exAppService;
	}

	public function isOpenProjectRunningAsExApp(string $openprojectUrl) : bool {
		return str_ends_with($openprojectUrl, '/proxy/' . self::APP_ID_PROXY_OPENPROJECT);
	}

	public function setHeadersForProxyRequest(string $nextcloudUser, array $options): array {
		$options = [];
		$exAppconfigInformation = $this->exAppService->getExApp(self::APP_ID_PROXY_OPENPROJECT);
		$authorizationAppAPI = base64_encode($nextcloudUser . ":" . $exAppconfigInformation->getSecret());
		$options['headers']['host'] = $exAppconfigInformation->getHost() . ":" . $exAppconfigInformation->getPort();
		$options['headers']['ex-app-id'] = $exAppconfigInformation->getAppid();
		$options['headers']['authorization-app-api'] = $authorizationAppAPI;
		$options['headers']['ex-app-version'] = $exAppconfigInformation->getVersion();
		return $options;
	}
}
