<?php

declare(strict_types=1);

namespace OCA\OpenProject\Sabre;

use OCA\OpenProject\AppInfo\Application;
use OCP\IConfig;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\Sapi;

/**
 * inspired by https://gitlab.tugraz.at/dbp/nextcloud/webapppassword/-/blob/master/lib/Connector/Sabre/CorsPlugin.php
 */
class CorsPlugin extends ServerPlugin {
	/**
	 * @var string
	 */
	private $allowedOrigin;

	public function __construct(IConfig $config) {
		$this->allowedOrigin = $config->getAppValue(Application::APP_ID, 'oauth_instance_url');
	}

	/**
	 * @param Server $server
	 * @return void
	 */
	public function initialize(\Sabre\DAV\Server $server): void {
		$server->on('beforeMethod:*', [$this, 'setCorsHeaders'], 5);
	}

	/**
	 * @return void|bool
	 */
	public function setCorsHeaders(RequestInterface $request, ResponseInterface $response) {
		if ($response->hasHeader('access-control-allow-origin')) {
			return;
		}

		$origin = $request->getHeader('origin');
		if (empty($origin) || $origin !== $this->allowedOrigin) {
			return;
		}

		$response->addHeader('access-control-allow-origin', $origin);
		$response->addHeader('access-control-allow-methods', $request->getHeader('access-control-request-method'));
		$response->addHeader('access-control-allow-headers', $request->getHeader('access-control-request-headers'));
		$response->addHeader('access-control-expose-headers', 'etag, dav');
		$response->addHeader('access-control-allow-credentials', 'true');

		if ($request->getMethod() === 'OPTIONS' && empty($request->getHeader('authorization'))) {
			$response->setStatus(204);
			Sapi::sendResponse($response);

			return false;
		}
	}
}
