<?php

use Composer\Autoload\ClassLoader;

include_once __DIR__.'/vendor/autoload.php';

$classLoader = new ClassLoader();
$classLoader->addPsr4("OCA\\OpenProject\\Service\\", __DIR__ . '/lib/Service', true);
$classLoader->addPsr4("OCP\\", __DIR__ . '/../../lib/public', true);
$classLoader->addPsr4("OC\\", __DIR__ . '/../../lib/private', true);
$classLoader->addPsr4("OCA\\OpenProject\\AppInfo\\", __DIR__ . '/lib/AppInfo', true);
$classLoader->register();

set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/share/php');
