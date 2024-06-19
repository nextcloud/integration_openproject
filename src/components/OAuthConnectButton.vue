<template>
	<NcButton v-if="!!isAdminConfigOk"
		class="oauth-connect--button"
		@click="onOAuthClick">
		<template #icon>
			<OpenInNewIcon :size="20" />
		</template>
		{{ t('integration_openproject', 'Connect to OpenProject') }}
	</NcButton>
	<div v-else class="oauth-connect--message" v-html="adminConfigNotOkMessage" /> <!-- eslint-disable-line vue/no-v-html -->
</template>
<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import '@nextcloud/dialogs/styles/toast.scss'
// import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { NcButton } from '@nextcloud/vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import dompurify from 'dompurify'
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
			if (getCurrentUser().isAdmin) {
				const linkText = t('integration_openproject', 'Administration Settings > OpenProject')
				const url = generateUrl('/settings/admin/openproject')
				const htmlLink = `<a class="link" href="${url}" target="_blank" title="${linkText}">${linkText}</a>`
				const hintText = t('integration_openproject', 'Some OpenProject Integration application settings are not working. '
				+ 'Configure the OpenProject Integration in: {htmlLink}', { htmlLink }, null, { escape: false, sanitize: false })
				return dompurify.sanitize(hintText, { ADD_ATTR: ['target'] })
			}
			return t('integration_openproject', 'Some OpenProject Integration application settings are not working.'
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
			if (window.location.pathname.includes('apps/files') && Object.keys(this.fileInfo).length === 0) {
				return { page: 'files' }
			}
			if (window.location.pathname.includes('call')) {
				return { page: 'spreed', callUrl: window.location.href }
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
		padding: 0 18px;
		line-height: 1.4rem;
	}
}
</style>
<style>
.link {
	color: #1a67a3 !important;
	font-style: italic;
}
</style>
