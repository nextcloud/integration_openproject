<?php

/**
 * @copyright Copyright (c) 2022 Swikriti Tripathi <swikriti@jankaritech.com>
 *
 * @author Your name <swikriti@jankaritech.com>
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
 *
 */

namespace OCA\OpenProject\Controller;

use OCA\OpenProject\Service\DirectUploadService;
use OCP\IL10N;
use OCP\IRequest;

class DirectUploadController {
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var DirectUploadService
	 */
	private $directUploadService;

	public function __construct(string $appName,
								IRequest $request,
								IL10N $l,
								DirectUploadService $directUploadService) {
		parent::__construct($appName, $request);
		$this->l = $l;
		$this->directUploadService = $directUploadService;
	}

	public function prepareDirectUpload(int $fileId): string {
		return "Hello world";
	}
}
