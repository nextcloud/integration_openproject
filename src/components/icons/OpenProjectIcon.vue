<!--
  - SPDX-FileCopyrightText: 2023 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon openproject-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg xmlns="http://www.w3.org/2000/svg"
			:width="size"
			:height="size"
			viewBox="0 0 150 150"
			version="1.1"
			:fill="fillColor">
			<path d="m 80,110 h 0.013 C 80.01,110 80,110.086 80,110.166 c 0,4.01 3.357,7.224 7.5,7.224 4.143,0 7.5,-3.194 7.5,-7.204 C 95,110.104 94.99,110 94.987,110 H 95 V 92 H 80 Z" />
			<path d="M 115,13 H 105 C 91.193,13 80,24.045 80,37.853 v 11 9 V 68 H 46 45 36 C 22.193,68 11,79.046 11,92.853 v 20 C 11,126.659 22.193,138 36,138 h 10 c 13.807,0 25,-11.341 25,-25.147 v -20 C 71,92.518 70.988,92 70.975,92 H 56 v 0.853 7 13 C 56,118.366 51.514,123 46,123 H 36 c -5.514,0 -10,-4.634 -10,-10.147 v -20 C 26,87.339 30.486,83 36,83 h 8 1 1 22.914 2.086 34 7 3 c 13.807,0 25,-11.341 25,-25.147 v -20 C 140,24.045 128.807,13 115,13 Z m 10,44.853 C 125,63.367 120.514,68 115,68 h -3 -3 -4 -10 v -10.147 -9 -1 -10 C 95,32.338 99.486,28 105,28 h 10 c 5.514,0 10,4.338 10,9.853 z" />
		</svg>

	</span>
</template>

<script>
export default {
	name: 'OpenProjectIcon',
	props: {
		size: {
			type: Number,
			default: 32,
		},
		title: {
			type: String,
			default: '',
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
	},
}
</script>
