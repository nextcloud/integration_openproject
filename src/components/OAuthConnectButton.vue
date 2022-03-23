<template>
	<button v-if="!!requestUrl"
		class="oauth-connect--button"
		@click="onOAuthClick">
		<span class="icon icon-external" />
		{{ t('integration_openproject', 'Connect to OpenProject') }}
	</button>
	<div v-else class="oauth-connect--message">
		{{ adminConfigNotOkMessage }}
	</div>
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
			type: [String, Boolean],
			required: true,
		},
	},

	computed: {
		adminConfigNotOkMessage() {
			return t('integration_openproject', 'Some OpenProject integration application settings are not working.'
				+ ' Please contact your Nextcloud administrator.')
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
<style lang="scss" scoped>
.oauth-connect {
	&--message {
		font-size: 1rem;
		text-align: center;
		font-weight: 400;
		color: #878787;
		padding: 0px 18px;
		line-height: 1.4rem;
	}
}
</style>
