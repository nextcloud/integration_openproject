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
		<div class="group-folder-setup">
			<FormHeading index="4"
				:is-group-folder-setup-heading="true"
				:title="t('integration_openproject', 'Managed project folders (recommended)')"
				:is-setup-complete-without-group-folders="isSetupCompleteWithoutGroupFolders"
				:is-there-error-after-group-folder-and-app-password-setup="isThereErrorAfterGroupFolderAndAppPasswordSetup"
				:is-complete="isGroupFolderSetupCompleted"
				:is-disabled="isGroupFolderSetUpInDisableMode" />
			<div v-if="showDefaultManagedFolders">
				<div v-if="isGroupFolderSetupFormInEdit">
					<NcCheckboxRadioSwitch type="switch" :checked.sync="isProjectFolderSwitchEnabled" @update:checked="changeGroupFolderSetUpState">
						<b>{{ t('integration_openproject', 'Automatically managed folders') }}</b>
					</NcCheckboxRadioSwitch>
					<div v-if="isProjectFolderSwitchEnabled === false" class="complete-without-groupfolders">
						<p class="group-folder-description">
							{{
								t('integration_openproject', 'We recommend using this functionality but it is not mandatory. Please activate in case you want to use the automatic creation and management of project folders.')
							}}
						</p>
						<div class="form-actions">
							<NcButton type="primary"
								data-test-id="complete-without-group-folder-form-btn"
								@click="completeIntegrationWithoutGroupFolderSetUp">
								<template #icon>
									<CheckBoldIcon :size="20" />
								</template>
								{{
									textLabelGroupFolderSetupButton
								}}
							</NcButton>
						</div>
					</div>
					<div v-else>
						<p class="group-folder-description">
							{{
								t('integration_openproject', 'Let OpenProject create folders per project automatically. It will ensure that every team member has always the correct access permissions.')
							}}
						</p>
						<br>
						<b>{{ t('integration_openproject', 'OpenProject user, group and folder') }}</b>
						<p class="group-folder-description">
							{{
								t('integration_openproject', 'For automatically managing project folders, this app needs to setup a special group folder, assigned to a group and managed by a user, each called "OpenProject".')
							}} <br>
							{{
								t('integration_openproject', 'The app will never delete files or folders, even if you deactivate this later.')
							}}
						</p>
						<GroupFolderError v-if="groupFolderSetupError !== null" :group-folder-set-up-error-message-description="groupFolderSetUpErrorMessageDescription(groupFolderSetupError)" :group-folder-set-up-error="groupFolderSetupError" />
						<div class="form-actions">
							<NcButton v-if="groupFolderSetupError === null"
								type="primary"
								data-test-id="complete-with-projectfolders-form-btn"
								@click="setUpProjectGroupFolders">
								<template #icon>
									<LoadingIcon v-if="loadingSetupGroupFolder" class="loading-spinner" :size="20" />
									<CheckBoldIcon v-else :size="20" />
								</template>
								{{ textLabelGroupFolderSetupButton }}
							</NcButton>
							<NcButton v-else-if="groupFolderSetupError"
								type="primary"
								data-test-id="complete-with-projectfolders-form-btn"
								@click="setUpProjectGroupFolders">
								<template #icon>
									<LoadingIcon v-if="loadingSetupGroupFolder" class="loading-spinner" :size="20" />
									<RestoreIcon v-else :size="20" />
								</template>
								{{ t('integration_openproject', 'Retry setup OpenProject user, group and folder') }}
							</NcButton>
						</div>
					</div>
				</div>
				<div v-else class="group-folder-status">
					<div class="group-folder-status-value">
						<b>Automatic managed folders:</b> {{ state.app_password_set || oPUserAppPassword !== null ? t('integration_openproject', 'Active') : t('integration_openproject', 'Inactive') }}
					</div>
					<GroupFolderError
						v-if="state.app_password_set && !isGroupFolderSetupCorrect"
						:group-folder-set-up-error-message-description="groupFolderSetUpErrorMessageDescription(state.group_folder_status.errorMessage)"
						:group-folder-set-up-error="state.group_folder_status.errorMessage" />
					<div class="form-actions">
						<NcButton
							data-test-id="edit-group-folder-setup"
							@click="setGroupFolderSetUpToEditMode">
							<template #icon>
								<PencilIcon :size="20" />
							</template>
							{{ t('integration_openproject', 'Edit managed project folders') }}
						</NcButton>
					</div>
				</div>
			</div>
		</div>
		<div v-if="state.app_password_set">
			<FormHeading index="5"
				:title="t('integration_openproject', 'Project folders application connection')"
				:is-complete="isOPUserAppPasswordFormComplete"
				:is-disabled="isOPUserAppPasswordInDisableMode" />
			<div v-if="state.app_password_set">
				<TextInput v-if="isOPUserAppPasswordFormInEdit"
					id="openproject-system-password"
					v-model="oPUserAppPassword"
					class="py-1"
					read-only
					is-required
					with-copy-btn
					:label="t('integration_openproject', 'Application Password')"
					:hint-text="userAppPasswordHint" />
				<FieldValue v-else
					:title="t('integration_openproject', 'Application Password')"
					is-required
					hide-value
					with-inspection
					value="" />
				<div class="form-actions">
					<NcButton v-if="isOPUserAppPasswordFormInEdit"
						type="primary"
						:disabled="!opUserAppPassword"
						data-test-id="submit-op-system-password-form-btn"
						@click="setOPUserAppPasswordToViewMode">
						<template #icon>
							<CheckBoldIcon :size="20" />
						</template>
						{{ t('integration_openproject', 'Done, complete setup') }}
					</NcButton>
					<NcButton v-else
						data-test-id="reset-user-app-password"
						@click="resetOPUserAppPassword">
						<template #icon>
							<AutoRenewIcon :size="20" />
						</template>
						{{ t('integration_openproject', 'Replace application password') }}
					</NcButton>
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
import { F_MODES, FORM } from '../utils.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import GroupFolderError from './admin/GroupFolderError.vue'
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
		NcCheckboxRadioSwitch,
		GroupFolderError,
	},
	data() {
		return {
			formMode: {
				// server host form is never disabled.
				// it's either editable or view only
				server: F_MODES.EDIT,
				opOauth: F_MODES.DISABLE,
				ncOauth: F_MODES.DISABLE,
				opUserAppPassword: F_MODES.DISABLE,
				groupFolderSetUp: F_MODES.DISABLE,
			},
			isFormCompleted: {
				server: false, opOauth: false, ncOauth: false, opUserAppPassword: false, groupFolderSetUp: false,
			},
			buttonTextLabel: {
				keepCurrentChange: t('integration_openproject', 'Keep current change'),
				completeWithoutGroupFolderSetup: t('integration_openproject', 'Complete without group folder setup'),
				completeWithGroupFolderSetup: t('integration_openproject', 'Setup OpenProject user, group and folder'),
			},
			loadingServerHostForm: false,
			loadingSetupGroupFolder: false,
			loadingOPOauthForm: false,
			isOpenProjectInstanceValid: null,
			openProjectNotReachableErrorMessage: null,
			openProjectNotReachableErrorMessageDetails: null,
			state: loadState('integration_openproject', 'admin-config'),
			isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
			serverHostUrlForEdit: null,
			isServerHostUrlReadOnly: true,
			oPOAuthTokenRevokeStatus: null,
			oPUserAppPassword: null,
			isProjectFolderSwitchEnabled: null,
			groupFolderSetupError: null,
			isGroupFolderAlreadySetup: null,
			// we assume this value as true and without error, it is set false when something goes wrong after the group folder setup is already completed
			isGroupFolderSetupCorrect: true,
			showDefaultManagedFolders: false,
			// this keeps track of the state of group folder when user has done some setup (with or without)
			currentGroupFolderState: false,
			textLabelGroupFolderSetupButton: null,
			// pointer for which form the request is coming
			isFormStep: null,
		}
	},
	computed: {
		ncClientId() {
			return this.state.nc_oauth_client?.nextcloud_client_id
		},
		ncClientSecret() {
			return this.state.nc_oauth_client?.nextcloud_client_secret
		},
		opUserAppPassword() {
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
			return this.isFormCompleted.groupFolderSetUp
		},
		isOPUserAppPasswordFormComplete() {
			return this.isFormCompleted.opUserAppPassword
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
		isOPUserAppPasswordFormInEdit() {
			return this.formMode.opUserAppPassword === F_MODES.EDIT
		},
		isGroupFolderSetupFormInEdit() {
			return this.formMode.groupFolderSetUp === F_MODES.EDIT
		},
		isNcOAuthFormInDisableMode() {
			return this.formMode.ncOauth === F_MODES.DISABLE
		},
		isGroupFolderSetUpInDisableMode() {
			return this.formMode.groupFolderSetUp === F_MODES.DISABLE
		},
		isOPUserAppPasswordInDisableMode() {
			return this.formMode.opUserAppPassword === F_MODES.DISABLE
		},
		isThereErrorAfterGroupFolderAndAppPasswordSetup() {
			return (this.opUserAppPassword && this.formMode.groupFolderSetUp !== F_MODES.EDIT && this.isGroupFolderSetupCorrect === false)
		},
		isGroupFolderSetupCompleted() {
			return this.isGroupFolderSetupFormInEdit ? false : this.opUserAppPassword ? true : this.oPUserAppPassword !== null
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
		userAppPasswordHint() {
			const linkText = t('integration_openproject', 'Administration > File storages')
			const htmlLink = `<a class="link" href="${this.adminFileStorageHref}" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'This value is only visible now and then never again. Copy this password to OpenProject {htmlLink} as an Administrator.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		isIntegrationComplete() {
			return (this.isServerHostFormComplete
				 && this.isOPOAuthFormComplete
				 && this.isNcOAuthFormComplete
				 && this.isManagedGroupFolderSetUpComplete
				 && !this.isOPUserAppPasswordFormInEdit
			)
		},
		isSetupCompleteWithoutGroupFolders() {
			if (this.isGroupFolderSetupFormInEdit) {
				return false
			}
			if (this.isFormCompleted.groupFolderSetUp === false && !this.opUserAppPassword) {
				return false
			}
			return !this.opUserAppPassword
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
				if (this.state.group_folder_status) {
					this.isGroupFolderSetupCorrect = this.state.group_folder_status.status
					if (this.state.group_folder_status.status === true) {
						this.isGroupFolderAlreadySetup = true
					}
				}
				if (this.state.fresh_group_folder_setup === true) {
					this.currentGroupFolderState = true
					this.textLabelGroupFolderSetupButton = this.buttonTextLabel.completeWithGroupFolderSetup
				} else {
					this.textLabelGroupFolderSetupButton = this.buttonTextLabel.keepCurrentChange
				}
				if (this.state.openproject_instance_url && this.state.openproject_client_id && this.state.openproject_client_secret && this.state.nc_oauth_client) {
					this.showDefaultManagedFolders = true
				}
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
				if (this.formMode.ncOauth === F_MODES.VIEW) {
					this.showDefaultManagedFolders = true
				}
				if (this.showDefaultManagedFolders) {
					this.formMode.groupFolderSetUp = F_MODES.VIEW
					this.isFormCompleted.groupFolderSetUp = true
				}
				if (this.state.app_password_set) {
					this.formMode.opUserAppPassword = F_MODES.VIEW
					this.isFormCompleted.opUserAppPassword = true
					this.currentGroupFolderState = true
					this.isProjectFolderSwitchEnabled = true
					this.textLabelGroupFolderSetupButton = this.buttonTextLabel.keepCurrentChange
				}

				if (this.currentGroupFolderState === true) {
					this.isProjectFolderSwitchEnabled = true
				} else {
					this.isProjectFolderSwitchEnabled = false
				}
			}
		},
		groupFolderSetUpErrorMessageDescription(errorKey) {
			switch (errorKey) {
			case 'The group folder name "OpenProject" integration already exists' :
				return t('integration_openproject', 'Please make sure to rename the group folder or completely delete the previous one or deactivate the automatically managed folders.')
			case 'The group folder app is not installed' :
				return t('integration_openproject', 'Please install the group folder to be able to use automatic managed folders or deactivate the automatically managed folders.')
			case 'The user "OpenProject" already exists' :
				return t('integration_openproject', 'Please make sure to completely delete the previous user or deactivate the automatically managed folders.')
			case 'The group "OpenProject" already exists' :
				return t('integration_openproject', 'Please make sure to completely delete the previous group or deactivate the automatically managed folders.')
			default:
				return t('integration_openproject', 'Something went wrong during groupfolder setup. Deactivate the automatically managed folders.')
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
		setGroupFolderSetUpToEditMode() {
			this.formMode.groupFolderSetUp = F_MODES.EDIT
			this.isFormCompleted.groupFolderSetUp = false
			this.isProjectFolderSwitchEnabled = this.currentGroupFolderState
		},
		setManagedGroupFolderSetupToViewMode() {
			this.currentGroupFolderState = true
			this.textLabelGroupFolderSetupButton = this.buttonTextLabel.keepCurrentChange
			this.isFormCompleted.groupFolderSetUp = true
			this.formMode.groupFolderSetUp = F_MODES.VIEW
			this.isGroupFolderSetupCorrect = true
		},
		async setNCOAuthFormToViewMode() {
			this.formMode.ncOauth = F_MODES.VIEW
			this.isFormCompleted.ncOauth = true
			if (!this.isIntegrationComplete) {
				this.showDefaultManagedFolders = true
				this.isProjectFolderSwitchEnabled = true
				this.textLabelGroupFolderSetupButton = this.buttonTextLabel.completeWithGroupFolderSetup
				this.formMode.groupFolderSetUp = F_MODES.EDIT
			}
		},
		setOPUserAppPasswordToViewMode() {
			this.formMode.opUserAppPassword = F_MODES.VIEW
			this.isFormCompleted.opUserAppPassword = true
		},
		changeGroupFolderSetUpState() {
			if (this.opUserAppPassword === false) {
				if (this.currentGroupFolderState === true && this.isProjectFolderSwitchEnabled === true) {
					this.textLabelGroupFolderSetupButton = this.buttonTextLabel.completeWithGroupFolderSetup
				} else if (this.currentGroupFolderState === true && this.isProjectFolderSwitchEnabled === false) {
					this.textLabelGroupFolderSetupButton = this.buttonTextLabel.completeWithoutGroupFolderSetup
				} else if (this.currentGroupFolderState === false && this.isProjectFolderSwitchEnabled === false) {
					this.textLabelGroupFolderSetupButton = this.buttonTextLabel.keepCurrentChange
				} else if (this.currentGroupFolderState === false && this.isProjectFolderSwitchEnabled === true) {
					this.textLabelGroupFolderSetupButton = this.buttonTextLabel.completeWithGroupFolderSetup
				}
			} else if (this.currentGroupFolderState === true && this.isProjectFolderSwitchEnabled === true) {
				this.textLabelGroupFolderSetupButton = this.buttonTextLabel.keepCurrentChange
			} else if (this.currentGroupFolderState === true && this.isProjectFolderSwitchEnabled === false) {
				this.textLabelGroupFolderSetupButton = this.buttonTextLabel.completeWithoutGroupFolderSetup
			}
		},
		async setUpProjectGroupFolders() {
			this.isFormStep = FORM.GROUP_FOLDER
			this.loadingSetupGroupFolder = true
			this.isGroupFolderAlreadySetup = await this.checkIfGroupFolderIsAlreadyReadyForSetup()
			if (this.isGroupFolderAlreadySetup) {
				if (!this.opUserAppPassword) {
					const success = await this.saveOPOptions()
					if (success) {
						this.formMode.opUserAppPassword = F_MODES.EDIT
						this.setManagedGroupFolderSetupToViewMode()
					}
				} else {
					this.setManagedGroupFolderSetupToViewMode()
				}
			} else {
				// we will check for the error making the setup_group_folder === true
				const success = await this.saveOPOptions()
				if (success) {
					this.setManagedGroupFolderSetupToViewMode()
					if ((this.formMode.opUserAppPassword === F_MODES.DISABLE && !this.opUserAppPassword) || this.formMode.opUserAppPassword === F_MODES.DISABLE) {
						this.formMode.opUserAppPassword = F_MODES.EDIT
					}
				}
			}
			this.loadingSetupGroupFolder = false
			if (this.formMode.groupFolderSetUp === F_MODES.VIEW) {
				this.groupFolderSetupError = null
			}
		},
		async saveOpenProjectHostUrl() {
			this.isFormStep = FORM.SERVER
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
			this.isFormStep = FORM.OP_OAUTH
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
			this.isFormStep = FORM.OP_OAUTH
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
			this.state.openproject_client_id = null
			this.state.openproject_client_secret = null
			this.state.default_enable_navigation = false
			this.state.openproject_instance_url = null
			this.state.default_enable_unified_search = false
			this.oPUserAppPassword = null
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
		getPayloadForSavingOPOptions() {
			let values = {
				openproject_client_id: this.state.openproject_client_id,
				openproject_client_secret: this.state.openproject_client_secret,
				openproject_instance_url: this.state.openproject_instance_url,
				default_enable_navigation: this.state.default_enable_navigation,
				default_enable_unified_search: this.state.default_enable_unified_search,
			}
			if (this.state.openproject_instance_url === null && this.state.openproject_client_secret === null && this.state.openproject_client_id === null) {
				// doing whole reset
				values = {
					...values,
					setup_group_folder: false,
					setup_app_password: false,
				}
			} else if (this.isFormStep === FORM.GROUP_FOLDER) {
				if (!this.isProjectFolderSwitchEnabled) {
					values = {
						setup_group_folder: false,
						setup_app_password: false,
					}
				} else if (this.isProjectFolderSwitchEnabled === true) {
					values = {
						setup_group_folder: !this.isGroupFolderAlreadySetup,
						setup_app_password: this.opUserAppPassword !== true,
					}
				}
			} else if (this.isFormStep === FORM.APP_PASSWORD) {
				values = {
					setup_app_password: true,
				}
			}
			return values
		},
		async saveOPOptions() {
			let success = false
			const url = generateUrl('/apps/integration_openproject/admin-config')
			const req = {
				values: this.getPayloadForSavingOPOptions(),
			}
			try {
				const response = await axios.put(url, req)
				// after successfully saving the admin credentials, the admin config status needs to be updated
				this.isAdminConfigOk = response?.data?.status === true
				if (response?.data?.oPUserAppPassword) {
					this.state.app_password_set = true
				}
				this.oPUserAppPassword = response?.data?.oPUserAppPassword
				this.oPOAuthTokenRevokeStatus = response?.data?.oPOAuthTokenRevokeStatus
				showSuccess(t('integration_openproject', 'OpenProject admin options saved'))
				success = true
			} catch (error) {
				console.error()
				this.isAdminConfigOk = null
				this.oPOAuthTokenRevokeStatus = null
				// catch the error response from the group folder response only
				// since the response message is to be displayed in the UI
				if (error.response.data.error) {
					this.groupFolderSetupError = error.response.data.error
				}
				showError(
					t('integration_openproject', 'Failed to save OpenProject admin options')
				)
			}
			this.notifyAboutOPOAuthTokenRevoke()
			return success
		},
		async checkIfGroupFolderIsAlreadyReadyForSetup() {
			let success = false
			try {
				const url = generateUrl('/apps/integration_openproject/group-folder-status')
				const response = await axios.get(url)
				success = response?.data?.result
			} catch (error) {
				console.error(error)
			}
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
			this.isFormStep = FORM.GROUP_FOLDER
			this.textLabelGroupFolderSetupButton = this.buttonTextLabel.keepCurrentChange
			const success = await this.saveOPOptions()
			if (success) {
				// if we have user app password already then we should delete it
				// also make password form disable and complete as false
				if (this.opUserAppPassword) {
					this.state.app_password_set = false
					this.isFormCompleted.opUserAppPassword = false
					this.formMode.opUserAppPassword = F_MODES.DISABLE
				}
				this.currentGroupFolderState = this.isProjectFolderSwitchEnabled
				this.isFormCompleted.groupFolderSetUp = true
				this.formMode.groupFolderSetUp = F_MODES.VIEW
			}
			// we want to show the error only when managed group folder is in edit mode
			if (this.formMode.groupFolderSetUp === F_MODES.VIEW) {
				this.groupFolderSetupError = null
			}
		},
		resetOPUserAppPassword() {
			OC.dialogs.confirmDestructive(
				t('integration_openproject', 'If you proceed, your old application password for the OpenProject user will be deleted and you will receive a new OpenProject user password.'),
				t('integration_openproject', 'Replace user app password'),
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
			this.isFormStep = FORM.APP_PASSWORD
			this.formMode.opUserAppPassword = F_MODES.EDIT
			this.isFormCompleted.opUserAppPassword = false
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

.group-folder-description {
	font-weight: 400;
}

.group-folder-status-value {
	padding: 6px 0;

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
