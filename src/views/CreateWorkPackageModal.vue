<template>
	<NcModal v-if="showModal">
		<iframe
			id="inlineFrameExample"
			title="Inline Frame Example"
			height="1000px"
			src="https://openproject.example.com/work_packages/new?iframe=true" />
	</NcModal>
</template>
<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
export default {
	name: 'CreateWorkPackageModal',
	components: {
		NcModal,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
	},
}
</script>
