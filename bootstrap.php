<?php

define('PHPUNIT_RUN', 1);
use Composer\Autoload\ClassLoader;

include_once __DIR__.'/vendor/autoload.php';
if (file_exists(__DIR__ . '/server')) {
	$serverPath = __DIR__ . '/server';
} else {
	$serverPath = __DIR__ . '/../..';
}
include_once $serverPath.'/3rdparty/autoload.php';
require_once $serverPath. '/lib/base.php';

$classLoader = new ClassLoader();
$classLoader->addPsr4("OCA\\OpenProject\\Service\\", __DIR__ . '/lib/Service', true);
$classLoader->addPsr4("OCP\\", $serverPath . '/lib/public', true);
$classLoader->addPsr4("OC\\", $serverPath . '/lib/private', true);
$classLoader->addPsr4("OCA\\Files\\Event\\", $serverPath . '/apps/files/lib/Event', true);
$classLoader->addPsr4("OCA\\OpenProject\\AppInfo\\", __DIR__ . '/lib/AppInfo', true);
$classLoader->addPsr4("OCA\\OpenProject\\Controller\\", __DIR__ . '/lib/Controller', true);
$classLoader->addPsr4("OCA\\OpenProject\\Exception\\", __DIR__ . '/lib/Exception', true);
$classLoader->register();

set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/share/php');
