<template>
	<button
		class="openproject-oauth"
		@click="onOAuthClick">
		<span class="icon icon-external" />
		{{ t('integration_openproject', 'Connect to OpenProject') }}
	</button>
</template>
<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'OAuthConnectButton',

	props: {
		requestUrl: {
			type: String,
			required: true,
		},
	},
	methods: {
		onOAuthClick() {
			const oauthState = Math.random().toString(36).substring(3)
			const requestUrl = this.requestUrl
				+ '&state=' + encodeURIComponent(oauthState)

			const req = {
				values: {
					oauth_state: oauthState,
				},
			}
			const url = generateUrl('/apps/integration_openproject/config')
			axios.put(url, req)
				.then(() => {
					window.location.replace(requestUrl)
				})
				.catch((error) => {
					showError(
						t('integration_openproject', 'Failed to save OpenProject OAuth state')
						+ ': ' + error.message
					)
				})
		},
	},
}
</script>
