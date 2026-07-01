<!--
  - SPDX-FileCopyrightText: 2021-2025 Jankari Tech Pvt. Ltd.
  - SPDX-FileCopyrightText: 2021-2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div id="openproject_prefs" class="section">
		<TermsOfServiceUnsigned :is-all-terms-of-service-signed-for-user-open-project="isAllTermsOfServiceSignedForUserOpenProject" />
		<SettingsTitle is-setting="admin" />
		<NcNoteCard v-if="!isAdminAuditConfigurationSetUpCorrectly" class="note-card" type="info">
			<p class="note-card--info-description" v-html="getAdminAuditConfigurationHint" /> <!-- eslint-disable-line vue/no-v-html -->
		</NcNoteCard>
		<FormOpenProjectHost
			:is-dark-theme="isDarkTheme"
			:openproject-url="state.openproject_instance_url"
			@formcomplete="markFormComplete" />
		<FormAuthMethod
			:is-dark-theme="isDarkTheme"
			:auth-method="state.authorization_method"
			:apps="state.apps"
			:form-state="form"
			@formcomplete="markFormComplete" />
		<FormSSOSettings
			v-if="isOidcMethod"
			:is-dark-theme="isDarkTheme"
			:form-state="form"
			:sso-settings="state.authorization_settings"
			:sso-providers="state.oidc_providers"
			:apps="state.apps"
			@formcomplete="markFormComplete" />
		<FormOAuthSettings
			v-if="isOAuthMethod || !form.authenticationMethod.complete"
			:is-dark-theme="isDarkTheme"
			:form-state="form"
			:oauth-settings="{
				openproject_client_id: state.openproject_client_id,
				openproject_client_secret: state.openproject_client_secret,
				nc_oauth_client: state.nc_oauth_client,
			}"
			@formcomplete="markFormComplete" />
		<FormProjectFolder
			:is-dark-theme="isDarkTheme"
			:form-order="projectFolderFormOrder"
			:form-state="form"
			:project-folder-info="{
				projectFolderEnabled: state.project_folder_enabled,
				hasAppPassword: state.app_password_set,
				app: state.apps.groupfolders,
				folderStatus: state.project_folder_info,
				encryption: state.encryption_info,
			}"
			@formcomplete="markFormComplete" />
		<!--
		<div class="project-folder-setup">
			<FormHeading :index="isOidcMethod ? '4' : '5'"
				:is-project-folder-setup-heading="true"
				:title="t('integration_openproject', 'Project folders (recommended)')"
				:is-setup-complete-without-project-folders="isSetupCompleteWithoutProjectFolders"
				:has-error="isThereErrorAfterProjectFolderAndAppPasswordSetup || showGroupfoldersAppError"
				:show-encryption-warning-for-group-folders="showEncryptionWarningForGroupFolders"
				:is-complete="isProjectFolderSetupCompleted"
				:is-disabled="isProjectFolderFormInDisableMode"
				:is-dark-theme="isDarkTheme" />
			<ErrorNote
				v-if="showGroupfoldersAppError"
				:error-title="messagesFmt.appNotEnabledOrUnsupported(getGroupfoldersAppName, getMinSupportedGroupfoldersVersion)"
				:error-link="appLinks.groupfolders.installLink"
				:error-link-label="messages.installLatestVersionNow" />
			<NcNoteCard v-else-if="projectFolderSetupError || isThereErrorAfterProjectFolderAndAppPasswordSetup" class="note-card" type="error">
				<p class="note-card--title">
					<b v-if="isThereErrorAfterProjectFolderAndAppPasswordSetup">{{ state.project_folder_info.errorMessage }}</b>
					<b v-else>{{ projectFolderSetupError }}</b>
				</p>
				<p class="note-card--error-description" v-html="projectFolderSetUpErrorMessageDescription" />
			</NcNoteCard>
			<NcNoteCard v-else-if="showEncryptionWarningForGroupFolders" class="note-card" type="warning">
				<p class="note-card--title">
					<b>{{ t('integration_openproject', 'Encryption for the Team Folders App is not enabled.') }}</b>
				</p>
				<p class="note-card--warning-description" v-html="getGroupFoldersEncryptionWarningHint" />
			</NcNoteCard>
			<div v-if="showDefaultManagedProjectFolders">
				<div v-if="isProjectFolderSetupFormInEdit">
					<NcCheckboxRadioSwitch type="switch" :checked.sync="isProjectFolderSwitchEnabled" @update:checked="changeProjectFolderSetUpState">
						<b>{{ t('integration_openproject', 'Automatically managed folders') }}</b>
					</NcCheckboxRadioSwitch>
					<div v-if="isProjectFolderSwitchEnabled === false" class="complete-without-groupfolders">
						<p class="project-folder-description">
							{{
								t('integration_openproject', 'We recommend using this functionality but it is not mandatory. Please activate it in case you want to use the automatic creation and management of project folders.')
							}}
						</p>
						<div class="form-actions">
							<NcButton type="primary"
								data-test-id="complete-without-project-folder-form-btn"
								@click="completeIntegrationWithoutProjectFolderSetUp">
								<template #icon>
									<CheckBoldIcon fill-color="#FFFFFF" :size="20" />
								</template>
								{{
									textLabelProjectFolderSetupButton
								}}
							</NcButton>
						</div>
					</div>
					<div v-else>
						<p class="project-folder-description">
							{{
								t('integration_openproject', 'Let OpenProject create folders per project automatically. It will ensure that every team member has always the correct access permissions.')
							}}
						</p>
						<br>
						<b>{{ t('integration_openproject', 'OpenProject user, group and folder') }}</b>
						<p class="project-folder-description">
							{{
								t('integration_openproject', 'For automatically managing project folders, this app needs to setup a special team folder, assigned to a group and managed by a user, each called "OpenProject".')
							}} <br>
							{{
								t('integration_openproject', 'The app will never delete files or folders, even if you deactivate this later.')
							}}
						</p>
						<div class="form-actions">
							<NcButton
								type="primary"
								:disabled="showGroupfoldersAppError"
								data-test-id="complete-with-project-folders-form-btn"
								@click="setUpProjectGroupFolders">
								<template #icon>
									<NcLoadingIcon v-if="loadingProjectFolderSetup" class="loading-spinner" :size="20" />
									<RestoreIcon v-else-if="projectFolderSetupError" fill-color="#FFFFFF" :size="20" />
									<CheckBoldIcon v-else fill-color="#FFFFFF" :size="20" />
								</template>
								{{ textLabelProjectFolderSetupButton }}
							</NcButton>
						</div>
					</div>
				</div>
				<div v-else class="project-folder-status">
					<div class="project-folder-status-value">
						<b>{{ t('integration_openproject','Automatically managed folders:') }}</b> {{ opUserAppPassword ? t('integration_openproject', 'Active') : t('integration_openproject', 'Inactive') }}
					</div>
					<div class="form-actions">
						<NcButton
							data-test-id="edit-project-folder-setup"
							@click="setProjectFolderSetUpToEditMode">
							<template #icon>
								<PencilIcon :size="20" />
							</template>
							{{ t('integration_openproject', 'Edit project folders') }}
						</NcButton>
					</div>
				</div>
			</div>
		</div>
		<div v-if="state.app_password_set">
			<FormHeading index="6"
				:title="t('integration_openproject', 'Project folders application connection')"
				:is-complete="isOPUserAppPasswordFormComplete"
				:is-disabled="isOPUserAppPasswordInDisableMode"
				:is-dark-theme="isDarkTheme" />
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
							<CheckBoldIcon fill-color="#FFFFFF" :size="20" />
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
	-->
		<NcButton id="reset-all-app-settings-btn"
			type="error"
			:disabled="!resettableForm"
			@click="resetIntegrationSetup">
			<template #icon>
				<RestoreIcon :size="20" />
			</template>
			{{ t('integration_openproject', 'Reset') }}
		</NcButton>
		<div v-if="isSetupComplete" class="default-prefs">
			<h2>{{ t('integration_openproject', 'Default user settings') }}</h2>
			<p>
				{{ t('integration_openproject', 'A new user will receive these defaults and they will be applied to the integration app till the user changes them.') }}
			</p>
			<br>
			<CheckBox v-model="state.default_enable_navigation"
				input-id="default-prefs--link"
				:label="t('integration_openproject', 'Enable navigation link')"
				@input="setDefaultConfig">
				<template #hint>
					<p class="user-setting-description" v-html="userSettingDescription.NAVIGATION_LINK_DESCRIPTION" /> <!-- eslint-disable-line vue/no-v-html -->
				</template>
			</CheckBox>
			<CheckBox v-model="state.default_enable_unified_search"
				input-id="default-prefs--u-search"
				:label="t('integration_openproject', 'Enable unified search for tickets')"
				@input="setDefaultConfig">
				<template #hint>
					<p class="user-setting-description" v-html="userSettingDescription.UNIFIED_SEARCH_DESCRIPTION" /> <!-- eslint-disable-line vue/no-v-html -->
				</template>
			</CheckBox>
		</div>
	</div>
</template>

<script>
import util from 'util'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
// import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
// import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import {
	// NcLoadingIcon,
	// NcCheckboxRadioSwitch,
	NcButton,
	NcNoteCard,
} from '@nextcloud/vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
// import AutoRenewIcon from 'vue-material-design-icons/Autorenew.vue'
// import TextInput from './admin/TextInput.vue'
// import FieldValue from './admin/FieldValue.vue'
// import FormHeading from './admin/FormHeading.vue'
// import ErrorNote from './settings/ErrorNote.vue'
import CheckBox from '../components/settings/CheckBox.vue'
import SettingsTitle from '../components/settings/SettingsTitle.vue'
import { F_MODES, USER_SETTINGS, AUTH_METHOD, SSO_PROVIDER_TYPE, SSO_PROVIDER_LABEL, ADMIN_SETTINGS_FORM } from '../utils.js'
import TermsOfServiceUnsigned from './admin/TermsOfServiceUnsigned.vue'
import dompurify from 'dompurify'
import { messages, messagesFmt } from '../constants/messages.js'
import { appLinks } from '../constants/links.js'
import FormOpenProjectHost from './admin/FormOpenProjectHost.vue'
import FormAuthMethod from './admin/FormAuthMethod.vue'
import FormSSOSettings from './admin/FormSSOSettings.vue'
import FormOAuthSettings from './admin/FormOAuthSettings.vue'
import FormProjectFolder from './admin/FormProjectFolder.vue'
import { saveAdminConfig } from '../api/settings.js'

export default {
	name: 'AdminSettings',
	components: {
		NcButton,
		// FieldValue,
		// FormHeading,
		// TextInput,
		SettingsTitle,
		// CheckBoldIcon,
		// PencilIcon,
		// NcLoadingIcon,
		// AutoRenewIcon,
		RestoreIcon,
		CheckBox,
		// NcCheckboxRadioSwitch,
		TermsOfServiceUnsigned,
		NcNoteCard,
		// ErrorNote,
		FormOpenProjectHost,
		FormAuthMethod,
		FormSSOSettings,
		FormOAuthSettings,
		FormProjectFolder,
	},
	data() {
		return {
			form: JSON.parse(JSON.stringify(ADMIN_SETTINGS_FORM)),
			formMode: {
				// server host form is never disabled.
				// it's either editable or view only
				opUserAppPassword: F_MODES.DISABLE,
				projectFolderSetUp: F_MODES.DISABLE,
			},
			isFormCompleted: {
				opUserAppPassword: false, projectFolderSetUp: false,
			},
			buttonTextLabel: {
				keepCurrentChange: t('integration_openproject', 'Keep current setup'),
				completeWithoutProjectFolderSetup: t('integration_openproject', 'Complete without project folders'),
				completeWithProjectFolderSetup: t('integration_openproject', 'Setup OpenProject user, group and folder'),
				retrySetupWithProjectFolder: t('integration_openproject', 'Retry setup OpenProject user, group and folder'),
			},
			loadingProjectFolderSetup: false,
			state: loadState('integration_openproject', 'admin-settings-config'),
			isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
			oPUserAppPassword: null,
			isProjectFolderSwitchEnabled: null,
			projectFolderSetupError: null,
			isProjectFolderAlreadySetup: null,
			// we assume this value as true and without error, it is set false when something goes wrong after the project folder setup is already completed
			isProjectFolderSetupCorrect: true,
			showDefaultManagedProjectFolders: false,
			// this keeps track of the state of project folder when user has done some setup (with or without)
			currentProjectFolderState: false,
			textLabelProjectFolderSetupButton: null,
			// pointer for which form the request is coming
			isFormStep: null,
			isDarkTheme: null,
			isAllTermsOfServiceSignedForUserOpenProject: true,
			userSettingDescription: USER_SETTINGS,
			SSO_PROVIDER_TYPE,
			SSO_PROVIDER_LABEL,
			messages,
			messagesFmt,
			appLinks,
		}
	},
	computed: {
		resettableForm() {
			const formAdded = !!Object.values(this.form).find(({ complete }) => complete === true)
			const hasPreSEtup = this.state.openproject_instance_url
				|| this.state.authorization_method
				|| this.state.sso_provider_type
				|| this.state.openproject_client_id
				|| this.state.openproject_client_secret
			return formAdded || hasPreSEtup
		},
		opUserAppPassword() {
			return this.state.app_password_set
		},
		isAdminAuditConfigurationSetUpCorrectly() {
			return this.state.admin_audit_configuration_correct
		},
		isServerHostFormComplete() {
			return this.form.serverHost.complete
		},
		isAuthorizationMethodFormComplete() {
			return this.form.authenticationMethod.complete
		},
		isAuthorizationSettingFormComplete() {
			return (this.form.openprojectOauth.complete && this.form.nextcloudOauth.complete) || this.form.ssoSettings.complete
		},
		isManagedGroupFolderSetUpComplete() {
			return this.isFormCompleted.projectFolderSetUp
		},
		isOPUserAppPasswordFormComplete() {
			return this.isFormCompleted.opUserAppPassword
		},
		isOPUserAppPasswordFormInEdit() {
			return this.formMode.opUserAppPassword === F_MODES.EDIT
		},
		isProjectFolderSetupFormInEdit() {
			return this.formMode.projectFolderSetUp === F_MODES.EDIT
		},
		isProjectFolderFormInDisableMode() {
			return this.formMode.projectFolderSetUp === F_MODES.DISABLE
		},
		isOPUserAppPasswordInDisableMode() {
			return this.formMode.opUserAppPassword === F_MODES.DISABLE
		},
		isThereErrorAfterProjectFolderAndAppPasswordSetup() {
			return (this.opUserAppPassword && this.formMode.projectFolderSetUp !== F_MODES.EDIT && this.isProjectFolderSetupCorrect === false)
		},
		isProjectFolderSetupCompleted() {
			return this.isProjectFolderSetupFormInEdit ? false : this.opUserAppPassword
		},
		getCurrentAuthMethod() {
			return this.form.authenticationMethod.value
		},
		isOAuthMethod() {
			return this.getCurrentAuthMethod === AUTH_METHOD.OAUTH2
		},
		isOidcMethod() {
			return this.getCurrentAuthMethod === AUTH_METHOD.OIDC
		},
		adminFileStorageHref() {
			const path = '%s/admin/settings/storages'
			const host = this.form.serverHost.value
			return util.format(path, host)
		},
		userAppPasswordHint() {
			const linkText = t('integration_openproject', 'Administration > File storages')
			const htmlLink = `<a class="link" href="${this.adminFileStorageHref}" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'This value will only be accessible once. Now, as an administrator copy this password to OpenProject {htmlLink}.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		projectFolderSetUpErrorMessageDescription() {
			const linkText = t('integration_openproject', 'troubleshooting guide')
			const htmlLink = `<a class="link" href="https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#troubleshooting" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'Setting up the OpenProject user, group and team folder was not possible. Please check this {htmlLink} on how to resolve this situation.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		getAdminAuditConfigurationHint() {
			const linkTextForAdminAudit = t('integration_openproject', this.getAdminAuditAppName)
			const adminAuditAppUrlForDownload = generateUrl('settings/apps/featured/admin_audit')
			const linkTextForDocumentation = t('integration_openproject', 'documentation')
			const htmlLinkForAdminAudit = `<a class="link" href="${adminAuditAppUrlForDownload}" target="_blank" title="${linkTextForAdminAudit}">${linkTextForAdminAudit}</a>`
			const htmlLinkForDocumentaion = `<a class="link" href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/logging_configuration.html#admin-audit-log-optional" target="_blank" title="${linkTextForDocumentation}">${linkTextForDocumentation}</a>`
			const hintTextForAdminAudit = t('integration_openproject', 'To activate audit logs for the OpenProject integration, please enable the "{htmlLinkForAdminAudit}" app and follow the configuration steps outlined in the {htmlLinkForDocumentaion}.', { htmlLinkForAdminAudit, htmlLinkForDocumentaion }, null, { escape: false, sanitize: false })
			return dompurify.sanitize(hintTextForAdminAudit, { ADD_ATTR: ['target'] })
		},
		getGroupFoldersEncryptionWarningHint() {
			const linkText = t('integration_openproject', 'documentation')
			const htmlLink = `<a class="link" href="https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#files-are-not-encrypted-when-using-nextcloud-server-side-encryption" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'Server-side encryption is active, but encryption for Team Folders is not yet enabled. To ensure secure storage of files in project folders, please follow the configuration steps in the {htmlLink}.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		isSetupComplete() {
			return (this.isServerHostFormComplete
				&& this.isAuthorizationMethodFormComplete
				 && this.isAuthorizationSettingFormComplete
				 && this.isManagedGroupFolderSetUpComplete
				 && !this.isOPUserAppPasswordFormInEdit
			)
		},
		showEncryptionWarningForGroupFolders() {
			if (!this.isProjectFolderAlreadySetup || !this.state.app_password_set || this.isProjectFolderSetupFormInEdit) {
				return false
			}
			return this.state.encryption_info.server_side_encryption_enabled
				&& !this.state.encryption_info.encryption_enabled_for_groupfolders
		},
		getGroupfoldersAppName() {
			return this.state.apps.groupfolders.name
		},
		getAdminAuditAppName() {
			return this.state.admin_audit_app_name
		},
		hasEnabledSupportedGroupfoldersApp() {
			return this.state.apps.groupfolders.enabled && this.state.apps.groupfolders.supported
		},
		getMinSupportedGroupfoldersVersion() {
			return this.state.apps.groupfolders.minimum_version
		},
		showGroupfoldersAppError() {
			return this.isProjectFolderSwitchEnabled && !this.hasEnabledSupportedGroupfoldersApp && !this.isProjectFolderFormInDisableMode
		},
		projectFolderFormOrder() {
			let formOrder = this.form.projectFolder.order
			if (this.getCurrentAuthMethod) {
				console.info(this.form.projectFolder, this.getCurrentAuthMethod)
				formOrder = this.form.projectFolder[this.getCurrentAuthMethod].order
			}
			return formOrder.toString()
		},
	},
	watch: {
		'form.ssoSettings.complete'() {
			if (this.form.ssoSettings.complete && this.formMode.projectFolderSetUp === F_MODES.DISABLE) {
				this.formMode.projectFolderSetUp = F_MODES.EDIT
				this.showDefaultManagedProjectFolders = true
				this.isProjectFolderSwitchEnabled = true
				this.textLabelProjectFolderSetupButton = this.buttonTextLabel.completeWithProjectFolderSetup
			}
		},
		'isAuthorizationSettingFormComplete'() {
			if (this.form.openprojectOauth.complete && this.form.nextcloudOauth.complete && this.formMode.projectFolderSetUp === F_MODES.DISABLE) {
				this.formMode.projectFolderSetUp = F_MODES.EDIT
				this.showDefaultManagedProjectFolders = true
				this.isProjectFolderSwitchEnabled = true
				this.textLabelProjectFolderSetupButton = this.buttonTextLabel.completeWithProjectFolderSetup
			}
		},
	},
	created() {
		this.init()
	},
	mounted() {
		this.isDarkTheme = window.getComputedStyle(this.$el).getPropertyValue('--background-invert-if-dark') === 'invert(100%)'
	},
	methods: {
		init() {
			if (this.state) {
				if (this.state.all_terms_of_services_signed === false) {
					this.isAllTermsOfServiceSignedForUserOpenProject = false
				}
				if (this.state.project_folder_info) {
					this.isProjectFolderSetupCorrect = this.state.project_folder_info.status
					if (this.state.project_folder_info.status === true) {
						this.isProjectFolderAlreadySetup = true
					}
				}
				if (this.state.fresh_project_folder_setup === true && this.isProjectFolderFormInDisableMode) {
					this.currentProjectFolderState = true
					this.textLabelProjectFolderSetupButton = this.buttonTextLabel.completeWithProjectFolderSetup
				} else {
					this.textLabelProjectFolderSetupButton = this.buttonTextLabel.keepCurrentChange
				}
				if (this.state.openproject_instance_url && this.isAuthorizationSettingFormComplete) {
					this.showDefaultManagedProjectFolders = true
					this.formMode.projectFolderSetUp = F_MODES.EDIT
				}
				if (this.state.fresh_project_folder_setup === false) {
					this.showDefaultManagedProjectFolders = true
				}

				if (this.textLabelProjectFolderSetupButton === this.buttonTextLabel.keepCurrentChange) {
					this.showDefaultManagedProjectFolders = true
					this.formMode.projectFolderSetUp = F_MODES.VIEW
					this.isFormCompleted.projectFolderSetUp = true
				}
				if (this.state.app_password_set) {
					this.formMode.opUserAppPassword = F_MODES.VIEW
					this.isFormCompleted.opUserAppPassword = true
					this.currentProjectFolderState = true
					this.isProjectFolderSwitchEnabled = true
					this.textLabelProjectFolderSetupButton = this.buttonTextLabel.keepCurrentChange
				}
				this.isProjectFolderSwitchEnabled = this.currentProjectFolderState === true
			}
		},
		// for child components to mark the respective form as complete
		markFormComplete(formFn) {
			formFn(this.form)
		},
		resetIntegrationSetup() {
			OC.dialogs.confirmDestructive(
				t('integration_openproject', 'Are you sure that you want to reset this app and delete all settings and all connections of all Nextcloud users to OpenProject?'),
				t('integration_openproject', 'Reset OpenProject Integration'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('integration_openproject', 'Yes, reset'),
					confirmClasses: 'error',
					cancel: t('integration_openproject', 'Cancel'),
				},
				async (result) => {
					if (result) {
						await this.confirmResetIntegrationSetup(this.getCurrentAuthMethod)
					}
				},
				true,
			)
		},
		async confirmResetIntegrationSetup(authMethod) {
			// to avoid general console errors, we need to set the form to
			// editor mode so that we can update the form fields with null values
			// also, form completeness should be set to false

			// reset form states to default
			this.state.default_enable_navigation = false
			this.state.default_enable_unified_search = false
			this.oPUserAppPassword = null
			this.state.authorization_method = null
			this.state.openproject_client_id = null
			this.state.openproject_client_secret = null
			this.state.openproject_instance_url = null
			// if the authorization method is "oidc"
			if (authMethod === AUTH_METHOD.OIDC) {
				this.state.authorization_settings.targeted_audience_client_id = null
			}
			const data = {
				// oauth settings
				openproject_client_id: null,
				openproject_client_secret: null,
				// oidc settings
				oidc_provider: null,
				targeted_audience_client_id: null,
				sso_provider_type: null,
				token_exchange: null,
				// general settings
				openproject_instance_url: null,
				authorization_method: null,
				setup_project_folder: null,
				setup_app_password: false,
				default_enable_navigation: false,
				default_enable_unified_search: false,
			}

			try {
				const response = await saveAdminConfig(data)
				// after successfully saving the admin credentials, the admin config status needs to be updated
				this.isAdminConfigOk = response?.data?.status === true
				if (response?.data?.oPUserAppPassword) {
					this.state.app_password_set = true
					this.oPUserAppPassword = response?.data?.oPUserAppPassword
				}
				showSuccess(t('integration_openproject', 'OpenProject admin options saved'))
			} catch (error) {
				console.error()
				this.isAdminConfigOk = null
				if (error.response?.data?.error) {
					this.projectFolderSetupError = error.response.data.error
				}
				showError(
					t('integration_openproject', 'Failed to save OpenProject admin options'),
				)
			}
			// reload the web page
			window.location.reload()
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
					+ ': ' + error.response.request.responseText,
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

.project-folder-description {
	font-weight: 400;
}

.project-folder-status-value {
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
	.default-prefs {
		.user-setting-description {
			opacity: .7;
			margin-top: 0.2rem;
			padding-left: 5px;
		}
	}
	.note-card {
		max-width: 900px;
	}
	.link {
		color: #1a67a3 !important;
		font-style: normal;
	}
}
</style>
