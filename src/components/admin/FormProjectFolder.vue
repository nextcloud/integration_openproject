<!--
  - SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section>
		<div class="project-folder-setup">
			<FormHeading :index="projectFolderFormIndex"
				:is-project-folder-setup-heading="true"
				:title="t('integration_openproject', 'Project folders (recommended)')"
				:is-setup-complete-without-project-folders="isProjectFolderFormInViewMode && isSetupCompleteWithoutProjectFolder"
				:has-error="!!projectFolderSetupError || showTeamfolderAppError"
				:show-encryption-warning-for-group-folders="showEncryptionWarning"
				:is-complete="isProjectFolderFormComplete && savedProjectFolderState"
				:is-disabled="isProjectFolderFormInDisableMode"
				:is-dark-theme="isDarkTheme" />
			<ErrorNote
				v-if="showTeamfolderAppError"
				:error-title="messagesFmt.appNotEnabledOrUnsupported(getTeamfolderAppName, getMinSupportedTeamfolderAppVersion)"
				:error-link="appLinks.groupfolders.installLink"
				:error-link-label="messages.installLatestVersionNow" />
			<NcNoteCard v-else-if="projectFolderSetupError" class="note-card" type="error">
				<p class="note-card--title">
					<b>{{ projectFolderSetupError }}</b>
				</p>
				<p class="note-card--error-description" v-html="getProjectFolderSetupErrorDescription" /> <!-- eslint-disable-line vue/no-v-html -->
			</NcNoteCard>
			<NcNoteCard v-else-if="showEncryptionWarning" class="note-card" type="warning">
				<p class="note-card--title">
					<b>{{ t('integration_openproject', 'Encryption for the Team Folders App is not enabled.') }}</b>
				</p>
				<p class="note-card--warning-description" v-html="getProjectFolderEncryptionWarning" /> <!-- eslint-disable-line vue/no-v-html -->
			</NcNoteCard>
			<div v-if="showProjectFolderForm" class="project-folder-form-container">
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
				<div v-else class="project-folder-form">
					<NcCheckboxRadioSwitch type="switch" :checked.sync="currentProjectFolderState" @update:checked="changeProjectFolderState">
						<b>{{ t('integration_openproject', 'Automatically managed folders') }}</b>
					</NcCheckboxRadioSwitch>
					<div v-if="!currentProjectFolderState" class="complete-without-groupfolders">
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
								:disabled="showTeamfolderAppError"
								data-test-id="complete-with-project-folders-form-btn"
								@click="saveProjectFolder()">
								<template #icon>
									<NcLoadingIcon v-if="loading" class="loading-spinner" :size="20" />
									<RestoreIcon v-else-if="retryProjectFolderSetup" fill-color="#FFFFFF" :size="20" />
									<CheckBoldIcon v-else fill-color="#FFFFFF" :size="20" />
								</template>
								{{ folderSetupButtonLabel }}
							</NcButton>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div v-if="showAppPasswordForm" class="app-password-form-container">
			<FormHeading :index="appPasswordFormIndex"
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
				:hint-text="getAppPasswordHint" />
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
import util from 'util'
import { NcLoadingIcon, NcButton, NcCheckboxRadioSwitch, NcNoteCard } from '@nextcloud/vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import AutoRenewIcon from 'vue-material-design-icons/Autorenew.vue'
import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import FieldValue from './FieldValue.vue'
import FormHeading from './FormHeading.vue'
import TextInput from './TextInput.vue'
import ErrorNote from '../settings/ErrorNote.vue'
import { appLinks } from '../../constants/links.js'
import { messages, messagesFmt } from '../../constants/messages.js'
import { F_MODES } from '../../utils.js'
import { saveAdminConfig, getProjectFolderStatus } from '../../api/settings.js'

export default {
	name: 'FormProjectFolder',
	components: {
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcButton,
		NcNoteCard,
		AutoRenewIcon,
		CheckBoldIcon,
		PencilIcon,
		RestoreIcon,
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
			type: Number,
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
			currentProjectFolderState: true,
			savedProjectFolderState: null,
			folderSetupButtonLabel: messages.projectFolderSetup.completeWithProjectFolderSetup,
			appPassword: null,
			appPasswordCreated: false,
			retryProjectFolderSetup: false,
			messages,
			messagesFmt,
			appLinks,
		}
	},
	computed: {
		projectFolderFormIndex() {
			return this.formOrder.toString()
		},
		appPasswordFormIndex() {
			return (this.formOrder + 1).toString()
		},
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
			if (this.savedProjectFolderState) {
				return t('integration_openproject', 'Active')
			}
			return t('integration_openproject', 'Inactive')
		},
		unchangedProjectFolderForm() {
			return this.savedProjectFolderState === this.currentProjectFolderState
		},
		showAppPasswordForm() {
			return this.isProjectFolderFormComplete && this.isSetupCompleteWithProjectFolder && this.currentProjectFolderState
		},
		hasAppPassword() {
			return this.appPasswordCreated
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
			return !this.appPasswordCreated && this.savedProjectFolderState === false
		},
		isSetupCompleteWithProjectFolder() {
			return this.appPasswordCreated && this.savedProjectFolderState === true
		},
		showTeamfolderAppError() {
			return this.currentProjectFolderState && !this.hasEnabledSupportedTeamfolderApp && !this.isProjectFolderFormInDisableMode
		},
		showEncryptionWarning() {
			if (!this.projectFolderInfo.folderStatus.status || !this.appPasswordCreated || this.isProjectFolderFormInEditMode) {
				return false
			}
			return this.projectFolderInfo.encryption.server_side_encryption_enabled
				&& !this.projectFolderInfo.encryption.encryption_enabled_for_groupfolders
		},
		hasEnabledSupportedTeamfolderApp() {
			return this.projectFolderInfo.app.enabled && this.projectFolderInfo.app.supported
		},
		getTeamfolderAppName() {
			return this.projectFolderInfo.app.name
		},
		getMinSupportedTeamfolderAppVersion() {
			return this.projectFolderInfo.app.minimum_version
		},
		openprojectFileStorageUrl() {
			const path = '%s/admin/settings/storages'
			const host = this.formState.serverHost.value
			return util.format(path, host)
		},
		getProjectFolderSetupErrorDescription() {
			const linkText = t('integration_openproject', 'troubleshooting guide')
			const htmlLink = `<a class="link" href="https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#troubleshooting" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'Setting up the OpenProject user, group and team folder was not possible. Please check this {htmlLink} on how to resolve this situation.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		getProjectFolderEncryptionWarning() {
			const linkText = t('integration_openproject', 'documentation')
			const htmlLink = `<a class="link" href="https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#files-are-not-encrypted-when-using-nextcloud-server-side-encryption" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'Server-side encryption is active, but encryption for Team Folders is not yet enabled. To ensure secure storage of files in project folders, please follow the configuration steps in the {htmlLink}.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		getAppPasswordHint() {
			const linkText = t('integration_openproject', 'Administration > File storages')
			const htmlLink = `<a class="link" href="${this.openprojectFileStorageUrl}" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'This value will only be accessible once. Now, as an administrator copy this password to OpenProject {htmlLink}.', { htmlLink }, null, { escape: false, sanitize: false })
		},
	},
	created() {
		if (!this.projectFolderInfo.freshSetup) {
			this.setProjectFolderFormToViewMode()
			this.$emit('formcomplete', this.markFormComplete)

			if (this.projectFolderInfo.hasAppPassword) {
				this.savedProjectFolderState = true
				this.appPasswordCreated = true
				this.setAppPasswordFormToViewMode()
			} else {
				this.savedProjectFolderState = false
			}
			this.currentProjectFolderState = this.savedProjectFolderState

			if (this.projectFolderInfo.folderStatus?.errorMessage && this.currentProjectFolderState) {
				this.projectFolderSetupError = this.projectFolderInfo.folderStatus.errorMessage
			}

			this.updateProjectFolderButtonLabel()
		} else if (this.isAuthorizationSettingFormComplete && !this.isProjectFolderFormComplete) {
			this.setProjectFolderFormToEditMode()
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
		setAppPasswordFormToDisableMode() {
			this.passwordFormMode = F_MODES.DISABLE
		},
		setAppPasswordFormToViewMode() {
			this.passwordFormMode = F_MODES.VIEW
		},
		setAppPasswordFormToEditMode() {
			this.passwordFormMode = F_MODES.EDIT
		},
		editProjectFolder() {
			this.setProjectFolderFormToEditMode()
			this.folderSetupButtonLabel = messages.projectFolderSetup.keepCurrentChange
		},
		updateProjectFolderButtonLabel() {
			if (this.currentProjectFolderState) {
				if (this.projectFolderSetupError && this.appPasswordCreated) {
					this.folderSetupButtonLabel = messages.projectFolderSetup.retrySetupWithProjectFolder
				} else {
					this.folderSetupButtonLabel = messages.projectFolderSetup.completeWithProjectFolderSetup
				}
			} else {
				this.folderSetupButtonLabel = messages.projectFolderSetup.completeWithoutProjectFolderSetup
			}
		},
		changeProjectFolderState() {
			// update error message when user toggles the switch
			if (!this.currentProjectFolderState) {
				this.projectFolderSetupError = null
			} else if (this.projectFolderInfo.folderStatus?.errorMessage) {
				this.projectFolderSetupError = this.projectFolderInfo.folderStatus.errorMessage
			}

			if (this.isProjectFolderFormComplete) {
				if (this.unchangedProjectFolderForm) {
					this.folderSetupButtonLabel = messages.projectFolderSetup.keepCurrentChange
				} else {
					this.updateProjectFolderButtonLabel()
				}
			} else {
				this.updateProjectFolderButtonLabel()
			}
		},
		async hasExistingProjectFolder() {
			try {
				const response = await getProjectFolderStatus()
				return response.data?.result
			} catch (error) {
				console.error(error.message)
			}
			return false
		},
		async saveProjectFolder(recreateAppPassword = false) {
			if (this.unchangedProjectFolderForm && !recreateAppPassword) {
				this.setProjectFolderFormToViewMode()
				return
			}

			const data = {
				setup_project_folder: this.currentProjectFolderState,
				setup_app_password: this.currentProjectFolderState,
			}

			// only send setup_app_password if recreating app password
			if (recreateAppPassword) {
				delete data.setup_project_folder
				data.setup_app_password = true
			} else if (this.currentProjectFolderState) {
				// state is not loaded so check using API
				const projectFolderExists = await this.hasExistingProjectFolder()
				// keep the current setup if found
				if (projectFolderExists && this.hasAppPassword) {
					this.setProjectFolderFormToViewMode()
					return
				} else if (projectFolderExists && !this.hasAppPassword) {
					data.setup_project_folder = false
					data.setup_app_password = true
				}
			}

			this.loading = true
			try {
				const response = await saveAdminConfig(data)

				if (this.currentProjectFolderState) {
					this.appPassword = response.data.oPUserAppPassword
					this.appPasswordCreated = true
					this.setAppPasswordFormToEditMode()
				} else {
					this.appPassword = null
					this.appPasswordCreated = false
					this.setAppPasswordFormToDisableMode()
				}

				this.setProjectFolderFormToViewMode()
				this.$emit('formcomplete', this.markFormComplete)
				showSuccess(t('integration_openproject', 'OpenProject admin options saved'))
				this.savedProjectFolderState = this.currentProjectFolderState
			} catch (error) {
				let errorMessage = error.message
				if (error.response?.data?.error) {
					this.projectFolderSetupError = error.response.data.error
					if (this.currentProjectFolderState) {
						this.retryProjectFolderSetup = true
						this.folderSetupButtonLabel = messages.projectFolderSetup.retrySetupWithProjectFolder
					}
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
.project-folder-description {
	font-weight: 400;
}

.project-folder-status-value {
	padding: 6px 0;

}

.py-1 {
	padding: 0.3rem 0;
}
</style>
