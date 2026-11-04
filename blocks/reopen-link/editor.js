(({ blocks, element, blockEditor, i18n }) => {
	blocks.registerBlockType('silvertrail/reopen-link', {
		edit({ attributes, setAttributes }) {
			return element.createElement(
				'p',
				blockEditor.useBlockProps(),
				element.createElement(blockEditor.RichText, {
					tagName: 'a',
					value: attributes.text,
					allowedFormats: [],
					placeholder: i18n.__('Privacy settings', 'silvertrail'),
					onChange: (text) => setAttributes({ text }),
				}),
			);
		},
	});
})(window.wp);
