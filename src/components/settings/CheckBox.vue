<!--
  - SPDX-FileCopyrightText: 2022 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="form-check-input">
		<input :id="inputId"
			:value="modelValue"
			type="checkbox"
			class="checkbox"
			:checked="modelValue"
			@click="$emit('click', $event)"
			@input="$emit('update:modelValue', $event.target.checked)"
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
		modelValue: {
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
	emits: ['update:modelValue', 'click', 'change', 'focus', 'blur'],
}
</script>
<style lang="scss" scoped>
.form-check-input {
	margin-bottom: 1rem;
}
</style>
