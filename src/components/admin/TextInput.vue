<template>
	<div class="input-wrapper">
		<div class="text-input">
			<div class="text-input-label">
				{{ translate(labelText) }}
			</div>
			<input :id="id"
				ref="textInput"
				:value="value"
				:type="type"
				class="full-width"
				:class="{'error': !!errorMessage}"
				:placeholder="translate(placeHolder)"
				@input="$emit('input', $event.target.value)"
				@change="$emit('change', $event.target.value)"
				@focus="$emit('focus', $event)"
				@blur="$emit('blur', $event)">
			<div v-if="errorMessage || hintText" class="text-input-messages">
				<div v-if="errorMessage" class="text-input-error">
					{{ translate(errorMessage) }}
				</div>
				<div v-else
					class="text-input-hint"
					v-html="hintText" />
			</div>
		</div>
		<button v-if="withCopyBtn"
			class="copy-btn"
			:disabled="isDisabled"
			@click="copyValue">
			<div class="copy-icon" />
			<span>{{ translate("Copy value") }}</span>
		</button>
	</div>
</template>
<script>
export default {
	name: 'TextInput',
	props: {
		value: {
			required: true,
		},
		id: {
			type: String,
			required: true,
		},
		type: {
			type: String,
			default: 'text',
		},
		placeHolder: {
			default: '',
			type: String,
		},
		label: {
			required: true,
			type: String,
		},
		hintText: {
			default: false,
			type: [String, Boolean],
		},
		errorMessage: {
			default: false,
			type: [String, Boolean],
		},
		isRequired: {
			default: false,
			type: Boolean,
		},
		withCopyBtn: {
			default: false,
			type: Boolean,
		},
	},
	computed: {
		labelText() {
			if (this.isRequired) {
				return `${this.label} *`
			} else return this.label
		},
		isDisabled() {
			if (!this.value) return 'disabled'
			else return false
		},
	},
	methods: {
		translate(text) {
			return t('integration_openproject', text)
		},
		copyValue() {
			navigator.clipboard.writeText(this.value)
		},
	},
}
</script>
<style lang="scss" scoped>
.input-wrapper {
	display: flex;
	align-items: center;
}
.text-input {
	.full-width {
		width: 100%;
	}

	&-label {
		font-weight: 700;
		font-size: .875rem;
		line-height: 1.25rem;
		color: #333333;
	}

	&-messages {
		font-weight: 400;
		font-size: .75rem;
		line-height: 1rem;
	}

	.error {
		border: 2px solid var(--color-error) !important;
	}

	&-error {
			color: var(--color-error);
	}
	input[data-focus-visible-added].error {
		outline: none;
		box-shadow: none;
	}
}
.copy-btn {
	display: flex;
	align-items: center;
	margin-left: 10px;
	margin-top: 6px;
	span {
		margin-left: 6px;
	}
	.copy-icon {
		width: 16px;
		height: 16px;
		background-size: 16px;
		background-repeat: no-repeat;
		background-position: center;
		background-image: url(./../../../img/copy.svg);
	}
}

body[data-theme-dark-highcontrast], body[data-theme-dark], body.theme--dark {
	.text-input {
		&-label {
			color: #FFFFFF;
		}
	}
}
</style>
<style>
.text-input .link {
	color: #1a67a3 !important;
	font-style: italic;
}
</style>
