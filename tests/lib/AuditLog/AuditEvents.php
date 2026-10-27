<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Tests\AuditLog;

use OCA\OpenProject\AuditLog\Operation;
use OCP\Log\Audit\CriticalActionPerformedEvent;

final class AuditEvents {
	/**
	 * Servers before the operation was added to CriticalActionPerformedEvent do not keep it
	 */
	public static function hasOperation(CriticalActionPerformedEvent $event, Operation $operation): bool {
		return !method_exists($event, 'getOperation') || $event->getOperation() === $operation->value;
	}
}
