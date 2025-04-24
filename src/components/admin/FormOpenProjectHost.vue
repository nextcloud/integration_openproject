<!--
  - SPDX-FileCopyrightText: 2021-2025 Jankari Tech Pvt. Ltd.
  - SPDX-FileCopyrightText: 2021-2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div :id="formId">
		<FormHeading
			index="1"
			:title="t('integration_openproject', 'OpenProject server')"
			:is-complete="isFormComplete"
			:is-dark-theme="isDarkTheme" />
		<FieldValue v-if="isViewMode"
			is-required
			class="pb-1"
			:title="t('integration_openproject', 'OpenProject host')"
			:value="serverUrl" />
		<TextInput v-else
			ref="urlInput"
			v-model="serverUrl"
			data-test-id="openproject-server-host"
			is-required
			class="pb-1"
			:label="t('integration_openproject', 'OpenProject host')"
			place-holder="https://www.my-openproject.com"
			:hint-text="t('integration_openproject', 'Please introduce your OpenProject hostname')"
			:error-message="errorMessage"
			:error-message-details="errorDetails"
			@input="onFormChanged" />
		<div class="form-actions">
			<NcButton v-if="isViewMode"
				data-test-id="edit-server-host"
				@click="setFormToEditMode">
				<template #icon>
					<PencilIcon :size="20" />
				</template>
				{{ t('integration_openproject', 'Edit server information') }}
			</NcButton>
			<NcButton v-if="isFormComplete && isEditMode"
				class="mr-2"
				data-test-id="cancel-server-host-edit"
				@click="cancelEdit">
				{{ t('integration_openproject', 'Cancel') }}
			</NcButton>
			<NcButton v-if="isEditMode"
				type="primary"
				data-test-id="save-server-host"
				:disabled="disableSave"
				@click="saveUrl">
				<template #icon>
					<NcLoadingIcon v-if="loading" class="loading-spinner" :size="20" />
					<CheckBoldIcon v-else fill-color="#FFFFFF" :size="20" />
				</template>
				{{ t('integration_openproject', 'Save') }}
			</NcButton>
		</div>
	</div>
</template>
<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import FormHeading from './FormHeading.vue'
import FieldValue from './FieldValue.vue'
import TextInput from './TextInput.vue'
import { F_MODES } from '../../utils.js'
import { validateOPInstance, saveAdminConfig } from '../../api/settings.js'

export default {
	name: 'FormOpenProjectHost',
	components: {
		FormHeading,
		FieldValue,
		TextInput,
		NcButton,
		NcLoadingIcon,
		CheckBoldIcon,
		PencilIcon,
	},
	props: {
		formId: {
			type: String,
			default: 'server-host',
		},
		isDarkTheme: {
			type: Boolean,
			default: false,
		},
		openprojectUrl: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			formMode: F_MODES.EDIT,
			serverUrl: '',
			loading: false,
			errorMessage: '',
			errorDetails: '',
			formDirty: false,
			previousUrl: '',
		}
	},
	computed: {
		isFormComplete() {
			return !!this.previousUrl
		},
		isViewMode() {
			return this.formMode === F_MODES.VIEW
		},
		isEditMode() {
			return this.formMode === F_MODES.EDIT
		},
		disableSave() {
			return !this.formDirty
		},
	},
	created() {
		if (this.openprojectUrl) {
			this.setFromToViewMode()
			this.serverUrl = this.openprojectUrl
			this.previousUrl = this.openprojectUrl
			this.$emit('formcomplete', this.formId)
		}
	},
	methods: {
		onFormChanged(value) {
			if (this.isFormComplete) {
				if (this.serverUrl === this.previousUrl || !this.serverUrl) {
					this.formDirty = false
					return
				}
			} else {
				if (!value) {
					this.formDirty = false
					return
				}
			}

			if (!this.formDirty) {
				this.formDirty = true
			}
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
			this.setFormMode(F_MODES.VIEW)
			if (this.openprojectUrl) {
				this.serverUrl = this.openprojectUrl
			} else {
				this.serverUrl = this.previousUrl
			}
			this.formDirty = false
			this.resetErrors()
		},
		resetErrors() {
			this.errorMessage = ''
			this.errorDetails = ''
		},
		async saveUrl() {
			this.loading = true
			if (await this.validateUrl()) {
				try {
					await saveAdminConfig({ openproject_instance_url: this.serverUrl })

					this.setFromToViewMode()
					showSuccess(t('integration_openproject', 'OpenProject admin options saved'))

					if (!this.isFormComplete) {
						this.$emit('formcomplete', this.formId)
					}

					this.formDirty = false
					this.previousUrl = this.serverUrl
				} catch (error) {
					showError(
						t('integration_openproject', 'Failed to save OpenProject admin options'),
					)
					console.error(error)
				}
			}
			this.loading = false
		},
		async validateUrl() {
			const response = await validateOPInstance(this.serverUrl)
			this.resetErrors()
			if (response.data.result === true) {
				return true
			} else {
				switch (response.data.result) {
				case 'invalid':
					this.errorMessage = t(
						'integration_openproject',
						'URL is invalid',
					)
					this.errorDetails = t(
						'integration_openproject',
						'The URL should have the form "https://openproject.org"',
					)
					break
				case 'not_valid_body':
					this.errorMessage = t(
						'integration_openproject',
						'There is no valid OpenProject instance listening at that URL, please check the Nextcloud logs',
					)
					break
				case 'client_exception': {
					this.errorMessage = t(
						'integration_openproject',
						'There is no valid OpenProject instance listening at that URL, please check the Nextcloud logs',
					)
					this.errorDetails = t(
						'integration_openproject',
						'Response:',
					) + ' "' + response.data.details + '"'
					break
				}
				case 'server_exception': {
					this.errorMessage = t(
						'integration_openproject',
						'Server replied with an error message, please check the Nextcloud logs',
					)
					this.errorDetails = response.data.details
					break
				}
				case 'local_remote_servers_not_allowed': {
					const linkText = t('integration_openproject', 'Documentation')
					const htmlLink = `<a class="link" href="https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/" target="_blank" title="${linkText}">${linkText}</a>`

					this.errorMessage = t(
						'integration_openproject',
						'Accessing OpenProject servers with local addresses is not allowed.',
					)
					this.errorDetails = t(
						'integration_openproject',
						'To be able to use an OpenProject server with a local address, enable the `allow_local_remote_servers` setting. {htmlLink}.',
						{ htmlLink },
						null,
						{ escape: false, sanitize: false },
					)
					break
				}
				case 'redirected':
				{
					const location = response.data.details
					this.errorMessage = t(
						'integration_openproject',
						'The given URL redirects to \'{location}\'. Please do not use a URL that leads to a redirect.',
						{ location },
					)
					break
				}
				case 'unexpected_error':
				case 'network_error':
				case 'request_exception':
				default: {
					this.errorMessage = t(
						'integration_openproject',
						'Could not connect to the given URL, please check the Nextcloud logs',
					)
					this.errorDetails = response.data.details
					break
				}
				}
				await this.$nextTick()
				await this.$refs.urlInput?.$refs?.textInput?.focus()
				return false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.form-actions {
	display: flex;
	align-items: center;
	padding: 15px 0;
}

.pb-1 {
	padding-bottom: .5rem;
}

.mr-2 {
	margin-right: .5rem;
}
</style>
