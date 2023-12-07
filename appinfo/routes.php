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
		['name' => 'config#autoOauthCreation', 'url' => '/nc-oauth', 'verb' => 'POST'],
		['name' => 'config#checkConfig', 'url' => '/check-config', 'verb' => 'GET'],

		['name' => 'directDownload#directDownload', 'url' => '/direct/{token}/{fileName}', 'verb' => 'GET'],

		['name' => 'directUpload#prepareDirectUpload', 'url' => '/direct-upload-token', 'verb' => 'POST'],
		['name' => 'directUpload#directUpload', 'url' => '/direct-upload/{token}', 'verb' => 'POST'],
		['name' => 'directUpload#preflighted_cors', 'url' => '/direct-upload/{token}', 'verb' => 'OPTIONS'],

		['name' => 'config#setUpIntegration', 'url' => '/setup', 'verb' => 'POST'],
		['name' => 'config#resetIntegration', 'url' => '/setup', 'verb' => 'DELETE'],
		['name' => 'config#updateIntegration', 'url' => '/setup', 'verb' => 'PATCH'],

		['name' => 'openProjectAPI#getNotifications', 'url' => '/notifications', 'verb' => 'GET'],
		['name' => 'openProjectAPI#markNotificationAsRead', 'url' => '/work-packages/{workpackageId}/notifications', 'verb' => 'DELETE'],
		['name' => 'openProjectAPI#getOpenProjectUrl', 'url' => '/url', 'verb' => 'GET'],
		['name' => 'openProjectAPI#getOpenProjectAvatar', 'url' => '/avatar', 'verb' => 'GET'],
		['name' => 'openProjectAPI#getSearchedWorkPackages', 'url' => '/work-packages', 'verb' => 'GET'],
		['name' => 'openProjectAPI#linkWorkPackageToFile', 'url' => '/work-packages', 'verb' => 'POST'],
		['name' => 'openProjectAPI#getWorkPackageFileLinks', 'url' => '/work-packages/{id}/file-links', 'verb' => 'GET'],
		['name' => 'openProjectAPI#getOpenProjectWorkPackageStatus', 'url' => '/statuses/{id}', 'verb' => 'GET'],
		['name' => 'openProjectAPI#getOpenProjectWorkPackageType', 'url' => '/types/{id}', 'verb' => 'GET'],
		['name' => 'openProjectAPI#deleteFileLink', 'url' => '/file-links/{id}', 'verb' => 'DELETE'],
		['name' => 'openProjectAPI#isValidOpenProjectInstance', 'url' => '/is-valid-op-instance', 'verb' => 'POST'],
		['name' => 'openProjectAPI#getOpenProjectOauthURLWithStateAndPKCE', 'url' => '/op-oauth-url', 'verb' => 'GET'],
		['name' => 'openProjectAPI#getProjectFolderSetupStatus', 'url' => '/project-folder-status', 'verb' => 'GET'],
		['name' => 'openProjectAPI#getAvailableOpenProjectProjects', 'url' => '/projects','verb' => 'GET'],
		['name' => 'openProjectAPI#getOpenProjectWorkPackageForm', 'url' => '/projects/{projectId}/work-packages/form','verb' => 'POST'],
		['name' => 'openProjectAPI#getAvailableAssigneesOfAProject', 'url' => '/projects/{projectId}/available-assignees','verb' => 'GET'],
		['name' => 'openProjectAPI#createWorkPackage', 'url' => '/create/work-packages','verb' => 'POST'],
		['name' => 'openProjectAPI#getOpenProjectConfiguration', 'url' => '/configuration', 'verb' => 'GET'],
	],
	'ocs' => [
		['name' => 'files#getFileInfo', 'url' => '/fileinfo/{fileId}', 'verb' => 'GET'],
		['name' => 'files#getFilesInfo', 'url' => '/filesinfo', 'verb' => 'POST'],
	]
];
