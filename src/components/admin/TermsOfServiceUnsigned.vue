<template>
	<NcModal
		v-if="showModal">
		<div class="tos-modal-wrapper">
			<div class="tos-modal-content">
				<AlertCircleOutline fill-color="#FF0000" :size="60" />
				<div class="tos-modal-content-description">
					<p class="tos-modal-content-description-failure">
						For user "OpenProject", several "Terms of services" have not been signed.<br>
						Sign any unsigned "Terms Of Services" for user "OpenProject".
					</p>
				</div>
				<div class="tos-modal-content-button">
					<NcButton
						data-test-id="sign-tos-for-user-openproject"
						@click="signTOSForUserOpenProject">
						<template #icon>
							<NcLoadingIcon v-if="isLoading" class="loading-spinner" :size="25" />
							<CheckBoldIcon v-else :size="20" />
						</template>
						{{ t('integration_openproject', 'Sign Terms of services') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>
<script>

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TermsOfServiceUnsigned',
	components: {
		NcModal,
		NcButton,
		CheckBoldIcon,
		AlertCircleOutline,
		NcLoadingIcon,
	},
	props: {
		isAnyTermsOfServiceUnsignedForUserOpenProject: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			isLoading: false,
			showModal: this.isAnyTermsOfServiceUnsignedForUserOpenProject,
		}
	},
	methods: {
		async signTOSForUserOpenProject() {
			this.isLoading = true
			let success = false
			try {
				const url = generateUrl('/apps/integration_openproject/sign-tos-openproject')
				const response = await axios.post(url)
				success = response?.data?.result
				if (success) {
					this.isLoading = false
					showSuccess(t('integration_openproject', 'All the Terms of services are singed for user "OpenProject" successfully!'))
				}
			} catch (error) {
				console.error(error)
				this.isLoading = false
				showError(t('integration_openproject', 'Failed to sign Terms and Services for user "OpenProject"'))
			} finally {
				this.closeModal()
			}
		},
		closeModal() {
			this.showModal = false
		},
	},
}
</script>
<style lang="scss" scoped>
.tos-modal-wrapper {
	height: 225px;
	display: flex;
	justify-content: center;
	align-items: center;
}

.tos-modal-content {
	&-description {
		text-align: center;
		margin-top: 10px;
		&-failure {
			color: var(--color-error);
		}
		&-success {
			color: var(--color-success);
		}
	}
	&-button {
		display: flex;
		justify-content: center;
		margin-top: 18px;
		margin-bottom: 10px;
	}
}
</style>
