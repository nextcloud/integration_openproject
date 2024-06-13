<template>
	<NcModal v-if="showModal">
		<div class="terms-of-service-modal-wrapper">
			<div class="tos-modal-content">
				<AlertCircleOutline fill-color="#FF0000" :size="60" />
				<div class="terms-of-service-modal-content-description">
					<p class="terms-of-service-modal-content-description-failure">
						{{ t('integration_openproject', 'For user "OpenProject", several "Terms of services" have not been signed.') }}
					</p>
					<p class="terms-of-service-modal-content-description-failure">
						{{ t('integration_openproject', 'Sign any unsigned "Terms Of Services" for user "OpenProject".') }}
					</p>
				</div>
				<div class="terms-of-service-modal-content-button">
					<NcButton
						data-test-id="sign-terms-of-service-for-user-openproject"
						@click="signTermsOfServiceForUserOpenProject">
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

import { NcButton, NcModal, NcLoadingIcon } from '@nextcloud/vue'
import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
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
		isAllTermsOfServiceSignedForUserOpenProject: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			isLoading: false,
			showModal: !this.isAllTermsOfServiceSignedForUserOpenProject,
		}
	},
	methods: {
		async signTermsOfServiceForUserOpenProject() {
			this.isLoading = true
			try {
				const url = generateUrl('/apps/integration_openproject/sign-term-of-service')
				const response = await axios.post(url)
				const result = response?.data?.result
				this.isLoading = false
				if (result) {
					showSuccess(t('integration_openproject', 'All terms of services are signed for user "OpenProject" successfully!'))
				}
			} catch (error) {
				console.error(error)
				this.isLoading = false
				showError(t('integration_openproject', 'Failed to sign terms of services for user "OpenProject"'))
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
.terms-of-service-modal-wrapper {
	height: 225px;
	display: flex;
	justify-content: center;
	align-items: center;
}

.terms-of-service-modal-content {
	&-description {
		text-align: center;
		margin-top: 10px;
		&-failure {
			color: var(--color-error);
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
