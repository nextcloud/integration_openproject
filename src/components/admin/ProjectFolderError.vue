<template>
	<div class="project-folder-error">
		<div class="project-folder-error-alert">
			<AlertCircleOutline fill-color="#FF0000" :size="26" />
			<b class="project-folder-error-alert-message">{{ projectFolderSetUpError }}</b>
		</div>
		<p>{{ projectFolderSetUpErrorMessageDescription }}</p>
	</div>
</template>
<script>

import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
export default {
	name: 'ProjectFolderError',
	components: {
		AlertCircleOutline,
	},
	props: {
		projectFolderSetUpError: {
			type: String,
			required: true,
		},
		projectFolderSetUpErrorMessageDescription: {
			type: String,
			required: true,
		},
	},
}
</script>
<style lang="scss" scoped>
.project-folder-error {
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
