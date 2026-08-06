/**
 * SPDX-FileCopyrightText: 2022-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, beforeEach, afterEach, vi } from 'vitest'
import { nextTick } from 'vue'

import TextInput from '../../../../src/components/admin/TextInput.vue'

Object.assign(navigator, {
	clipboard: {
		writeText: () => {},
	},
})

vi.mock('@nextcloud/dialogs', () => ({
	showSuccess: vi.fn(),
}))

const selector = {
	textInputLabel: '.text-input-label',
	copyButton: '.text-input-copy-value',
	copyIcon: '.icon-clippy',
}

global.t = (app, text) => text

const selectors = {
	input: 'input',
	copyButton: 'button.text-input-copy-value',
}

describe('TextInput.vue', () => {
	describe('messages', () => {
		it('should show error message if provided', () => {
			const wrapper = getWrapper({
				hintText: null,
				errorMessage: 'some error message',
			})
			expect(wrapper.element).toMatchSnapshot()
		})
		it('should show hint text if provided', () => {
			const wrapper = getWrapper({
				hintText: 'some hint message',
				errorMessage: null,
			})
			expect(wrapper.element).toMatchSnapshot()
		})
		it('should show error message if both error message and hint text are provided', () => {
			const wrapper = getWrapper({
				hintText: 'some hint message',
				errorMessage: 'some error message',
			})
			expect(wrapper.element).toMatchSnapshot()
		})
		it('should show error message details if both error message and details are provided', () => {
			// the content of the error message details is not tested because the popup is rendered
			// outside the wrapper
			const wrapper = getWrapper({
				errorMessage: 'some error message',
				errorMessageDetails: 'here are some details',
			})
			expect(wrapper.element).toMatchSnapshot()
		})
		it('should not show error message details if not error message is provided', () => {
			const wrapper = getWrapper({
				errorMessageDetails: 'here are some details',
			})
			expect(wrapper.element).toMatchSnapshot()
		})
	})
	describe('is required prop', () => {
		it('should add asterik to the label text', () => {
			const wrapper = getWrapper({
				isRequired: true,
			})
			expect(wrapper.find(selector.textInputLabel).element).toMatchSnapshot()
		})
		it('should not add asterik to the label text', () => {
			const wrapper = getWrapper({
				isRequired: false,
			})
			expect(wrapper.find(selector.textInputLabel).element).toMatchSnapshot()
		})
	})
	describe('with copy button prop', () => {
		let wrapper
		beforeEach(async () => {
			wrapper = getWrapper({
				withCopyBtn: true,
			})
		})
		it('should render copy button if set', () => {
			expect(wrapper.element).toMatchSnapshot()
		})
		it('should be disabled if the input value is empty', () => {
			expect(wrapper.find(selector.copyButton).attributes()).toHaveProperty('disabled')
		})
		it('should be enabled if the input value is non empty', async () => {
			wrapper = getWrapper({
				withCopyBtn: true,
				modelValue: 'some-value',
			})
			expect(wrapper.find(selector.copyButton).attributes().disabled).toBeUndefined()
		})
		describe('on click', () => {
			let copyButton
			vi.useFakeTimers()
			const spyWriteToClipboard = vi.spyOn(navigator.clipboard, 'writeText')
				.mockImplementationOnce(() => vi.fn())
			beforeEach(() => {
				wrapper = getWrapper({
					withCopyBtn: true,
					modelValue: 'some-value-to-copy',
				})
				copyButton = wrapper.find(selector.copyButton)
			})
			it('should copy the input value', async () => {
				await copyButton.trigger('click')
				expect(spyWriteToClipboard).toBeCalledTimes(1)
				expect(spyWriteToClipboard).toBeCalledWith('some-value-to-copy')
			})
			it('should change the copy icon with the copied icon', async () => {
				expect(copyButton.attributes().title).toBe('Copy value')
				await copyButton.trigger('click')
				await nextTick()
				copyButton = wrapper.find(selector.copyButton)
				expect(copyButton.attributes().title).toBe('Copied!')
				vi.advanceTimersByTime(5000)
				await nextTick()
				copyButton = wrapper.find(selector.copyButton)
				expect(copyButton.attributes().title).toBe('Copy value')
			})
		})
	})
	describe('readonly prop', () => {
		it('should set the input to readonly', () => {
			const wrapper = getWrapper({
				readOnly: true,
			})
			expect(wrapper.element).toMatchSnapshot()
		})
	})
	describe('disabled prop', () => {
		it('should disable input elements when disabled is true', () => {
			const wrapper = getWrapper({
				label: 'test label',
				withCopyBtn: true,
				disabled: true,
				modelValue: 'some value',
			})

			expect(wrapper.find(selectors.input).attributes()).toHaveProperty('disabled')
			expect(wrapper.find(selectors.copyButton).attributes()).toHaveProperty('disabled')
		})
		it('should disable input elements when disabled is false', () => {
			const wrapper = getWrapper({
				label: 'test label',
				withCopyBtn: true,
				disabled: false,
				modelValue: 'some value',
			})

			expect(wrapper.find(selectors.input).attributes().disabled).toBeFalsy()
			expect(wrapper.find(selectors.copyButton).attributes().disabled).toBeFalsy()
		})
	})
})

function getWrapper(props = {}) {
	return mount(TextInput, {
		props: {
			id: 'unique-id',
			label: 'some label',
			...props,
		},
		global: {
			mocks: {
				t: (app, msg) => msg,
			},
		},
	})
}
