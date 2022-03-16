<template>
	<div id="openproject_prefs" class="section">
		<SettingsTitle />
		<div id="openproject-content">
			<div id="toggle-openproject-navigation-link">
				<input id="openproject-link"
					type="checkbox"
					class="checkbox"
					:checked="state.navigation_enabled"
					@input="onNavigationChange">
				<label for="openproject-link">{{ t('integration_openproject', 'Enable navigation link') }}</label>
			</div>
			<br><br>
			<div v-if="connected" class="openproject-grid-form">
				<label class="openproject-connected">
					<a class="icon icon-checkmark-color" />
					{{ t('integration_openproject', 'Connected as {user}', { user: state.user_name }) }}
				</label><br>
				<button id="openproject-rm-cred" @click="onLogoutClick">
					<span class="icon icon-close" />
					{{ t('integration_openproject', 'Disconnect from OpenProject') }}
				</button>
			</div>
			<div v-if="connected" id="openproject-search-block">
				<input id="search-openproject"
					type="checkbox"
					class="checkbox"
					:checked="state.search_enabled"
					@input="onSearchChange">
				<label for="search-openproject">{{ t('integration_openproject', 'Enable unified search for tickets') }}</label>
				<br><br>
				<p v-if="state.search_enabled" class="settings-hint">
					<span class="icon icon-details" />
					{{ t('integration_openproject', 'Warning, everything you type in the search bar will be sent to your OpenProject instance.') }}
				</p>
				<input id="notification-openproject"
					type="checkbox"
					class="checkbox"
					:checked="state.notification_enabled"
					@input="onNotificationChange">
				<label for="notification-openproject">{{ t('integration_openproject', 'Enable notifications for activity in my work packages') }}</label>
			</div>
			<OAuthConnectButton v-else :request-url="requestUrl" />
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'
import SettingsTitle from '../components/settings/SettingsTitle'
import OAuthConnectButton from './OAuthConnectButton'

export default {
	name: 'PersonalSettings',

	components: {
		SettingsTitle, OAuthConnectButton,
	},

	data() {
		return {
			loading: false,
			state: loadState('integration_openproject', 'user-config'),
		}
	},

	computed: {
		requestUrl() {
			return this.state.request_url
		},
		connected() {
			if (!this.requestUrl) return false
			return this.state.token && this.state.token !== ''
				&& this.state.user_name && this.state.user_name !== ''
		},
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const zmToken = urlParams.get('openprojectToken')
		if (zmToken === 'success') {
			showSuccess(t('integration_openproject', 'Successfully connected to OpenProject!'))
		} else if (zmToken === 'error') {
			showError(t('integration_openproject', 'OAuth access token could not be obtained:') + ' ' + urlParams.get('message'))
		}
	},

	methods: {
		onLogoutClick() {
			this.state.token = ''
			this.saveOptions({ token: this.state.token, token_type: '' })
		},
		onNotificationChange(e) {
			this.state.notification_enabled = e.target.checked
			this.saveOptions({ notification_enabled: this.state.notification_enabled ? '1' : '0' })
		},
		onSearchChange(e) {
			this.state.search_enabled = e.target.checked
			this.saveOptions({ search_enabled: this.state.search_enabled ? '1' : '0' })
		},
		onNavigationChange(e) {
			this.state.navigation_enabled = e.target.checked
			this.saveOptions({ navigation_enabled: this.state.navigation_enabled ? '1' : '0' })
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_openproject/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_openproject', 'OpenProject options saved'))
					if (response.data.user_name !== undefined) {
						this.state.user_name = response.data.user_name
						if (this.state.token && response.data.user_name === '') {
							showError(t('integration_openproject', 'Incorrect access token'))
						}
					}
				})
				.catch((error) => {
					console.debug(error.response)
					const msg = error.response?.data?.errorMessage === 'Invalid token'
						? t('integration_openproject', 'Invalid token')
						: error.response?.data?.errorMessage === 'Not found'
							? t('integration_openproject', 'OpenProject instance not found')
							: error.response?.request?.responseText
					showError(
						t('integration_openproject', 'Failed to save OpenProject options')
						+ ': ' + msg
					)
				})
				.then(() => {
					this.loading = false
				})
		},
	},
}
</script>

<style scoped lang="scss">
#openproject-search-block {
	margin-top: 30px;
}

.openproject-grid-form label {
	line-height: 38px;
}

.openproject-grid-form input {
	width: 100%;
}

.openproject-grid-form {
	max-width: 600px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	button .icon {
		margin-bottom: -1px;
	}
}

#openproject_prefs .icon {
	display: inline-block;
	width: 32px;
}

#openproject_prefs .grid-form .icon {
	margin-bottom: -3px;
}

#openproject-content {
	margin-left: 40px;
}

#openproject-search-block .icon {
	width: 22px;
}

#openproject-content .oauth-connect--message {
	text-align: left !important;
}
</style>
