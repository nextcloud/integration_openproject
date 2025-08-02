<!--
  - SPDX-FileCopyrightText: 2021-2025 Jankari Tech Pvt. Ltd.
  - SPDX-FileCopyrightText: 2021-2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div :class="formId">
		<FormHeading
			:index="formOrder"
			:title="t('integration_openproject', 'Authentication method')"
			:is-complete="isFormComplete"
			:is-disabled="!showSettings"
			:is-dark-theme="isDarkTheme" />
		<div v-if="showSettings" class="auth-method">
			<div v-if="isEditMode">
				<div class="auth-method--hint">
					<p class="title">
						{{ t('integration_openproject', 'Need help setting this up?') }}
					</p>
					<p class="description" v-html="getHelpText" /> <!-- eslint-disable-line vue/no-v-html -->
				</div>
				<div class="auth-method--options">
					<NcCheckboxRadioSwitch
						id="oauth-auth-method"
						class="radio-check"
						:checked.sync="selectedAuthMethod"
						:value="authMethodType.OAUTH2"
						type="radio">
						{{ authMethodLabel.OAUTH2 }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						id="sso-auth-method"
						class="radio-check"
						:checked.sync="selectedAuthMethod"
						:value="authMethodType.OIDC"
						:disabled="!hasEnabledSupportedUserOidcApp"
						type="radio">
						{{ authMethodLabel.OIDC }}
					</NcCheckboxRadioSwitch>
					<div class="info-container">
						<p>
							{{ messages.opRequiredVersionAndPlanHint }}
						</p>
						<ErrorLabel
							v-if="!hasEnabledSupportedUserOidcApp"
							:disabled="disableErrorLabel"
							:error="`${messagesFmt.appNotEnabledOrSupported('user_oidc')}. ${messagesFmt.minimumVersionRequired(getMinSupportedUserOidcVersion)}`" />
					</div>
				</div>
			</div>
			<p v-else class="auth-method--label">
				{{ getSelectedAuthMethodLabel }}
			</p>
			<div class="form-actions">
				<NcButton v-if="isViewMode"
					data-test-id="edit-auth-method"
					@click="setFormToEditMode">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
					{{ t('integration_openproject', 'Edit authentication method') }}
				</NcButton>
				<NcButton v-if="isFormComplete && isEditMode"
					class="mr-2"
					data-test-id="cancel-auth-method-edit"
					@click="cancelEdit">
					{{ t('integration_openproject', 'Cancel') }}
				</NcButton>
				<NcButton v-if="isEditMode"
					data-test-id="save-auth-method"
					type="primary"
					:disabled="disableSave"
					@click="saveSettings">
					<template #icon>
						<NcLoadingIcon v-if="loading" class="loading-spinner" :size="20" />
						<CheckBoldIcon v-else fill-color="#FFFFFF" :size="20" />
					</template>
					{{ t('integration_openproject', 'Save') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>
<script>
import { NcLoadingIcon, NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import FormHeading from './FormHeading.vue'
import ErrorLabel from '../ErrorLabel.vue'
import { F_MODES, AUTH_METHOD, AUTH_METHOD_LABEL, ADMIN_SETTINGS_FORM } from '../../utils.js'
import { messages, messagesFmt } from '../../constants/messages.js'
import { saveAdminConfig } from '../../api/settings.js'

export default {
	name: 'FormAuthMethod',
	components: {
		NcLoadingIcon,
		NcButton,
		NcCheckboxRadioSwitch,
		CheckBoldIcon,
		PencilIcon,
		FormHeading,
		ErrorLabel,
	},
	props: {
		apps: {
			type: Object,
			required: true,
		},
		currentSetting: {
			type: String,
			required: true,
		},
		isDarkTheme: {
			type: Boolean,
			default: false,
		},
		authMethod: {
			type: String,
			default: null,
		},
	},
	data() {
		return {
			formMode: F_MODES.EDIT,
			formId: ADMIN_SETTINGS_FORM.authenticationMethod.id,
			formOrder: ADMIN_SETTINGS_FORM.authenticationMethod.order.toString(),
			authMethodType: AUTH_METHOD,
			authMethodLabel: AUTH_METHOD_LABEL,
			messages,
			messagesFmt,
			loading: false,
			// state that holds the current changed auth method
			selectedAuthMethod: AUTH_METHOD.OAUTH2,
			// state that holds the saved (to server) auth method
			savedAuthMethod: null,
		}
	},
	computed: {
		showSettings() {
			return this.currentSetting === this.formId || !!this.isFormComplete
		},
		isFormComplete() {
			return !!this.savedAuthMethod
		},
		isViewMode() {
			return this.formMode === F_MODES.VIEW
		},
		isEditMode() {
			return this.formMode === F_MODES.EDIT
		},
		hasEnabledSupportedUserOidcApp() {
			return this.apps.user_oidc.enabled && this.apps.user_oidc.supported
		},
		getMinSupportedUserOidcVersion() {
			return this.apps.user_oidc.minimum_version
		},
		getSelectedAuthMethodLabel() {
			return this.authMethodLabel[this.selectedAuthMethod.toUpperCase()]
		},
		getHelpText() {
			const linkText = t('integration_openproject', 'authentication methods you can use with OpenProject')
			const htmlLink = `<a class="link" href="https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/#3-configure-authentication-method" target="_blank" title="${linkText}">${linkText}</a>`
			return t('integration_openproject', 'Please read our guide on {htmlLink}.', { htmlLink }, null, { escape: false, sanitize: false })
		},
		disableSave() {
			if (this.isFormComplete) {
				return this.authMethod === this.selectedAuthMethod
				|| this.savedAuthMethod === this.selectedAuthMethod
			}
			return false
		},
		disableErrorLabel() {
			return this.authMethod !== this.authMethodType.OIDC
		},
	},
	created() {
		if (this.authMethod) {
			this.setFromToViewMode()
			this.selectedAuthMethod = this.authMethod
			this.savedAuthMethod = this.authMethod
			this.$emit('formcomplete', this.markFormComplete)
		}
	},
	methods: {
		markFormComplete(form) {
			form.authenticationMethod.value = this.selectedAuthMethod
			form.authenticationMethod.complete = true
			return form
		},
		setFormMode(mode) {
			this.formMode = mode
		},
		setFromToViewMode() {
			this.setFormMode(F_MODES.VIEW)
		},
		setFormToEditMode() {
			this.setFormMode(F_MODES.EDIT)
		},
		cancelEdit() {
			this.setFromToViewMode()
			if (this.authMethod) {
				this.selectedAuthMethod = this.authMethod
			} else {
				this.selectedAuthMethod = this.savedAuthMethod
			}
		},
		async saveSettings() {
			// open the confirmation dialog when only swithing back and forth between two authorization method
			if (this.isEditMode && this.isFormComplete) {
				await OC.dialogs.confirmDestructive(
					t(
						'integration_openproject',
						'If you proceed this method, you will have an '
							+ this.selectedAuthMethod.toUpperCase()
							+ ' based authentication configuration which will delete all the configuration setting for current '
							+ this.savedAuthMethod.toUpperCase()
							+ ' based authentication. You can switch back to it anytime.',
					),
					t('integration_openproject', 'Switch Authentication Method'),
					{
						type: OC.dialogs.YES_NO_BUTTONS,
						confirm: t('integration_openproject', 'Yes, switch'),
						confirmClasses: 'error',
						cancel: t('integration_openproject', 'Cancel'),
					},
					async (result) => {
						if (result) {
							await this.confirmSaveSettings()
							window.location.reload()
						}
					},
					true,
				)
				return
			}
			await this.confirmSaveSettings()
		},
		async confirmSaveSettings() {
			this.loading = true
			try {
				await saveAdminConfig({
					authorization_method: this.selectedAuthMethod,
					// reset other settings
					openproject_client_id: null,
					openproject_client_secret: null,
					sso_provider_type: null,
					oidc_provider: null,
					targeted_audience_client_id: null,
					token_exchange: null,
				})

				this.setFromToViewMode()
				showSuccess(t('integration_openproject', 'OpenProject admin options saved'))
				this.$emit('formcomplete', this.markFormComplete)
				this.savedAuthMethod = this.selectedAuthMethod
			} catch (error) {
				showError(
					t('integration_openproject', 'Failed to save OpenProject admin options'),
				)
				console.error(error)
			}
			this.loading = false
		},
	},
}
</script>

<style scoped lang="scss">
.auth-method {
	&--hint {
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
		.info-container {
			margin-left: 2.4rem;
			font-size: 14px;
		}
	}
	.form-actions {
		display: flex;
		align-items: center;
		padding: 15px 0;
	}
}

.mr-2 {
	margin-right: .5rem;
}
</style>
