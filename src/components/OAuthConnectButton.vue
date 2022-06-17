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
		async onOAuthClick() {
			const url = generateUrl('/apps/integration_openproject/op-oauth-url')
			axios.get(url)
				.then((result) => {
					window.location.replace(result.data)
				})
				.catch((error) => {
					showError(
						t('integration_openproject', 'Failed to redirect to OpenProject')
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
		color: #333333;
		padding: 0px 18px;
		line-height: 1.4rem;
	}
}

body[data-theme-dark], body[data-theme-dark-highcontrast], body.theme--dark {
	.oauth-connect--message {
		color: #cfcfcf;
	}
}
</style>
