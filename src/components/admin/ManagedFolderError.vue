<template>
	<div class="group-folder-error">
		<div class="group-folder-error-alert">
			<AlertCircleOutline fill-color="#FF0000" :size="26" />
			<b class="group-folder-error-alert-message">{{ this.groupFolderSetUpError }}</b>
		</div>
		<p>{{ this.groupFolderSetUpErrorMessageDescription }}</p>
	</div>
</template>
<script>

import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import AlertCircleOutline from "vue-material-design-icons/AlertCircleOutline.vue";
export default {
	name: 'ManagedFolderError',
	components: {
		AlertCircleOutline
	},
	props: {
		groupFolderSetUpError: {
			type: String,
			required: true,
		},
		groupFolderSetUpErrorMessageDescription: {
			type: String,
			required: true,
		},
	},
}
</script>
<style lang="scss" scoped>
.group-folder-error {
	&-alert {
		display: flex;
		justify-content: start;
		align-items: center;
		padding-top: 15px;

		&-message {
			padding-left: 4px;
		}
	}
	color: var(--color-error);
}
</style>
