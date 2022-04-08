<template>
	<div class="saved-info">
		<b class="title">
			{{ title }}{{ isRequired ? '*' : '' }}:
		</b>
		&nbsp;
		<span>{{ value }}</span>
	</div>
</template>
<script>
export default {
	name: 'FieldValue',
	props: {
		title: {
			type: String,
			required: true,
		},
		value: {
			type: String,
			required: true,
		},
		isRequired: {
			type: Boolean,
			default: false,
		},
	},
}
</script>
<style lang="scss">
.saved-info {
	padding: 6px 0;
	display: flex;
	align-items: center;
}
</style>
