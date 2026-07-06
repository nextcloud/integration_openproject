export function toMatchSerializedSnapshot(element) {
	element = element.replace(/ id="[^"]+"/g, ' id="__ID__"').replace(/ uid="[^"]+"/g, ' uid="__UID__"')
	expect(element).toMatchSnapshot()
}
