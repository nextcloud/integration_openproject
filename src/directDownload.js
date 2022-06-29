/* jshint esversion: 6 */

/**
 * Nextcloud - openproject
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

import Vue from 'vue'
import './bootstrap'
import DirectDownload from './components/DirectDownload'

// eslint-disable-next-line
'use strict'

// eslint-disable-next-line
new Vue({
	el: '#openproject_direct_download',
	render: h => h(DirectDownload),
})
