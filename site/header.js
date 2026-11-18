const header = document.querySelector('.site-header');
let lastY = scrollY;

addEventListener(
	'scroll',
	() => {
		// Clamped, so overscroll at either end does not flip the direction.
		const y = Math.max(0, Math.min(scrollY, document.documentElement.scrollHeight - innerHeight));
		if (y === lastY) {
			return;
		}
		const up = y < lastY;
		lastY = y;
		if (y === 0) {
			delete header.dataset.state;
		} else if (up) {
			header.dataset.state = 'shown';
		} else if (header.dataset.state === 'shown') {
			header.dataset.state = 'hidden';
		} else if (!header.dataset.state && y > header.offsetHeight) {
			// The header has already scrolled out of view, so it hides without sliding.
			header.style.transition = 'none';
			header.dataset.state = 'hidden';
			header.getBoundingClientRect();
			header.style.transition = '';
		}
	},
	{ passive: true },
);

// The browser reads the scroll padding when the jump starts, so the header takes its end state first.
document.addEventListener('click', (event) => {
	const link = event.target.closest('a[href*="#"]');
	if (!link || !header.dataset.state || link.origin !== location.origin || link.pathname !== location.pathname) {
		return;
	}
	const target = document.getElementById(decodeURIComponent(link.hash.slice(1)));
	if (target) {
		header.dataset.state = target.getBoundingClientRect().top > 0 ? 'hidden' : 'shown';
	}
});
