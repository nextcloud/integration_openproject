<template>
	<div class="form-check-input">
		<input :id="inputId"
			:value="value"
			type="checkbox"
			class="checkbox"
			:checked="value"
			@click="$emit('click', $event)"
			@input="$emit('input', $event.target.checked)"
			@change="$emit('change', $event.target.checked)"
			@focus="$emit('focus', $event)"
			@blur="$emit('blur', $event)">
		<label :for="inputId">
			{{ label }}
		</label>
		<slot name="hint" />
	</div>
</template>
<script>
export default {
	name: 'CheckBox',
	props: {
		value: {
			default: false,
			type: Boolean,
		},
		inputId: {
			type: String,
			required: true,
		},
		label: {
			type: String,
			required: true,
		},
	},
}
</script>
<style lang="scss" scoped>
.form-check-input {
	margin-bottom: 1rem;
}
</style>
