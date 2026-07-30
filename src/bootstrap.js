/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { translate, translatePlural } from '@nextcloud/l10n'

const app = createApp({})
app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural
app.config.globalProperties.OC = window.OC
app.config.globalProperties.OCA = window.OCA
