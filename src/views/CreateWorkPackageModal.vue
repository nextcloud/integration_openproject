<template>
	<NcModal class="create-workpackage-modal" v-if="showModal" :show.sync="showModal"
           :canClose="true" :size="normal" @close="closeModal">
    <h2 class="create-workpackage-modal--title">{{t('integration_openproject', 'Create and link a new work package')}}</h2>
    <iframe
			id="create-workpackage-iframe"
			width="600px"
      height="600px"
			:src="getIframeSource"
      @load="handleIframeLoad"/>
	</NcModal>
</template>
<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import { loadState } from "@nextcloud/initial-state";

export default {
	name: 'CreateWorkPackageModal',
	components: {
		NcModal,
	},
  data: () => ({
    openprojectUrl: loadState('integration_openproject', 'openproject-url'),
  }),
  props: {
		showModal: {
			type: Boolean,
			default: false,
		}
	},
  computed:{
    getIframeSource(){
      const baseurl = this.openprojectUrl.replace(/\/+$/, '')
      return baseurl + "/work_packages/new?iframe=true"
    }
  },
  methods:{
    closeModal(){
      this.showModal = false
    },
    handleIframeLoad() {
      window.addEventListener('message', (event) => {
        if (event.origin !== this.openprojectUrl) return
          // alert(
          //     'Saved. WorkPackage ID: '
          //     + event.data.openProjectEventPayload.workPackageId
          //     + ' '
          //     + event.data.openProjectEventPayload.workPackageUrl
          // )
        const eventData = event.data
        // send the data to the parent component to create link to the work package
        this.$emit('createWorkPackage', eventData);
        this.closeModal()
        // Perform actions based on the message
      });
    },
  }
}
</script>
<style lang="scss">
.create-workpackage-modal {
  &--title {
    text-align: center;
    padding: 10px;
  }
}
</style>
