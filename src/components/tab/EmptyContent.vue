<template>
	<div class="empty-content">
		<div class="empty-content--wrapper">
			<div class="empty-content--icon">
				<LinkPlusIcon v-if="!!isAdminConfigOk && isStateOk" :size="60" />
				<LinkOffIcon v-else :size="60" />
			</div>
			<div v-if="!!isAdminConfigOk" class="empty-content--message">
				<div class="empty-content--message--title">
					{{ emptyContentTitleMessage }}
				</div>
				<div v-if="!!emptyContentSubTitleMessage" class="empty-content--message--sub-title">
					{{ emptyContentSubTitleMessage }}
				</div>
			</div>
			<div v-if="showConnectButton" class="empty-content--connect-button">
				<OAuthConnectButton :is-admin-config-ok="isAdminConfigOk" :file-info="fileInfo" />
			</div>
		</div>
	</div>
</template>

<script>
import LinkPlusIcon from 'vue-material-design-icons/LinkPlus.vue'
import LinkOffIcon from 'vue-material-design-icons/LinkOff.vue'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import OAuthConnectButton from '../OAuthConnectButton.vue'
import { STATE } from '../../utils.js'

export default {
	name: 'EmptyContent',
	components: { OAuthConnectButton, LinkPlusIcon, LinkOffIcon },
	props: {
		state: {
			type: String,
			required: true,
			default: STATE.OK,
		},
		isAdminConfigOk: {
			type: Boolean,
			required: true,
		},
		errorMessage: {
			type: String,
			default: '',
		},
		fileInfo: {
			type: Object,
			default() {
				return {}
			},
		},
	},
	data() {
		return {
			settingsUrl: generateUrl('/settings/user/openproject'),
		}
	},
	computed: {
		isStateOk() {
			return this.state === STATE.OK
		},
		showConnectButton() {
			return [STATE.NO_TOKEN, STATE.ERROR].includes(this.state)
		},
		emptyContentTitleMessage() {
			if (this.state === STATE.NO_TOKEN) {
				return t('integration_openproject', 'No connection with OpenProject')
			} else if (this.state === STATE.CONNECTION_ERROR) {
				return t('integration_openproject', 'Error connecting to OpenProject')
			} else if (this.state === STATE.FAILED_FETCHING_WORKPACKAGES) {
				return t('integration_openproject', 'Could not fetch work packages from OpenProject')
			} else if (this.isStateOk) {
				return t('integration_openproject', 'No OpenProject links yet')
			}
			return t('integration_openproject', 'Unexpected Error')
		},
		emptyContentSubTitleMessage() {
			if (this.isStateOk) {
				return t('integration_openproject', 'To add a link, use the search bar above to find the desired work package')
			}
			return ''
		},
	},
}
</script>

<style scoped lang="scss">
.empty-content {
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	&--wrapper {
		height: fit-content;
	}
	&--icon {
		padding: 1vh 0;
		display: flex;
		align-items: center;
		justify-content: center;
		img {
			height: 50px;
			width: 50px;
			filter: var(--background-invert-if-dark);
		}
	}
	&--message {
		text-align: center;
		&--title {
			font-size: 1.2rem;
			line-height: 1.4rem;
			font-weight: 600;
			padding: 4px 12px 12px 12px;
			color: #333333;
		}
		&--sub-title {
			font-size: 1rem;
			line-height: 1.4rem;
			text-align: center;
			font-weight: 400;
			color: #878787;
			padding: 0px 18px;
		}
	}
	&--connect-button {
		padding: 1vh 0;
		display: flex;
		align-items: center;
		justify-content: center;
	}
}

body.theme--dark, body[data-theme-dark], body[data-theme-dark-highcontrast] {
	.empty-content--message--title {
		color: #cfcfcf;
	}
}
</style>
