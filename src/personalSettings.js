/* jshint esversion: 6 */

/**
 * SPDX-FileCopyrightText: 2021-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { setupGlobalProperties } from './setup.js'
import PersonalSettings from './components/PersonalSettings.vue'

const app = createApp(PersonalSettings)
setupGlobalProperties(app)
app.mount('#openproject_prefs')