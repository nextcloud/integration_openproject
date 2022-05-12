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
		</div>
		<button v-if="!isAdminConfigOk" class="save-config-btn" @click="saveOptions">
			Save
		</button>
		<button v-else class="update-config-btn" @click="updateForm">
			Update
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
		}
	},
	methods: {
		updateForm() {
			const that = this
			OC.dialogs.confirmDestructive(
				t(
					'integration_openproject',
					'Are you sure you want to update the admin settings?'
					+ ' After saving, every connected users must need to re-connect to the Openproject instance.'
				),
				t('integration_openproject', 'Confirm Update'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('integration_openproject', 'Update'),
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

.save-config-btn, .update-config-btn {
	margin-left: 30px;
}
</style>
