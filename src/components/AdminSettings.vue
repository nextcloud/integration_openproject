<template>
	<div id="openproject_prefs" class="section">
		<h2>
			<a class="icon icon-openproject" />
			{{ t('integration_openproject', 'OpenProject integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_openproject', 'If you want to allow your Nextcloud users to use OAuth to authenticate to a OpenProject instance, create an application in your OpenProject admin settings and put the Client ID (AppId) and the Client secret below.') }}
			<br><br>
			<span class="icon icon-details" />
			{{ t('integration_openproject', 'Make sure you set the "Redirect URI" to') }}
			<b> {{ redirect_uri }} </b>
		</p>
		<div class="grid-form">
			<label for="openproject-oauth-instance">
				<a class="icon icon-link" />
				{{ t('integration_openproject', 'OpenProject instance address') }}
			</label>
			<input id="openproject-oauth-instance"
				v-model="state.oauth_instance_url"
				type="text"
				:placeholder="t('integration_openproject', 'OpenProject address')"
				@input="onInput">
			<label for="openproject-client-id">
				<a class="icon icon-category-auth" />
				{{ t('integration_openproject', 'Client ID') }}
			</label>
			<input id="openproject-client-id"
				v-model="state.client_id"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_openproject', 'Client ID of the OAuth app in OpenProject')"
				@focus="readonly = false"
				@input="onInput">
			<label for="openproject-client-secret">
				<a class="icon icon-category-auth" />
				{{ t('integration_openproject', 'Client secret') }}
			</label>
			<input id="openproject-client-secret"
				v-model="state.client_secret"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_openproject', 'Client secret of the OAuth app in OpenProject')"
				@focus="readonly = false"
				@input="onInput">
		</div>
		<div>
			<input
				id="openproject-allow_individual_connection"
				v-model="state.allow_individual_connection"
				type="checkbox"
				class="checkbox"
				@input="onInput">
			<label for="openproject-allow_individual_connection">{{ t('integration_openproject', 'Allow users to have individual connections with OpenProject servers of their choice') }}</label>
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
	name: 'AdminSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_openproject', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_openproject/oauth-redirect'),
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			const that = this
			delay(() => {
				that.saveOptions()
			}, 2000)()
		},
		saveOptions() {
			const req = {
				values: {
					client_id: this.state.client_id,
					client_secret: this.state.client_secret,
					oauth_instance_url: this.state.oauth_instance_url,
					allow_individual_connection: this.state.allow_individual_connection,
				},
			}
			const url = generateUrl('/apps/integration_openproject/admin-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_openproject', 'OpenProject admin options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_openproject', 'Failed to save OpenProject admin options')
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
.grid-form label {
	line-height: 38px;
}

.grid-form input {
	width: 100%;
}

.grid-form {
	max-width: 500px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	margin-left: 30px;
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

</style>
