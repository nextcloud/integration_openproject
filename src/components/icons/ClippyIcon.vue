<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon clippy-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			:width="size"
			:height="size"
			enable-background="new 0 0 16 16"
			version="1.1"
			viewBox="0 0 16 16"
			xml:space="preserve"
			xmlns="http://www.w3.org/2000/svg">
			<path d="m13 15h-11l0.0005-10h11v3.0002l1-0.0004 0.0005-5.0001c0.000058-0.5834-0.4165-1.0002-1.0005-1.0001l-3.467 0.0005c0.0008-1.183-0.9492-2.0001-2.1325-2.0001s-2.1333 0.8171-2.1333 2.0004h-3.2c-0.5834 0-1.0662 0.4166-1.0662 0.9999l-0.0005 12c-0.0000243 0.584 0.4833 1 1.0667 1l10.933-0.0005c0.584-0.001 1-0.416 1-1v-3h-1zm-8.8005-12h1.0672c0.5833 0 1.0666-0.4162 1.0666-0.9996 0-0.5833 0.4834-0.9337 1.0667-0.9337s1.0667 0.3504 1.0667 0.9337c0 0.5834 0.5333 0.9996 1.0666 0.9996h1.2667c0.517 0 1.2 0.4166 1.2 1h-9c-0.0004-0.65 0.5988-1 1.1988-1zm-1.1995 8h2v-1h-2zm7.9998-2v-2l-4 3 3.9998 3v-2l5.0002-0.00005v-2l-4.9998-0.00005zm-8 4h4v-1h-4zm6-7h-6v1h6zm-3 2h-3v1h3z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'ClippyIcon',
	props: {
		title: {
			type: String,
			default: '',
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>
