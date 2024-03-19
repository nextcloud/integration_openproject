import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

let mytimer = 0
export function delay(callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(mytimer)
		mytimer = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

export function checkOauthConnectionResult(oauthConnectionResult, oauthConnectionErrorMessage) {
	if (oauthConnectionResult === 'success') {
		showSuccess(t('integration_openproject', 'Successfully connected to OpenProject!'))
	} else if (oauthConnectionResult === 'error') {
		showError(
			t(
				'integration_openproject',
				'OAuth access token could not be obtained:'
			) + ' ' + oauthConnectionErrorMessage
		)
	}
}

export const STATE = {
	OK: 'ok',
	ERROR: 'error',
	LOADING: 'loading',
	NO_TOKEN: 'no-token',
	CONNECTION_ERROR: 'connection-error',
	FAILED_FETCHING_WORKPACKAGES: 'failed-fetching-workpackages',
}

export const F_MODES = {
	VIEW: 0,
	EDIT: 1,
	DISABLE: 2,
}

export const FORM = {
	SERVER: 0,
	OP_OAUTH: 1,
	NC_OAUTH: 2,
	GROUP_FOLDER: 3,
	APP_PASSWORD: 4,
}

export const WORKPACKAGES_SEARCH_ORIGIN = {
	PROJECT_TAB: 'project-tab',
	LINK_MULTIPLE_FILES_MODAL: 'link-multiple-files-modal',
}
export const USER_SETTINGS = {
	NAVIGATION_LINK_DESCRIPTION: t('integration_openproject', 'This check will show "OpenProject Icon" in the navigation bar to navigate directly to OpenProject instance.'),
	UNIFIED_SEARCH_DESCRIPTION: t('integration_openproject', 'This check will allow to unified or global search for the OpenProject tickets from the Nextcloud.'),
}

export const NO_OPTION_TEXT_STATE = {
	START_TYPING: 0,
	SEARCHING: 1,
	RESULT: 2,
}
