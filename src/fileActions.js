/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import './bootstrap.js'

OCA.Files.fileActions.registerAction({
	name: 'open-project',
	displayName: t('integration_openproject', 'OpenProject'),
	mime: 'all',
	permissions: OC.PERMISSION_READ,
	iconClass: 'icon-openproject',
	actionHandler: (filename, context) => {
		const fileList = context.fileList
		if (!fileList._detailsView) {
			return
		}
		// use the sidebar-tab id for the navigation
		fileList.showDetailsView(filename, 'open-project')
	},
})
