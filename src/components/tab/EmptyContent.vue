<template>
	<div class="empty-content">
		<div class="empty-content--wrapper">
			<div class="empty-content--icon">
				<img :src="flowSvg" alt="flow">
			</div>
			<div v-if="adminConfigStatus" class="empty-content--title">
				{{ emptyContentMessage }}
			</div>
			<div v-if="showConnectButton" class="empty-content--connect-button">
				<OAuthConnectButton :request-url="requestUrl" :admin-config-status="adminConfigStatus" />
			</div>
		</div>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import OAuthConnectButton from '../OAuthConnectButton'

export default {
	name: 'EmptyContent',
	components: { OAuthConnectButton },
	props: {
		state: {
			type: String,
			required: true,
			default: 'ok',
		},
		requestUrl: {
			type: String,
			required: true,
		},
		errorMessage: {
			type: String,
			default: '',
		},
		adminConfigStatus: {
			type: Boolean,
			required: true,
		},
	},
	data() {
		return {
			settingsUrl: generateUrl('/settings/user/connected-accounts'),
		}
	},
	computed: {
		flowSvg() {
			return require('../../../img/flow.svg')
		},
		showConnectButton() {
			return ['error', 'no-token'].includes(this.state)
		},
		emptyContentMessage() {
			if (this.state === 'no-token') {
				return t('integration_openproject', 'No OpenProject account connected')
			} else if (this.state === 'connection-error') {
				return t('integration_openproject', 'Error connecting to OpenProject')
			} else if (this.state === 'failed-fetching-workpackages') {
				return t('integration_openproject', 'Could not fetch work packages from OpenProject')
			} else if (this.state === 'error') {
				return t('integration_openproject', 'Unexpected Error')
			} else if (this.state === 'ok') {
				return t('integration_openproject', 'No workspaces linked yet, search for work package to add!')
			}
			return 'invalid state'
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
	&--title {
		text-align: center;
		font-size: 1.2rem;
		line-height: 1.4rem;
		font-weight: 600;
		padding-top: 4px;
		color: #333333;
	}
	&--subtitle {
		font-size: .875rem;
		font-weight: 400;
		color: #6d6d6d;
		line-height: 1rem;
		padding: 8px 0;
	}
	&--connect-button {
		padding: 1vh 0;
		display: flex;
		align-items: center;
		justify-content: center;
	}
}
</style>
