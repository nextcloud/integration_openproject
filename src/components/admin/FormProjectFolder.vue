<!--
  - SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section>
		<div class="project-folder-setup">
			<FormHeading :index="formOrder"
				:is-project-folder-setup-heading="true"
				:title="t('integration_openproject', 'Project folders (recommended)')"
				:is-setup-complete-without-project-folders="isSetupCompleteWithoutProjectFolder"
				:has-error="isThereErrorAfterProjectFolderAndAppPasswordSetup || showGroupfoldersAppError"
				:show-encryption-warning-for-group-folders="showEncryptionWarningForGroupFolders"
				:is-complete="isProjectFolderFormComplete && enableProjectFolder"
				:is-disabled="isProjectFolderFormInDisableMode"
				:is-dark-theme="isDarkTheme" />
			<ErrorNote
				v-if="showGroupfoldersAppError"
				:error-title="messagesFmt.appNotEnabledOrUnsupported(getTeamFolderAppName, getMinSupportedTeamFolderAppVersion)"
				:error-link="appLinks.groupfolders.installLink"
				:error-link-label="messages.installLatestVersionNow" />
			<NcNoteCard v-else-if="projectFolderSetupError || isThereErrorAfterProjectFolderAndAppPasswordSetup" class="note-card" type="error">
				<p class="note-card--title">
					<b v-if="isThereErrorAfterProjectFolderAndAppPasswordSetup">{{ getSetupErrorMessage }}</b>
					<b v-else>{{ projectFolderSetupError }}</b>
				</p>
				<p class="note-card--error-description" v-html="projectFolderSetUpErrorMessageDescription" /> <!-- eslint-disable-line vue/no-v-html -->
			</NcNoteCard>
			<NcNoteCard v-else-if="showEncryptionWarningForGroupFolders" class="note-card" type="warning">
				<p class="note-card--title">
					<b>{{ t('integration_openproject', 'Encryption for the Team Folders App is not enabled.') }}</b>
				</p>
				<p class="note-card--warning-description" v-html="getGroupFoldersEncryptionWarningHint" /> <!-- eslint-disable-line vue/no-v-html -->
			</NcNoteCard>
			<div v-if="showProjectFolderForm">
				<div v-if="isProjectFolderFormInViewMode" class="project-folder-status">
					<div class="project-folder-status-value">
						<b>{{ t('integration_openproject','Automatically managed folders:') }}</b> {{ projectFolderStatusLabel }}
					</div>
					<div class="form-actions">
						<NcButton
							data-test-id="edit-project-folder-setup"
							@click="editProjectFolder">
							<template #icon>
								<PencilIcon :size="20" />
							</template>
							{{ t('integration_openproject', 'Edit project folders') }}
						</NcButton>
					</div>
				</div>
				<div v-else>
					<NcCheckboxRadioSwitch type="switch" :checked.sync="enableProjectFolder" @update:checked="changeProjectFolderState">
						<b>{{ t('integration_openproject', 'Automatically managed folders') }}</b>
					</NcCheckboxRadioSwitch>
					<div v-if="!enableProjectFolder" class="complete-without-groupfolders">
						<p class="project-folder-description">
							{{
								t('integration_openproject', 'We recommend using this functionality but it is not mandatory. Please activate it in case you want to use the automatic creation and management of project folders.')
							}}
						</p>
						<div class="form-actions">
							<NcButton type="primary"
								data-test-id="complete-without-project-folder-form-btn"
								@click="saveProjectFolder()">
								<template #icon>
									<CheckBoldIcon fill-color="#FFFFFF" :size="20" />
								</template>
								{{
									folderSetupButtonLabel
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
								@click="saveProjectFolder()">
								<template #icon>
									<NcLoadingIcon v-if="loading" class="loading-spinner" :size="20" />
									<RestoreIcon v-else-if="projectFolderSetupError" fill-color="#FFFFFF" :size="20" />
									<CheckBoldIcon v-else fill-color="#FFFFFF" :size="20" />
								</template>
								{{ folderSetupButtonLabel }}
							</NcButton>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div v-if="showAppPasswordForm">
			<FormHeading index="6"
				:title="t('integration_openproject', 'Project folders application connection')"
				:is-complete="hasAppPassword"
				:is-disabled="isAppPasswordFormInDisableMode"
				:is-dark-theme="isDarkTheme" />
			<TextInput v-if="isAppPasswordFormInEditMode"
				id="openproject-system-password"
				v-model="appPassword"
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
				<NcButton v-if="isAppPasswordFormInEditMode"
					type="primary"
					:disabled="!appPassword"
					data-test-id="submit-op-system-password-form-btn"
					@click="setAppPasswordFormToViewMode">
					<template #icon>
						<CheckBoldIcon fill-color="#FFFFFF" :size="20" />
					</template>
					{{ t('integration_openproject', 'Done, complete setup') }}
				</NcButton>
				<NcButton v-else
					data-test-id="reset-user-app-password"
					@click="resetAppPassword">
					<template #icon>
						<AutoRenewIcon :size="20" />
					</template>
					{{ t('integration_openproject', 'Replace application password') }}
				</NcButton>
			</div>
		</div>
	</section>
</template>

<script>
import { NcLoadingIcon, NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import AutoRenewIcon from 'vue-material-design-icons/Autorenew.vue'
import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import FieldValue from './FieldValue.vue'
import FormHeading from './FormHeading.vue'
import TextInput from './TextInput.vue'
import ErrorNote from '../settings/ErrorNote.vue'
import { appLinks } from '../../constants/links.js'
import { messages, messagesFmt } from '../../constants/messages.js'
import { F_MODES } from '../../utils.js'
import { saveAdminConfig, getProjectFolderStatus } from '../../api/settings.js'

const FOLDER_SETUP_BUTTON_LABEL = {
	keepCurrentChange: t('integration_openproject', 'Keep current setup'),
	completeWithoutProjectFolderSetup: t('integration_openproject', 'Complete without project folders'),
	completeWithProjectFolderSetup: t('integration_openproject', 'Setup OpenProject user, group and folder'),
	retrySetupWithProjectFolder: t('integration_openproject', 'Retry setup OpenProject user, group and folder'),
}

export default {
	name: 'FormProjectFolder',
	components: {
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcButton,
		AutoRenewIcon,
		CheckBoldIcon,
		PencilIcon,
		FieldValue,
		FormHeading,
		TextInput,
		ErrorNote,
	},
	props: {
		isDarkTheme: {
			type: Boolean,
			default: false,
		},
		formState: {
			type: Object,
			required: true,
		},
		formOrder: {
			type: String,
			required: true,
		},
		projectFolderInfo: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			loading: false,
			folderFormMode: F_MODES.DISABLE,
			passwordFormMode: F_MODES.DISABLE,
			projectFolderSetupError: null,
			enableProjectFolder: true,
			folderSetupButtonLabel: FOLDER_SETUP_BUTTON_LABEL.completeWithProjectFolderSetup,
			appPassword: null,
			messages,
			messagesFmt,
			appLinks,
		}
	},
	computed: {
		showProjectFolderForm() {
			return this.isAuthorizationSettingFormComplete || this.isProjectFolderFormComplete
		},
		isAuthorizationSettingFormComplete() {
			return (this.formState.openprojectOauth.complete && this.formState.nextcloudOauth.complete) || this.formState.ssoSettings.complete
		},
		isProjectFolderFormComplete() {
			return this.formState.projectFolder.complete
		},
		isProjectFolderFormInDisableMode() {
			return this.folderFormMode === F_MODES.DISABLE
		},
		isProjectFolderFormInViewMode() {
			return this.folderFormMode === F_MODES.VIEW
		},
		isProjectFolderFormInEditMode() {
			return this.folderFormMode === F_MODES.EDIT
		},
		projectFolderStatusLabel() {
			if (this.enableProjectFolder) {
				return t('integration_openproject', 'Active')
			}
			return t('integration_openproject', 'Inactive')
		},
		unchangedProjectFolderForm() {
			return this.enableProjectFolder === this.projectFolderInfo.projectFolderEnabled
				&& this.folderSetupButtonLabel === FOLDER_SETUP_BUTTON_LABEL.keepCurrentChange
		},
		showAppPasswordForm() {
			return this.isProjectFolderFormComplete
				&& this.isProjectFolderFormInViewMode
				&& this.enableProjectFolder
		},
		hasAppPassword() {
			return !!this.projectFolderInfo.hasAppPassword || !!this.appPassword
		},
		isAppPasswordFormInDisableMode() {
			return this.passwordFormMode === F_MODES.DISABLE
		},
		isAppPasswordFormInViewMode() {
			return this.passwordFormMode === F_MODES.VIEW
		},
		isAppPasswordFormInEditMode() {
			return this.passwordFormMode === F_MODES.EDIT
		},
		isSetupCompleteWithoutProjectFolder() {
			if (this.isProjectFolderFormInEditMode || !this.isProjectFolderFormComplete) {
				return false
			}
			return (!this.projectFolderInfo.projectFolderEnabled && !this.projectFolderInfo.hasAppPassword)
				|| (!this.enableProjectFolder && !this.appPassword)
		},
		isThereErrorAfterProjectFolderAndAppPasswordSetup() {
			return (this.appPassword && !this.isProjectFolderFormInEditMode && this.isProjectFolderSetupCorrect === false)
		},
		showGroupfoldersAppError() {
			return this.enableProjectFolder && !this.hasEnabledSupportedGroupfoldersApp && !this.isProjectFolderFormInDisableMode
		},
		getSetupErrorMessage() {
			return this.projectFolderInfo.folderStatus?.errorMessage
		},
		showEncryptionWarningForGroupFolders() {
			if (!this.isProjectFolderAlreadySetup || !this.projectFolderInfo.hasAppPassword || this.isProjectFolderFormInEditMode) {
				return false
			}
			return this.projectFolderInfo.encryption.server_side_encryption_enabled
				&& !this.projectFolderInfo.encryption.encryption_enabled_for_groupfolders
		},
		hasEnabledSupportedGroupfoldersApp() {
			return this.projectFolderInfo.app.enabled && this.projectFolderInfo.app.supported
		},
		getTeamFolderAppName() {
			return this.projectFolderInfo.app.name
		},
		getMinSupportedTeamFolderAppVersion() {
			return this.projectFolderInfo.app.minimum_version
		},
		projectFolderSetUpErrorMessageDescription() {
			const linkText = t('integration_openproject', 'troubleshooting guide')
			const htmlLink = `<a class="link" href="https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#troubleshooting" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'Setting up the OpenProject user, group and team folder was not possible. Please check this {htmlLink} on how to resolve this situation.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		getGroupFoldersEncryptionWarningHint() {
			const linkText = t('integration_openproject', 'documentation')
			const htmlLink = `<a class="link" href="https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#files-are-not-encrypted-when-using-nextcloud-server-side-encryption" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'Server-side encryption is active, but encryption for Team Folders is not yet enabled. To ensure secure storage of files in project folders, please follow the configuration steps in the {htmlLink}.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		userAppPasswordHint() {
			const linkText = t('integration_openproject', 'Administration > File storages')
			const htmlLink = `<a class="link" href="${this.adminFileStorageHref}" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'This value will only be accessible once. Now, as an administrator copy this password to OpenProject {htmlLink}.', { htmlLink }, null, { escape: false, sanitize: false })
		},
	},
	created() {
		console.info(this.projectFolderInfo)
		this.enableProjectFolder = this.projectFolderInfo.projectFolderEnabled
		if ((this.projectFolderInfo.projectFolderEnabled && this.projectFolderInfo.hasAppPassword)
			|| (!this.projectFolderInfo.projectFolderEnabled && !this.projectFolderInfo.hasAppPassword)
		) {
			this.setProjectFolderFormToViewMode()
			this.$emit('formcomplete', this.markFormComplete)
		}

		if (this.projectFolderInfo.hasAppPassword) {
			this.setAppPasswordFormToViewMode()
		}
	},
	methods: {
		markFormComplete(formState) {
			formState.projectFolder.complete = true
			return formState
		},
		setProjectFolderFormToViewMode() {
			this.folderFormMode = F_MODES.VIEW
		},
		setProjectFolderFormToEditMode() {
			this.folderFormMode = F_MODES.EDIT
		},
		setAppPasswordFormToViewMode() {
			this.passwordFormMode = F_MODES.VIEW
		},
		setAppPasswordFormToEditMode() {
			this.passwordFormMode = F_MODES.EDIT
		},
		editProjectFolder() {
			this.setProjectFolderFormToEditMode()
			this.folderSetupButtonLabel = FOLDER_SETUP_BUTTON_LABEL.keepCurrentChange
		},
		changeProjectFolderState() {
			if (this.isProjectFolderFormComplete) {
			 if (this.enableProjectFolder === this.projectFolderInfo.projectFolderEnabled) {
					this.folderSetupButtonLabel = FOLDER_SETUP_BUTTON_LABEL.keepCurrentChange
			 } else if (this.enableProjectFolder) {
					this.folderSetupButtonLabel = FOLDER_SETUP_BUTTON_LABEL.completeWithProjectFolderSetup
			 } else {
					this.folderSetupButtonLabel = FOLDER_SETUP_BUTTON_LABEL.completeWithoutProjectFolderSetup
			 }
			} else if (this.enableProjectFolder) {
				this.folderSetupButtonLabel = FOLDER_SETUP_BUTTON_LABEL.completeWithProjectFolderSetup
			} else {
				this.folderSetupButtonLabel = FOLDER_SETUP_BUTTON_LABEL.completeWithoutProjectFolderSetup
			}
		},
		async hasExistingProjectFolder() {
			try {
				const response = await getProjectFolderStatus()
				return response.data?.result
			} catch (error) {
				console.error(error)
			}
			return false
		},
		async saveProjectFolder(recreateAppPassword = false) {
			if (this.unchangedProjectFolderForm) {
				this.setProjectFolderFormToViewMode()
				return
			}

			const data = {
				setup_project_folder: this.enableProjectFolder,
				setup_app_password: this.enableProjectFolder,
			}

			// only send setup_app_password if recreating app password
			if (recreateAppPassword) {
				delete data.setup_project_folder
				data.setup_app_password = true
			} else if (this.enableProjectFolder) {
				const projectFolderExists = await this.hasExistingProjectFolder()
				// keep the current setup if found
				if (projectFolderExists && this.hasAppPassword) {
					this.setProjectFolderFormToViewMode()
					return
				} else if (projectFolderExists && !this.hasAppPassword) {
					delete data.setup_project_folder
					data.setup_app_password = true
				}
			}

			this.loading = true
			try {
				const response = await saveAdminConfig(data)

				if (this.enableProjectFolder) {
					this.appPassword = response.data.oPUserAppPassword
					this.setAppPasswordFormToEditMode()
				}

				this.setProjectFolderFormToViewMode()
				this.$emit('formcomplete', this.markFormComplete)
				showSuccess(t('integration_openproject', 'OpenProject admin options saved'))
			} catch (error) {
				let errorMessage = error.message
				if (error.response?.data?.error) {
					this.projectFolderSetupError = error.response.data.error
					errorMessage = error.response.data.error
				}
				const message = t('integration_openproject', 'Failed to save OpenProject admin options')
				showError(message + ': ' + errorMessage)
			}
			this.loading = false
			if (this.isProjectFolderFormInViewMode) {
				this.projectFolderSetupError = null
			}
		},
		async recreateAppPassword() {
			this.setAppPasswordFormToEditMode()
			await this.saveProjectFolder(true)
		},
		resetAppPassword() {
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
						await this.recreateAppPassword()
					}
				},
				true,
			)
		},
	},
}
</script>

<style scoped lang="scss">
.py-1 {
	padding: 0.3rem 0;
}
</style>
