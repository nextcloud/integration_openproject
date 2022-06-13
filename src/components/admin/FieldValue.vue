<template>
	<div class="field-value">
		<b class="title">
			{{ title + (isRequired ? '*' : '') }}:
		</b>
		&nbsp;
		<div v-if="encryptValue" data-test-id="encrypted-value">
			<span v-if="inspect">{{ value }}</span>
			<span v-else>{{ encryptedValue }}</span>
		</div>
		<span v-else>{{ value }}</span>

		<div v-if="encryptValue && withInspection"
			class="inspect-icon"
			:class="{
				'eye-icon-off': inspect,
				'eye-icon': !inspect,
			}"
			@click="toggleInspection" />
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
		encryptValue: {
			type: Boolean,
			default: false,
		},
		withInspection: {
			type: Boolean,
			default: false,
		},
	},
	data: () => ({
		inspect: false,
	}),
	computed: {
		encryptedValue() {
			return this.value.substr(0, 8) + '*'.repeat(15)
		},
	},
	methods: {
		toggleInspection() {
			this.inspect = !this.inspect
		},
	},
}
</script>
<style lang="scss">
.field-value {
	padding: 6px 0;
	display: flex;
	align-items: center;
}

.inspect-icon {
	cursor: pointer;
	margin-left: 6px;
	width: 16px;
	height: 10px;
	background-size: 16px;
	background-repeat: no-repeat;
	background-position: center;
}

.eye-icon {
	background-image: url(./../../../img/eye.svg);
}

.eye-icon-off {
	background-image: url(./../../../img/add.svg);
}
</style>
