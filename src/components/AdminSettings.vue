<template>
	<div id="openproject_prefs" class="section">
		<SettingsTitle />
		<div class="openproject-server-host">
			<FormHeading index="1"
				title="OpenProject Server"
				:is-complete="isServerHostStateComplete" />
			<FieldValue v-if="isServerHostStateComplete && !isServerHostFormInEdit"
				is-required
				class="pb-1"
				title="OpenProject host"
				:value="state.oauth_instance_url" />
			<TextInput v-if="isServerHostFormInEdit || !isServerHostStateComplete"
				id="openproject-oauth-instance"
				ref="openproject-oauth-instance-input"
				v-model="state.oauth_instance_url"
				is-required
				class="pb-2"
				label="OpenProject host"
				place-holder="https://www.my-openproject.com"
				hint-text="Please introduce your OpenProject host name"
				:error-message="serverHostErrMessage" />
			<Button v-if="isServerHostStateComplete && !isServerHostFormInEdit"
				icon-class="pencil-icon"
				text="Edit server information"
				@click="setServerHostFormToEditMode" />
			<div v-else class="d-flex">
				<Button v-if="isServerHostStateComplete"
					text="Cancel"
					@click="setServerHostFormToViewMode" />
				<Button class="submit-btn submit-server"
					:class="{'submit-disabled': !state.oauth_instance_url}"
					icon-class="check-icon"
					text="Save"
					:is-loading="loadingState.server"
					@click="saveOpenProjectHostUrl" />
			</div>
		</div>
		<div class="openproject-oauth-values">
			<FormHeading index="2"
				title="OpenProject OAuth settings"
				:is-complete="isOPOauthStateComplete"
				:is-disabled="isOPOAuthFormModeDisabled"
			/>
			<div v-if="isServerHostStateComplete">
				<FieldValue v-if="isOPOauthStateComplete && !isOPOauthFormInEdit"
					is-required
					title="OpenProject OAuth client ID"
					:value="state.client_id" />
				<TextInput v-if="isOPOauthFormInEdit"
					id="openproject-oauth-client-id"
					v-model="state.client_id"
					class="pb-2"
					label="OpenProject OAuth client ID"
					is-required
					with-copy-btn
					:hint-text="openProjectClientHint" />
				<FieldValue v-if="isOPOauthStateComplete && !isOPOauthFormInEdit"
					is-required
					class="pb-1"
					title="OpenProject OAuth client secret"
					encrypt-value
					:value="state.client_secret" />
				<TextInput v-if="isOPOauthFormInEdit"
					id="openproject-oauth-client-secret"
					v-model="state.client_secret"
					is-required
					with-copy-btn
					class="pb-2"
					label="OpenProject OAuth client secret"
					:hint-text="openProjectClientHint" />
				<Button v-if="isOPOauthStateComplete && !isOPOauthFormInEdit"
					icon-class="reset-icon"
					text="Reset OpenProject OAuth values"
					@click="resetOPOAuthClientValues" />
				<Button v-else
					class="submit-btn submit-openproject-oauth"
					:class="{'submit-disabled': !state.client_id || !state.client_secret}"
					icon-class="check-icon"
					text="Save"
					:is-loading="loadingState.opOauth"
					@click="saveOPOauthClientValues" />
			</div>
		</div>
		<div class="nextcloud-oauth-values">
			<FormHeading index="3"
				title="Nextcloud OAuth client"
				:is-complete="isNcOAuthStateCompleted"
				:is-disabled="isNcOAuthFormModeDisabled"
			/>
			<div v-if="state.nc_oauth_client && isOPOauthStateComplete">
				<TextInput v-if="isNcOauthFormInEdit"
					id="nextcloud-oauth-client-id"
					v-model="state.nc_oauth_client.clientId"
					class="pb-2"
					is-required
					with-copy-btn
					label="Nextcloud OAuth client ID"
					:hint-text="nextcloudClientHint" />
				<FieldValue v-else
					title="Nextcloud OAuth client ID"
					:value="state.nc_oauth_client.clientId"
					is-required />
				<TextInput v-if="isNcOauthFormInEdit"
					id="nextcloud-oauth-client-secret"
					v-model="state.nc_oauth_client.clientSecret"
					class="pb-2"
					is-required
					with-copy-btn
					label="Nextcloud OAuth client secret"
					:hint-text="nextcloudClientHint" />
				<FieldValue v-else
					title="Nextcloud OAuth client secret"
					is-required
					:value="ncClientSecret"
					encrypt-value
					with-inspection />
				<Button v-if="isNcOauthFormInEdit"
					class="submit-btn submit-nextcloud-oauth"
					:class="{'submit-disabled': isNcOauthStateDisabled}"
					icon-class="check-icon"
					text="Done"
					@click="setNCOAuthFormToView" />
				<Button v-else
					icon-class="reset-icon"
					text="Reset Nextcloud OAuth values"
					@click="resetNcOauthValues" />
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
import TextInput from './admin/TextInput'
import FormHeading from './admin/FormHeading'
import FieldValue from './admin/FieldValue'
import Button from './admin/Button'
import { F_STATES, F_MODES } from './../utils'

export default {
	name: 'AdminSettings',

	components: {
		Button,
		FieldValue,
		FormHeading,
		TextInput,
		SettingsTitle,
	},
	data() {
		return {
			formState: {
				server: F_STATES.INCOMPLETE,
				opOauth: F_STATES.INCOMPLETE,
				ncOauth: F_STATES.INCOMPLETE,
			},
			formMode: {
				// server host form is never disabled.
				// it's either editable or view only
				server: F_MODES.EDIT,
				opOauth: F_MODES.DISABLE,
				ncOauth: F_MODES.DISABLE,
			},
			loadingState: {},
			isOpenProjectInstanceValid: null,
			state: loadState('integration_openproject', 'admin-config'),
			isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
		}
	},
	computed: {
		serverHostErrMessage() {
			if (
				this.state.oauth_instance_url === ''
				|| this.isOpenProjectInstanceValid === null
				|| this.isOpenProjectInstanceValid
			) return false
			return 'Please introduce a valid OpenProject host name'
		},
		isServerHostStateComplete() {
			return this.formState.server === F_STATES.COMPLETE
		},
		isServerHostFormInEdit() {
			return this.formMode.server === F_MODES.EDIT
		},
		isOPOauthStateComplete() {
			return this.formState.opOauth === F_STATES.COMPLETE
		},
		isOPOAuthFormModeDisabled() {
			return this.formMode.opOauth === F_MODES.DISABLE
		},
		isOPOauthFormInEdit() {
			return this.formMode.opOauth === F_MODES.EDIT
		},
		isNcOAuthStateCompleted() {
			return this.formState.ncOauth === F_STATES.COMPLETE
		},
		isNcOAuthFormModeDisabled() {
			return this.formMode.ncOauth === F_MODES.DISABLE
		},
		isNcOauthFormInEdit() {
			return this.formMode.ncOauth === F_MODES.EDIT
		},
		isNcOauthStateDisabled() {
			return this.formState.opOauth !== F_STATES.COMPLETE
		},
		ncClientId() {
			return this.state.nc_oauth_client?.clientId
		},
		ncClientSecret() {
			return this.state.nc_oauth_client?.clientSecret
		},
		openProjectClientHint() {
			return this.translate('Go to your OpenProject')
				+ ' <a class="link" href="https://google.com">'
				+ this.translate('Administration > File storages')
				+ '</a> '
				+ this.translate('as an Administrator and start the setup and copy the values here.')
		},
		nextcloudClientHint() {
			return this.translate('Copy the following values back into the OpenProject')
				+ ' <a class="link" href="https://google.com">'
				+ this.translate('Administration > File storages')
				+ '</a> '
				+ this.translate('as an Administrator.')
		},
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
	created() {
		if (this.state) {
			if (this.state.oauth_instance_url) {
				this.formState.server = F_STATES.COMPLETE
				this.formMode.server = F_MODES.VIEW
			}
			if (this.state.client_secret && this.state.client_id) {
				this.formState.opOauth = F_STATES.COMPLETE
				this.formMode.opOauth = F_MODES.VIEW
			}
			if (this.state.oauth_instance_url) {
				if (!this.state.client_secret || !this.state.client_id) {
					this.formState.opOauth = F_STATES.INCOMPLETE
					this.formMode.opOauth = F_MODES.EDIT
				}
			}
			if (this.state.nc_oauth_client) {
				if (this.state.nc_oauth_client.clientId && this.state.nc_oauth_client.clientSecret) {
					this.formState.ncOauth = F_STATES.COMPLETE
					this.formMode.ncOauth = F_MODES.VIEW
				}
			}
		}
	},
	methods: {
		translate(text) {
			return t('integration_openproject', text)
		},
		setServerHostFormToEditMode() {
			this.formMode.server = F_MODES.EDIT
		},
		setServerHostFormToViewMode() {
			this.formMode.server = F_MODES.VIEW
		},
		setNCOAuthFormToView() {
			this.formMode.ncOauth = F_MODES.VIEW
		},
		async saveOpenProjectHostUrl() {
			this.loadingState.server = true
			await this.validateOpenProjectInstance()
			if (this.isOpenProjectInstanceValid) {
				await this.saveOPOptions()
				this.formState.server = F_STATES.COMPLETE
				this.formMode.server = F_MODES.VIEW
			}
			this.loadingState.server = false
		},
		async saveOPOauthClientValues() {
			this.loadingState.opOauth = true
			await this.saveOPOptions()
			if (this.isAdminConfigOk) {
				this.formMode.opOauth = F_MODES.VIEW
				this.formState.opOauth = F_STATES.COMPLETE
				// if we do not have NC OAuth client yet, a new client is created
				if (!this.state.nc_oauth_client) {
					this.createNCOAuthClient()
				}
			}
			this.loadingState.opOauth = false
		},
		resetOPOAuthClientValues() {
			OC.dialogs.confirmDestructive(
				this.translate(
					'Are you sure you want to replace the OpenProject OAuth client details?'
					+ ' Every currently connected user will need to re-authorize this Nextcloud'
					+ ' instance to have access to their OpenProject account.'
				),
				this.translate('Replace OpenProject OAuth client details'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: this.translate('Yes, Replace'),
					confirmClasses: 'error',
					cancel: this.translate('Cancel'),
				},
				async (result) => {
					if (result) {
						await this.clearOPClientValues()
					}
				},
				true
			)
		},
		async clearOPClientValues() {
			this.state.client_id = null
			this.state.client_secret = null
			const saved = await this.saveOPOptions()
			if (saved) {
				this.formState.opOauth = F_STATES.INCOMPLETE
				this.formMode.opOauth = F_MODES.EDIT
			}
		},
		async saveOPOptions() {
			const url = generateUrl('/apps/integration_openproject/admin-config')
			const req = {
				values: {
					client_id: this.state.client_id,
					client_secret: this.state.client_secret,
					oauth_instance_url: this.state.oauth_instance_url,
				},
			}
			try {
				const response = await axios.put(url, req)
				// after successfully saving the admin credentials, the admin config status needs to be updated
				this.isAdminConfigOk = response?.data?.status === true
				showSuccess(this.translate('OpenProject admin options saved'))
				return true
			} catch (error) {
				console.debug(error)
				showError(
					this.translate('Failed to save OpenProject admin options')
				)
				return false
			}
		},
		createNCOAuthClient() {
			const url = generateUrl('/apps/integration_openproject/nc-oauth')
			axios.post(url).then((response) => {
				this.state.nc_oauth_client = response.data
				// generate part is complete but still the NC OAuth form is set to edit mode
				// so that copy buttons will be available for the user
				this.formMode.ncOauth = F_MODES.EDIT
				this.formState.ncState = F_STATES.COMPLETE
			}).catch((error) => {
				showError(
					this.translate('Failed to create Nextcloud OAuth client')
					+ ': ' + error.response.request.responseText
				)
			})
		},
		resetNcOauthValues() {
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
						this.state.nc_oauth_client = null
						this.createNCOAuthClient()
					}
				},
				true
			)
		},
		async validateOpenProjectInstance() {
			const url = generateUrl('/apps/integration_openproject/is-valid-op-instance')
			const response = await axios.post(url, { url: this.state.oauth_instance_url })
			if (response.data !== true) {
				showError(
					this.translate('No OpenProject detected at the URL')
				)
				this.isOpenProjectInstanceValid = false
				this.$refs['openproject-oauth-instance-input']?.$refs?.textInput?.focus()
			} else this.isOpenProjectInstanceValid = true
		},
	},
}
</script>

<style scoped lang="scss">
#openproject_prefs {
	div {
		width: 100%;
	}
	.d-flex {
		display: flex;
		align-items: center;
	}
	.pb-1 {
		padding-bottom: .5rem;
	}
	.pb-2 {
		padding-bottom: 1rem;
	}
	.submit-btn {
		background: #397DDA;
		border: #397DDA;
		color: white;

	}
	.submit-disabled {
		background: #CCCCCC;
		color: #FFFFFF;
		pointer-events: none;
	}
}
</style>
<style lang="scss">
.pencil-icon {
	background-image: url(./../../img/pencil.svg);
}

.reset-icon {
	background-image: url(./../../img/reset.svg);
}

.check-icon {
	background-image: url(./../../img/check.svg);
}

body.theme--dark, body[data-theme-dark], body[data-theme-dark-highcontrast] {
	.pencil-icon, .reset-icon, .eye-icon, .copy-icon {
		filter: invert(100%);
	}
}
</style>
