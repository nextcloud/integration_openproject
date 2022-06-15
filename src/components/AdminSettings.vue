<template>
	<div id="openproject_prefs" class="section">
		<SettingsTitle />
		<div class="openproject-server-host">
			<FormHeading index="1"
				title="OpenProject Server"
				:is-complete="isServerHostFormComplete" />
			<FieldValue v-if="isServerHostFormInView"
				is-required
				class="pb-1"
				title="OpenProject host"
				:value="state.oauth_instance_url" />
			<TextInput v-else
				id="openproject-oauth-instance"
				ref="openproject-oauth-instance-input"
				v-model="serverHostUrlForEdit"
				is-required
				class="pb-2"
				label="OpenProject host"
				place-holder="https://www.my-openproject.com"
				hint-text="Please introduce your OpenProject host name"
				:error-message="serverHostErrorMessage" />
			<div class="d-flex">
				<Button v-if="isServerHostFormInView"
					data-test-id="reset-server-host-btn"
					icon-class="pencil-icon"
					text="Edit server information"
					@click="setServerHostFormToEditMode" />
				<Button v-if="isServerHostFormComplete && isServerHostFormInEdit"
					text="Cancel"
					data-test-id="cancel-edit-server-host-btn"
					@click="setServerHostFormToViewMode" />
				<Button v-if="isServerHostFormInEdit"
					class="submit-btn"
					icon-class="check-icon"
					text="Save"
					:is-loading="loadingServerHostForm"
					:is-disabled="!serverHostUrlForEdit || serverHostUrlForEdit === state.oauth_instance_url"
					@click="saveOpenProjectHostUrl" />
			</div>
		</div>
		<div class="openproject-oauth-values">
			<FormHeading index="2"
				title="OpenProject OAuth settings"
				:is-complete="isOPOAuthFormComplete"
				:is-disabled="isOPOAuthFormInDisableMode" />
			<div v-if="isServerHostFormComplete">
				<FieldValue v-if="isOPOAuthFormInView"
					is-required
					:value="state.client_id"
					title="OpenProject OAuth client ID" />
				<TextInput v-else
					id="openproject-oauth-client-id"
					v-model="state.client_id"
					class="pb-2"
					is-required
					label="OpenProject OAuth client ID"
					:hint-text="openProjectClientHint" />
				<FieldValue v-if="isOPOAuthFormInView"
					is-required
					class="pb-1"
					encrypt-value
					title="OpenProject OAuth client secret"
					:value="state.client_secret" />
				<TextInput v-else
					id="openproject-oauth-client-secret"
					v-model="state.client_secret"
					is-required
					class="pb-2"
					label="OpenProject OAuth client secret"
					:hint-text="openProjectClientHint" />
				<Button v-if="isOPOAuthFormComplete && isOPOAuthFormInView"
					data-test-id="reset-op-oauth-btn"
					icon-class="reset-icon"
					text="Reset OpenProject OAuth values"
					@click="resetOPOAuthClientValues" />
				<Button v-else
					class="submit-btn"
					data-test-id="submit-op-oauth-btn"
					text="Save"
					icon-class="check-icon"
					:is-loading="loadingOPOauthForm"
					:is-disabled="!state.client_id || !state.client_secret"
					@click="saveOPOAuthClientValues" />
			</div>
		</div>
		<div class="nextcloud-oauth-values">
			<FormHeading index="3"
				title="Nextcloud OAuth client"
				:is-complete="isNcOAuthFormComplete"
				:is-disabled="isNcOAuthFormInDisableMode" />
			<div v-if="state.nc_oauth_client">
				<TextInput v-if="isNcOAuthFormInEdit"
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
				<TextInput v-if="isNcOAuthFormInEdit"
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
					encrypt-value
					with-inspection
					:value="ncClientSecret" />
				<Button v-if="isNcOAuthFormInEdit"
					class="submit-btn"
					text="Yes, I have copied these values"
					icon-class="check-icon"
					:is-disabled="!ncClientId || !ncClientSecret"
					@click="setNCOAuthFormToViewMode" />
				<Button v-else
					data-test-id="reset-nc-oauth-btn"
					icon-class="reset-icon"
					text="Reset Nextcloud OAuth values"
					@click="resetNcOauthValues" />
			</div>
		</div>
	</div>
</template>

<script>
import util from 'util'
import axios from '@nextcloud/axios'
import '@nextcloud/dialogs/styles/toast.scss'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import Button from './admin/Button'
import TextInput from './admin/TextInput'
import FieldValue from './admin/FieldValue'
import FormHeading from './admin/FormHeading'
import SettingsTitle from '../components/settings/SettingsTitle'
import { F_MODES } from './../utils'

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
			formMode: {
				// server host form is never disabled.
				// it's either editable or view only
				server: F_MODES.EDIT,
				opOauth: F_MODES.DISABLE,
				ncOauth: F_MODES.DISABLE,
			},
			isFormCompleted: {
				server: false, opOauth: false, ncOauth: false,
			},
			loadingServerHostForm: false,
			loadingOPOauthForm: false,
			isOpenProjectInstanceValid: null,
			state: loadState('integration_openproject', 'admin-config'),
			isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
			serverHostUrlForEdit: null,
		}
	},
	computed: {
		ncClientId() {
			return this.state.nc_oauth_client?.clientId
		},
		ncClientSecret() {
			return this.state.nc_oauth_client?.clientSecret
		},
		serverHostErrorMessage() {
			if (
				this.serverHostUrlForEdit === ''
				|| this.isOpenProjectInstanceValid === null
				|| this.isOpenProjectInstanceValid
			) return null
			return 'Please introduce a valid OpenProject host name'
		},
		isServerHostFormComplete() {
			return this.isFormCompleted.server
		},
		isOPOAuthFormComplete() {
			return this.isFormCompleted.opOauth
		},
		isNcOAuthFormComplete() {
			return this.isFormCompleted.ncOauth
		},
		isServerHostFormInView() {
			return this.formMode.server === F_MODES.VIEW
		},
		isOPOAuthFormInView() {
			return this.formMode.opOauth === F_MODES.VIEW
		},
		isServerHostFormInEdit() {
			return this.formMode.server === F_MODES.EDIT
		},
		isNcOAuthFormInEdit() {
			return this.formMode.ncOauth === F_MODES.EDIT
		},
		isOPOAuthFormInDisableMode() {
			return this.formMode.opOauth === F_MODES.DISABLE
		},
		isNcOAuthFormInDisableMode() {
			return this.formMode.ncOauth === F_MODES.DISABLE
		},
		adminFileStorageHref() {
			let hostPart = ''
			const urlPart = '%sadmin/settings/storages'
			if (this.state?.oauth_instance_url.endsWith('/')) {
				hostPart = this.state.oauth_instance_url
			} else hostPart = this.state.oauth_instance_url + '/'
			return util.format(urlPart, hostPart)
		},
		openProjectClientHint() {
			return this.translate('Go to your OpenProject'
				+ ` <a class="link" href="${this.adminFileStorageHref}" target="_blank"`
				+ 'title="Administration > File storages">'
				+ 'Administration > File storages'
				+ '</a> '
				+ 'as an Administrator and start the setup and copy the values here.')
		},
		nextcloudClientHint() {
			return this.translate('Copy the following values back into the OpenProject'
				+ ` <a class="link" href="${this.adminFileStorageHref}" target="_blank"`
				+ 'title="Administration > File storages">'
				+ 'Administration > File storages'
				+ '</a> '
				+ 'as an Administrator.')
		},
	},
	created() {
		if (this.state) {
			if (this.state.oauth_instance_url) {
				this.formMode.server = F_MODES.VIEW
				this.isFormCompleted.server = true
			}
			if (!!this.state.client_id && !!this.state.client_secret) {
				this.formMode.opOauth = F_MODES.VIEW
				this.isFormCompleted.opOauth = true
			}
			if (this.state.oauth_instance_url) {
				if (!this.state.client_id || !this.state.client_secret) {
					this.formMode.opOauth = F_MODES.EDIT
				}
			}
			if (this.state.nc_oauth_client) {
				this.formMode.ncOauth = F_MODES.VIEW
				this.isFormCompleted.ncOauth = true
			}
		}
	},
	methods: {
		translate(text) {
			return t('integration_openproject', text)
		},
		setServerHostFormToViewMode() {
			this.formMode.server = F_MODES.VIEW
		},
		setServerHostFormToEditMode() {
			this.formMode.server = F_MODES.EDIT
			// set the edit variable to the current saved value
			this.serverHostUrlForEdit = this.state.oauth_instance_url
			this.isOpenProjectInstanceValid = null
		},
		setNCOAuthFormToViewMode() {
			this.formMode.ncOauth = F_MODES.VIEW
			this.isFormCompleted.ncOauth = true
		},
		async saveOpenProjectHostUrl() {
			this.loadingServerHostForm = true
			await this.validateOpenProjectInstance()
			if (this.isOpenProjectInstanceValid) {
				const saved = await this.saveOPOptions()
				if (saved) {
					this.state.oauth_instance_url = this.serverHostUrlForEdit
					this.formMode.server = F_MODES.VIEW
					this.isFormCompleted.server = true
					if (this.isFormCompleted.opOauth === true) {
						// (re)create the Nextcloud OAuth client if we
						// 1. already have a complete OpenProject OAuth values
						// 2. already have a complete Nextcloud OAuth values
						// if we do not have Nextcloud OAuth client created yet,
						// it will be created automatically while saving the 2nd form i.e. OpenProject OAuth Values form
						if (this.isFormCompleted.ncOauth === true) {
							await this.createNCOAuthClient()
						}
					} else {
						// set the OpenProject OAuth values form to edit mode if not completed yet
						this.formMode.opOauth = F_MODES.EDIT
					}
				}
			}
			this.loadingServerHostForm = false
		},
		async saveOPOAuthClientValues() {
			await this.saveOPOptions()
			if (this.isAdminConfigOk) {
				this.formMode.opOauth = F_MODES.VIEW
				this.isFormCompleted.opOauth = true

				// if we do not have Nextcloud OAuth client yet, a new client is created
				if (!this.state.nc_oauth_client) {
					this.createNCOAuthClient()
				}
			}
		},
		resetOPOAuthClientValues() {
			OC.dialogs.confirmDestructive(
				this.translate(
					'If you proceed you will need to update these settings with the new'
					+ ' OpenProject OAuth credentials. Also, all users will need to reauthorize'
					+ ' access to their OpenProject account.'
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
						await this.clearOPOAuthClientValues()
					}
				},
				true
			)
		},
		async clearOPOAuthClientValues() {
			this.formMode.opOauth = F_MODES.EDIT
			this.isFormCompleted.opOauth = false
			this.state.client_id = null
			this.state.client_secret = null
			const saved = await this.saveOPOptions()
			if (!saved) {
				this.formMode.opOauth = F_MODES.VIEW
				this.isFormCompleted.opOauth = true
			}
		},
		async validateOpenProjectInstance() {
			const url = generateUrl('/apps/integration_openproject/is-valid-op-instance')
			const response = await axios.post(url, { url: this.serverHostUrlForEdit })
			if (response.data !== true) {
				showError(
					this.translate('No OpenProject detected at the URL')
				)
				this.isOpenProjectInstanceValid = false
				await this.$nextTick()
				await this.$refs['openproject-oauth-instance-input']?.$refs?.textInput?.focus()
			} else {
				this.isOpenProjectInstanceValid = true
				this.state.oauth_instance_url = this.serverHostUrlForEdit
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
		resetNcOauthValues() {
			OC.dialogs.confirmDestructive(
				this.translate(
					'If you proceed you will need to update the settings in your OpenProject '
					+ 'with the new Nextcloud OAuth credentials. Also, all users in OpenProject '
					+ 'will need to reauthorize access to their Nextcloud account.'
				),
				this.translate('Replace Nextcloud OAuth values'),
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
		createNCOAuthClient() {
			const url = generateUrl('/apps/integration_openproject/nc-oauth')
			axios.post(url).then((response) => {
				this.state.nc_oauth_client = response.data
				// generate part is complete but still the NC OAuth form is set to
				// edit mode and not completed state so that copy buttons will be available for the user
				this.formMode.ncOauth = F_MODES.EDIT
				this.isFormCompleted.ncOauth = false
			}).catch((error) => {
				showError(
					this.translate('Failed to create Nextcloud OAuth client')
					+ ': ' + error.response.request.responseText
				)
			})
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
