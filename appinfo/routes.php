<?php
/**
 * Nextcloud - OpenProject
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

return [
    'routes' => [
        ['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
        ['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
        ['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
        ['name' => 'openProjectAPI#getNotifications', 'url' => '/notifications', 'verb' => 'GET'],
        ['name' => 'openProjectAPI#getOpenProjectUrl', 'url' => '/url', 'verb' => 'GET'],
        ['name' => 'openProjectAPI#getOpenProjectAvatar', 'url' => '/avatar', 'verb' => 'GET'],
    ]
];
