(({ blocks, data, element, blockEditor, i18n }) => {
	const el = element.createElement;
	const label = window.silvertrailBlocks?.label || i18n.__('Privacy settings', 'silvertrail');
	const navigationLink = 'silvertrail/navigation-link';
	const icon = el(
		'svg',
		{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '2 2 28 28', fill: 'currentColor', 'aria-hidden': true, focusable: false },
		el('rect', { x: 4, y: 13, width: 11, height: 6, rx: 3 }),
		el('rect', { x: 17, y: 13, width: 11, height: 6, rx: 3 }),
	);

	const link = (wrapper, className) =>
		function edit({ attributes, setAttributes }) {
			return el(
				wrapper,
				blockEditor.useBlockProps({ className }),
				el(blockEditor.RichText, {
					tagName: 'a',
					className: className ? 'wp-block-navigation-item__content' : undefined,
					value: attributes.text ?? label,
					allowedFormats: [],
					placeholder: label,
					onChange: (text) => setAttributes({ text }),
				}),
			);
		};

	blocks.registerBlockType('silvertrail/reopen-link', { icon, example: {}, edit: link('p') });
	blocks.registerBlockType(navigationLink, { icon, example: {}, edit: link('div', 'wp-block-navigation-item') });

	// Navigation pins Page link and Custom Link at the top of its Add block list. This pins the privacy link after them.
	data.subscribe(() => {
		const editor = data.select('core/block-editor');
		editor.getBlocksByName('core/navigation').forEach((clientId) => {
			const settings = editor.getBlockListSettings(clientId);
			const pinned = settings?.prioritizedInserterBlocks;
			if (pinned && !pinned.includes(navigationLink)) {
				data.dispatch('core/block-editor').updateBlockListSettings(clientId, {
					...settings,
					prioritizedInserterBlocks: [...pinned, navigationLink],
				});
			}
		});
	}, 'core/block-editor');
})(window.wp);
