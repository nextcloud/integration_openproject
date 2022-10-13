<template>
	<div class="workpackage" @mouseover="resetColorsOnHover" @mouseleave="resetColorsOnHover">
		<div class="row">
			<div class="row__status"
				:style="{'background-color': getWPStatusColor(), border: wpStatusBorder}">
				<div class="row__status__title"
					:style="{'color': wpStatusFontColor }">
					{{ workpackage.statusTitle }}
				</div>
			</div>
			<div class="row__workpackage">
				#{{ workpackage.id }} - {{ workpackage.project }}
			</div>
		</div>
		<div class="row">
			<div class="row__subject">
				<span :ref="workpackage.typeTitle"
					class="row__subject__type"
					:style="{'color': workpackage.typeCol, '-webkit-text-stroke': wpTypeTextStroke}">
					{{ workpackage.typeTitle }}
				</span>
				{{ workpackage.subject }}
			</div>
		</div>
		<div class="row">
			<div v-if="workpackage.assignee" class="row__assignee">
				<div class="row__assignee__avatar">
					<Avatar class="item-avatar"
						:size="23"
						:url="workpackage.picture"
						:user="workpackage.assignee"
						:display-name="workpackage.assignee" />
				</div>
				<div class="row__assignee__assignee">
					{{ workpackage.assignee }}
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar.js'

export default {
	name: 'WorkPackage',
	components: {
		Avatar,
	},
	props: {
		workpackage: {
			type: Object,
			required: true,
		},
	},
	data: () => ({
		wpTypeTextStroke: 'unset',
		wpStatusFontColor: '#FFFFFF',
		wpStatusBorder: '0',
	}),
	created() {
		this.setWPTypeTextStroke()
		this.setWPStatusFontColor()
		this.setWPStatusBorder()
	},
	methods: {
		resetColorsOnHover() {
			this.setWPTypeTextStroke()
			this.setWPStatusBorder()
		},
		getWPStatusColor() {
			if (this.workpackage.statusCol === undefined || this.workpackage.statusCol === '') {
				return '#F99601'
			}
			return this.workpackage.statusCol
		},
		setWPStatusFontColor() {
			this.wpStatusFontColor = '#FFFFFF'
			try {
				const contrast = this.contrastRatio(
					this.wpStatusFontColor,
					this.getWPStatusColor()
				)
				if (contrast <= 2) {
					this.wpStatusFontColor = '#000000'
				}
			} catch (e) {
				// something went  wrong, leave the values as they are
			}
		},
		setWPStatusBorder() {
			try {
				let contrast = this.getContrastBetweenStatusColorAndBackground()
				if (contrast <= 2) {
					contrast = this.contrastRatio(this.getWPStatusColor(), '#000000')
					if (contrast <= 2) {
						this.wpStatusBorder = 'solid 1px #FFFFFF'
						return
					}
					this.wpStatusBorder = 'solid 1px #000000'
					return
				}
				this.wpStatusBorder = '0'
			} catch (e) {
				// something went  wrong, leave the values as they are
			}
		},
		getContrastBetweenStatusColorAndBackground() {
			const backgroundColor = this.getBackgroundColor(this.getWPBackgroundElement())
			return this.contrastRatio(this.getWPStatusColor(), backgroundColor)
		},
		setWPTypeTextStroke() {
			this.wpTypeTextStroke = 'unset'
			try {
				const contrast = this.getContrastBetweenTypeColorAndBackground()
				if (contrast <= 2) {
					this.wpTypeTextStroke = '0.5px grey'
				}
			} catch {
				// something went  wrong, leave the values as they are
			}
		},
		getContrastBetweenTypeColorAndBackground() {
			const backgroundColor = this.getBackgroundColor(this.getWPBackgroundElement())
			return this.contrastRatio(this.workpackage.typeCol, backgroundColor)
		},
		getWPBackgroundElement() {
			let el = document.getElementById('workpackage-' + this.workpackage.id)
			if (el === null) {
				el = document.getElementById('tab-open-project')
			}
			return el
		},
		hexToRgbA(hex) {
			let c
			if (/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) {
				c = hex.substring(1).split('')
				if (c.length === 3) {
					c = [c[0], c[0], c[1], c[1], c[2], c[2]]
				}
				c = '0x' + c.join('')
				return 'rgba(' + [(c >> 16) & 255, (c >> 8) & 255, c & 255].join(',') + ',1)'
			}
			throw new Error('Bad Hex')
		},
		// Parse rgb(r, g, b) and rgba(r, g, b, a) strings into an array.
		// originated from https://github.com/jasonday/color-contrast/blob/e533684ce5d0c8d28479b3301b184bf88f5b7dd6/color-contrast.js
		// Adapted from https://github.com/gka/chroma.js
		parseRgb(colorString) {
			let i, rgb, _i, _j
			try {
				colorString = this.hexToRgbA(colorString)
			} catch (e) {
				// it was not a hex string, so most likely a rgb(a) string
			}
			const rgbMatch = colorString.match(/rgb\(\s*(-?\d+),\s*(-?\d+)\s*,\s*(-?\d+)\s*\)/)
			const rgbaMatch = colorString.match(/rgba\(\s*(-?\d+),\s*(-?\d+)\s*,\s*(-?\d+)\s*,\s*([01]|[01]?\.\d+)\)/)
			if (rgbMatch !== null) {
				rgb = rgbMatch.slice(1, 4)
				for (i = _i = 0; _i <= 2; i = ++_i) {
					rgb[i] = +rgb[i]
				}
				rgb[3] = 1
				// eslint-disable-next-line no-cond-assign
			} else if (rgbaMatch !== null) {
				rgb = rgbaMatch.slice(1, 5)
				for (i = _j = 0; _j <= 3; i = ++_j) {
					rgb[i] = +rgb[i]
				}
			} else {
				throw new Error('could not parse color')
			}
			return rgb

		},
		// originated from https://github.com/jasonday/color-contrast/blob/e533684ce5d0c8d28479b3301b184bf88f5b7dd6/color-contrast.js
		// Based on http://www.w3.org/TR/WCAG20/#relativeluminancedef
		relativeLuminance(c) {
			const lum = []
			for (let i = 0; i < 3; i++) {
				const v = c[i] / 255
				lum.push(v < 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4))
			}
			return (0.2126 * lum[0]) + (0.7152 * lum[1]) + (0.0722 * lum[2])
		},
		// originated from https://github.com/jasonday/color-contrast/blob/e533684ce5d0c8d28479b3301b184bf88f5b7dd6/color-contrast.js
		// Based on http://www.w3.org/TR/WCAG20/#contrast-ratiodef
		contrastRatio(x, y) {
			const l1 = this.relativeLuminance(this.parseRgb(x))
			const l2 = this.relativeLuminance(this.parseRgb(y))
			return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05)
		},
		// originated from https://github.com/jasonday/color-contrast/blob/e533684ce5d0c8d28479b3301b184bf88f5b7dd6/color-contrast.js
		getBackgroundColor(el) {
			const styles = getComputedStyle(el)
			const bgColor = styles.backgroundColor
			const bgImage = styles.backgroundImage
			const rgb = this.parseRgb(bgColor) + ''
			const alpha = rgb.split(',')

			// if background has alpha transparency, flag manual check
			if (alpha[3] < 1 && alpha[3] > 0) {
				throw new Error('could not determine background color')
			}

			// if element has no background image, or transparent background (alpha == 0) return bgColor
			if (bgColor !== 'rgba(0, 0, 0, 0)' && bgColor !== 'transparent' && bgImage === 'none' && alpha[3] !== '0') {
				return bgColor
			} else if (bgImage !== 'none') {
				throw new Error('could not determine background color')
			}

			// retest if not returned above
			if (el.tagName === 'HTML') {
				return 'rgb(255, 255, 255)'
			} else {
				return this.getBackgroundColor(el.parentNode)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.workpackage {
	width: 100%;
	padding: 15px 6px 0 10px;
	border-bottom: 1px solid rgb(237 237 237);

	.row {
		display: flex;
		padding: 3px;
		flex-wrap: wrap;

		&__status {
			padding: 5px 10px;
			width: fit-content;
			height: 20px;
			justify-content: center;
			display: flex;
			align-items: center;
			text-align: center;
			font-size: 0.75rem;
			border-radius: 2px;
			margin-right: 4px;

			&__title {
				font-size: 0.75rem;
				line-height: 14px;
				text-align: center;
			}
		}

		&__workpackage {
			color: #878787;
			font-size: 0.8rem;
			height: 20px;
			line-height: 20px;
		}

		&__subject {
			font-size: 0.875rem;
			text-align: justify;
			white-space: normal;
			text-justify: inter-word;
			display: -webkit-box;
			-webkit-line-clamp: 2;
			-webkit-box-orient: vertical;
			overflow: hidden;
			text-overflow: ellipsis;

			&__type {
				font-size: 0.75rem;
				font-weight: bold;
				text-transform: uppercase;
				margin-right: 4px;
			}
		}

		&__assignee {
			display: flex;
			flex-direction: row;
			padding: 5px;

			&__assignee {
				font-size: 0.81rem;
				color: #878787;
				text-align: center;
				padding-left: 5px;
			}
		}
	}
}

.workpackage div {
	cursor: pointer;
}
</style>
