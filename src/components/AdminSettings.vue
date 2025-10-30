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
			:current-setting="currentSetting"
			@formcomplete="markFormComplete" />
		<FormAuthMethod
			:is-dark-theme="isDarkTheme"
			:auth-method="state.authorization_method"
			:apps="state.apps"
			:current-setting="currentSetting"
			@formcomplete="markFormComplete" />
		<div v-if="isOidcMethod" class="authorization-settings">
			<FormHeading index="3"
				:title="t('integration_openproject', 'Authentication settings')"
				:is-complete="isAuthorizationSettingFormComplete"
				:is-disabled="isAuthorizationSettingFormInDisabledMode"
				:is-dark-theme="isDarkTheme"
				:has-error="!hasEnabledSupportedUserOidcApp || hasOidcAppErrorWithNextcloudHub" />
			<ErrorNote
				v-if="!hasEnabledSupportedUserOidcApp"
				:error-title="messagesFmt.appNotEnabledOrUnsupported(getUserOidcAppName, getMinSupportedUserOidcVersion)"
				:error-link="appLinks.user_oidc.installLink"
				:error-link-label="messages.installLatestVersionNow" />
			<ErrorNote
				v-else-if="hasOidcAppErrorWithNextcloudHub"
				:error-title="messagesFmt.appNotEnabledOrUnsupported(getOidcAppName, getMinSupportedOidcVersion)"
				:error-link="appLinks.oidc.installLink"
				:error-link-label="messages.installLatestVersionNow" />
			<div class="authorization-settings--content">
				<FieldValue v-if="isAuthorizationSettingsInViewMode"
					is-required
					class="pb-1"
					:title="t('integration_openproject', 'OIDC Provider Type')"
					:value="getSSOProviderType" />
				<div v-else class="authorization-settings--content--section sso-provider-type">
					<p class="authorization-settings--content--label">
						{{ t('integration_openproject', 'OIDC Provider Type') }} *
					</p>
					<NcCheckboxRadioSwitch
						:checked.sync="authorizationSetting.SSOProviderType"
						:disabled="!hasEnabledSupportedUserOidcApp || !hasEnabledSupportedOIDCApp"
						:value="SSO_PROVIDER_TYPE.nextcloudHub"
						type="radio">
						{{ messages.nextcloudHubProvider }}
					</NcCheckboxRadioSwitch>
					<div class="error-container">
						<ErrorLabel
							v-if="!hasEnabledSupportedOIDCApp"
							:error="messagesFmt.appNotEnabledOrUnsupported(getOidcAppName, getMinSupportedOidcVersion)"
							:disabled="disableNCHubUnsupportedHint" />
					</div>
					<NcCheckboxRadioSwitch
						:checked.sync="authorizationSetting.SSOProviderType"
						:disabled="!hasEnabledSupportedUserOidcApp"
						:value="SSO_PROVIDER_TYPE.external"
						type="radio">
						{{ messages.externalOIDCProvider }}
					</NcCheckboxRadioSwitch>
				</div>
				<FieldValue v-if="isAuthorizationSettingsInViewMode && isExternalSSOProvider"
					is-required
					class="pb-1"
					:title="t('integration_openproject', 'OIDC Provider')"
					:value="getCurrentSelectedOIDCProvider" />
				<div v-else-if="isExternalSSOProvider" class="authorization-settings--content--section sso-provider">
					<p class="authorization-settings--content--label">
						{{ t('integration_openproject', 'Select a provider *') }}
					</p>
					<NcSelect
						input-id="provider-search-input"
						:disabled="!hasEnabledSupportedUserOidcApp"
						:placeholder="t('integration_openproject', 'Select an OIDC provider')"
						:options="registeredOidcProviders"
						:value="getCurrentSelectedOIDCProvider"
						:filterable="true"
						:close-on-select="true"
						:clear-search-on-blur="() => false"
						:append-to-body="false"
						:label-outside="true"
						:input-label="t('integration_openproject', 'OIDC provider')"
						@option:selected="onSelectOIDCProvider" />
					<p class="description" v-html="getConfigureOIDCHintText" /> <!-- eslint-disable-line vue/no-v-html -->
				</div>
				<FieldValue v-if="isAuthorizationSettingsInViewMode && isExternalSSOProvider"
					class="pb-1"
					:title="messages.enableTokenExchange"
					:value="authorizationSetting.enableTokenExchange" />
				<div v-else-if="isExternalSSOProvider" class="authorization-settings--content--section sso-token-exchange">
					<p class="authorization-settings--content--label">
						{{ messages.tokenExchangeFormLabel }}
					</p>
					<p class="description">
						{{ messages.tokenExchangeHintText }}
					</p>
					<NcCheckboxRadioSwitch
						type="switch"
						:checked.sync="authorizationSetting.enableTokenExchange">
						<b>{{ messages.enableTokenExchange }}</b>
					</NcCheckboxRadioSwitch>
				</div>
				<div v-if="showClientIDField">
					<FieldValue v-if="isAuthorizationSettingsInViewMode"
						is-required
						class="pb-1"
						:title="messages.opClientId"
						:value="state.authorization_settings.targeted_audience_client_id" />
					<div v-else class="authorization-settings--content--section sso-client-id">
						<TextInput
							id="authorization-method-target-client-id"
							v-model="authorizationSetting.currentTargetedAudienceClientIdSelected"
							class="py-1"
							is-required
							:disabled="!hasEnabledSupportedUserOidcApp || hasOidcAppErrorWithNextcloudHub"
							:place-holder="messages.opClientId"
							:label="messages.opClientId"
							:hint-text="messages.opClientIdHintText" />
					</div>
				</div>
			</div>
			<div class="form-actions">
				<NcButton v-if="isAuthorizationSettingsInViewMode"
					:disabled="!hasEnabledSupportedUserOidcApp"
					data-test-id="reset-auth-settings-btn"
					@click="setAuthorizationSettingInEditMode">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
					{{ t('integration_openproject', 'Edit authentication settings') }}
				</NcButton>
				<NcButton v-if="isSSOSettingsInEditMode"
					class="mr-2"
					data-test-id="cancel-edit-auth-setting-btn"
					@click="setAuthorizationSettingToViewMode">
					{{ t('integration_openproject', 'Cancel') }}
				</NcButton>
				<NcButton v-if="isAuthorizationSettingInEditMode"
					data-test-id="submit-oidc-auth-settings-values-btn"
					type="primary"
					:disabled="disableSaveSSOSettings"
					@click="saveOIDCAuthSetting">
					<template #icon>
						<NcLoadingIcon v-if="loadingAuthorizationMethodForm" class="loading-spinner" :size="20" />
						<CheckBoldIcon v-else fill-color="#FFFFFF" :size="20" />
					</template>
					{{ t('integration_openproject', 'Save') }}
				</NcButton>
			</div>
		</div>
		<div v-if="showOAuthSettings" class="openproject-oauth-values">
			<FormHeading index="3"
				:title="t('integration_openproject', 'OpenProject OAuth settings')"
				:is-complete="isOPOAuthFormComplete"
				:is-disabled="isOPOAuthFormInDisableMode"
				:is-dark-theme="isDarkTheme" />
			<div v-if="form.authenticationMethod.complete">
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
							<NcLoadingIcon v-if="loadingOPOauthForm" class="loading-spinner" :size="20" />
							<CheckBoldIcon v-else fill-color="#FFFFFF" :size="20" />
						</template>
						{{ t('integration_openproject', 'Save') }}
					</NcButton>
				</div>
			</div>
		</div>
		<div v-if="showOAuthSettings" class="nextcloud-oauth-values">
			<FormHeading index="4"
				:title="t('integration_openproject', 'Nextcloud OAuth client')"
				:is-complete="isNcOAuthFormComplete"
				:is-disabled="isNcOAuthFormInDisableMode"
				:is-dark-theme="isDarkTheme" />
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
					:value="ncClientSecret" />
				<div class="form-actions">
					<NcButton v-if="isNcOAuthFormInEdit"
						type="primary"
						:disabled="!ncClientId"
						data-test-id="submit-nc-oauth-values-form-btn"
						@click="setNCOAuthFormToViewMode">
						<template #icon>
							<CheckBoldIcon fill-color="#FFFFFF" :size="20" />
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
			<div v-if="!state.nc_oauth_client && isOPOAuthFormComplete && isOPOAuthFormInView && showDefaultManagedProjectFolders">
				<NcButton data-test-id="reset-nc-oauth-btn"
					@click="resetNcOauthValues">
					<template #icon>
						<AutoRenewIcon :size="20" />
					</template>
					{{ t('integration_openproject', 'Create Nextcloud OAuth values') }}
				</NcButton>
			</div>
		</div>
		<div class="project-folder-setup">
			<FormHeading :index="isOidcMethod ? '4' : '5'"
				:is-project-folder-setup-heading="true"
				:title="t('integration_openproject', 'Project folders (recommended)')"
				:is-setup-complete-without-project-folders="isSetupCompleteWithoutProjectFolders"
				:has-error="isThereErrorAfterProjectFolderAndAppPasswordSetup || showGroupfoldersAppError"
				:show-encryption-warning-for-group-folders="showEncryptionWarningForGroupFolders"
				:is-complete="isProjectFolderSetupCompleted"
				:is-disabled="isProjectFolderSetUpInDisableMode"
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
				<p class="note-card--error-description" v-html="projectFolderSetUpErrorMessageDescription" /> <!-- eslint-disable-line vue/no-v-html -->
			</NcNoteCard>
			<NcNoteCard v-else-if="showEncryptionWarningForGroupFolders" class="note-card" type="warning">
				<p class="note-card--title">
					<b>{{ t('integration_openproject', 'Encryption for the Team Folders App is not enabled.') }}</b>
				</p>
				<p class="note-card--warning-description" v-html="getGroupFoldersEncryptionWarningHint" /> <!-- eslint-disable-line vue/no-v-html -->
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
		<NcButton id="reset-all-app-settings-btn"
			type="error"
			:disabled="!resettableForm"
			@click="resetAllAppValuesConfirmation">
			<template #icon>
				<RestoreIcon :size="20" />
			</template>
			{{ t('integration_openproject', 'Reset') }}
		</NcButton>
		<div v-if="isIntegrationCompleteWithOauth2 || isIntegrationCompleteWithOIDC" class="default-prefs">
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
import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import {
	NcLoadingIcon,
	NcCheckboxRadioSwitch,
	NcButton,
	NcNoteCard,
	NcSelect,
} from '@nextcloud/vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import AutoRenewIcon from 'vue-material-design-icons/Autorenew.vue'
import TextInput from './admin/TextInput.vue'
import FieldValue from './admin/FieldValue.vue'
import FormHeading from './admin/FormHeading.vue'
import CheckBox from '../components/settings/CheckBox.vue'
import SettingsTitle from '../components/settings/SettingsTitle.vue'
import ErrorNote from './settings/ErrorNote.vue'
import { F_MODES, FORM, USER_SETTINGS, AUTH_METHOD, SSO_PROVIDER_TYPE, SSO_PROVIDER_LABEL, ADMIN_SETTINGS_FORM, settingsFlowGenerator } from '../utils.js'
import TermsOfServiceUnsigned from './admin/TermsOfServiceUnsigned.vue'
import dompurify from 'dompurify'
import { messages, messagesFmt } from '../constants/messages.js'
import { appLinks } from '../constants/links.js'
import ErrorLabel from './ErrorLabel.vue'
import FormOpenProjectHost from './admin/FormOpenProjectHost.vue'
import FormAuthMethod from './admin/FormAuthMethod.vue'

export default {
	name: 'AdminSettings',
	components: {
		ErrorLabel,
		NcSelect,
		NcButton,
		FieldValue,
		FormHeading,
		TextInput,
		SettingsTitle,
		CheckBoldIcon,
		PencilIcon,
		NcLoadingIcon,
		AutoRenewIcon,
		RestoreIcon,
		CheckBox,
		NcCheckboxRadioSwitch,
		TermsOfServiceUnsigned,
		NcNoteCard,
		ErrorNote,
		FormOpenProjectHost,
		FormAuthMethod,
	},
	data() {
		return {
			form: JSON.parse(JSON.stringify(ADMIN_SETTINGS_FORM)),
			currentSetting: null,
			settingsStepper: settingsFlowGenerator(),
			formMode: {
				// server host form is never disabled.
				// it's either editable or view only
				authorizationMethod: F_MODES.DISABLE,
				authorizationSetting: F_MODES.DISABLE,
				SSOSettings: F_MODES.DISABLE,
				opOauth: F_MODES.DISABLE,
				ncOauth: F_MODES.DISABLE,
				opUserAppPassword: F_MODES.DISABLE,
				projectFolderSetUp: F_MODES.DISABLE,
			},
			isFormCompleted: {
				server: false, authorizationMethod: false, authorizationSetting: false, opOauth: false, ncOauth: false, opUserAppPassword: false, projectFolderSetUp: false,
			},
			buttonTextLabel: {
				keepCurrentChange: t('integration_openproject', 'Keep current setup'),
				completeWithoutProjectFolderSetup: t('integration_openproject', 'Complete without project folders'),
				completeWithProjectFolderSetup: t('integration_openproject', 'Setup OpenProject user, group and folder'),
				retrySetupWithProjectFolder: t('integration_openproject', 'Retry setup OpenProject user, group and folder'),
			},
			loadingProjectFolderSetup: false,
			loadingOPOauthForm: false,
			loadingAuthorizationMethodForm: false,
			loadingAuthorizationSettingForm: false,
			state: loadState('integration_openproject', 'admin-settings-config'),
			isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
			oPOAuthTokenRevokeStatus: null,
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
			authorizationSetting: {
				oidcProviderSet: null,
				currentOIDCProviderSelected: null,
				currentTargetedAudienceClientIdSelected: null,
				SSOProviderType: SSO_PROVIDER_TYPE.nextcloudHub,
				enableTokenExchange: false,
			},
			registeredOidcProviders: [],
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
		ncClientId() {
			return this.state.nc_oauth_client?.nextcloud_client_id
		},
		ncClientSecret() {
			return '*******'

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
			return this.isFormCompleted.authorizationMethod
		},
		isAuthorizationSettingFormComplete() {
			return this.isFormCompleted.authorizationSetting
		},
		isOPOAuthFormComplete() {
			return this.isFormCompleted.opOauth
		},
		isManagedGroupFolderSetUpComplete() {
			return this.isFormCompleted.projectFolderSetUp
		},
		isOPUserAppPasswordFormComplete() {
			return this.isFormCompleted.opUserAppPassword
		},
		isNcOAuthFormComplete() {
			return this.isFormCompleted.ncOauth
		},
		isAuthorizationSettingsInViewMode() {
			return this.formMode.authorizationSetting === F_MODES.VIEW
		},
		isOPOAuthFormInView() {
			return this.formMode.opOauth === F_MODES.VIEW
		},
		isNcOAuthFormInEdit() {
			return this.formMode.ncOauth === F_MODES.EDIT
		},
		isOPOAuthFormInDisableMode() {
			return this.formMode.opOauth === F_MODES.DISABLE
		},
		isAuthorizationSettingFormInDisabledMode() {
			return this.formMode.authorizationSetting === F_MODES.DISABLE
		},
		isOPUserAppPasswordFormInEdit() {
			return this.formMode.opUserAppPassword === F_MODES.EDIT
		},
		isProjectFolderSetupFormInEdit() {
			return this.formMode.projectFolderSetUp === F_MODES.EDIT
		},
		isProjectFolderSetupFormInDisableMode() {
			return this.formMode.projectFolderSetUp === F_MODES.DISABLE
		},
		isAuthorizationSettingInEditMode() {
			return this.formMode.authorizationSetting === F_MODES.EDIT
		},
		isSSOSettingsInEditMode() {
			return this.formMode.SSOSettings === F_MODES.EDIT
		},
		isNcOAuthFormInDisableMode() {
			return this.formMode.ncOauth === F_MODES.DISABLE
		},
		isProjectFolderSetUpInDisableMode() {
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
		showOAuthSettings() {
			return this.isOAuthMethod || !this.form.authenticationMethod.complete
		},
		adminFileStorageHref() {
			const path = '%s/admin/settings/storages'
			const host = this.form.serverHost.value
			return util.format(path, host)
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
		getConfigureOIDCHintText() {
			const linkText = t('integration_openproject', 'OpenID Connect settings')
			const settingsUrl = this.appLinks.user_oidc.settingsLink
			const htmlLink = `<a class="link" href="${settingsUrl}" target="_blank" title="${linkText}">${linkText}</a>`
			return this.messagesFmt.configureOIDCProviders(htmlLink)
		},
		getUserOidcMinimumVersion() {
			return this.state.user_oidc_minimum_version
		},
		isIntegrationCompleteWithOauth2() {
			return (this.isServerHostFormComplete
				&& this.isAuthorizationMethodFormComplete
				 && this.isOPOAuthFormComplete
				 && this.isNcOAuthFormComplete
				 && this.isManagedGroupFolderSetUpComplete
				 && !this.isOPUserAppPasswordFormInEdit
			)
		},
		isIntegrationCompleteWithOIDC() {
			return (this.isServerHostFormComplete
				&& this.isAuthorizationMethodFormComplete
				&& this.isAuthorizationSettingFormComplete
				&& this.isManagedGroupFolderSetUpComplete
				&& !this.isOPUserAppPasswordFormInEdit
			)
		},
		isSetupCompleteWithoutProjectFolders() {
			if (this.isProjectFolderSetupFormInEdit) {
				return false
			}
			if (this.isFormCompleted.projectFolderSetUp === false && !this.opUserAppPassword) {
				return false
			}
			return !this.opUserAppPassword
		},
		showEncryptionWarningForGroupFolders() {
			if (!this.isProjectFolderAlreadySetup || !this.state.app_password_set || this.isProjectFolderSetupFormInEdit) {
				return false
			}
			return this.state.encryption_info.server_side_encryption_enabled
				&& !this.state.encryption_info.encryption_enabled_for_groupfolders
		},
		disableSaveSSOSettings() {
			const { currentOIDCProviderSelected, SSOProviderType, enableTokenExchange } = this.authorizationSetting
			if (SSOProviderType === this.SSO_PROVIDER_TYPE.nextcloudHub) {
				const typeChanged = SSOProviderType !== this.state.authorization_settings.sso_provider_type
				const hasClientId = !!this.authorizationSetting.currentTargetedAudienceClientIdSelected || !!this.getCurrentSelectedTargetedClientId
				const clientIdChanged = this.authorizationSetting.currentTargetedAudienceClientIdSelected !== this.getCurrentSelectedTargetedClientId
				if (hasClientId) {
					return !typeChanged && !clientIdChanged
				}
				return !hasClientId
			}

			const formValueChanged = currentOIDCProviderSelected !== this.state.authorization_settings.oidc_provider
				|| enableTokenExchange !== this.state.authorization_settings.token_exchange

			if (!enableTokenExchange) {
				return currentOIDCProviderSelected === null || !formValueChanged
			}

			const clientIdChanged = this.authorizationSetting.currentTargetedAudienceClientIdSelected !== this.getCurrentSelectedTargetedClientId
			return this.authorizationSetting.currentTargetedAudienceClientIdSelected === null
				|| !this.authorizationSetting.currentTargetedAudienceClientIdSelected
				|| (!formValueChanged && !clientIdChanged)
		},
		getCurrentSelectedOIDCProvider() {
			return this.authorizationSetting.currentOIDCProviderSelected
		},
		getCurrentSelectedTargetedClientId() {
			return this.state.authorization_settings.targeted_audience_client_id
		},
		getSSOProviderType() {
			return this.authorizationSetting.SSOProviderType
		},
		getUserOidcAppName() {
			return this.state.apps.user_oidc.name
		},
		getOidcAppName() {
			return this.state.apps.oidc.name
		},
		getGroupfoldersAppName() {
			return this.state.apps.groupfolders.name
		},
		getAdminAuditAppName() {
			return this.state.admin_audit_app_name
		},
		hasEnabledSupportedUserOidcApp() {
			return this.state.apps.user_oidc.enabled && this.state.apps.user_oidc.supported
		},
		getMinSupportedUserOidcVersion() {
			return this.state.apps.user_oidc.minimum_version
		},
		hasEnabledSupportedOIDCApp() {
			return this.state.apps.oidc.enabled && this.state.apps.oidc.supported
		},
		getMinSupportedOidcVersion() {
			return this.state.apps.oidc.minimum_version
		},
		hasEnabledSupportedGroupfoldersApp() {
			return this.state.apps.groupfolders.enabled && this.state.apps.groupfolders.supported
		},
		getMinSupportedGroupfoldersVersion() {
			return this.state.apps.groupfolders.minimum_version
		},
		isExternalSSOProvider() {
			return this.authorizationSetting.SSOProviderType === SSO_PROVIDER_TYPE.external
		},
		hasOidcAppErrorWithNextcloudHub() {
			return !this.hasEnabledSupportedOIDCApp && this.authorizationSetting.SSOProviderType === SSO_PROVIDER_TYPE.nextcloudHub
		},
		showGroupfoldersAppError() {
			return this.isProjectFolderSwitchEnabled && !this.hasEnabledSupportedGroupfoldersApp && !this.isProjectFolderSetupFormInDisableMode
		},
		disableNCHubUnsupportedHint() {
			if (!this.hasEnabledSupportedOIDCApp) {
				if (this.formMode.SSOSettings === F_MODES.DISABLE || this.formMode.SSOSettings === F_MODES.NEW) {
					return true
				} else if (this.isExternalSSOProvider) {
					return true
				}
			}
			return false
		},
		showClientIDField() {
			if (this.authorizationSetting.SSOProviderType === SSO_PROVIDER_TYPE.nextcloudHub) {
				return true
			}
			return this.authorizationSetting.enableTokenExchange
		},
	},
	watch: {
		'authorizationSetting.SSOProviderType'() {
			if (this.isExternalSSOProvider && this.state.authorization_settings.sso_provider_type !== this.SSO_PROVIDER_TYPE.external) {
				this.authorizationSetting.currentOIDCProviderSelected = null
			}
		},
		'form.authenticationMethod.complete'() {
			if (this.form.authenticationMethod.complete && this.formMode.authorizationSetting === F_MODES.DISABLE) {
				this.formMode.authorizationSetting = F_MODES.EDIT
			}
		},
	},
	created() {
		this.currentSetting = this.settingsStepper.next().value

		this.init()
		if (!this.hasEnabledSupportedOIDCApp && (this.formMode.SSOSettings === F_MODES.DISABLE || this.formMode.SSOSettings === F_MODES.NEW)) {
			this.authorizationSetting.SSOProviderType = SSO_PROVIDER_TYPE.external
		}
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
				if (this.state.fresh_project_folder_setup === true && this.formMode.projectFolderSetUp === F_MODES.DISABLE) {
					this.currentProjectFolderState = true
					this.textLabelProjectFolderSetupButton = this.buttonTextLabel.completeWithProjectFolderSetup
				} else {
					this.textLabelProjectFolderSetupButton = this.buttonTextLabel.keepCurrentChange
				}
				// for oauth2 authorization
				if (this.state.openproject_instance_url
					&& this.state.openproject_client_id
					&& this.state.openproject_client_secret
					&& this.state.nc_oauth_client
				) {
					this.showDefaultManagedProjectFolders = true
				}
				// for oidc authorization
				if (this.state.authorization_method === AUTH_METHOD.OIDC
					&& this.state.openproject_instance_url
					&& this.state.authorization_settings.oidc_provider
					&& this.state.authorization_settings.targeted_audience_client_id
				) {
					this.showDefaultManagedProjectFolders = true
				}
				if (this.state.fresh_project_folder_setup === false) {
					this.showDefaultManagedProjectFolders = true
				}
				if (this.state.authorization_method) {
					this.formMode.authorizationMethod = F_MODES.VIEW
					this.isFormCompleted.authorizationMethod = true
				}
				if (this.state.openproject_instance_url && this.state.authorization_method) {
					if (this.state.authorization_method === AUTH_METHOD.OAUTH2) {
						if (!this.state.openproject_client_id || !this.state.openproject_client_secret) {
							this.formMode.authorizationSetting = F_MODES.EDIT
						}
					}
					if (this.state.authorization_method === AUTH_METHOD.OIDC) {
						if (!this.state.authorization_settings.oidc_provider || !this.state.authorization_settings.targeted_audience_client_id) {
							this.formMode.authorizationSetting = F_MODES.EDIT
							this.formMode.SSOSettings = F_MODES.NEW
						}
					}
				}
				if (this.state.authorization_method === AUTH_METHOD.OIDC && this.state.authorization_settings.sso_provider_type) {
					if (this.state.authorization_settings.sso_provider_type === SSO_PROVIDER_TYPE.nextcloudHub) {
						if (this.state.authorization_settings.targeted_audience_client_id) {
							this.formMode.authorizationSetting = F_MODES.VIEW
							this.formMode.SSOSettings = F_MODES.VIEW
							this.isFormCompleted.authorizationSetting = true
						}
					} else if (this.state.authorization_settings.oidc_provider) {
						if (this.state.authorization_settings.token_exchange) {
							if (this.state.authorization_settings.targeted_audience_client_id) {
								this.formMode.authorizationSetting = F_MODES.VIEW
								this.formMode.SSOSettings = F_MODES.VIEW
								this.isFormCompleted.authorizationSetting = true
							}
						} else {
							this.formMode.authorizationSetting = F_MODES.VIEW
							this.formMode.SSOSettings = F_MODES.VIEW
							this.isFormCompleted.authorizationSetting = true
						}
					}
					this.authorizationSetting.oidcProviderSet = this.authorizationSetting.currentOIDCProviderSelected = this.state.authorization_settings.oidc_provider
					this.authorizationSetting.currentTargetedAudienceClientIdSelected = this.state.authorization_settings.targeted_audience_client_id
					this.authorizationSetting.SSOProviderType = this.state.authorization_settings.sso_provider_type
					this.authorizationSetting.enableTokenExchange = this.state.authorization_settings.token_exchange
				}
				if (!!this.state.openproject_client_id && !!this.state.openproject_client_secret) {
					this.formMode.opOauth = F_MODES.VIEW
					this.isFormCompleted.opOauth = true
				}
				if (!this.state.authorization_method) {
					this.formMode.authorizationMethod = F_MODES.EDIT
				}
				if (this.state.authorization_method) {
					if (!this.state.openproject_client_id && !this.state.openproject_client_secret) {
						this.formMode.opOauth = F_MODES.EDIT
					}
				}

				if (this.state.nc_oauth_client) {
					this.formMode.ncOauth = F_MODES.VIEW
					this.isFormCompleted.ncOauth = true
				}
				if (!this.state.nc_oauth_client
					&& this.state.openproject_instance_url
					&& this.state.openproject_client_id
					&& this.state.openproject_client_secret
				    && this.textLabelProjectFolderSetupButton === 'Keep current setup') {
					this.showDefaultManagedProjectFolders = true
					this.formMode.projectFolderSetUp = F_MODES.VIEW
					this.isFormCompleted.projectFolderSetUp = true
				}
				if (this.formMode.ncOauth === F_MODES.VIEW || this.formMode.authorizationSetting === F_MODES.VIEW) {
					this.showDefaultManagedProjectFolders = true
				}
				if (this.showDefaultManagedProjectFolders) {
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

				if (this.state.oidc_providers) {
					this.registeredOidcProviders = this.state.oidc_providers
				}
			}
		},
		markFormComplete(formFn) {
			formFn(this.form)
			this.nextSettings()
		},
		nextSettings() {
			this.currentSetting = this.settingsStepper.next().value
		},
		closeRequestModal() {
			this.show = false
		},
		setAuthorizationSettingToViewMode() {
			this.formMode.authorizationSetting = F_MODES.VIEW
			this.formMode.SSOSettings = F_MODES.VIEW
			this.isFormCompleted.authorizationSetting = true
			this.authorizationSetting.SSOProviderType = this.state.authorization_settings.sso_provider_type
			this.authorizationSetting.currentOIDCProviderSelected = this.state.authorization_settings.oidc_provider
			this.authorizationSetting.enableTokenExchange = this.state.authorization_settings.token_exchange
			this.authorizationSetting.currentTargetedAudienceClientIdSelected = this.state.authorization_settings.targeted_audience_client_id
		},
		setAuthorizationSettingInEditMode() {
			this.formMode.authorizationSetting = F_MODES.EDIT
			this.formMode.SSOSettings = F_MODES.EDIT
			this.isFormCompleted.authorizationSetting = false
		},
		setProjectFolderSetUpToEditMode() {
			this.formMode.projectFolderSetUp = F_MODES.EDIT
			this.isFormCompleted.projectFolderSetUp = false
			this.isProjectFolderSwitchEnabled = this.currentProjectFolderState
		},
		setProjectFolderSetupToViewMode() {
			this.currentProjectFolderState = true
			this.textLabelProjectFolderSetupButton = this.buttonTextLabel.keepCurrentChange
			this.isFormCompleted.projectFolderSetUp = true
			this.formMode.projectFolderSetUp = F_MODES.VIEW
			this.isProjectFolderSetupCorrect = true
		},
		async setNCOAuthFormToViewMode() {
			this.formMode.ncOauth = F_MODES.VIEW
			this.isFormCompleted.ncOauth = true
			if (!this.isIntegrationCompleteWithOauth2 && this.formMode.projectFolderSetUp !== F_MODES.EDIT && this.formMode.opUserAppPassword !== F_MODES.EDIT) {
				this.formMode.projectFolderSetUp = F_MODES.EDIT
				this.showDefaultManagedProjectFolders = true
				this.isProjectFolderSwitchEnabled = true
				this.textLabelProjectFolderSetupButton = this.buttonTextLabel.completeWithProjectFolderSetup
			}
		},
		setOPUserAppPasswordToViewMode() {
			this.formMode.opUserAppPassword = F_MODES.VIEW
			this.isFormCompleted.opUserAppPassword = true
		},
		changeProjectFolderSetUpState() {
			if (this.opUserAppPassword === false) {
				if (this.currentProjectFolderState === true && this.isProjectFolderSwitchEnabled === true) {
					this.textLabelProjectFolderSetupButton = this.buttonTextLabel.completeWithProjectFolderSetup
				} else if (this.currentProjectFolderState === true && this.isProjectFolderSwitchEnabled === false) {
					this.textLabelProjectFolderSetupButton = this.buttonTextLabel.completeWithoutProjectFolderSetup
				} else if (this.currentProjectFolderState === false && this.isProjectFolderSwitchEnabled === false) {
					this.textLabelProjectFolderSetupButton = this.buttonTextLabel.keepCurrentChange
				} else if (this.currentProjectFolderState === false && this.isProjectFolderSwitchEnabled === true) {
					this.textLabelProjectFolderSetupButton = this.buttonTextLabel.completeWithProjectFolderSetup
				}
			} else if (this.currentProjectFolderState === true && this.isProjectFolderSwitchEnabled === true) {
				this.textLabelProjectFolderSetupButton = this.buttonTextLabel.keepCurrentChange
			} else if (this.currentProjectFolderState === true && this.isProjectFolderSwitchEnabled === false) {
				this.textLabelProjectFolderSetupButton = this.buttonTextLabel.completeWithoutProjectFolderSetup
			}
		},
		async setUpProjectGroupFolders() {
			this.isFormStep = FORM.GROUP_FOLDER
			this.loadingProjectFolderSetup = true
			this.isProjectFolderAlreadySetup = await this.checkIfProjectFolderIsAlreadyReadyForSetup()
			if (this.isProjectFolderAlreadySetup) {
				if (!this.opUserAppPassword) {
					const success = await this.saveOPOptions()
					if (success) {
						this.formMode.opUserAppPassword = F_MODES.EDIT
						this.setProjectFolderSetupToViewMode()
					}
				} else {
					this.setProjectFolderSetupToViewMode()
				}
			} else {
				// we will check for the error making the setup_project_folder === true
				const success = await this.saveOPOptions()
				if (success) {
					this.setProjectFolderSetupToViewMode()
					this.isProjectFolderAlreadySetup = true
					if ((this.formMode.opUserAppPassword === F_MODES.DISABLE && !this.opUserAppPassword) || this.formMode.opUserAppPassword === F_MODES.DISABLE) {
						this.formMode.opUserAppPassword = F_MODES.EDIT
					}
				}
			}
			this.loadingProjectFolderSetup = false
			if (this.formMode.projectFolderSetUp === F_MODES.VIEW) {
				this.projectFolderSetupError = null
			}
		},
		async saveOPOAuthClientValues() {
			this.isFormStep = FORM.OP_OAUTH
			if (await this.saveOPOptions()) {
				this.formMode.opOauth = F_MODES.VIEW
				this.isFormCompleted.opOauth = true

				// if we do not have Nextcloud OAuth client yet, a new client is created
				if (!this.state.nc_oauth_client) {
					this.createNCOAuthClient()
				}
			}
		},
		async saveOIDCAuthSetting() {
			this.isFormStep = FORM.AUTHORIZATION_SETTING
			this.loadingAuthorizationMethodForm = true

			if (this.authorizationSetting.SSOProviderType === this.SSO_PROVIDER_TYPE.nextcloudHub) {
				this.authorizationSetting.oidcProviderSet = this.SSO_PROVIDER_LABEL.nextcloudHub
				this.authorizationSetting.currentOIDCProviderSelected = this.SSO_PROVIDER_LABEL.nextcloudHub
			} else {
				this.authorizationSetting.oidcProviderSet = this.getCurrentSelectedOIDCProvider
			}

			const success = await this.saveOPOptions()
			if (success) {
				this.formMode.authorizationSetting = F_MODES.VIEW
				this.formMode.SSOSettings = F_MODES.VIEW
				this.isFormCompleted.authorizationSetting = true
				if (!this.isIntegrationCompleteWithOIDC && this.formMode.projectFolderSetUp !== F_MODES.EDIT && this.formMode.opUserAppPassword !== F_MODES.EDIT) {
					this.formMode.projectFolderSetUp = F_MODES.EDIT
					this.showDefaultManagedProjectFolders = true
					this.isProjectFolderSwitchEnabled = true
					this.textLabelProjectFolderSetupButton = this.buttonTextLabel.completeWithProjectFolderSetup
				}
				this.state.authorization_settings.sso_provider_type = this.authorizationSetting.SSOProviderType
				this.state.authorization_settings.oidc_provider = this.authorizationSetting.currentOIDCProviderSelected
				this.state.authorization_settings.token_exchange = this.authorizationSetting.enableTokenExchange
				this.state.authorization_settings.targeted_audience_client_id = this.authorizationSetting.currentTargetedAudienceClientIdSelected
			}
			this.loadingAuthorizationMethodForm = false
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
				true,
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
				t('integration_openproject', 'Reset OpenProject Integration'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('integration_openproject', 'Yes, reset'),
					confirmClasses: 'error',
					cancel: t('integration_openproject', 'Cancel'),
				},
				async (result) => {
					if (result) {
						await this.resetAllAppValues(this.getCurrentAuthMethod)
					}
				},
				true,
			)
		},
		async resetAllAppValues(authMethod) {
			// to avoid general console errors, we need to set the form to
			// editor mode so that we can update the form fields with null values
			// also, form completeness should be set to false

			// reset form states to default
			this.isFormCompleted.opOauth = false
			this.isFormCompleted.server = false
			this.formMode.opOauth = F_MODES.EDIT
			this.formMode.SSOSettings = F_MODES.NEW

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
				this.authorizationSetting.currentOIDCProviderSelected = null
			}
			await this.saveOPOptions()
			window.location.reload()
		},
		getPayloadForSavingOPOptions() {
			let values = {
				openproject_client_id: this.state.openproject_client_id,
				openproject_client_secret: this.state.openproject_client_secret,
				default_enable_navigation: this.state.default_enable_navigation,
				default_enable_unified_search: this.state.default_enable_unified_search,
			}
			if (this.state.openproject_instance_url === null) {
				values.openproject_instance_url = null
			}
			// by default, it will be an oauth2 reset
			if (this.state.authorization_method === null) {
				values = {
					...values,
					authorization_method: null,
					setup_project_folder: false,
					setup_app_password: false,
					oidc_provider: null,
					targeted_audience_client_id: null,
					sso_provider_type: null,
					token_exchange: null,

				}
			} else if (this.isFormStep === FORM.AUTHORIZATION_SETTING) {
				values = {
					oidc_provider: this.getCurrentSelectedOIDCProvider,
					targeted_audience_client_id: this.authorizationSetting.currentTargetedAudienceClientIdSelected,
					sso_provider_type: this.authorizationSetting.SSOProviderType,
					token_exchange: this.authorizationSetting.enableTokenExchange,
				}
			} else if (this.isFormStep === FORM.AUTHORIZATION_METHOD) {
				values = {
					...values,
					authorization_method: this.state.authorization_method,
					oidc_provider: this.isIntegrationCompleteWithOIDC ? this.getCurrentSelectedOIDCProvider : null,
					targeted_audience_client_id: this.isIntegrationCompleteWithOIDC ? this.authorizationSetting.currentTargetedAudienceClientIdSelected : null,
					sso_provider_type: this.authorizationSetting.SSOProviderType,
					token_exchange: this.authorizationSetting.enableTokenExchange,
				}
			} else if (this.isFormStep === FORM.GROUP_FOLDER) {
				if (!this.isProjectFolderSwitchEnabled) {
					values = {
						setup_project_folder: false,
						setup_app_password: false,
					}
				} else if (this.isProjectFolderSwitchEnabled === true) {
					values = {
						setup_project_folder: !this.isProjectFolderAlreadySetup,
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
					this.oPUserAppPassword = response?.data?.oPUserAppPassword
				}
				this.oPOAuthTokenRevokeStatus = response?.data?.oPOAuthTokenRevokeStatus
				showSuccess(t('integration_openproject', 'OpenProject admin options saved'))
				success = true
			} catch (error) {
				console.error()
				this.isAdminConfigOk = null
				this.oPOAuthTokenRevokeStatus = null
				if (error.response.data.error) {
					this.projectFolderSetupError = error.response.data.error
				}
				showError(
					t('integration_openproject', 'Failed to save OpenProject admin options'),
				)
			}
			this.notifyAboutOPOAuthTokenRevoke()
			return success
		},
		async checkIfProjectFolderIsAlreadyReadyForSetup() {
			let success = false
			try {
				const url = generateUrl('/apps/integration_openproject/project-folder-status')
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
					t('integration_openproject', 'Failed to perform revoke request due to connection error with the OpenProject server'),
				)
				break
			case 'other_error':
				showError(
					t('integration_openproject', 'Failed to revoke some users\' OpenProject OAuth access tokens'),
				)
				break
			case 'success':
				showSuccess(
					t('integration_openproject', 'Successfully revoked users\' OpenProject OAuth access tokens'),
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
				true,
			)
		},
		async completeIntegrationWithoutProjectFolderSetUp() {
			this.isFormStep = FORM.GROUP_FOLDER
			this.textLabelProjectFolderSetupButton = this.buttonTextLabel.keepCurrentChange
			const success = await this.saveOPOptions()
			if (success) {
				// also make password form disable and complete as false
				if (this.opUserAppPassword) {
					this.isFormCompleted.opUserAppPassword = false
					this.formMode.opUserAppPassword = F_MODES.DISABLE
				}
				this.state.app_password_set = false
				this.currentProjectFolderState = this.isProjectFolderSwitchEnabled
				this.isFormCompleted.projectFolderSetUp = true
				this.formMode.projectFolderSetUp = F_MODES.VIEW
			}
			// we want to show the error only when project folder form is in edit mode
			if (this.formMode.projectFolderSetUp === F_MODES.VIEW) {
				this.projectFolderSetupError = null
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
				true,
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
					+ ': ' + error.response.request.responseText,
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
					+ ': ' + error.response.request.responseText,
				)
			})
		},
		onSelectOIDCProvider(selectedOption) {
			this.authorizationSetting.currentOIDCProviderSelected = selectedOption
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
	.authorization-method {
		&--description {
			font-size: 14px;
			.title {
				font-weight: 700;
			}
			.description {
				margin-top: 0.1rem;
			}
		}
		&--options {
			margin-top: 1rem;
			.radio-check {
				font-weight: 500;
			}
		}
	}
	.authorization-settings {
		&--content {
			max-width: 550px;
			&--label {
				font-weight: 700;
				font-size: .875rem;
				color: var(--color-primary-text)
			}
			&--section {
				margin-top: 0.7rem;
			}
		}
		.description {
			margin-top: 0.1rem;
		}
	}
	.error-container {
		margin-left: 2.4rem;
		font-size: 14px;
	}
}

[data-theme-light] {
	#openproject_prefs {
		.authorization-settings {
			&--content {
				&--label {
					color: var(--color-main-text)
				}
			}
		}
	}
}
</style>
