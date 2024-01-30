<template>
	<NcModal
		v-if="showModal">
		<div class="tos-modal-wrapper">
			<div class="tos-modal-content">
				<AlertCircleOutline v-if="!signTOSSuccessful" fill-color="#FF0000" :size="60" />
				<CheckBoldIcon v-else fill-color="var(--color-success)" :size="60" />
				<div class="tos-modal-content-description">
					<p v-if="!signTOSSuccessful && !errorMessage" class="tos-modal-content-description-failure">
						Some "Terms of services" has not been signed up for user OpenProject.<br>
						Click below to sign all unsigned "Terms Of Services" for user OpenProject.
					</p>
					<p v-else-if="!signTOSSuccessful && errorMessage" class="tos-modal-content-description-failure">
						{{ errorMessage }}
					</p>
					<p v-else class="tos-modal-content-description-success">
						All the "Terms of services" signed for user OpenProject Succuessfully!"
					</p>
				</div>
				<div class="tos-modal-content-button">
					<NcButton v-if="!signTOSSuccessful"
						data-test-id="sign-tos-for-openproject"
						@click="signTOSForUserOpenProject">
						<template #icon>
							<LoadingIcon v-if="isLoading" class="loading-spinner" :size="25" />
							<CheckBoldIcon v-else :size="20" />
						</template>
						{{ t('integration_openproject', signTOSButtonLabel) }}
					</NcButton>
					<NcButton v-else
						data-test-id="close-modal"
						@click="closeModal">
						{{ t('integration_openproject', 'Close') }}
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
import LoadingIcon from 'vue-material-design-icons/Loading.vue'

export default {
	name: 'TermsOfServiceUnsigned',
	components: {
		NcModal,
		NcButton,
		CheckBoldIcon,
		AlertCircleOutline,
		LoadingIcon,
	},
	props: {
		isAnyUnsignedTermsOfServiceForUserOpenProject: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			signTOSSuccessful: false,
			isLoading: false,
			showModal: this.isAnyUnsignedTermsOfServiceForUserOpenProject,
			errorMessage: '',
			signTOSButtonLabel: 'Sign Terms of Services',
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
					this.signTOSSuccessful = true
				}
			} catch (error) {
				console.error(error)
				this.errorMessage = error.response.data.error
				this.isLoading = false
				this.signTOSButtonLabel = 'Retry Again'
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
	}
}
</style>
