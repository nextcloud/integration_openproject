<template>
	<button v-on="$listeners">
		<div v-if="iconClass" class="b-icon" :class="iconClass" />
		<span>{{ t('integration_openproject', text) }}</span>
		<div v-if="isLoading" class="icon-loading" />
	</button>
</template>
<script>
export default {
	name: 'Button',
	props: {
		isLoading: {
			type: Boolean,
			default: false,
		},
		iconClass: {
			type: String,
			default: null,
		},
		text: {
			required: true,
			type: String,
		},
	},
}
</script>
<style lang="scss" scoped>
button {
	font-size: .875rem;
	line-height: 1.25rem;
	font-weight: 400;
	position: relative;
	.b-icon {
		height: 16px;
		width: 16px;
		background-repeat: no-repeat;
		background-position: center;
		margin-right: 6px;
	}
	.icon-loading {
		min-height: 0;
	}
	.icon-loading:after {
		height: 14px;
		width: 14px;
		top: 6px;
		left: 20px;
	}
}
</style>
