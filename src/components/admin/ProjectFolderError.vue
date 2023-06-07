<template>
	<div class="project-folder-error">
		<div class="project-folder-error-alert">
			<AlertCircleOutline fill-color="#FF0000" :size="26" />
			<b class="project-folder-error-alert-message">{{ projectFolderSetUpError }}</b>
		</div>
		<p v-html="sanitizedHintText" /> <!-- eslint-disable-line vue/no-v-html -->
	</div>
</template>
<script>

import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import dompurify from 'dompurify'
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
	computed: {
		sanitizedHintText() {
			return dompurify.sanitize(this.projectFolderSetUpErrorMessageDescription, { ADD_ATTR: ['target'] })
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

<style>
.project-folder-error .link {
	color: #1a67a3 !important;
	font-style: italic;
}
</style>
