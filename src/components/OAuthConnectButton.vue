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
		generateRandomString(length) {
			let text = ''
			const possible = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~'
			for (let i = 0; i < length; i++) {
				text += possible.charAt(Math.floor(Math.random() * possible.length))
			}
			return text
		},
		async digest(text) {
			return await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text))
		},
		async generateCodeChallenge(codeVerifier) {
			const digest = await this.digest(codeVerifier)
			return btoa(String.fromCharCode(...new Uint8Array(digest)))
				.replace(/=/g, '')
				.replace(/\+/g, '-')
				.replace(/\//g, '_')
		},
		async onOAuthClick() {
			const oauthState = this.generateRandomString(10)
			const codeVerifier = this.generateRandomString(128)
			const codeChallenge = await this.generateCodeChallenge(codeVerifier)
			const requestUrl = this.requestUrl
				+ '&state=' + encodeURIComponent(oauthState)
				+ '&code_challenge=' + codeChallenge
				+ '&code_challenge_method=S256'

			const req = {
				values: {
					oauth_state: oauthState,
					code_verifier: codeVerifier,
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
