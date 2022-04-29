const stylelintConfig = require('@nextcloud/stylelint-config')
stylelintConfig.rules = {
	...stylelintConfig.rules,
	'selector-list-comma-space-after': 'always',
	'declaration-colon-space-after': 'always',
	'max-empty-lines': 1,
	"block-opening-brace-space-before": "always"
}
module.exports = stylelintConfig
