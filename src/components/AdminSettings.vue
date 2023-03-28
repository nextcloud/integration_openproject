<template>
	<div id="openproject_prefs" class="section">
		<SettingsTitle />
		<div class="openproject-server-host">
			<FormHeading index="1"
				:title="t('integration_openproject', 'OpenProject server')"
				:is-complete="isServerHostFormComplete" />
			<FieldValue v-if="isServerHostFormInView"
				is-required
				class="pb-1"
				:title="t('integration_openproject', 'OpenProject host')"
				:value="state.openproject_instance_url" />
			<TextInput v-else
				id="openproject-oauth-instance"
				ref="openproject-oauth-instance-input"
				v-model="serverHostUrlForEdit"
				is-required
				:read-only="isServerHostUrlReadOnly"
				class="pb-1"
				:label="t('integration_openproject', 'OpenProject host')"
				place-holder="https://www.my-openproject.com"
				:hint-text="t('integration_openproject', 'Please introduce your OpenProject host name')"
				:error-message="serverHostErrorMessage"
				:error-message-details="openProjectNotReachableErrorMessageDetails"
				@click="isServerHostUrlReadOnly = false"
				@input="isOpenProjectInstanceValid = null" />
			<div class="form-actions">
				<NcButton v-if="isServerHostFormInView"
					data-test-id="reset-server-host-btn"
					@click="setServerHostFormToEditMode">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
					{{ t('integration_openproject', 'Edit server information') }}
				</NcButton>
				<NcButton v-if="isServerHostFormComplete && isServerHostFormInEdit"
					class="mr-2"
					data-test-id="cancel-edit-server-host-btn"
					@click="setServerHostFormToViewMode">
					{{ t('integration_openproject', 'Cancel') }}
				</NcButton>
				<NcButton v-if="isServerHostFormInEdit"
					type="primary"
					data-test-id="submit-server-host-form-btn"
					:disabled="!serverHostUrlForEdit || serverHostUrlForEdit === state.openproject_instance_url"
					@click="saveOpenProjectHostUrl">
					<template #icon>
						<LoadingIcon v-if="loadingServerHostForm" class="loading-spinner" :size="20" />
						<CheckBoldIcon v-else :size="20" />
					</template>
					{{ t('integration_openproject', 'Save') }}
				</NcButton>
			</div>
		</div>
		<div class="openproject-oauth-values">
			<FormHeading index="2"
				:title="t('integration_openproject', 'OpenProject OAuth settings')"
				:is-complete="isOPOAuthFormComplete"
				:is-disabled="isOPOAuthFormInDisableMode" />
			<div v-if="isServerHostFormComplete">
				<FieldValue v-if="isOPOAuthFormInView"
					is-required
					:value="state.openproject_client_id"
					title="OpenProject OAuth client ID" />
				<TextInput v-else
					id="openproject-oauth-client-id"
					v-model="state.openproject_client_id"
					class="py-1"
					is-required
					label="OpenProject OAuth client ID"
					:hint-text="openProjectClientHint" />
				<FieldValue v-if="isOPOAuthFormInView"
					is-required
					class="pb-1"
					encrypt-value
					title="OpenProject OAuth client secret"
					:value="state.openproject_client_secret" />
				<TextInput v-else
					id="openproject-oauth-client-secret"
					v-model="state.openproject_client_secret"
					is-required
					class="py-1"
					label="OpenProject OAuth client secret"
					:hint-text="openProjectClientHint" />
				<div class="form-actions">
					<NcButton v-if="isOPOAuthFormComplete && isOPOAuthFormInView"
						data-test-id="reset-op-oauth-btn"
						@click="resetOPOAuthClientValues">
						<template #icon>
							<AutoRenewIcon :size="20" />
						</template>
						{{ t('integration_openproject', 'Replace OpenProject OAuth values') }}
					</NcButton>
					<NcButton v-else
						data-test-id="submit-op-oauth-btn"
						type="primary"
						:disabled="!state.openproject_client_id || !state.openproject_client_secret"
						@click="saveOPOAuthClientValues">
						<template #icon>
							<LoadingIcon v-if="loadingOPOauthForm" class="loading-spinner" :size="20" />
							<CheckBoldIcon v-else :size="20" />
						</template>
						{{ t('integration_openproject', 'Save') }}
					</NcButton>
				</div>
			</div>
		</div>
		<div class="nextcloud-oauth-values">
			<FormHeading index="3"
				:title="t('integration_openproject', 'Nextcloud OAuth client')"
				:is-complete="isNcOAuthFormComplete"
				:is-disabled="isNcOAuthFormInDisableMode" />
			<div v-if="state.nc_oauth_client">
				<TextInput v-if="isNcOAuthFormInEdit"
					id="nextcloud-oauth-client-id"
					v-model="state.nc_oauth_client.nextcloud_client_id"
					class="py-1"
					read-only
					is-required
					with-copy-btn
					label="Nextcloud OAuth client ID"
					:hint-text="nextcloudClientHint" />
				<FieldValue v-else
					title="Nextcloud OAuth client ID"
					:value="state.nc_oauth_client.nextcloud_client_id"
					is-required />
				<TextInput v-if="isNcOAuthFormInEdit"
					id="nextcloud-oauth-client-secret"
					v-model="state.nc_oauth_client.nextcloud_client_secret"
					class="py-1"
					read-only
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
				<div class="form-actions">
					<NcButton v-if="isNcOAuthFormInEdit"
						type="primary"
						:disabled="!ncClientId || !ncClientSecret"
						data-test-id="submit-nc-oauth-values-form-btn"
						@click="setNCOAuthFormToViewMode">
						<template #icon>
							<CheckBoldIcon :size="20" />
						</template>
						{{ t('integration_openproject', 'Yes, I have copied these values') }}
					</NcButton>
					<NcButton v-else
						data-test-id="reset-nc-oauth-btn"
						@click="resetNcOauthValues">
						<template #icon>
							<AutoRenewIcon :size="20" />
						</template>
						{{ t('integration_openproject', 'Replace Nextcloud OAuth values') }}
					</NcButton>
				</div>
			</div>
		</div>
		<div class="managecd-projectfolders-and-password">
			<FormHeading index="4"
				:is-managed-project-heading="true"
				:is-complete="isManagedGroupFolderSetUpFormInEdit ? false : state.app_password_set ? true : oPSystemPassword !== null"
				:title="t('integration_openproject', 'Managed project folders (recommended)')"
				:is-managed-folder-in-active="(isManagedGroupFolderSetUpFormInEdit) ? false : isManagedFolderActive === false && state.managed_folder_state === false"
				:is-disabled="isManagedGroupFolderSetUpInDisableMode" />
			<div v-if="state.default_managed_folders">
				<div v-if="isManagedGroupFolderSetUpFormInEdit">
					<CheckboxRadioSwitch type="switch" :checked="isGroupfolderSetupAutomaticallyReady" @update:checked="changeGroupFolderSetUpState">
						<b>Automatically managed folders</b>
					</CheckboxRadioSwitch>
					<div v-if="isGroupfolderSetupAutomaticallyReady === false" class="complete-without-groupfolders">
						<p class="managed-folder-description">
							{{
								t('integration_openproject', 'We recommend using this functionality but is not mandatory. Please activate in case you want to use the automatic creation and management of project folders.')
							}}
						</p>
						<div class="form-actions">
							<Button type="primary"
								data-test-id="complete-without-projectfolders-form-btn"
								@click="completeIntegrationWithoutGroupFolderSetUp">
								<template #icon>
									<CheckBoldIcon :size="20" />
								</template>
								{{
									t('integration_openproject', iskeepCurrentCompleteWithoutIntegration)
								}}
							</Button>
						</div>
					</div>
					<div v-else>
						<p class="managed-folder-description">
							{{
								t('integration_openproject', 'Let OpenProject create folders per project automatically. It will ensure that every team member has always the correct access permission.')
							}}
						</p>
						<br>
						<b>OpenProject OAuth client ID</b>
						<p class="managed-folder-description">
							{{
								t('integration_openproject', 'For automatically managing project folders, this app needs to setup a special group folde, assigned to a group and managed by a folder,each called "OpenProject".')
							}} <br>
							{{
								t('integration_openproject', 'The app will never delete files or folders, even if you deactivate this later')
							}}
						</p>

						<div v-if="groupFolderSetUpError">
							<p class="groupfolder-error">
								{{ groupFolderSetUpError }}
							</p>
						</div>
						<div class="form-actions">
							<Button v-if="groupFolderSetUpError === null"
								type="primary"
								data-test-id="complete-with-projectfolders-form-btn"
								@click="checkForErrorOrSetUpOpenProjectGroupFolders">
								<template #icon>
									<LoadingIcon v-if="loadingSetUpGroupFolder" class="loading-spinner" :size="20" />
									<CheckBoldIcon v-else :size="20" />
								</template>
								{{ t('integration_openproject', iskeepCurrentCompleteIntegration) }}
							</Button>
							<Button v-else-if="groupFolderSetUpError"
								type="primary"
								data-test-id="complete-with-projectfolders-form-btn">
								<template #icon>
									<CheckBoldIcon :size="20" />
								</template>
								{{ t('integration_openproject', 'Retry setup OpenProject user, group and folder') }}
							</Button>
						</div>
					</div>
				</div>
				<div v-else>
					<b>Automatic managed folders:</b> {{ state.app_password_set ? "Active" : oPSystemPassword !== null ? t('integration_openproject', 'Active') : t('integration_openproject', 'Inactive') }}
					<div class="form-actions">
						<Button
							data-test-id="reset-server-host-btn"
							@click="setManagedGroupFolderSetUpToEditMode">
							<template #icon>
								<PencilIcon :size="20" />
							</template>
							{{ t('integration_openproject', 'Edit managed project folders') }}
						</Button>
					</div>
				</div>
			</div>
		</div>
		<div v-if="state.managed_folder_state">
			<FormHeading index="5"
				:title="t('integration_openproject', 'OpenProject system user password')"
				:is-complete="isOPSystemPasswordFormComplete"
				:is-disabled="isOPSystemPasswordInDisableMode" />
			<div v-if="state.app_password_set">
				<TextInput v-if="isOpSystemPasswordFormInEdit"
					id="openproject-system-password"
					v-model="oPSystemPassword"
					class="py-1"
					read-only
					is-required
					with-copy-btn
					label="OpenProject application password"
					:hint-text="nextcloudClientHint" />
				<FieldValue v-else
					title="OpenProject System Password"
					is-required
					hide-value
					with-inspection
					value="" />
				<div class="form-actions">
					<Button v-if="isOpSystemPasswordFormInEdit"
						type="primary"
						:disabled="!opSystemPassword"
						data-test-id="submit-op-system-password-form-btn"
						@click="setOPSytemPasswordToViewMode">
						<template #icon>
							<CheckBoldIcon :size="20" />
						</template>
						{{ t('integration_openproject', 'Yes, I have copied these values') }}
					</Button>
					<Button v-else
						data-test-id="reset-op-system-password"
						@click="resetOPSystemPassword">
						<template #icon>
							<AutoRenewIcon :size="20" />
						</template>
						{{ t('integration_openproject', 'Replace password') }}
					</Button>
				</div>
			</div>
		</div>
		<NcButton id="reset-all-app-settings-btn"
			type="error"
			:disabled="isResetButtonDisabled"
			@click="resetAllAppValuesConfirmation">
			<template #icon>
				<RestoreIcon :size="20" />
			</template>
			{{ t('integration_openproject', 'Reset') }}
		</NcButton>
		<div v-if="isIntegrationComplete" class="default-prefs">
			<h2>{{ t('integration_openproject', 'Default user settings') }}</h2>
			<p>
				{{ t('integration_openproject', 'A new user will receive these defaults and they will be applied to the integration app till the user changes them.') }}
			</p>
			<br>
			<CheckBox v-model="state.default_enable_navigation"
				input-id="default-prefs--link"
				:label="t('integration_openproject', 'Enable navigation link')"
				@input="setDefaultConfig" />
			<CheckBox v-model="state.default_enable_unified_search"
				input-id="default-prefs--u-search"
				:label="t('integration_openproject', 'Enable unified search for tickets')"
				@input="setDefaultConfig" />
		</div>
	</div>
</template>

<script>
import util from 'util'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'
import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import LoadingIcon from 'vue-material-design-icons/Loading.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import AutoRenewIcon from 'vue-material-design-icons/Autorenew.vue'
import TextInput from './admin/TextInput.vue'
import FieldValue from './admin/FieldValue.vue'
import FormHeading from './admin/FormHeading.vue'
import CheckBox from '../components/settings/CheckBox.vue'
import SettingsTitle from '../components/settings/SettingsTitle.vue'
import { F_MODES } from '../utils.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'

export default {
	name: 'AdminSettings',
	components: {
		NcButton,
		FieldValue,
		FormHeading,
		TextInput,
		SettingsTitle,
		CheckBoldIcon,
		PencilIcon,
		LoadingIcon,
		AutoRenewIcon,
		RestoreIcon,
		CheckBox,
		CheckboxRadioSwitch,
	},
	data() {
		return {
			formMode: {
				// server host form is never disabled.
				// it's either editable or view only
				server: F_MODES.EDIT,
				opOauth: F_MODES.DISABLE,
				ncOauth: F_MODES.DISABLE,
				opSystemPassword: F_MODES.DISABLE,
				managedGroupFolderSetUp: F_MODES.DISABLE,
			},
			isFormCompleted: {
				server: false, opOauth: false, ncOauth: false, opSystemPassword: false, managedGroupFolderSetUp: false,
			},
			loadingServerHostForm: false,
			loadingSetUpGroupFolder: false,
			loadingOPOauthForm: false,
			isOpenProjectInstanceValid: null,
			openProjectNotReachableErrorMessage: null,
			openProjectNotReachableErrorMessageDetails: null,
			state: loadState('integration_openproject', 'admin-config'),
			isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
			serverHostUrlForEdit: null,
			isServerHostUrlReadOnly: true,
			oPOAuthTokenRevokeStatus: null,
			oPSystemPassword: null,
			isGroupfolderSetupAutomaticallyReady: null,
			groupFolderSetUpError: null,
			isManagedFolderActive: null,
			iskeepCurrentCompleteWithoutIntegration: 'Keep Current Change',
			iskeepCurrentCompleteIntegration: 'Setup OpenProject user,group and folder',
		}
	},
	computed: {
		ncClientId() {
			return this.state.nc_oauth_client?.nextcloud_client_id
		},
		ncClientSecret() {
			return this.state.nc_oauth_client?.nextcloud_client_secret
		},
		opSystemPassword() {
			return this.state.app_password_set
		},
		serverHostErrorMessage() {
			if (
				this.serverHostUrlForEdit === ''
				|| this.isOpenProjectInstanceValid === null
				|| this.isOpenProjectInstanceValid
			) return null
			return this.openProjectNotReachableErrorMessage
		},
		isServerHostFormComplete() {
			return this.isFormCompleted.server
		},
		isOPOAuthFormComplete() {
			return this.isFormCompleted.opOauth
		},
		isManagedGroupFolderSetUpComplete() {
			return this.isFormCompleted.managedGroupFolderSetUp
		},
		isOPSystemPasswordFormComplete() {
			return this.isFormCompleted.opSystemPassword
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
		isOpSystemPasswordFormInEdit() {
			return this.formMode.opSystemPassword === F_MODES.EDIT
		},
		isManagedGroupFolderSetUpFormInEdit() {
			return this.formMode.managedGroupFolderSetUp === F_MODES.EDIT
		},
		isNcOAuthFormInDisableMode() {
			return this.formMode.ncOauth === F_MODES.DISABLE
		},
		isManagedGroupFolderSetUpInDisableMode() {
			return this.formMode.managedGroupFolderSetUp === F_MODES.DISABLE
		},
		isOPSystemPasswordInDisableMode() {
			return this.formMode.opSystemPassword === F_MODES.DISABLE
		},
		adminFileStorageHref() {
			let hostPart = ''
			const urlPart = '%sadmin/settings/storages'
			if (this.state?.openproject_instance_url.endsWith('/')) {
				hostPart = this.state.openproject_instance_url
			} else hostPart = this.state.openproject_instance_url + '/'
			return util.format(urlPart, hostPart)
		},
		openProjectClientHint() {
			const linkText = t('integration_openproject', 'Administration > File storages')
			const htmlLink = `<a class="link" href="${this.adminFileStorageHref}" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'Go to your OpenProject {htmlLink} as an Administrator and start the setup and copy the values here.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		nextcloudClientHint() {
			const linkText = t('integration_openproject', 'Administration > File storages')
			const htmlLink = `<a class="link" href="${this.adminFileStorageHref}" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'Copy the following values back into the OpenProject {htmlLink} as an Administrator.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		isIntegrationComplete() {
			return (this.isServerHostFormComplete
				 && this.isOPOAuthFormComplete
				 && this.isNcOAuthFormComplete
				&& this.isManagedGroupFolderSetUpComplete
			)
		},
		isResetButtonDisabled() {
			return !(this.state.openproject_client_id || this.state.openproject_client_secret || this.state.openproject_instance_url)
		},
	},
	created() {
		this.init()
	},
	methods: {
		init() {
			if (this.state) {
				// console.log(this.state)
				if (this.state.openproject_instance_url) {
					this.formMode.server = F_MODES.VIEW
					this.isFormCompleted.server = true
				}
				if (!!this.state.openproject_client_id && !!this.state.openproject_client_secret) {
					this.formMode.opOauth = F_MODES.VIEW
					this.isFormCompleted.opOauth = true
				}
				if (this.state.openproject_instance_url) {
					if (!this.state.openproject_client_id || !this.state.openproject_client_secret) {
						this.formMode.opOauth = F_MODES.EDIT
					}
				}
				if (this.state.nc_oauth_client) {
					this.formMode.ncOauth = F_MODES.VIEW
					this.isFormCompleted.ncOauth = true
				}
				if (this.state.default_managed_folders) {
					this.formMode.managedGroupFolderSetUp = F_MODES.VIEW
					this.isFormCompleted.managedGroupFolderSetUp = true
				}
				if (this.state.app_password_set) {
					this.formMode.opSystemPassword = F_MODES.VIEW
					this.isFormCompleted.opSystemPassword = true
					this.iskeepCurrentCompleteIntegration = 'Keep Current Change'
				}
				// condition for active and inactive for managed project folders
				if (this.state.managed_folder_state) {
					this.isManagedFolderActive = false
					this.isGroupfolderSetupAutomaticallyReady = true
				} else if (this.state.default_managed_folders === true) {
					this.isManagedFolderActive = false
					this.iskeepCurrentCompleteWithoutIntegration = 'Keep Current Change'
				}
			}
		},
		setServerHostFormToViewMode() {
			this.formMode.server = F_MODES.VIEW
		},
		setServerHostFormToEditMode() {
			this.formMode.server = F_MODES.EDIT
			// set the edit variable to the current saved value
			this.serverHostUrlForEdit = this.state.openproject_instance_url
			this.isOpenProjectInstanceValid = null
		},
		setManagedGroupFolderSetUpToEditMode() {
			this.formMode.managedGroupFolderSetUp = F_MODES.EDIT
			this.isFormCompleted.managedGroupFolderSetUp = false
			this.isGroupfolderSetupAutomaticallyReady = this.state.managed_folder_state
			this.isManagedFolderActive = false
		},
		async setNCOAuthFormToViewMode() {
			this.formMode.ncOauth = F_MODES.VIEW
			this.isFormCompleted.ncOauth = true
			if (this.state.default_managed_folders === false && this.isGroupfolderSetupAutomaticallyReady === null) {
				this.iskeepCurrentCompleteWithoutIntegration = 'Complete Integration without groupfolders'
				this.isGroupfolderSetupAutomaticallyReady = false
			}
			if (this.state.default_managed_folders === false) {
				this.iskeepCurrentCompleteWithoutIntegration = 'Complete Integration without groupfolders'
				this.formMode.managedGroupFolderSetUp = F_MODES.EDIT
				this.state.default_managed_folders = true
				await this.saveOPOptions()
			}
		},
		setOPSytemPasswordToViewMode() {
			this.formMode.opSystemPassword = F_MODES.VIEW
			this.isFormCompleted.opSystemPassword = true
		},
		changeGroupFolderSetUpState() {
			this.isGroupfolderSetupAutomaticallyReady = !this.isGroupfolderSetupAutomaticallyReady
			if (this.state.managed_folder_state === true && this.isGroupfolderSetupAutomaticallyReady === true) {
				this.iskeepCurrentCompleteWithoutIntegration = 'Keep Current Change'
			} else if (this.state.managed_folder_state === false && this.isGroupfolderSetupAutomaticallyReady === false) {
				this.iskeepCurrentCompleteIntegration = 'Keep Current Change'
			} else if (this.state.managed_folder_state === true && this.isGroupfolderSetupAutomaticallyReady === false) {
				this.iskeepCurrentCompleteWithoutIntegration = 'Complete Integration without groupfolders'
			} else if (this.state.managed_folder_state === false && this.isGroupfolderSetupAutomaticallyReady === true) {
				this.iskeepCurrentCompleteIntegration = 'Setup OpenProject user,group and folder'
			}

		},
		async checkForErrorOrSetUpOpenProjectGroupFolders() {
			this.loadingSetUpGroupFolder = true
			const success = await this.saveOPOptions()
			if (success) {
				this.state.managed_folder_state = true
				this.iskeepCurrentCompleteIntegration = 'Keep Current Change'
			}
			if (this.groupFolderSetUpError === null) {
				this.isFormCompleted.managedGroupFolderSetUp = true
				this.formMode.managedGroupFolderSetUp = F_MODES.VIEW
				this.formMode.opSystemPassword = F_MODES.EDIT
			}
			this.loadingSetUpGroupFolder = false
		},
		async saveOpenProjectHostUrl() {
			this.loadingServerHostForm = true
			await this.validateOpenProjectInstance()
			if (this.isOpenProjectInstanceValid) {
				const saved = await this.saveOPOptions()
				if (saved) {
					this.state.openproject_instance_url = this.serverHostUrlForEdit
					this.formMode.server = F_MODES.VIEW
					this.isFormCompleted.server = true
					if (!this.isFormCompleted.opOauth) {
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
				t('integration_openproject', 'If you proceed you will need to update these settings with the new OpenProject OAuth credentials. Also, all users will need to reauthorize access to their OpenProject account.'),
				t('integration_openproject', 'Replace OpenProject OAuth values'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('integration_openproject', 'Yes, replace'),
					confirmClasses: 'error',
					cancel: t('integration_openproject', 'Cancel'),
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
			this.state.openproject_client_id = null
			this.state.openproject_client_secret = null
			const saved = await this.saveOPOptions()
			if (!saved) {
				this.formMode.opOauth = F_MODES.VIEW
				this.isFormCompleted.opOauth = true
			}
		},
		resetAllAppValuesConfirmation() {
			OC.dialogs.confirmDestructive(
				t('integration_openproject', 'Are you sure that you want to reset this app and delete all settings and all connections of all Nextcloud users to OpenProject?'),
				t('integration_openproject', 'Reset OpenProject integration'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('integration_openproject', 'Yes, reset'),
					confirmClasses: 'error',
					cancel: t('integration_openproject', 'Cancel'),
				},
				async (result) => {
					if (result) {
						await this.resetAllAppValues()
					}
				},
				true
			)
		},
		async resetAllAppValues() {
			// to avoid general console errors, we need to set the form to
			// editor mode so that we can update the form fields with null values
			// also, form completeness should be set to false
			this.formMode.opOauth = F_MODES.EDIT
			this.isFormCompleted.opOauth = false
			this.formMode.server = F_MODES.EDIT
			this.isFormCompleted.server = false
			// look for another method not to use this one
			this.formMode.opSystemPassword = F_MODES.DISABLE

			this.state.openproject_client_id = null
			this.state.openproject_client_secret = null
			this.state.openproject_instance_url = null
			this.state.default_enable_navigation = false
			this.state.default_enable_unified_search = false
			this.state.default_managed_folders = false
			this.isGroupfolderSetupAutomaticallyReady = false

			await this.saveOPOptions()
			window.location.reload()
		},
		async validateOpenProjectInstance() {
			const url = generateUrl('/apps/integration_openproject/is-valid-op-instance')
			const response = await axios.post(url, { url: this.serverHostUrlForEdit })
			this.openProjectNotReachableErrorMessageDetails = null
			this.openProjectNotReachableErrorMessage = t(
				'integration_openproject',
				'Please introduce a valid OpenProject host name'
			)
			if (response.data.result === true) {
				this.isOpenProjectInstanceValid = true
				this.state.openproject_instance_url = this.serverHostUrlForEdit
			} else {
				switch (response.data.result) {
				case 'invalid':
					this.openProjectNotReachableErrorMessage = t(
						'integration_openproject',
						'URL is invalid',
					)
					this.openProjectNotReachableErrorMessageDetails = t(
						'integration_openproject',
						'The URL should have the form "https://openproject.org"'
					)
					break
				case 'not_valid_body':
					this.openProjectNotReachableErrorMessage = t(
						'integration_openproject',
						'There is no valid OpenProject instance listening at that URL, please check the Nextcloud logs'
					)
					break
				case 'client_exception': {
					this.openProjectNotReachableErrorMessage = t(
						'integration_openproject',
						'There is no valid OpenProject instance listening at that URL, please check the Nextcloud logs'
					)
					this.openProjectNotReachableErrorMessageDetails = t(
						'integration_openproject',
						'Response:'
					) + ' "' + response.data.details + '"'
					break
				}
				case 'server_exception': {
					this.openProjectNotReachableErrorMessage = t(
						'integration_openproject',
						'Server replied with an error message, please check the Nextcloud logs'
					)
					this.openProjectNotReachableErrorMessageDetails = response.data.details
					break
				}
				case 'local_remote_servers_not_allowed': {
					const linkText = t('integration_openproject', 'Documentation')
					const htmlLink = `<a class="link" href="https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/" target="_blank" title="${linkText}">${linkText}</a>`

					this.openProjectNotReachableErrorMessage = t(
						'integration_openproject',
						'Accessing OpenProject servers with local addresses is not allowed.'
					)
					this.openProjectNotReachableErrorMessageDetails = t(
						'integration_openproject',
						'To be able to use an OpenProject server with a local address, enable the `allow_local_remote_servers` setting. {htmlLink}.',
						{ htmlLink },
						null,
						{ escape: false, sanitize: false }
					)
					break
				}
				case 'redirected':
				{
					const location = response.data.details
					this.openProjectNotReachableErrorMessage = t(
						'integration_openproject',
						'The given URL redirects to \'{location}\'. Please do not use a URL that leads to a redirect.',
						{ location }
					)
					break
				}
				case 'unexpected_error':
				case 'network_error':
				case 'request_exception':
				default: {
					this.openProjectNotReachableErrorMessage = t(
						'integration_openproject',
						'Could not connect to the given URL, please check the Nextcloud logs'
					)
					this.openProjectNotReachableErrorMessageDetails = response.data.details
					break
				}
				}
				this.isOpenProjectInstanceValid = false
				await this.$nextTick()
				await this.$refs['openproject-oauth-instance-input']?.$refs?.textInput?.focus()
			}
		},
		appPassword() {
			const restAppPassword = false
			if (this.isGroupfolderSetupAutomaticallyReady === false) {
				// case for deletion
				return null
			} else if (this.isOpSystemPasswordFormInEdit) {
				// case for new app password we set it true
				return true
			} else if (this.state.openproject_instance_url === null && this.state.openproject_client_secret === null && this.state.openproject_client_id === null) {
				// in case of whole reset
				return null
			} else if (this.state.openproject_instance_url === null || this.state.openproject_client_secret === null || this.state.openproject_client_id === null) {
				return false
			} else if (this.state.managed_folder_state === false && this.isGroupfolderSetupAutomaticallyReady === true) {
				return true
			}
			return restAppPassword
		},
		setUpGroupFolder() {
			if (this.state.managed_folder_state === true && this.isGroupfolderSetupAutomaticallyReady === true) {
				return false
			}
			if (this.state.managed_folder_state === false && this.isGroupfolderSetupAutomaticallyReady === true) {
				return true
			}
			return false
		},
		async saveOPOptions() {
			let success = false
			const url = generateUrl('/apps/integration_openproject/admin-config')
			const appPassword = this.appPassword()
			const groupFolderSetUp = this.setUpGroupFolder()
			const req = {
				values: {
					openproject_client_id: this.state.openproject_client_id,
					openproject_client_secret: this.state.openproject_client_secret,
					openproject_instance_url: this.state.openproject_instance_url,
					default_enable_navigation: this.state.default_enable_navigation,
					default_enable_unified_search: this.state.default_enable_unified_search,
					setup_group_folder: groupFolderSetUp,
					default_managed_folders: this.state.default_managed_folders,
					managed_folder_state: this.isGroupfolderSetupAutomaticallyReady,
					reset_app_password: appPassword,
				},
			}
			try {
				const response = await axios.put(url, req)
				// after successfully saving the admin credentials, the admin config status needs to be updated
				this.isAdminConfigOk = response?.data?.status === true
				if (response.data?.openproject_user_app_password) {
					this.oPSystemPassword = response.data?.openproject_user_app_password
					this.state.app_password_set = response.data?.openproject_user_app_password
				} else {
					this.oPSystemPassword = response.data?.openproject_user_app_password
				}
				this.oPOAuthTokenRevokeStatus = response?.data?.oPOAuthTokenRevokeStatus
				showSuccess(t('integration_openproject', 'OpenProject admin options saved'))
				success = true
			} catch (error) {
				this.isAdminConfigOk = null
				this.oPOAuthTokenRevokeStatus = null
				console.error(error)
				showError(
					t('integration_openproject', 'Failed to save OpenProject admin options')
				)
			}
			this.notifyAboutOPOAuthTokenRevoke()
			return success
		},
		notifyAboutOPOAuthTokenRevoke() {
			switch (this.oPOAuthTokenRevokeStatus) {
			case 'connection_error':
				showError(
					t('integration_openproject', 'Failed to perform revoke request due to connection error with the OpenProject server')
				)
				break
			case 'other_error':
				showError(
					t('integration_openproject', 'Failed to revoke some users\' OpenProject OAuth access tokens')
				)
				break
			case 'success':
				showSuccess(
					t('integration_openproject', 'Successfully revoked users\' OpenProject OAuth access tokens')
				)
				break
			default:
				break
			}
		},
		resetNcOauthValues() {
			OC.dialogs.confirmDestructive(
				t('integration_openproject', 'If you proceed you will need to update the settings in your OpenProject with the new Nextcloud OAuth credentials. Also, all users in OpenProject will need to reauthorize access to their Nextcloud account.'),
				t('integration_openproject', 'Replace Nextcloud OAuth values'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('integration_openproject', 'Yes, replace'),
					confirmClasses: 'error',
					cancel: t('integration_openproject', 'Cancel'),
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
		async completeIntegrationWithoutGroupFolderSetUp() {
			this.isFormCompleted.managedGroupFolderSetUp = true
			this.formMode.managedGroupFolderSetUp = F_MODES.VIEW
			this.iskeepCurrentCompleteWithoutIntegration = 'Keep Current Change'
			if (!this.state.app_password_set) {
				const success = await this.saveOPOptions()
				if (success) {
					this.state.managed_folder_state = this.isGroupfolderSetupAutomaticallyReady
					this.isManagedFolderActive = this.state.managed_folder_state
				}

			} else {
				// the value of this null will help in the deletion of the app password
				// since the integration is done without the groupfolder setup and the app password must be deleted
				const success = await this.saveOPOptions()
				if (success) {
					this.state.managed_folder_state = this.isGroupfolderSetupAutomaticallyReady
					this.state.app_password_set = false
					this.isFormCompleted.opSystemPassword = false
					this.formMode.opSystemPassword = F_MODES.DISABLE
				}

			}
		},
		resetOPSystemPassword() {
			OC.dialogs.confirmDestructive(
				t('integration_openproject', 'If you proceed old password for the OpenProject user will be deleted and you will receive a new system user password.'),
				t('integration_openproject', 'Replace OpenProject system user password'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('integration_openproject', 'Yes, replace'),
					confirmClasses: 'error',
					cancel: t('integration_openproject', 'Cancel'),
				},
				async (result) => {
					if (result) {
						await this.createNewAppPassword()
					}
				},
				true
			)
		},
		async createNewAppPassword() {
			this.formMode.opSystemPassword = F_MODES.EDIT
			this.isFormCompleted.opSystemPassword = false
			await this.saveOPOptions()
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
					t('integration_openproject', 'Failed to create Nextcloud OAuth client')
					+ ': ' + error.response.request.responseText
				)
			})
		},
		setDefaultConfig() {
			const url = generateUrl('/apps/integration_openproject/admin-config')
			const req = {
				values: {
					default_enable_navigation: !!this.state.default_enable_navigation,
					default_enable_unified_search: !!this.state.default_enable_unified_search,
				},
			}
			axios.put(url, req).then((res) => {
				showSuccess(t('integration_openproject', 'Default user configuration saved'))
			}).catch(error => {
				showError(
					t('integration_openproject', 'Failed to save default user configuration')
					+ ': ' + error.response.request.responseText
				)
			})
		},
	},
}
</script>

<style scoped lang="scss">
@import '../../css/tab.css';

#reset-all-app-settings-btn {
	position: absolute;
	top: 30px;
	right: 22px;
}

.managed-folder-description {
	 font-weight: 400;
}

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
	.py-1 {
		padding: .3rem 0;
	}
	.mr-2 {
		margin-right: .5rem;
	}
	.default-prefs {
		padding-top: 1.2rem;
	}
}
</style>
<style lang="scss">
#openproject_prefs {
	.button-vue {
		height: 32px !important;
		min-height: 32px !important;

		&--vue-primary {
			.button-vue__text {
				color: #fff !important;
			}
		}

		&--vue-error {
			.button-vue__text {
				color: #fff !important;
			}
		}

		&__text {
			font-weight: 400 !important;
			font-size: 14px !important;
			line-height: 20px !important;
		}
	}
	.form-actions {
		display: flex;
		align-items: center;
		padding: 15px 0;
	}
}
</style>
