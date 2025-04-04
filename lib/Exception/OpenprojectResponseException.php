<?php

/**
 * SPDX-FileCopyrightText: 2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Exception;

use Exception;
use Throwable;

class OpenprojectResponseException extends Exception {

	/**
	 * @param string $message
	 * @param int $code
	 * @param Throwable|null $previous
	 */
	public function __construct(string $message, int $code = 0, Throwable $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}
