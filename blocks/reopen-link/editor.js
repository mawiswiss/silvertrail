(({ blocks, element, blockEditor, i18n }) => {
	const el = element.createElement;
	const icon = el(
		'svg',
		{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '2 2 28 28', fill: 'currentColor', 'aria-hidden': true, focusable: false },
		el('rect', { x: 4, y: 13, width: 11, height: 6, rx: 3 }),
		el('rect', { x: 17, y: 13, width: 11, height: 6, rx: 3 }),
	);

	blocks.registerBlockType('silvertrail/reopen-link', {
		icon,
		edit({ attributes, setAttributes }) {
			return el(
				'p',
				blockEditor.useBlockProps(),
				el(blockEditor.RichText, {
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
