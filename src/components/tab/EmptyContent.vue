<template>
	<div class="empty-content">
		<div class="empty-content--wrapper">
			<div class="empty-content--icon">
				<img v-if="!!requestUrl && state !== 'ok'" :src="noConnectionSvg" alt="no connection">
				<img v-else-if="!!requestUrl && state === 'ok'" :src="addLinkSvg" alt="add work package">
				<img v-else :src="noConnectionSvg" alt="error">
			</div>
			<div v-if="!!requestUrl" class="empty-content--title" v-html="emptyContentMessage" />
			<div v-if="showConnectButton" class="empty-content--connect-button">
				<OAuthConnectButton :request-url="requestUrl" />
			</div>
		</div>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
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
		noConnectionSvg() {
			return require('../../../img/noConnection.svg')
		},
		addLinkSvg() {
			return require('../../../img/addLink.svg')
		},
		showConnectButton() {
			return ['error', 'no-token'].includes(this.state)
		},
		emptyContentMessage() {
			if (this.state === 'no-token') {
				return '<div>No connection with OpenProject</div>'
			} else if (this.state === 'connection-error') {
				return '<div>Error connecting to OpenProject</div>'
			} else if (this.state === 'failed-fetching-workpackages') {
				return '<div>Could not fetch work packages from OpenProject</div>'
			} else if (this.state === 'error') {
				return '<div>Unexpected Error</div>'
			} else if (this.state === 'ok') {
				return '<div>'
						+ '<div style="font-weight: 600; padding-bottom: 10px;">No OpenProject links yet </div>'
						+ '<div style="font-weight: 400; font-size: 1rem; color: #878787; padding: 0px 18px 0px 18px;">'
						+ 'To add a link, use the search bar above to find the desired work package'
					    + '</div>'
					    + '</div>'
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
		img{
			height: 50px;
			width: 50px;
		}
	}
	&--title {
		text-align: center;
		font-size: 1.2rem;
		line-height: 1.4rem;
		font-weight: 600;
		padding: 4px 12px 12px 12px;
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
