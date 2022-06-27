<template>
	<div class="form-heading"
		:class="{'disabled': isDisabled}">
		<div v-if="isComplete" class="complete">
			<CheckBoldIcon fill-color="#FFFFFF" :size="12" />
		</div>
		<div v-else class="index">
			{{ index }}
		</div>
		<div class="title"
			:class="{'green-text': isComplete}">
			{{ title }}
		</div>
	</div>
</template>
<script>

import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'

export default {
	name: 'FormHeading',
	components: {
		CheckBoldIcon,
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
	},
}
</script>
<style lang="scss" scoped>
.form-heading {
	display: flex;
	justify-content: start;
	align-items: center;
	padding: 20px 0;

	.green-text {
		color: #308720;
	}

	.complete {
		height: 16px;
		width: 16px;
		border-radius: 50%;
		background: #308720;
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
		background: #555555;
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
