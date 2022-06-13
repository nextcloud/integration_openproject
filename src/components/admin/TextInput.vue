<template>
	<div class="text-input-wrapper">
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
			:disabled="isCopyDisabled"
			@click="copyValue">
			<div class="copy-icon" />
			<span>{{ translate("Copy value") }}</span>
		</button>
	</div>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TextInput',
	props: {
		value: {
			default: '',
			type: String,
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
			default: null,
			type: [String, null],
		},
		errorMessage: {
			default: null,
			type: [String, null],
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
		isCopyDisabled() {
			return !this.value
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
.text-input-wrapper {
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
	cursor: copy;
	display: flex;
	align-items: center;
	margin-left: 10px;
	margin-top: 6px;
	span {
		cursor: copy;
		margin-left: 6px;
	}
	.copy-icon {
		cursor: copy;
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
