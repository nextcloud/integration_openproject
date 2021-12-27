<?php

use Composer\Autoload\ClassLoader;

include_once __DIR__.'/vendor/autoload.php';

$classLoader = new ClassLoader();
$classLoader->addPsr4("OCA\\OpenProject\\Service\\", __DIR__ . '/lib/Service', true);
$classLoader->register();

set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/share/php');
