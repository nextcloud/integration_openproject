<template>
	<div class="group-folder-error">
		<div class="group-folder-error-alert">
			<AlertCircleOutline fill-color="#FF0000" :size="26" />
			<b class="group-folder-error-alert-message">{{ groupFolderSetUpError }}</b>
		</div>
		<p>{{ groupFolderSetUpErrorMessageDescription }}</p>
	</div>
</template>
<script>

import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
export default {
	name: 'GroupFolderError',
	components: {
		AlertCircleOutline,
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
