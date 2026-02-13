<?php

/**
 * SPDX-FileCopyrightText: 2021-2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

define('PHPUNIT_RUN', 1);
use Composer\Autoload\ClassLoader;

include_once __DIR__.'/vendor/autoload.php';
$serverPath = __DIR__ . '/server';

if (!file_exists($serverPath)) {
	throw new RuntimeException('Server path not found: ' . $serverPath);
}

include_once $serverPath.'/3rdparty/autoload.php';
require_once $serverPath. '/lib/base.php';

$classLoader = new ClassLoader();
$classLoader->addPsr4("OCP\\", $serverPath . '/lib/public', true);
$classLoader->addPsr4("OC\\", $serverPath . '/lib/private', true);
$classLoader->addPsr4("OCA\\Files\\", $serverPath . '/apps/files/lib', true);
$classLoader->register();

set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/share/php');
