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
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { NcButton, NcNoteCard } from '@nextcloud/vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import dompurify from 'dompurify'
import CheckBox from '../components/settings/CheckBox.vue'
import SettingsTitle from '../components/settings/SettingsTitle.vue'
import { USER_SETTINGS, AUTH_METHOD, SSO_PROVIDER_TYPE, SSO_PROVIDER_LABEL, ADMIN_SETTINGS_FORM } from '../utils.js'
import TermsOfServiceUnsigned from './admin/TermsOfServiceUnsigned.vue'
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
		SettingsTitle,
		RestoreIcon,
		CheckBox,
		TermsOfServiceUnsigned,
		NcNoteCard,
		FormOpenProjectHost,
		FormAuthMethod,
		FormSSOSettings,
		FormOAuthSettings,
		FormProjectFolder,
	},
	data() {
		return {
			form: structuredClone(ADMIN_SETTINGS_FORM),
			state: loadState('integration_openproject', 'admin-settings-config'),
			isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
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
		isProjectFolderFormComplete() {
			return this.form.projectFolder.complete
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
		getAdminAuditConfigurationHint() {
			const linkTextForAdminAudit = t('integration_openproject', this.getAdminAuditAppName)
			const adminAuditAppUrlForDownload = generateUrl('settings/apps/featured/admin_audit')
			const linkTextForDocumentation = t('integration_openproject', 'documentation')
			const htmlLinkForAdminAudit = `<a class="link" href="${adminAuditAppUrlForDownload}" target="_blank" title="${linkTextForAdminAudit}">${linkTextForAdminAudit}</a>`
			const htmlLinkForDocs = `<a class="link" href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/logging_configuration.html#admin-audit-log-optional" target="_blank" title="${linkTextForDocumentation}">${linkTextForDocumentation}</a>`
			const hintTextForAdminAudit = t('integration_openproject', 'To activate audit logs for the OpenProject integration, please enable the "{htmlLinkForAdminAudit}" app and follow the configuration steps outlined in the {htmlLinkForDocs}.', { htmlLinkForAdminAudit, htmlLinkForDocs }, null, { escape: false, sanitize: false })
			return dompurify.sanitize(hintTextForAdminAudit, { ADD_ATTR: ['target'] })
		},
		isSetupComplete() {
			return (this.isServerHostFormComplete
				&& this.isAuthorizationMethodFormComplete
				 && this.isAuthorizationSettingFormComplete
				 && this.isProjectFolderFormComplete
			)
		},
		getAdminAuditAppName() {
			return this.state.admin_audit_app_name
		},
		projectFolderFormOrder() {
			let formOrder = this.form.projectFolder.order
			if (this.getCurrentAuthMethod) {
				formOrder = this.form.projectFolder[this.getCurrentAuthMethod].order
			}
			return formOrder
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
				showSuccess(t('integration_openproject', 'OpenProject admin options saved'))
			} catch (error) {
				console.error(error.message)
				this.isAdminConfigOk = null
				if (error.response?.data?.error) {
					console.error(error.response?.data?.error)
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
