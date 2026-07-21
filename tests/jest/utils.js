/**
 * SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export function toMatchSerializedSnapshot(element) {
	element = element
		.replace(/ id="[^"]+"/g, ' id="__ID__"')
		.replace(/ uid="[^"]+"/g, ' uid="__UID__"')
		.replace(/ aria-controls="[^"]+"/g, ' aria-controls="__ID__"')
		.replace(/ aria-labelledby="[^"]+"/g, ' aria-labelledby="__ID__"')
		.replace(/ aria-activedescendant="[^"]+"/g, ' aria-activedescendant="__ID__"')
		.replace(/ aria-owns="[^"]+"/g, ' aria-owns="__ID__"')
	expect(element).toMatchSnapshot()
}
