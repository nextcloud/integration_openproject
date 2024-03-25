<template>
	<div class="settings">
		<h2 class="settings--title">
			<OpenProjectIcon />
			<span>{{ title }}</span>
		</h2>
		<p class="documentation-info" v-html="sanitizedHintText" /> <!-- eslint-disable-line vue/no-v-html -->
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import OpenProjectIcon from '../icons/OpenProjectIcon.vue'
import dompurify from 'dompurify'

export default {
	name: 'SettingsTitle',
	components: {
		OpenProjectIcon,
	},
	props: {
		isSetting: {
			type: String,
			required: true,
		},
	},
	computed: {
		title() {
			return t('integration_openproject', 'OpenProject Integration')
		},
		getSetUpIntegrationDocumentationLinkText() {
			const linkText = t('integration_openproject', 'setting up a Nextcloud file storage')
			const htmlLink = `<a class="link" href="https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'Visit our documentation for in-depth information on {htmlLink} integration.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		getUserGuideDocumentationLinkText() {
			const linkText = t('integration_openproject', 'user guide')
			const htmlLink = `<a class="link" href="https://www.openproject.org/docs/user-guide/file-management/nextcloud-integration/" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'Learn how to get the most out of the OpenProject integration by visiting our {htmlLink}.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		sanitizedHintText() {
			if (this.isSetting === 'admin') {
				return dompurify.sanitize(this.getSetUpIntegrationDocumentationLinkText, { ADD_ATTR: ['target'] })
			} else if (this.isSetting === 'personal') {
				return dompurify.sanitize(this.getUserGuideDocumentationLinkText, { ADD_ATTR: ['target'] })
			}
			return ''
		},
	},
	methods: {

	},
}
</script>
<style lang="scss">
@import '../../../css/dashboard.css';

.settings {
	&--title {
		display: flex;
		align-items: center;
		.icon-openproject {
			min-height: 32px;
			min-width: 32px;
			background-size: cover;
		}
		span {
			padding-left: 10px;
		}
	}
}

.settings .link {
	color: #1a67a3 !important;
	font-style: normal;
}

</style>
