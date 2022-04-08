<template>
	<div id="openproject_prefs" class="section">
		<SettingsTitle />
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
				:placeholder="t('integration_openproject', 'OpenProject address')">
			<label for="openproject-client-id">
				<a class="icon icon-category-auth" />
				{{ t('integration_openproject', 'Client ID') }}
			</label>
			<input id="openproject-client-id"
				v-model="state.client_id"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_openproject', 'Client ID of the OAuth app in OpenProject')"
				@focus="readonly = false">
			<label for="openproject-client-secret">
				<a class="icon icon-category-auth" />
				{{ t('integration_openproject', 'Client secret') }}
			</label>
			<input id="openproject-client-secret"
				v-model="state.client_secret"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_openproject', 'Client secret of the OAuth app in OpenProject')"
				@focus="readonly = false">
			<button v-if="state.nc_oauth_client === null"
				@click="onNextcloudOauthCreateClick">
				{{ t('integration_openproject', 'Create a Nextcloud OAuth client for OpenProject') }}
			</button>
			<span v-if="state.nc_oauth_client === null" />
			<label v-if="state.nc_oauth_client !== null"
				for="nextcloud-client-id">
				<a class="icon icon-category-auth" />
				{{ t('integration_openproject', 'Nextcloud client ID') }}
			</label>
			<input v-if="state.nc_oauth_client !== null"
				id="openproject-client-id"
				type="text"
				:value="ncClientId"
				:readonly="true">
			<label v-if="state.nc_oauth_client !== null"
				for="nextcloud-client-secret">
				<a class="icon icon-category-auth" />
				{{ t('integration_openproject', 'Nextcloud client secret') }}
			</label>
			<input v-if="state.nc_oauth_client !== null"
				id="openproject-client-secret"
				type="text"
				:value="ncClientSecret"
				:readonly="true">
		</div>
		<button v-if="!isAdminConfigOk" class="save-config-btn" @click="validateOpenProjectInstance">
			Save
			<div v-if="isLoading" class="icon-loading" />
		</button>
		<button v-else class="update-config-btn" @click="validateOpenProjectInstance">
			Update
			<div v-if="isLoading" class="icon-loading" />
		</button>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'
import SettingsTitle from '../components/settings/SettingsTitle'
import { translate as t } from '@nextcloud/l10n'
import { STATE } from '../utils'

export default {
	name: 'AdminSettings',

	components: {
		SettingsTitle,
	},

	data() {
		return {
			state: loadState('integration_openproject', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_openproject/oauth-redirect'),
			loadingState: STATE.OK,
		}
	},
	computed: {
		isLoading() {
			return this.loadingState === STATE.LOADING
		},
		ncClientId() {
			return this.state.nc_oauth_client?.clientId
		},
		ncClientSecret() {
			return this.state.nc_oauth_client?.clientSecret
		},
	},
	methods: {
		updateForm() {
			const that = this
			OC.dialogs.confirmDestructive(
				t(
					'integration_openproject',
					'Are you sure you want to replace the OpenProject OAuth client details?'
					+ ' Every currently connected user will need to re-authorize this Nextcloud instance to have access to their OpenProject account.'
				),
				t('integration_openproject', 'Replace OpenProject OAuth client details'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('integration_openproject', 'Replace'),
					confirmClasses: 'error',
					cancel: t('integration_openproject', 'Cancel'),
				},
				async (result) => {
					if (result) {
						await that.saveOptions()
					}
				},
				true
			)
		},
		async saveOptions() {
			const req = {
				values: {
					client_id: this.state.client_id,
					client_secret: this.state.client_secret,
					oauth_instance_url: this.state.oauth_instance_url,
				},
			}
			const url = generateUrl('/apps/integration_openproject/admin-config')
			try {
				const response = await axios.put(url, req)
				// after successfully saving the admin credentials, the admin config status needs to be updated
				this.isAdminConfigOk = response?.data?.status === true
				showSuccess(t('integration_openproject', 'OpenProject admin options saved'))
			} catch (error) {
				console.debug(error)
				showError(
					t('integration_openproject', 'Failed to save OpenProject admin options')
				)
			}
		},
		async validateOpenProjectInstance() {
			this.loadingState = STATE.LOADING
			const url = generateUrl('/apps/integration_openproject/is-valid-op-instance')
			const response = await axios.post(url, { url: this.state.oauth_instance_url })
			if (response.data !== true) {
				showError(
					t('integration_openproject', 'No OpenProject detected at the URL')
				)
				this.loadingState = STATE.OK
				return
			}
			if (this.isAdminConfigOk) {
				this.updateForm()
			} else {
				await this.saveOptions()
			}
			this.loadingState = STATE.OK
		},
		onNextcloudOauthCreateClick() {
			const url = generateUrl('/apps/integration_openproject/nc-oauth')
			axios.post(url).then((response) => {
				this.state.nc_oauth_client = response.data
			}).catch((error) => {
				showError(
					t('integration_openproject', 'Failed to create Nextcloud OAuth client')
					+ ': ' + error.response.request.responseText
				)
			})
	},
}
</script>

<style scoped lang="scss">
#openproject_prefs {
	.icon {
		display: inline-block;
		width: 32px;
	}
	.grid-form {
		max-width: 500px;
		display: grid;
		grid-template: 1fr / 1fr 1fr;
		margin-left: 30px;

		label {
			line-height: 38px;
		}

		input {
			width: 100%;
		}

		.icon {
			margin-bottom: -3px;
		}
	}
}

.save-config-btn, .update-config-btn {
	margin-left: 30px;
}

body.theme--dark, body[data-theme-dark], body[data-theme-dark-highcontrast] {
	.icon-details, .icon-link, .icon-category-auth {
		filter: contrast(0);
	}
}

.icon-loading {
	min-height: 0;
}

.icon-loading:after {
	height: 14px;
	width: 14px;
	top: 0;
}
</style>
