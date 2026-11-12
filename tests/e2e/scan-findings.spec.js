import { test, expect } from '@playwright/test';
import '../../assets/scan-findings.js';

const { analyze } = globalThis.silvertrailFindings;

const URL = 'http://localhost:8892/';
const STYLESHEET = { url: 'https://fonts.googleapis.com/css2?family=Fraunces', type: 'style' };
const FONT = { url: 'https://fonts.gstatic.com/s/fraunces/v38/font.woff2', type: 'style' };

const page = (windows) => ({ url: URL, first: [], declined: [], agreed: [], reloaded: [], held: [], stored: [], scripts: [], ...windows });

test.describe('leak check findings', () => {
	test('counts a font file that Resource Timing reports only after the reload under its stylesheet', () => {
		const result = analyze({ services: {}, pages: [page({ first: [STYLESHEET], reloaded: [STYLESHEET, FONT] })] });

		expect(result.guard.map((item) => item.host)).toEqual(['fonts.googleapis.com']);
		expect(result.late).toEqual([]);
		expect(result.after).toEqual([]);
		expect(result.warnings).toBe(0);
	});

	test('counts a font file contacted before a choice under its stylesheet', () => {
		const result = analyze({ services: {}, pages: [page({ first: [STYLESHEET, FONT] }), page({ url: `${URL}about/`, first: [FONT] })] });

		expect(result.guard.map((item) => [item.host, item.pages])).toEqual([['fonts.googleapis.com', [URL, `${URL}about/`]]]);
		expect(result.problems).toBe(1);
	});

	test('still guards two hosts of one provider separately when they leak at different times', () => {
		const result = analyze({ services: {}, pages: [page({ first: [STYLESHEET], declined: [FONT] })] });

		expect(result.guard.map((item) => [item.host, item.when])).toEqual([
			['fonts.googleapis.com', 'before'],
			['fonts.gstatic.com', 'declined'],
		]);
	});

	test('still reports a host of another provider that appears only after the reload', () => {
		const result = analyze({ services: {}, pages: [page({ first: [STYLESHEET], reloaded: [STYLESHEET, { url: 'https://pixel.example.test/late.js', type: 'script' }] })] });

		expect(result.late.map((item) => item.host)).toEqual(['pixel.example.test']);
	});

	test('still lists a host after agreeing when only its domain was contacted before the choice', () => {
		const result = analyze({
			services: {},
			pages: [page({ first: [{ url: 'https://slow.example.test/beacon', type: 'other' }], agreed: [{ url: 'https://unlisted.example.test/pixel.gif', type: 'image' }] })],
		});

		expect(result.after.map((item) => item.host)).toEqual(['unlisted.example.test']);
	});
});
