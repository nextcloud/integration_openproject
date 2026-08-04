/**
 * SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, vi } from 'vitest'
import { config } from '@vue/test-utils'
import { name as appName } from '../../package.json'

// allow rendering of default slot content in stubs
config.global.renderStubDefaultSlot = true

beforeAll(() => {
  vi.resetModules()
})

globalThis.OCA = {}
globalThis.OC = {}
globalThis.appName = appName
globalThis.appVersion = '0.0.0'
// globalThis.structuredClone = v => JSON.parse(JSON.stringify(v))

globalThis.t = (_app, text) => text
globalThis.getLanguage = vi.fn(() => '')
// globalThis.getGettextBuilder = vi.fn(() => ({
// 	detectLanguage: () => ({
// 		build: () => ({
// 			ngettext: (s) => s,
// 			gettext: (s) => s,
// 			addTranslations: vi.fn(),
// 		}),
// 	}),
//   getLanguage: vi.fn(() => ''),
// }))
