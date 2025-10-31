<!--
  - SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div :class="formId">
		<FormHeading
			:index="formOrder"
			:title="t('integration_openproject', 'Authentication settings')"
			:is-complete="isFormComplete"
			:is-disabled="!showSettings"
			:is-dark-theme="isDarkTheme"
			:has-error="!hasEnabledSupportedUserOidcApp || showOidcAppError" />
		<div v-if="showSettings" class="sso-settings authorization-settings">
			<ErrorNote
				v-if="!hasEnabledSupportedUserOidcApp"
				:error-title="messagesFmt.appNotEnabledOrUnsupported(getUserOidcAppName, getMinSupportedUserOidcVersion)"
				:error-link="appLinks.user_oidc.installLink"
				:error-link-label="messages.installLatestVersionNow" />
			<ErrorNote
				v-else-if="showOidcAppError"
				:error-title="messagesFmt.appNotEnabledOrUnsupported(getOidcAppName, getMinSupportedOidcVersion)"
				:error-link="appLinks.oidc.installLink"
				:error-link-label="messages.installLatestVersionNow" />
			<div class="authorization-settings--content">
				<FieldValue v-if="isViewMode"
					is-required
					class="pb-1"
					:title="t('integration_openproject', 'OIDC Provider Type')"
					:value="savedForm.sso_provider_type" />
				<div v-else class="authorization-settings--content--section sso-provider-type">
					<p class="authorization-settings--content--label">
						{{ t('integration_openproject', 'OIDC Provider Type') }} *
					</p>
					<NcCheckboxRadioSwitch
						:checked.sync="currentForm.sso_provider_type"
						:disabled="!hasEnabledSupportedUserOidcApp || !hasEnabledSupportedOIDCApp"
						:value="ssoProviderType.nextcloudHub"
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
						:checked.sync="currentForm.sso_provider_type"
						:disabled="!hasEnabledSupportedUserOidcApp"
						:value="ssoProviderType.external"
						type="radio">
						{{ messages.externalOIDCProvider }}
					</NcCheckboxRadioSwitch>
				</div>
				<FieldValue v-if="isViewMode && isExternalSSO"
					is-required
					class="pb-1"
					:title="t('integration_openproject', 'OIDC Provider')"
					:value="savedForm.oidc_provider" />
				<div v-else-if="isExternalSSOSelected" class="authorization-settings--content--section sso-provider">
					<p class="authorization-settings--content--label">
						{{ t('integration_openproject', 'Select a provider *') }}
					</p>
					<NcSelect
						input-id="provider-search-input"
						:disabled="!hasEnabledSupportedUserOidcApp"
						:placeholder="t('integration_openproject', 'Select an OIDC provider')"
						:options="ssoProviders"
						:value="currentForm.oidc_provider"
						:filterable="true"
						:close-on-select="true"
						:clear-search-on-blur="() => false"
						:append-to-body="false"
						:label-outside="true"
						:input-label="t('integration_openproject', 'OIDC provider')"
						@option:selected="onSelectSSOProvider" />
					<p class="description" v-html="getConfigureOIDCHintText" /> <!-- eslint-disable-line vue/no-v-html -->
				</div>
				<FieldValue v-if="isViewMode && isExternalSSO"
					class="pb-1"
					:title="messages.enableTokenExchange"
					:value="savedForm.token_exchange ? 'true' : 'false'" />
				<div v-else-if="isExternalSSOSelected" class="authorization-settings--content--section sso-token-exchange">
					<p class="authorization-settings--content--label">
						{{ messages.tokenExchangeFormLabel }}
					</p>
					<p class="description">
						{{ messages.tokenExchangeHintText }}
					</p>
					<NcCheckboxRadioSwitch
						type="switch"
						:checked.sync="currentForm.token_exchange">
						<b>{{ messages.token_exchange }}</b>
					</NcCheckboxRadioSwitch>
				</div>
				<div v-if="showClientIDField">
					<FieldValue v-if="isViewMode"
						is-required
						class="pb-1"
						:title="messages.opClientId"
						:value="savedForm.targeted_audience_client_id" />
					<div v-else class="authorization-settings--content--section sso-client-id">
						<TextInput
							id="authorization-method-target-client-id"
							v-model="currentForm.targeted_audience_client_id"
							class="py-1"
							is-required
							:disabled="!hasEnabledSupportedUserOidcApp || showOidcAppError"
							:place-holder="messages.opClientId"
							:label="messages.opClientId"
							:hint-text="messages.opClientIdHintText" />
					</div>
				</div>
			</div>
			<div class="form-actions">
				<NcButton v-if="isViewMode"
					:disabled="!hasEnabledSupportedUserOidcApp"
					data-test-id="edit-sso-settings"
					@click="setFormToEditMode">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
					{{ t('integration_openproject', 'Edit authentication settings') }}
				</NcButton>
				<NcButton v-if="isEditMode"
					class="mr-2"
					data-test-id="cancel-sso-settings-edit"
					@click="cancelEdit">
					{{ t('integration_openproject', 'Cancel') }}
				</NcButton>
				<NcButton v-if="showSaveButton"
					data-test-id="save-sso-settings"
					type="primary"
					:disabled="disableSaveSSOSettings"
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
import { NcLoadingIcon, NcButton, NcCheckboxRadioSwitch, NcSelect } from '@nextcloud/vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import FieldValue from './FieldValue.vue'
import FormHeading from './FormHeading.vue'
import TextInput from './TextInput.vue'
import ErrorLabel from '../ErrorLabel.vue'
import ErrorNote from '../settings/ErrorNote.vue'
import { F_MODES, ADMIN_SETTINGS_FORM, SSO_PROVIDER_TYPE, SSO_PROVIDER_LABEL } from '../../utils.js'
import { saveAdminConfig } from '../../api/settings.js'
import { messages, messagesFmt } from '../../constants/messages.js'
import { appLinks } from '../../constants/links.js'

const initialForm = {
	sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
	oidc_provider: SSO_PROVIDER_LABEL.nextcloudHub,
	targeted_audience_client_id: null,
	token_exchange: null,
}

export default {
	name: 'FormSSOSettings',
	components: {
		NcLoadingIcon,
		NcButton,
		NcSelect,
		NcCheckboxRadioSwitch,
		CheckBoldIcon,
		PencilIcon,
		ErrorLabel,
		ErrorNote,
		FieldValue,
		FormHeading,
		TextInput,
	},
	props: {
		apps: {
			type: Object,
			required: true,
		},
		formState: {
			type: Object,
			required: true,
		},
		ssoSettings: {
			type: Object,
			required: true,
		},
		ssoProviders: {
			type: Array,
			required: true,
		},
		isDarkTheme: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			formMode: F_MODES.NEW,
			formId: ADMIN_SETTINGS_FORM.ssoSettings.id,
			formOrder: ADMIN_SETTINGS_FORM.ssoSettings.order.toString(),
			ssoProviderType: SSO_PROVIDER_TYPE,
			messages,
			messagesFmt,
			loading: false,
			appLinks,
			// state that holds the current changed form values
			currentForm: JSON.parse(JSON.stringify(initialForm)),
			// state that holds the saved form values (useful for resetting)
			savedForm: JSON.parse(JSON.stringify(this.ssoSettings)),
		}
	},
	computed: {
		showSettings() {
			return this.formState.authenticationMethod.complete
		},
		isFormComplete() {
			if (!this.savedForm.sso_provider_type) {
				return false
			}

			if (this.savedForm.sso_provider_type === this.ssoProviderType.nextcloudHub) {
				return !!this.savedForm.targeted_audience_client_id
			} else if (this.savedForm.sso_provider_type === this.ssoProviderType.external) {
				if (this.savedForm.token_exchange) {
					return !!this.savedForm.oidc_provider && !!this.savedForm.targeted_audience_client_id
				}
				return !!this.savedForm.oidc_provider
			}
			return false
		},
		isViewMode() {
			return this.formMode === F_MODES.VIEW
		},
		isEditMode() {
			return this.formMode === F_MODES.EDIT
		},
		showSaveButton() {
			return this.isEditMode || this.formMode === F_MODES.NEW
		},
		isExternalSSO() {
			return this.savedForm.sso_provider_type === this.ssoProviderType.external
		},
		isExternalSSOSelected() {
			return this.currentForm.sso_provider_type === this.ssoProviderType.external
		},
		hasEnabledSupportedUserOidcApp() {
			return this.apps.user_oidc.enabled && this.apps.user_oidc.supported
		},
		getUserOidcAppName() {
			return this.apps.user_oidc.name
		},
		hasEnabledSupportedOIDCApp() {
			return this.apps.oidc.enabled && this.apps.oidc.supported
		},
		getOidcAppName() {
			return this.apps.oidc.name
		},
		getMinSupportedOidcVersion() {
			return this.apps.oidc.minimum_version
		},
		showOidcAppError() {
			return !this.hasEnabledSupportedOIDCApp && this.savedForm.sso_provider_type === this.ssoProviderType.nextcloudHub
		},
		getMinSupportedUserOidcVersion() {
			return this.apps.user_oidc.minimum_version
		},
		showClientIDField() {
			let state = this.savedForm
			// check using current form in other modes
			if (!this.isViewMode) {
				state = this.currentForm
			}
			if (state.sso_provider_type === this.ssoProviderType.nextcloudHub) {
				return true
			}
			return state.token_exchange
		},
		disableNCHubUnsupportedHint() {
			if (!this.hasEnabledSupportedOIDCApp) {
				if (this.formMode === F_MODES.NEW) {
					return true
				} else if (this.isExternalSSO) {
					return true
				}
			}
			return false
		},
		disableSaveSSOSettings() {
			if (this.currentForm.sso_provider_type === this.ssoProviderType.nextcloudHub) {
				const typeChanged = this.currentForm.sso_provider_type !== this.savedForm.sso_provider_type
				const hasClientId = !!this.currentForm.targeted_audience_client_id
				const clientIdChanged = this.currentForm.targeted_audience_client_id !== this.savedForm.targeted_audience_client_id
				if (hasClientId) {
					return !typeChanged && !clientIdChanged
				}
				return !hasClientId
			}

			const formValueChanged = this.currentForm.oidc_provider !== this.savedForm.oidc_provider
				|| this.currentForm.token_exchange !== this.savedForm.token_exchange

			if (!this.currentForm.token_exchange) {
				return this.currentForm.oidc_provider === null || !formValueChanged
			}

			const clientIdChanged = this.currentForm.targeted_audience_client_id !== this.savedForm.targeted_audience_client_id
			return !this.currentForm.targeted_audience_client_id || (!formValueChanged && !clientIdChanged)
		},
		getConfigureOIDCHintText() {
			const linkText = t('integration_openproject', 'OpenID Connect settings')
			const settingsUrl = this.appLinks.user_oidc.settingsLink
			const htmlLink = `<a class="link" href="${settingsUrl}" target="_blank" title="${linkText}">${linkText}</a>`
			return this.messagesFmt.configureOIDCProviders(htmlLink)
		},
	},
	watch: {
		'currentForm.sso_provider_type'() {
			if (this.currentForm.sso_provider_type !== this.savedForm.sso_provider_type) {
				if (this.currentForm.sso_provider_type === this.ssoProviderType.external) {
					this.currentForm.oidc_provider = null
				}
			}
		},
	},
	created() {
		// set the default type if not set
		if (!this.savedForm.sso_provider_type) {
			if (!this.hasEnabledSupportedOIDCApp && this.formMode === F_MODES.NEW) {
				this.savedForm.sso_provider_type = SSO_PROVIDER_TYPE.external
				this.savedForm.oidc_provider = null
			} else {
				this.savedForm.sso_provider_type = SSO_PROVIDER_TYPE.nextcloudHub
				this.savedForm.oidc_provider = SSO_PROVIDER_LABEL.nextcloudHub
			}
		}
		if (this.isFormComplete) {
			this.setFromToViewMode()
			this.$emit('formcomplete', this.markFormComplete)
		}
		this.currentForm = JSON.parse(JSON.stringify(this.savedForm))
	},
	methods: {
		markFormComplete(formState) {
			formState.ssoSettings.complete = true
			return formState
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
			this.currentForm = JSON.parse(JSON.stringify(this.savedForm))
			this.setFromToViewMode()
		},
		onSelectSSOProvider(selectedOption) {
			this.currentForm.oidc_provider = selectedOption
		},
		async saveSettings() {
			this.loading = true
			// reset fields based on provider type
			if (this.currentForm.sso_provider_type === this.ssoProviderType.nextcloudHub) {
				this.currentForm.oidc_provider = SSO_PROVIDER_LABEL.nextcloudHub
				this.currentForm.token_exchange = null
			} else if (this.currentForm.sso_provider_type === this.ssoProviderType.external) {
				if (!this.currentForm.token_exchange) {
					this.currentForm.targeted_audience_client_id = null
				}
			}
			try {
				await saveAdminConfig({
					sso_provider_type: this.currentForm.sso_provider_type,
					oidc_provider: this.currentForm.oidc_provider,
					targeted_audience_client_id: this.currentForm.targeted_audience_client_id,
					token_exchange: this.currentForm.token_exchange,
				})
				this.savedForm = JSON.parse(JSON.stringify(this.currentForm))
				this.setFromToViewMode()

				showSuccess(t('integration_openproject', 'OpenProject admin options saved'))
				this.$emit('formcomplete', this.markFormComplete)
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

<!-- Test cases
- switch to external from nextcloud hub option (DO NOT SET), form validation, enabled save, cleared unused fields, provider changed
- switch to nextcloud hub from external option (DO NOT SET), form validation, enabled save, cleared unused fields, provider empty
- check client-id field:
	- first setup:
		- NC hub
		- change to external with/without token exchange
	- edit setup:
		- NC hub
		- change to external with/without token exchange
-->
