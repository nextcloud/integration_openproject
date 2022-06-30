<?php
/**
 * Nextcloud - openproject
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\OpenProject\Controller;

use OCA\OpenProject\Service\DirectDownloadService;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IRequest;
use OCP\AppFramework\Controller;

use OCA\OpenProject\AppInfo\Application;

class DirectDownloadController extends Controller {

	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var DirectDownloadService
	 */
	private $directDownloadService;

	public function __construct(string $appName,
								IRequest $request,
								IInitialState $initialStateService,
								IL10N $l,
								DirectDownloadService $directDownloadService) {
		parent::__construct($appName, $request);
		$this->l = $l;
		$this->initialStateService = $initialStateService;
		$this->directDownloadService = $directDownloadService;
	}

	/**
	 * Direct download page
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function directDownloadPage(string $token, string $fileName): PublicTemplateResponse {
		$this->initialStateService->provideInitialState('direct', [
			'token' => $token,
			'fileName' => $fileName,
		]);
		$response = new PublicTemplateResponse(Application::APP_ID, 'directDownload');
		$response->setHeaderTitle($this->l->t('Direct download'));
		$response->setHeaderDetails($fileName);
		$response->setFooterVisible(false);
		return $response;
	}

	/**
	 * Direct download
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $token
	 * @param string $fileName
	 * @return DataDownloadResponse|TemplateResponse
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\Lock\LockedException
	 */
	public function directDownload(string $token, string $fileName) {
		$file = $this->directDownloadService->getDirectDownloadFile($token);
		if ($file !== null) {
			return new DataDownloadResponse($file->getContent(), $fileName, $file->getMimeType());
		}
		return new TemplateResponse('core', 'error', [
			'errors' => [
				[
					'error' => $this->l->t('Direct download error.'),
					'hint' => $this->l->t('This direct download link is invalid or has expired.'),
				],
			],
		], TemplateResponse::RENDER_AS_GUEST);
	}
}
