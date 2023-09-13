<template>
	<NcModal class="create-workpackage-modal"
		:show.sync="showModal"
		:can-close="true"
		:close-on-click-outside="false"
		:size="normal"
		@close="closeModal">
		<h2 class="create-workpackage-modal--title">
			{{ t('integration_openproject', 'Create and link a new work package') }}
		</h2>
		<LoadingIcon v-if="isLoading" class="loading-spinner" :size="40" />
		<iframe
			id="create-workpackage-iframe"
			width="600px"
			height="600px"
			:src="getIframeSource"
			@load="handleIframeLoad" />
	</NcModal>
</template>
<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import { loadState } from '@nextcloud/initial-state'
import { STATE } from '../utils'
import LoadingIcon from 'vue-material-design-icons/Loading.vue'

export default {
	name: 'CreateWorkPackageModal',
	components: {
		NcModal,
		LoadingIcon,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
	},
	data: () => ({
		openprojectUrl: loadState('integration_openproject', 'openproject-url'),
		state: STATE.LOADING,
		show: false,
	}),
	computed: {
		getIframeSource() {
			const baseurl = this.openprojectUrl.replace(/\/+$/, '')
			return baseurl + '/work_packages/new?iframe=true'
		},
		isLoading() {
			return this.state === STATE.LOADING
		},
	},
	methods: {
		closeModal() {
			this.showModal = false
			this.$emit('closeCreateWorkPackageModal')
		},
		handleIframeLoad() {
			this.state = STATE.OK
			window.addEventListener('message', (event) => {
				if (event.origin !== this.openprojectUrl) return
				const eventData = event.data
				// send the data to the parent component to create link to the work package
				this.$emit('createWorkPackage', eventData)
			})
		},
	},
}
</script>
<style lang="scss">
.create-workpackage-modal {
	&--title {
		text-align: center;
		padding: 10px;
	}
}

.loading-spinner {
	display: flex;
	align-self: center;
	justify-self: center;
}
</style>
