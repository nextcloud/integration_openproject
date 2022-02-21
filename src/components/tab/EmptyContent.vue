<template>
	<div class="fill-height center-content">
		<div class="empty-projects">
			<div class="empty-icon center-content">
				<img :src="flowSvg" alt="flow">
			</div>
			<div class="title text-center">
				{{ emptyContentMessage }}
			</div>
			<br>
			<div v-if="showConnectButton" class="connect-button">
				<a class="button" :href="settingsUrl">
					{{ t('integration_openproject', 'Connect to OpenProject') }}
				</a>
			</div>
		</div>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'EmptyContent',
	props: {
		state: {
			type: String,
			required: true,
			default: 'ok',
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
			} else if (this.state === 'error') {
				return t('integration_openproject', 'Error connecting to OpenProject')
			} else if (this.state === 'ok') {
				return t('integration_openproject', 'No workspaces linked yet, search for work package to add!')
			}
			return 'invalid state'
		},
	},
}
</script>

<style scoped lang="scss">
.empty-projects {
	.text-center {
		text-align: center;
	}
	.empty-icon {
		padding: 1vh 0;
		.icon img {
			height: 50px;
			width: 50px;
		}
	}
	.title {
		font-size: 1.2rem;
		line-height: 1.4rem;
		font-weight: 600;
		padding-top: 4px;
		color: #333333;
	}
	.subtitle {
		font-size: .875rem;
		font-weight: 400;
		color: #6d6d6d;
		line-height: 1rem;
		padding: 8px 0;
	}
}

.fill-height {
	height: 100%;
}
</style>
