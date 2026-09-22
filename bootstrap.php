<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021-2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Composer\Autoload\ClassLoader;

define('PHPUNIT_RUN', 1);

$rootDir = __DIR__;
$serverPath = getenv('SERVER_PATH') ?: $rootDir . '/server';
$serverBaseFile = '/lib/base.php';

if (!file_exists($serverPath . $serverBaseFile)) {
	$serverPath = $rootDir . '/../..';
}

if (!file_exists($serverPath . $serverBaseFile)) {
	throw new RuntimeException('Server files not found.');
}

require_once $rootDir . '/vendor/autoload.php';
require_once $serverPath . $serverBaseFile;
require_once $serverPath . '/tests/autoload.php';

$classLoader = new ClassLoader();
$classLoader->addPsr4("OCA\\OpenProject\\", $rootDir . '/lib', true);
$classLoader->register();
