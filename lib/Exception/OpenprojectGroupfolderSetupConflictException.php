<?php

namespace OCA\OpenProject\Exception;

use Exception;
use Throwable;

/**
 * thrown in the case when any entity, that is needed for the groupfolders setup,
 * already exists
 */
class OpenprojectGroupfolderSetupConflictException extends Exception {

	/**
	 * @param string $message
	 * @param int $code
	 * @param Throwable|null $previous
	 */
	public function __construct(string $message, int $code = 0, Throwable $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}
