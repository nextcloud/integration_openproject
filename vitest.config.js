/**
 * SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [vue()],
  test: {
    include: ['tests/js/**/*.spec.js'],
    setupFiles: ['tests/js/setup.js'],
    globals: true,
    environment: 'jsdom',
    clearMocks: true,
    restoreMocks: true,
    server: {
      deps: {
        inline: ['@nextcloud/vue'],
      },
    },
    coverage: {
      provider: 'v8',
      include: ['src/'],
      exclude: ['src/{api,constants}/', 'src/utils.js'],
      reporter: ['text', 'lcov'],
      reportsDirectory: './coverage/js',
    },
  },
})