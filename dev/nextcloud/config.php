<?php

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$CONFIG = [
	'apps_paths' =>
	array(
		0 =>
		array(
			'path' => '/var/www/html/apps',
			'url' => '/apps',
			'writable' => false,
		),
		1 =>
		array(
			'path' => '/var/www/html/custom_apps',
			'url' => '/custom_apps',
			'writable' => true,
		),
	),
];
