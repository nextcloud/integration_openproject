import { createLocalVue, mount } from '@vue/test-utils'
import TextInput from '../../../../src/components/admin/TextInput'

const localVue = createLocalVue()

Object.assign(navigator, {
	clipboard: {
		writeText: () => {},
	},
});

const selector = {
	textInputLabel: '.text-input-label',
	copyButton: '.copy-btn'
}

describe("TextInput", () => {
	describe("messages", () => {
		it('should show error message if provided', () => {
			const wrapper = getWrapper({
				hintText: null,
				errorMessage: 'some error message'
			})
			expect(wrapper).toMatchSnapshot()
		})
		it('should show hint text if provided', () => {
			const wrapper = getWrapper({
				hintText: "some hint message",
				errorMessage: null
			})
			expect(wrapper).toMatchSnapshot()
		})
		it('should show error message if both error message and hint text are provided', () => {
			const wrapper = getWrapper({
				hintText: "some hint message",
				errorMessage: 'some error message'
			})
			expect(wrapper).toMatchSnapshot()
		})
	})
	describe("is required prop", () => {
		it("should add asterik to the label text", () => {
			const wrapper = getWrapper({
				isRequired: true
			})
			expect(wrapper.find(selector.textInputLabel)).toMatchSnapshot()
		})
		it("should not add asterik to the label text", () => {
			const wrapper = getWrapper({
				isRequired: false
			})
			expect(wrapper.find(selector.textInputLabel)).toMatchSnapshot()
		})
	})
	describe("with copy button prop", () => {
		let wrapper
		beforeEach(() => {
			wrapper = getWrapper({
				withCopyBtn: true
			})
		})
		it("should render copy button if set", () => {
			expect(wrapper).toMatchSnapshot()
		})
		it("should be disabled if the input value is empty", () => {
			expect(wrapper.find(selector.copyButton).attributes().disabled).toBe('disabled')
		})
		it("should be enabled if the input value is non empty", async () => {
			wrapper = getWrapper({
				withCopyBtn: true,
				value: 'some-value'
			})
			expect(wrapper.find(selector.copyButton).attributes().disabled).toBe(undefined)
		})
		it("should copy the input value on click", async () => {
			const spyWriteToClipboard = jest.spyOn(navigator.clipboard, 'writeText')
				.mockImplementationOnce(() => jest.fn())
			wrapper = getWrapper({
				withCopyBtn: true,
				value: 'some-value-to-copy'
			})
			await wrapper.find(selector.copyButton).trigger("click")
			expect(spyWriteToClipboard).toBeCalledTimes(1)
			expect(spyWriteToClipboard).toBeCalledWith('some-value-to-copy')
		})
	})
})

function getWrapper(props = {}) {
	return mount(TextInput, {
		localVue,
		propsData: {
			value: null,
			id: 'unique-id',
			label: 'some label',
			...props,
		},
		mocks: {
			t: (app, msg) => msg,
		},
	})
}
