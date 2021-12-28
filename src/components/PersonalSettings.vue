<template>
	<div id="openproject_prefs" class="section">
		<h2>
			<a class="icon icon-openproject" />
			{{ t('integration_openproject', 'OpenProject integration') }}
		</h2>
		<p v-if="!showOAuth && !connected" class="settings-hint">
			{{ t('integration_openproject', 'To get your API access token yourself, go to the "Access token" section of your OpenProject account settings page.') }}
		</p>
		<div id="openproject-content">
			<div id="toggle-openproject-navigation-link">
				<input
					id="openproject-link"
					type="checkbox"
					class="checkbox"
					:checked="state.navigation_enabled"
					@input="onNavigationChange">
				<label for="openproject-link">{{ t('integration_openproject', 'Enable navigation link') }}</label>
			</div>
			<br><br>
			<p v-if="isInsecureUrl" class="settings-hint">
				<span class="icon icon-alert-outline" />
				{{ t('integration_openproject', 'Warning, connecting to your OpenProject instance via http is insecure.') }}
			</p>
			<div class="openproject-grid-form">
				<label for="openproject-url">
					<a class="icon icon-link" />
					{{ t('integration_openproject', 'OpenProject instance address') }}
				</label>
				<input id="openproject-url"
					v-model="state.url"
					type="text"
					:disabled="connected === true"
					:placeholder="t('integration_openproject', 'https://my.openproject.org')"
					@input="onInput">
				<label v-show="!showOAuth"
					for="openproject-token">
					<a class="icon icon-category-auth" />
					{{ t('integration_openproject', 'Access token') }}
				</label>
				<input v-show="!showOAuth"
					id="openproject-token"
					v-model="state.token"
					type="password"
					:disabled="connected === true"
					:placeholder="t('integration_openproject', 'OpenProject access token')"
					@input="onInput">
			</div>
			<button v-if="showOAuth && !connected"
				id="openproject-oauth"
				:disabled="loading === true"
				:class="{ loading }"
				@click="onOAuthClick">
				<span class="icon icon-external" />
				{{ t('integration_openproject', 'Connect to OpenProject') }}
			</button>
			<div v-if="connected" class="openproject-grid-form">
				<label class="openproject-connected">
					<a class="icon icon-checkmark-color" />
					{{ t('integration_openproject', 'Connected as {user}', { user: state.user_name }) }}
				</label>
				<button id="openproject-rm-cred" @click="onLogoutClick">
					<span class="icon icon-close" />
					{{ t('integration_openproject', 'Disconnect from OpenProject') }}
				</button>
			</div>
			<div v-if="connected" id="openproject-search-block">
				<input
					id="search-openproject"
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
				<input
					id="notification-openproject"
					type="checkbox"
					class="checkbox"
					:checked="state.notification_enabled"
					@input="onNotificationChange">
				<label for="notification-openproject">{{ t('integration_openproject', 'Enable notifications for activity in my work packages') }}</label>
			</div>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'PersonalSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_openproject', 'user-config'),
			initialToken: loadState('integration_openproject', 'user-config').token,
			loading: false,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_openproject/oauth-redirect'),
		}
	},

	computed: {
		isInsecureUrl() {
			return !this.state.url.startsWith('https://')
		},
		showOAuth() {
			return this.state.url.replace(/\/+$/, '') === this.state.oauth_instance_url.replace(/\/+$/, '')
				&& this.state.client_id
				&& this.state.client_secret
		},
		connected() {
			return this.state.token && this.state.token !== ''
				&& this.state.url && this.state.url !== ''
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
		onInput() {
			this.loading = true
			delay(() => {
				if (!this.state.url.startsWith('https://') && !this.state.url.startsWith('http://')) {
					this.state.url = 'https://' + this.state.url
				}
				const pattern = /^https?:\/\/[^ "]+$/
				if (pattern.test(this.state.url)) {
					this.saveOptions({
						url: this.state.url,
						token: this.state.token,
						token_type: this.showOAuth ? 'oauth' : 'access',
					})
				} else {
					this.saveOptions({
						url: '',
						token: this.state.token,
						token_type: this.showOAuth ? 'oauth' : 'access',
					})
				}
			}, 2000)()
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
		onOAuthClick() {
			const oauthState = Math.random().toString(36).substring(3)
			const requestUrl = this.state.url + '/oauth/authorize'
				+ '?client_id=' + encodeURIComponent(this.state.client_id)
				+ '&redirect_uri=' + encodeURIComponent(this.redirect_uri)
				+ '&response_type=code'
				+ '&state=' + encodeURIComponent(oauthState)

			const req = {
				values: {
					oauth_state: oauthState,
					redirect_uri: this.redirect_uri,
				},
			}
			const url = generateUrl('/apps/integration_openproject/config')
			axios.put(url, req)
				.then((response) => {
					window.location.replace(requestUrl)
				})
				.catch((error) => {
					showError(
						t('integration_openproject', 'Failed to save OpenProject OAuth state')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
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

.icon-openproject {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.theme--dark .icon-openproject {
	background-image: url(./../../img/app.svg);
}

#openproject-content {
	margin-left: 40px;
}

#openproject-search-block .icon {
	width: 22px;
}

</style>
