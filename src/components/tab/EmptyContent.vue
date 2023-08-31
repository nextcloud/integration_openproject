<template>
	<div class="empty-content">
		<div class="empty-content--wrapper">
			<div class="empty-content--icon">
				<CheckIcon v-if="isStateOk && dashboard" :size="60" />
				<LinkPlusIcon v-else-if="!!isAdminConfigOk && isStateOk && !dashboard && !isSmartPicker" :size="60" />
				<OpenProjectIcon v-else-if="!!isSmartPicker" class="empty-content--icon--openproject" />
				<LinkOffIcon v-else :size="60" />
			</div>
			<div v-if="!!isAdminConfigOk && !isSmartPicker" class="empty-content--message">
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
import CheckIcon from 'vue-material-design-icons/Check.vue'
import OpenProjectIcon from '../icons/OpenProjectIcon.vue'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import OAuthConnectButton from '../OAuthConnectButton.vue'
import { STATE } from '../../utils.js'

export default {
	name: 'EmptyContent',
	components: { OAuthConnectButton, LinkPlusIcon, LinkOffIcon, CheckIcon, OpenProjectIcon },
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
		dashboard: {
			type: Boolean,
			default: false,
		},
		isSmartPicker: {
			type: Boolean,
			default: false,
		},
		isMultipleWorkpackageLinking: {
			type: Boolean,
			default: false,
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
				if (this.dashboard) {
					return t('integration_openproject', 'No OpenProject notifications!')
				} else if (this.isMultipleWorkpackageLinking) {
					return t('integration_openproject', 'Add a new link to all selected files')
				}
				return t('integration_openproject', 'No OpenProject links yet')
			}
			return t('integration_openproject', 'Unexpected Error')
		},
		emptyContentSubTitleMessage() {
			if (this.isStateOk && !this.dashboard) {
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
		&--openproject {
			margin: 90px;
			display: block;
			align-items: center;
			justify-content: center;
		}
	}
	&--message {
		text-align: center;
		&--title {
			font-size: 1.2rem;
			line-height: 1.4rem;
			font-weight: 600;
			padding: 4px 12px 12px 12px;
		}
		&--sub-title {
			font-size: 1rem;
			line-height: 1.4rem;
			text-align: center;
			font-weight: 400;
			padding: 0 18px;
		}
	}
	&--connect-button {
		padding: 1vh 0;
		display: flex;
		align-items: center;
		justify-content: center;
	}
}
</style>
