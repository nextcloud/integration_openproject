<template>
	<div class="form-heading"
		:class="{'disabled': isDisabled}">
		<div v-if="isGroupFolderSetupHeading && isSetupCompleteWithoutGroupFolders" class="setup-complete-without-group-folders">
			<MinusThickIcon fill-color="#FFFFFF" :size="12" />
		</div>
		<div v-else-if="isThereErrorAfterGroupFolderAndAppPasswordSetup" class="group-folder-setup-status">
			<ExclamationThickIcon fill-color="#FFFFFF" :size="12" />
		</div>
		<div v-else-if="isComplete" class="complete">
			<CheckBoldIcon fill-color="#FFFFFF" :size="12" />
		</div>
		<div v-else class="index">
			{{ index }}
		</div>
		<div class="title"
			:class="{
				'green-text': isComplete,
				'red-text': isThereErrorAfterGroupFolderAndAppPasswordSetup
			}">
			{{ title }}
		</div>
	</div>
</template>
<script>

import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import MinusThickIcon from 'vue-material-design-icons/MinusThick.vue'
import ExclamationThickIcon from 'vue-material-design-icons/ExclamationThick.vue'
export default {
	name: 'FormHeading',
	components: {
		CheckBoldIcon,
		MinusThickIcon,
		ExclamationThickIcon,
	},
	props: {
		index: {
			type: String,
			required: true,
		},
		title: {
			type: String,
			required: true,
		},
		isDisabled: {
			type: Boolean,
			default: false,
		},
		isComplete: {
			type: Boolean,
			default: false,
		},
		isSetupCompleteWithoutGroupFolders: {
			type: Boolean,
			default: false,
		},
		isGroupFolderSetupHeading: {
			type: Boolean,
			default: false,
		},
		isThereErrorAfterGroupFolderAndAppPasswordSetup: {
			type: Boolean,
			default: false,
		},
	},
}
</script>
<style lang="scss" scoped>
.form-heading {
	display: flex;
	justify-content: start;
	align-items: center;
	padding: 15px 0;

	.green-text {
		color: var(--color-success);
	}

	.red-text {
		color: var(--color-error);
	}

	.complete {
		height: 16px;
		width: 16px;
		border-radius: 50%;
		background-color: var(--color-success);
		display: flex;
		justify-content: center;
		align-items: center;
	}

	.group-folder-setup-status {
		height: 16px;
		width: 16px;
		border-radius: 50%;
		background-color: var(--color-error);
		display: flex;
		justify-content: center;
		align-items: center;
	}

	.setup-complete-without-group-folders {
		height: 16px;
		width: 16px;
		border-radius: 50%;
		background: var(--color-loading-dark);
		display: flex;
		justify-content: center;
		align-items: center;
	}

	.index {
		height: 16px;
		width: 16px;
		font-size: 14px;
		font-weight: 600;
		line-height: 16px;
		text-align: center;
		border-radius: 50%;
		background: var(--color-loading-dark);
		color: white;
	}
	.title {
		font-weight: 700;
		font-size: 14px;
		line-height: 20px;
		padding-left: 6px;
	}
}

.form-heading.disabled {
	.index {
		background: #CCCCCC !important;
	}
	.title {
		color: #CCCCCC !important;
	}
}
</style>
