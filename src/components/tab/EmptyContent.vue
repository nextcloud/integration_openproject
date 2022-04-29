<template>
	<div class="empty-content">
		<div class="empty-content--wrapper">
			<div class="empty-content--icon">
				<img v-if="!!requestUrl && !isStateOk" :src="noConnectionSvg" alt="no connection">
				<img v-else-if="!!requestUrl && isStateOk" :src="addLinkSvg" alt="add work package">
				<img v-else :src="noConnectionSvg" alt="error">
			</div>
			<div v-if="!!requestUrl" class="empty-content--message">
				<div class="empty-content--message--title">
					{{ emptyContentTitleMessage }}
				</div>
				<div v-if="!!emptyContentSubTitleMessage" class="empty-content--message--sub-title">
					{{ emptyContentSubTitleMessage }}
				</div>
			</div>
			<div v-if="showConnectButton" class="empty-content--connect-button">
				<OAuthConnectButton :request-url="requestUrl" />
			</div>
		</div>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import OAuthConnectButton from '../OAuthConnectButton'
import { STATE } from '../../utils'

export default {
	name: 'EmptyContent',
	components: { OAuthConnectButton },
	props: {
		state: {
			type: String,
			required: true,
			default: STATE.OK,
		},
		requestUrl: {
			type: [String, Boolean],
			required: true,
		},
		errorMessage: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			settingsUrl: generateUrl('/settings/user/connected-accounts'),
		}
	},
	computed: {
		isStateOk() {
			return this.state === STATE.OK
		},
		noConnectionSvg() {
			return require('../../../img/noConnection.svg')
		},
		addLinkSvg() {
			return require('../../../img/addLink.svg')
		},
		showConnectButton() {
			return [STATE.ERROR, STATE.NO_TOKEN].includes(this.state)
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
</style>
