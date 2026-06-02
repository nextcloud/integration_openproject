<!--
  - SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section>
		<div :class="openprojectFormId">
			<FormHeading
				:index="openprojectFormOrder"
				:title="t('integration_openproject', 'OpenProject OAuth settings')"
				:is-complete="isOpenProjectFormComplete"
				:is-disabled="!showOpenProjectForm"
				:is-dark-theme="isDarkTheme" />
			<div v-if="showOpenProjectForm" class="oauth-settings--openproject">
				<FieldValue
					v-if="isOpenProjectFormInViewMode"
					is-required
					:value="savedOpenProjectForm.clientId"
					title="OpenProject OAuth client ID" />
				<TextInput
					v-else
					id="openproject-oauth-client-id"
					v-model="currentOpenProjectForm.clientId"
					class="py-1"
					is-required
					label="OpenProject OAuth client ID"
					:hint-text="openProjectClientHint" />
				<FieldValue
					v-if="isOpenProjectFormInViewMode"
					is-required
					class="pb-1"
					encrypt-value
					title="OpenProject OAuth client secret"
					:value="savedOpenProjectForm.clientSecret" />
				<TextInput
					v-else
					id="openproject-oauth-client-secret"
					v-model="currentOpenProjectForm.clientSecret"
					is-required
					class="py-1"
					label="OpenProject OAuth client secret"
					:hint-text="openProjectClientHint" />
				<div class="form-actions">
					<NcButton
						v-if="isOpenProjectFormComplete && isOpenProjectFormInViewMode"
						data-test-id="reset-op-oauth-btn"
						@click="resetOpenProjectClient">
						<template #icon>
							<AutoRenewIcon :size="20" />
						</template>
						{{ t('integration_openproject', 'Replace OpenProject OAuth values') }}
					</NcButton>
					<NcButton
						v-else
						data-test-id="submit-op-oauth-btn"
						type="primary"
						:disabled="disableOpenProjectFormSave"
						@click="saveOpenProjectClient">
						<template #icon>
							<NcLoadingIcon v-if="loading" class="loading-spinner" :size="20" />
							<CheckBoldIcon v-else fill-color="#FFFFFF" :size="20" />
						</template>
						{{ t('integration_openproject', 'Save') }}
					</NcButton>
				</div>
			</div>
		</div>
		<div :class="nextcloudFormId">
			<FormHeading
				:index="nextcloudFormOrder"
				:title="t('integration_openproject', 'Nextcloud OAuth client')"
				:is-complete="isNextcloudFormComplete"
				:is-disabled="!showNextcloudForm"
				:is-dark-theme="isDarkTheme" />
			<div v-if="showNextcloudForm" class="oauth-settings--nextcloud">
				<FieldValue
					v-if="isNextcloudFormInViewMode"
					title="Nextcloud OAuth client ID"
					:value="savedNextcloudForm.clientId"
					is-required />
				<TextInput
					v-else-if="isNextcloudFormComplete"
					id="nextcloud-oauth-client-id"
					v-model="savedNextcloudForm.clientId"
					class="py-1"
					read-only
					is-required
					with-copy-btn
					label="Nextcloud OAuth client ID"
					:hint-text="nextcloudClientHint" />
				<FieldValue
					v-if="isNextcloudFormInViewMode"
					title="Nextcloud OAuth client secret"
					is-required
					encrypt-value
					value="***" />
				<TextInput
					v-else-if="isNextcloudFormComplete"
					id="nextcloud-oauth-client-secret"
					v-model="savedNextcloudForm.clientSecret"
					class="py-1"
					read-only
					is-required
					with-copy-btn
					label="Nextcloud OAuth client secret"
					:hint-text="nextcloudClientHint" />
				<div class="form-actions">
					<NcButton
						v-if="
							!isNextcloudFormComplete
								&& isOpenProjectFormComplete
								&& isOpenProjectFormInViewMode"
						data-test-id="create-nc-oauth-btn"
						@click="createNextcloudClient">
						<template #icon>
							<AutoRenewIcon :size="20" />
						</template>
						{{ t('integration_openproject', 'Create Nextcloud OAuth values') }}
					</NcButton>
					<NcButton
						v-else-if="isNextcloudFormComplete && isNextcloudFormInViewMode"
						data-test-id="reset-nc-oauth-btn"
						@click="resetNextcloudClient">
						<template #icon>
							<AutoRenewIcon :size="20" />
						</template>
						{{ t('integration_openproject', 'Replace Nextcloud OAuth values') }}
					</NcButton>
					<NcButton
						v-else
						type="primary"
						:disabled="disableNextcloudFormSave"
						data-test-id="submit-nc-oauth-btn"
						@click="setNextcloudFromToViewMode">
						<template #icon>
							<CheckBoldIcon fill-color="#FFFFFF" :size="20" />
						</template>
						{{ t('integration_openproject', 'Yes, I have copied these values') }}
					</NcButton>
				</div>
			</div>
		</div>
	</section>
</template>

<script>
import { NcLoadingIcon, NcButton } from '@nextcloud/vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import AutoRenewIcon from 'vue-material-design-icons/Autorenew.vue'
import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import FieldValue from './FieldValue.vue'
import FormHeading from './FormHeading.vue'
import TextInput from './TextInput.vue'
import { F_MODES, ADMIN_SETTINGS_FORM, AUTH_METHOD } from '../../utils.js'
import { saveAdminConfig, createNextcloudOAuthClient } from '../../api/settings.js'
import { messages, messagesFmt } from '../../constants/messages.js'

export default {
	name: 'FormOAuthSettings',
	components: {
		NcLoadingIcon,
		NcButton,
		AutoRenewIcon,
		CheckBoldIcon,
		FieldValue,
		FormHeading,
		TextInput,
	},
	props: {
		formState: {
			type: Object,
			required: true,
		},
		oauthSettings: {
			type: Object,
			required: true,
		},
		isDarkTheme: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			openprojectFormMode: F_MODES.NEW,
			nextcloudFormMode: F_MODES.NEW,
			openprojectFormId: ADMIN_SETTINGS_FORM.openprojectOauth.id,
			nextcloudFormId: ADMIN_SETTINGS_FORM.nextcloudOauth.id,
			openprojectFormOrder: ADMIN_SETTINGS_FORM.openprojectOauth.order.toString(),
			nextcloudFormOrder: ADMIN_SETTINGS_FORM.nextcloudOauth.order.toString(),
			messages,
			messagesFmt,
			loading: false,
			openprojectTokenRevokeStatus: null,
			// state that holds the current changed form values
			currentOpenProjectForm: {
				clientId: this.oauthSettings.openproject_client_id || '',
				clientSecret: this.oauthSettings.openproject_client_secret || '',
			},
			// state that holds the saved form values (useful for resetting)
			savedOpenProjectForm: {
				clientId: this.oauthSettings.openproject_client_id || '',
				clientSecret: this.oauthSettings.openproject_client_secret || '',
			},
			savedNextcloudForm: {
				clientId: this.oauthSettings.nc_oauth_client?.nextcloud_client_id || '',
				clientSecret: '',
			},
		}
	},
	computed: {
		isOpenProjectFormComplete() {
			return !!this.savedOpenProjectForm.clientId && !!this.savedOpenProjectForm.clientSecret
		},
		isOpenProjectFormInViewMode() {
			return this.openprojectFormMode === F_MODES.VIEW
		},
		isOpenProjectFormInEditMode() {
			return this.openprojectFormMode === F_MODES.EDIT
		},
		showOpenProjectForm() {
			return this.formState.authenticationMethod.complete
				&& this.formState.authenticationMethod.value === AUTH_METHOD.OAUTH2
		},
		disableOpenProjectFormSave() {
			return !this.currentOpenProjectForm.clientId || !this.currentOpenProjectForm.clientSecret
		},
		openProjectClientHint() {
			const linkText = t('integration_openproject', 'Administration > File storages')
			const htmlLink = `<a class="link" href="${this.adminFileStorageHref}" target="_blank" title="${linkText}">${linkText}</a>`
			return t(
				'integration_openproject',
				'Go to your OpenProject {htmlLink} as an Administrator and start the setup and copy the values here.',
				{ htmlLink },
				null,
				{ escape: false, sanitize: false },
			)
		},
		isNextcloudFormComplete() {
			return !!this.savedNextcloudForm.clientId
		},
		isNextcloudFormInViewMode() {
			return this.nextcloudFormMode === F_MODES.VIEW
		},
		isNextcloudFormInEditMode() {
			return this.nextcloudFormMode === F_MODES.EDIT
		},
		showNextcloudForm() {
			return this.formState.openprojectOauth.complete || this.isNextcloudFormComplete
		},
		disableNextcloudFormSave() {
			return !this.savedNextcloudForm.clientId || !this.savedNextcloudForm.clientSecret
		},
		nextcloudClientHint() {
			const linkText = t('integration_openproject', 'Administration > File storages')
			const htmlLink = `<a class="link" href="${this.adminFileStorageHref}" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'Copy the following values back into the OpenProject {htmlLink} as an Administrator.', { htmlLink }, null, { escape: false, sanitize: false })
		},
	},
	watch: {},
	created() {
		if (this.isOpenProjectFormComplete) {
			this.setOpenProjectFromToViewMode()
			this.$emit('formcomplete', this.markOpenProjectFormComplete)
		} else {
			this.setOpenProjectFormToEditMode()
		}

		if (this.isNextcloudFormComplete) {
			this.setNextcloudFromToViewMode()
			this.$emit('formcomplete', this.markNextcloudFormComplete)
		}
	},
	methods: {
		markOpenProjectFormComplete(formState) {
			formState.openprojectOauth.complete = true
			return formState
		},
		markNextcloudFormComplete(formState) {
			formState.nextcloudOauth.complete = true
			return formState
		},
		setOpenProjectFormMode(mode) {
			this.openprojectFormMode = mode
		},
		setOpenProjectFromToViewMode() {
			this.setOpenProjectFormMode(F_MODES.VIEW)
		},
		setOpenProjectFormToEditMode() {
			this.setOpenProjectFormMode(F_MODES.EDIT)
		},
		setNextcloudFormMode(mode) {
			this.nextcloudFormMode = mode
		},
		setNextcloudFromToViewMode() {
			this.setNextcloudFormMode(F_MODES.VIEW)
			this.$emit('formcomplete', this.markNextcloudFormComplete)
		},
		setNextcloudFormToEditMode() {
			this.setNextcloudFormMode(F_MODES.EDIT)
		},
		notifyOpenProjectTokenRevoke() {
			switch (this.openprojectTokenRevokeStatus) {
			case 'connection_error':
				showError(
					t(
						'integration_openproject',
						'Failed to perform revoke request due to connection error with the OpenProject server',
					),
				)
				break
			case 'other_error':
				showError(
					t('integration_openproject', "Failed to revoke some users' OpenProject OAuth access tokens"),
				)
				break
			case 'success':
				showSuccess(
					t('integration_openproject', "Successfully revoked users' OpenProject OAuth access tokens"),
				)
				break
			default:
				break
			}
		},
		async createNextcloudClient() {
			try {
				const response = await createNextcloudOAuthClient()
				const { nextcloud_client_id: clientId, nextcloud_client_secret: clientSecret } = response.data
				this.savedNextcloudForm.clientId = clientId
				this.savedNextcloudForm.clientSecret = clientSecret
				// allow copying the generated Nextcloud OAuth client values
				// before switching to view mode
				this.setNextcloudFormToEditMode()
			} catch (error) {
				const errorMessage = t('integration_openproject', 'Failed to create Nextcloud OAuth client')
				showError(errorMessage + ': ' + error?.response?.request?.responseText)
			}
		},
		async saveOpenProjectClient() {
			try {
				const response = await saveAdminConfig({
					openproject_client_id: this.currentOpenProjectForm.clientId,
					openproject_client_secret: this.currentOpenProjectForm.clientSecret,
				})
				this.openprojectTokenRevokeStatus = response?.data?.oPOAuthTokenRevokeStatus
				this.savedOpenProjectForm = structuredClone(this.currentOpenProjectForm)
				this.setOpenProjectFromToViewMode()
				this.$emit('formcomplete', this.markOpenProjectFormComplete)
				showSuccess(t('integration_openproject', 'OpenProject admin options saved'))

				// Add new Nextcloud OAuth client if does not exist
				if (!this.savedNextcloudForm.clientId) {
					await this.createNextcloudClient()
				}
			} catch (error) {
				this.openprojectTokenRevokeStatus = null
				const errorMessage = error?.response?.data?.error || error?.message
				console.error(errorMessage)
				showError(t('integration_openproject', 'Failed to save OpenProject admin options'))
			}
			this.notifyOpenProjectTokenRevoke()
		},
		resetOpenProjectClient() {
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
						await this.confirmResetOpenProjectClient()
					}
				},
				true,
			)
		},
		async confirmResetOpenProjectClient() {
			this.currentOpenProjectForm.clientId = ''
			this.currentOpenProjectForm.clientSecret = ''
			await this.saveOpenProjectClient()
			this.setOpenProjectFormToEditMode()
		},
		resetNextcloudClient() {
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
						this.createNextcloudClient()
					}
				},
				true,
			)
		},
	},
}
</script>

<style scoped lang="scss">
.pb-1 {
	padding-bottom: 0.5rem;
}

.py-1 {
	padding: 0.3rem 0;
}
</style>
