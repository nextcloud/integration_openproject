<template>
	<div id="openproject_prefs" class="section">
		<SettingsTitle />
		<div class="form-content">
			<div class="openproject-server full-width">
				<FormHeading count="1"
					title="OpenProject Server"
					:is-complete="formState.server.isComplete" />
				<FieldValue v-if="formState.server.isComplete"
					is-required
					title="OpenProject host"
					:value="state.oauth_instance_url" />
				<TextInput v-else
					id="openproject-oauth-instance"
					v-model="state.oauth_instance_url"
					is-required
					label="OpenProject host"
					place-holder="https://www.my-openproject.com"
					hint-text="Please introduce your OpenProject host name" />
				<button v-if="formState.server.isComplete"
					class="edit-btn"
					@click="formState.server.isComplete = false">
					<div class="icon pencil-icon" />
					<div>Edit server information</div>
				</button>
				<button v-else
					class="submit-btn submit-server"
					:class="{'submit-disabled': formState.server.isDisabled}"
					@click="formState.server.isComplete = true">
					<div class="check-icon" />
					<span>Save</span>
				</button>
			</div>
			<div class="openproject-oauth full-width">
				<FormHeading count="2"
					title="OpenProject OAuth settings"
					:is-complete="formState.openProjectOauth.isComplete" />
				<FieldValue v-if="formState.openProjectOauth.isComplete"
					is-required
					title="OpenProject OAuth client ID"
					:value="state.client_id" />
				<TextInput v-else
					id="openproject-oauth-client-id"
					v-model="state.client_id"
					class="pb-3"
					label="OpenProject OAuth client ID"
					is-required
					:hint-text="openProjectClientHint" />
				<FieldValue v-if="formState.openProjectOauth.isComplete"
					is-required
					title="OpenProject OAuth client secret"
					:value="parsedOPClientSecret" />
				<TextInput v-else
					id="openproject-oauth-client-secret"
					v-model="state.client_id"
					is-required
					class="pb-3"
					label="OpenProject OAuth client secret"
					:hint-text="openProjectClientHint" />
				<button v-if="formState.openProjectOauth.isComplete"
					class="edit-btn"
					@click="updateOPOauthValues">
					<div class="icon reset-icon" />
					<span>Reset OpenProject OAuth values</span>
				</button>
				<button v-else
					class="submit-btn submit-openproject-oauth"
					:class="{'submit-disabled': formState.openProjectOauth.isDisabled}"
					@click="formState.openProjectOauth.isComplete = true">
					<div class="check-icon" />
					<span>Save</span>
				</button>
			</div>
			<div class="nextcloud-oauth full-width">
				<FormHeading count="3"
					title="Nextcloud OAuth client"
					:is-complete="formState.nextcloudOauth.isComplete" />
				<FieldValue v-if="formState.nextcloudOauth.isComplete"
					title="Nextcloud OAuth client ID"
					:value="ncState.client_id"
					is-required />
				<div v-else class="d-flex">
					<TextInput id="nextcloud-oauth-client-id"
						v-model="ncState.client_id"
						class="pb-3"
						is-required
						label="Nextcloud OAuth client ID"
						:hint-text="nextcloudClientHint" />
					<button class="copy-btn copy-nc-id" @click="copyNCClientId">
						<div class="copy-icon" />
						<span>Copy value</span>
					</button>
				</div>
				<div v-if="formState.nextcloudOauth.isComplete"
					class="saved-info">
					<b class="title">
						Nextcloud OAuth client secret*:
					</b>
					&nbsp;
					<div v-if="inspectNCClientSecret">
						{{ ncState.client_id }}
					</div>
					<div v-else>
						{{ parsedNcClientSecret }}
					</div>
					<div class="eye-icon" @click="inspectNCClientSecret = !inspectNCClientSecret" />
				</div>

				<div v-else class="d-flex">
					<TextInput id="nextcloud-oauth-client-secret"
						v-model="ncState.client_secret"
						class="pb-3"
						is-required
						label="Nextcloud OAuth client secret"
						:hint-text="nextcloudClientHint" />
					<button class="copy-btn copy-nc-secret"
						@click="copyNCClientSecret">
						<div class="copy-icon" />
						<span>Copy value</span>
					</button>
				</div>
				<button v-if="formState.nextcloudOauth.isComplete"
					class="edit-btn"
					@click="updateNcOauthValues">
					<div class="icon reset-icon" />
					<span>Reset Nextcloud OAuth values</span>
				</button>
				<button v-else
					class="submit-btn submit-nextcloud-oauth"
					:class="{'submit-disabled': formState.nextcloudOauth.isDisabled}"
					@click="formState.nextcloudOauth.isComplete = true">
					<div class="check-icon" />
					<span>Save</span>
				</button>
			</div>
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
import { translate as t } from '@nextcloud/l10n'
import { STATE } from '../utils'
import TextInput from './admin/TextInput'
import FormHeading from './admin/FormHeading'
import FieldValue from './admin/FieldValue'

export default {
	name: 'AdminSettings',

	components: {
		FieldValue,
		FormHeading,
		TextInput,
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
			inspectNCClientSecret: false,
			inspectOPClientSecret: false,
			formState: {
				server: {
					isComplete: true,
					isDisabled: false,
					active: false,
				},
				openProjectOauth: {
					isComplete: false,
					isDisabled: false,
					active: false,
				},
				nextcloudOauth: {
					isComplete: false,
					isDisabled: false,
					active: false,
				},
			},
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
		openProjectClientHint() {
			return 'Go to your OpenProject '
				+ '<a class="link" href="https://google.com">Administration > File storages</a> '
				+ 'as an Administrator and start the setup and copy the values here.'
		},
		nextcloudClientHint() {
			return 'Copy the following values back into the OpenProject '
				+ '<a class="link" href="https://google.com">Administration > File storages</a> '
				+ 'as an Administrator.'
		},
		parsedOPClientSecret() {
			const firstFour = this.state.client_secret.substr(0, 4)
			const secretLength = this.state.client_secret.length
			return firstFour + '*'.repeat(secretLength - 4)
		},
		parsedNcClientSecret() {
			const firstFour = this.ncState.client_secret.substr(0, 4)
			const secretLength = this.ncState.client_secret.length
			return firstFour + '*'.repeat(secretLength - 4)
		}
	},
	watch: {
		'state.oauth_instance_url': {
			handler(newValue, oldValue) {
				if (newValue !== oldValue) {
					this.state.nc_oauth_client = null
				}
			},
		},
	},
	methods: {
		translate(text) {
			return t('integration_openproject', text)
		},
		copyNCClientId() {
			navigator.clipboard.writeText(this.ncState.client_id)
			showSuccess(this.translate('Nextcloud OAuth client ID copied to clipboard'))
		},
		copyNCClientSecret() {
			navigator.clipboard.writeText(this.ncState.client_secret)
			showSuccess(this.translate('Nextcloud OAuth client secret copied to clipboard'))
		},
		updateNcOauthValues() {
			const that = this
			OC.dialogs.confirmDestructive(
				this.translate(
					'If you proceed you will need to update the settings in your file '
					+ 'storage with the new OpenProject OAuth credentials. Also, all users in '
					+ 'the file storage will need to reauthorize access to their OpenProject account.'
				),
				this.translate('Replace OpenProject OAuth values'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: this.translate('Yes, replace'),
					confirmClasses: 'error',
					cancel: this.translate('Cancel'),
				},
				async (result) => {
					if (result) {
						await that.saveOptions()
					}
				},
				true
			)
		},
		updateOPOauthValues() {
			const that = this
			OC.dialogs.confirmDestructive(
				this.translate(
					'Are you sure you want to replace the OpenProject OAuth client details?'
					+ ' Every currently connected user will need to re-authorize this Nextcloud'
					+ ' instance to have access to their OpenProject account.'
				),
				this.translate('Replace OpenProject OAuth client details'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: this.translate('Replace'),
					confirmClasses: 'error',
					cancel: this.translate('Cancel'),
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
				showSuccess(this.translate('OpenProject admin options saved'))
			} catch (error) {
				console.debug(error)
				showError(
					this.translate('Failed to save OpenProject admin options')
				)
			}
		},
		async validateOpenProjectInstance() {
			this.loadingState = STATE.LOADING
			const url = generateUrl('/apps/integration_openproject/is-valid-op-instance')
			const response = await axios.post(url, { url: this.state.oauth_instance_url })
			if (response.data !== true) {
				showError(
					this.translate('No OpenProject detected at the URL')
				)
				this.loadingState = STATE.OK
				this.isOpenProjectInstanceValid = false
				this.$refs['openproject-oauth-instance-input'].focus()
				return
			}
			if (this.isAdminConfigOk) {
				this.updateForm()
			} else {
				await this.saveOptions()
			}
			this.loadingState = STATE.OK
			this.isOpenProjectInstanceValid = true
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
	},
}
</script>

<style scoped lang="scss">
#openproject_prefs {
	.d-flex {
		display: flex;
		align-items: center;
	}
	.pb-3 {
		padding-bottom: 1rem;
	}
	.full-width {
		width: 100%;
	}
	.edit-btn {
		display: flex;
		align-items: center;
		justify-content: center;
		.icon {
			background-size: 16px;
			background-repeat: no-repeat;
			background-position: center;
			width: 16px;
			height: 16px;
			margin-right: 4px;
		}
	}
	.error {
		color: var(--color-error);
		border-color: var(--color-error);
	}
	.pencil-icon {
		background-image: url(./../../img/pencil.svg);
	}
	.reset-icon {
		background-image: url(./../../img/reset.svg);
	}
	.check-icon {
		height: 14px;
		width: 16px;
		background-repeat: no-repeat;
		background-position: center;
		background-image: url(./../../img/check.svg);
	}
	.eye-icon {
		margin-left: 6px;
		width: 16px;
		height: 10px;
		background-size: 16px;
		background-repeat: no-repeat;
		background-position: center;
		background-image: url(./../../img/eye.svg);
	}
	.copy-icon {
		margin-left: 6px;
		width: 15px;
		height: 16px;
		background-size: 16px;
		background-repeat: no-repeat;
		background-position: center;
		background-image: url(./../../img/copy.svg);
	}
	button {
		font-size: .875rem;
		line-height: 1.25rem;
		font-weight: 400;
	}
	.copy-btn {
		display: flex;
		align-items: center;
		margin-left: 10px;
		margin-top: -10px;
		span {
			margin-left: 6px;
		}
	}
	.submit-btn {
		display: flex;
		justify-content: center;
		align-items: center;
		margin: 6px 0;
		background: #397DDA;
		border: #397DDA;
		color: white;
		.icon {
			filter: invert(100%);
		}
		span {
			padding-left: 6px;
		}
	}
	.submit-disabled {
		background: #CCCCCC;
		color: #FFFFFF;
		pointer-events: none;
	}
	.form-content {
		max-width: 700px;
	}
}

body.theme--dark, body[data-theme-dark], body[data-theme-dark-highcontrast] {
	.pencil-icon, .reset-icon, .eye-icon, .copy-icon {
		filter: invert(100%);
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
