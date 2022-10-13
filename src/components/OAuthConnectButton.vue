<template>
	<NcButton v-if="!!isAdminConfigOk"
		class="oauth-connect--button"
		@click="onOAuthClick">
		<template #icon>
			<OpenInNewIcon :size="20" />
		</template>
		{{ t('integration_openproject', 'Connect to OpenProject') }}
	</NcButton>
	<div v-else class="oauth-connect--message">
		{{ adminConfigNotOkMessage }}
	</div>
</template>
<script>
import axios from '@nextcloud/axios'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/dist/Components/Button.js'

export default {
	name: 'OAuthConnectButton',

	components: {
		NcButton,
		OpenInNewIcon,
	},

	props: {
		isAdminConfigOk: {
			type: Boolean,
			required: true,
		},
		fileInfo: {
			type: Object,
			default() {
				return {}
			},
		},
	},

	computed: {
		adminConfigNotOkMessage() {
			return t('integration_openproject', 'Some OpenProject integration application settings are not working.'
				+ ' Please contact your Nextcloud administrator.')
		},
	},

	methods: {
		getOauthJourneyStartingPage() {
			if (window.location.pathname.includes('dashboard')) {
				return { page: 'dashboard' }
			}
			if (window.location.pathname.includes('apps/files') && this.fileInfo.id !== undefined) {
				return { page: 'files', file: this.fileInfo }
			}
			return { page: 'settings' }
		},
		async onOAuthClick() {
			const url = generateUrl('/apps/integration_openproject/op-oauth-url')
			axios.get(url)
				.then((result) => {
					const req = {
						values: {
							oauth_journey_starting_page: JSON.stringify(this.getOauthJourneyStartingPage()),
						},
					}
					const url = generateUrl('/apps/integration_openproject/config')
					axios.put(url, req)
						.then(() => {
							window.location.replace(result.data)
						})
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
		padding: 0 18px;
		line-height: 1.4rem;
	}
}

body[data-theme-dark], body[data-theme-dark-highcontrast], body.theme--dark {
	.oauth-connect--message {
		filter: invert(100%);
	}
}
</style>
