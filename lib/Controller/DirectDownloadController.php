<?php

/**
 * SPDX-FileCopyrightText: 2024 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Controller;

use OCA\OpenProject\Service\DirectDownloadService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;

class DirectDownloadController extends Controller {

	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var DirectDownloadService
	 */
	private $directDownloadService;

	public function __construct(string $appName,
		IRequest $request,
		IL10N $l,
		DirectDownloadService $directDownloadService) {
		parent::__construct($appName, $request);
		$this->l = $l;
		$this->directDownloadService = $directDownloadService;
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
					'error' => $this->l->t('Direct download error'),
					'hint' => $this->l->t('This direct download link is invalid or has expired'),
				],
			],
		], TemplateResponse::RENDER_AS_GUEST);
	}
}
