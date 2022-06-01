<template>
	<div id="openproject_prefs" class="section">
		<SettingsTitle />
		<div class="form-content">
			<div class="openproject-server full-width">
				<FormHeading count="1"
					title="OpenProject Server"
					:is-complete="isServerHostStateComplete" />
				<FieldValue v-if="isServerHostStateComplete && !isServerHostFormInEdit"
					is-required class="pb-1"
					title="OpenProject host"
					:value="state.oauth_instance_url" />
				<TextInput v-if="isServerHostFormInEdit || !isServerHostStateComplete"
					id="openproject-oauth-instance"
					v-model="state.oauth_instance_url"
					is-required class="pb-3"
					label="OpenProject host"
					place-holder="https://www.my-openproject.com"
					hint-text="Please introduce your OpenProject host name" />
				<button v-if="isServerHostStateComplete && !isServerHostFormInEdit"
					class="edit-btn"
					@click="setServerHostFormToEditMode">
					<div class="icon pencil-icon" />
					<div>Edit server information</div>
				</button>
				<div v-else class="d-flex">
					<button v-if="isServerHostStateComplete"
						@click="setServerHostFormToViewMode"
					>
						Cancel
					</button>
					<button
							class="submit-btn submit-server"
							:class="{'submit-disabled': isServerHostStateDisabled}"
							@click="saveOpenProjectHostUrl">
						<div class="check-icon" />
						<span>Save</span>
						<div class="icon-loading" v-if="isServerStateLoading" />
					</button>
				</div>
			</div>
			<div class="openproject-oauth full-width">
				<FormHeading count="2"
					title="OpenProject OAuth settings"
					:is-complete="isOPOauthStateComplete"
					:is-disabled="!isServerHostStateComplete" />
				<div v-if="isServerHostStateComplete">
					<FieldValue v-if="isOPOauthStateComplete && !isOPOauthFormInEdit"
								is-required
								title="OpenProject OAuth client ID"
								:value="state.client_id" />
					<TextInput v-if="isOPOauthFormInEdit"
							   id="openproject-oauth-client-id"
							   v-model="state.client_id"
							   class="pb-3"
							   label="OpenProject OAuth client ID"
							   is-required
							   :hint-text="openProjectClientHint" />
					<FieldValue v-if="isOPOauthStateComplete && !isOPOauthFormInEdit"
								is-required class="pb-1"
								title="OpenProject OAuth client secret"
								:value="parsedOPClientSecret" />
					<TextInput v-if="isOPOauthFormInEdit"
							   id="openproject-oauth-client-secret"
							   v-model="state.client_secret"
							   is-required
							   class="pb-3"
							   label="OpenProject OAuth client secret"
							   :hint-text="openProjectClientHint" />
					<button v-if="isOPOauthStateComplete && !isOPOauthFormInEdit"
							class="edit-btn"
							@click="resetOPOauthClientValues">
						<div class="icon reset-icon" />
						<span>Reset OpenProject OAuth values</span>
					</button>
					<button v-else
							class="submit-btn submit-openproject-oauth"
							:class="{'submit-disabled': state.client_id === '' || state.client_secret === ''}"
							@click="saveOPOauthClientValues">
						<div class="check-icon" />
						<span>Save</span>
					</button>
				</div>
			</div>
			<div class="nextcloud-oauth full-width">
				<FormHeading count="3"
					title="Nextcloud OAuth client"
					:is-complete="isNcOauthStateComplete"
					:is-disabled="!isOPOauthStateComplete"
				/>
				<div v-if="state.nc_oauth_client && isOPOauthStateComplete">
					<FieldValue v-if="isNcOauthStateComplete"
								title="Nextcloud OAuth client ID"
								:value="state.nc_oauth_client.clientId"
								is-required />
					<div v-else class="d-flex">
						<TextInput id="nextcloud-oauth-client-id"
								   v-model="state.nc_oauth_client.clientId"
								   class="pb-3"
								   is-required
								   label="Nextcloud OAuth client ID"
								   :hint-text="nextcloudClientHint" />
						<button class="copy-btn copy-nc-id" @click="copyNCClientId">
							<div class="copy-icon" />
							<span>Copy value</span>
						</button>
					</div>
					<div v-if="isNcOauthStateComplete"
						 class="saved-info pb-1">
						<b class="title">
							Nextcloud OAuth client secret*:
						</b>
						&nbsp;
						<div v-if="inspectNCClientSecret">
							{{ state.nc_oauth_client.clientId }}
						</div>
						<div v-else>
							{{ parsedNcClientSecret }}
						</div>
						<div class="eye-icon" @click="inspectNCClientSecret = !inspectNCClientSecret" />
					</div>

					<div v-else class="d-flex">
						<TextInput id="nextcloud-oauth-client-secret"
								   v-model="state.nc_oauth_client.clientSecret"
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
					<button v-if="isNcOauthStateComplete"
							class="edit-btn"
							@click="resetNcOauthValues">
						<div class="icon reset-icon" />
						<span>Reset Nextcloud OAuth values</span>
					</button>
					<button v-else
							class="submit-btn submit-nextcloud-oauth"
							:class="{'submit-disabled': isNcOauthStateDisabled}"
							@click="formState.ncOauth = 'COMPLETED'">
						<div class="check-icon" />
						<span>Done</span>
					</button>
				</div>
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

const F_STATES = {
	COMPLETED: 'COMPLETED',
	INCOMPLETE: 'INCOMPLETE',
}

const F_MODES = {
	EDIT: 'EDIT',
	VIEW: 'VIEW',
}

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
			isOpenProjectInstanceValid: false,
			inspectNCClientSecret: false,
			inspectOPClientSecret: false,
			ncState: {
				client_id: '',
				client_secret: '',
			},
			formState: {
				server: F_STATES.INCOMPLETE,
				opOauth: F_STATES.INCOMPLETE,
				ncOauth: F_STATES.INCOMPLETE
			},
			formMode: {
				server: F_MODES.EDIT,
				opOauth: F_MODES.EDIT,
				ncOauth: F_MODES.EDIT
			},
			loadingState: {
				server: false,
				opOauth: false,
				ncOauth: false
			},
			state: loadState('integration_openproject', 'admin-config'),
			isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
		}
	},
	computed: {
		isServerStateLoading() {
			return this.loadingState.server
		},
		isOpOauthStateLoading() {
			return this.loadingState.opOauth
		},
		isNcOauthStateLoading() {
			return this.loadingState.ncOauth
		},
		isServerHostStateComplete() {
			return this.formState.server === F_STATES.COMPLETED
		},
		isServerHostFormInEdit() {
			return this.formMode.server === F_MODES.EDIT
		},
		isServerHostStateDisabled() {
			return this.state.oauth_instance_url === ''
		},
		isOPOauthStateComplete() {
			return this.formState.opOauth === F_STATES.COMPLETED
		},
		isOPOauthStateDisabled() {
			console.log(this.state.op_oauth_client_id, this.state.op_oauth_client_secret)
			return !this.state.op_oauth_client_id || !this.state.op_oauth_client_secret;
		},
		isOPOauthFormInEdit() {
			return this.formMode.opOauth === F_MODES.EDIT
		},
		isNcOauthStateComplete() {
			return this.formState.ncOauth === F_STATES.COMPLETED
		},
		isNcOauthStateDisabled() {
			return this.formState.opOauth !== F_STATES.COMPLETED
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
			return firstFour + '*'.repeat(30)
		},
		parsedNcClientSecret() {
			const firstFour = this.state.nc_oauth_client.clientSecret.substr(0, 4)
			return firstFour + '*'.repeat(30)
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
	created() {
		if (this.state) {
			if (this.state.oauth_instance_url) {
				this.formState.server = F_STATES.COMPLETED
				this.formMode.server = F_MODES.VIEW
			}
			if (this.state.client_secret && this.state.client_id) {
				this.formState.opOauth = F_STATES.COMPLETED
				this.formMode.opOauth = F_MODES.VIEW
				if (!this.state.nc_oauth_client) {
					this.createNCOAuthClient()
				}
			}
			if (this.state.nc_oauth_client) {
				if (this.state.nc_oauth_client.clientId && this.state.nc_oauth_client.clientSecret) {
					this.formState.ncOauth = F_STATES.COMPLETED
					this.formMode.ncOauth = F_MODES.VIEW
				}
			}
		}
	},
	methods: {
		translate(text) {
			return t('integration_openproject', text)
		},
		copyNCClientId() {
			navigator.clipboard.writeText(this.state.nc_oauth_client.clientId)
			showSuccess(this.translate('Nextcloud OAuth client ID copied to clipboard'))
		},
		copyNCClientSecret() {
			navigator.clipboard.writeText(this.state.nc_oauth_client.clientSecret)
			showSuccess(this.translate('Nextcloud OAuth client secret copied to clipboard'))
		},
		setServerHostFormToEditMode() {
			this.formMode.server = F_MODES.EDIT
		},
		setServerHostFormToViewMode() {
			this.formMode.server = F_MODES.VIEW
		},
		setOPOauthFormToEditMode() {
			this.formMode.opOauth = F_MODES.EDIT
		},
		setOPOauthFormToViewMode() {
			this.formMode.opOauth = F_MODES.VIEW
		},
		setNcOauthFormToEditMode() {
			this.formMode.ncOauth = F_MODES.EDIT
		},
		setNcOauthFormToViewMode() {
			this.formMode.ncOauth = F_MODES.VIEW
		},
		async saveOpenProjectHostUrl() {
			this.loadingState.server = true
			const req = {
				values: {
					oauth_instance_url: this.state.oauth_instance_url
				}
			}
			const isValid = await this.validateOpenProjectInstance()
			if (isValid) {
				await this.saveOPOptions(req)
				this.formState.server = F_STATES.COMPLETED
				this.formMode.server = F_MODES.VIEW
			}
			this.loadingState.server = false
		},
		async saveOPOauthClientValues() {
			const req = {
				values: {
					client_id: this.state.client_id,
					client_secret: this.state.client_secret,
				}
			}
			await this.saveOPOptions(req)
			if (this.isAdminConfigOk) {
				this.formMode.opOauth = F_MODES.VIEW
				this.formState.opOauth = F_STATES.COMPLETED
				await this.createNCOAuthClient()
			}
		},
		resetOPOauthClientValues() {
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
						await this.clearOpClientValues()
					}
				},
				true
			)
		},
		async clearOpClientValues() {
			const req = {
				values: {
					client_id: '',
					client_secret: '',
				}
			}
			const saved = await this.saveOPOptions(req)
			if (saved) {
				this.state.client_secret = ''
				this.state.client_id = ''
				this.formState.opOauth = F_STATES.INCOMPLETE
				this.formMode.opOauth = F_MODES.EDIT
			}
		},
		async saveOPOptions(req) {
			const url = generateUrl('/apps/integration_openproject/admin-config')
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
				this.formMode.ncOauth = F_MODES.VIEW
				this.formState.ncState = F_STATES.COMPLETED
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
						await this.createNCOAuthClient()
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
				this.$refs['openproject-oauth-instance-input'].focus()
				return
			}
			const saved = await this.saveOPOptions()
			if (saved) this.isOpenProjectInstanceValid = true
		}
	},
}
</script>

<style scoped lang="scss">
#openproject_prefs {
	.d-flex {
		display: flex;
		align-items: center;
	}
	.pb-1 {
		padding-bottom: .5rem;
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
		position: relative;
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
		max-width: 800px;
	}
	.icon-loading {
		min-height: 0;
	}
	.icon-loading:after {
		height: 14px;
		width: 14px;
		top: 6px;
		left: 20px;
	}
}

body.theme--dark, body[data-theme-dark], body[data-theme-dark-highcontrast] {
	.pencil-icon, .reset-icon, .eye-icon, .copy-icon {
		filter: invert(100%);
	}
}
</style>
