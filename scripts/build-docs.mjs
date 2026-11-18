import { mkdirSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { Marked, Renderer, TextRenderer } from 'marked';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const SITE = 'https://silvertrail.marcwieland.name';
const SOURCE = 'https://github.com/mawiswiss/silvertrail/blob/main';
const SUMMARY = 'Silvertrail is a small cookie consent plugin for WordPress. It holds back everything a site loads from other servers until the visitor agrees, and passes the choice on to Google Consent Mode and the WP Consent API. These pages are the complete reference for developers and their agents.';

const read = (path) => readFileSync(join(ROOT, path), 'utf8');
const write = (path, content) => {
	mkdirSync(dirname(join(ROOT, path)), { recursive: true });
	writeFileSync(join(ROOT, path), content);
};

const escape = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const unescape = (text) => text.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&amp;/g, '&');

const version = read('silvertrail.php').match(/^ \* Version:\s+(\S+)$/m)[1];
const template = read('scripts/docs-template.html');

const index = read('docs/README.md');
const listed = [...index.slice(index.indexOf('\n## Pages\n')).matchAll(/^- \[([^\]]+)\]\(([a-z-]+)\.md\): (.+)$/gm)].map(([, label, name, summary]) => ({ label, name, summary }));
const unlisted = readdirSync(join(ROOT, 'docs')).filter((file) => file.endsWith('.md') && file !== 'README.md' && !listed.some((page) => `${page.name}.md` === file));
if (listed.length === 0 || unlisted.length > 0) {
	throw new Error(`docs/README.md must list every page under "## Pages". Missing: ${unlisted.join(', ') || 'all'}.`);
}

const pages = [{ label: 'Overview', name: 'README', summary: 'how Silvertrail works and where each concern lives.' }, ...listed].map((page) => ({
	...page,
	file: `docs/${page.name}.md`,
	path: page.name === 'README' ? '/docs/' : `/docs/${page.name}/`,
	markdown: page.name === 'README' ? '/docs/index.md' : `/docs/${page.name}.md`,
}));
const byFile = new Map(pages.map((page) => [`${page.name}.md`, page]));

function resolve(href, format) {
	if (/^([a-z]+:|#)/i.test(href)) {
		return href;
	}
	const [target, hash = ''] = href.split('#');
	const anchor = hash ? `#${hash}` : '';
	const page = byFile.get(target);
	if (page) {
		return format === 'html' ? `${page.path}${anchor}` : `${SITE}${page.markdown}${anchor}`;
	}
	return `${SOURCE}/${new URL(target, 'https://x/docs/').pathname.slice(1)}${anchor}`;
}

function markdownFor(page) {
	return read(page.file)
		.split(/(^```[\s\S]*?^```)/m)
		.map((part) => (part.startsWith('```') ? part : part.replace(/\]\(([^)\s]+)\)/g, (match, href) => `](${resolve(href, 'md')})`)))
		.join('');
}

function slugger() {
	const seen = new Map();
	return (text) => {
		const slug = text.toLowerCase().trim().replace(/[^\p{L}\p{N}\s_-]/gu, '').replace(/\s/g, '-');
		const count = seen.get(slug) ?? 0;
		seen.set(slug, count + 1);
		return count > 0 ? `${slug}-${count}` : slug;
	};
}

function render(page) {
	const slug = slugger();
	const plain = new TextRenderer();
	const toc = [];
	let title = '';
	let section = '';

	const marked = new Marked({
		gfm: true,
		renderer: {
			heading({ tokens, depth }) {
				const html = this.parser.parseInline(tokens);
				const text = unescape(this.parser.parseInline(tokens, plain));
				if (depth === 1) {
					title = text;
					return `<h1>${html}</h1>\n`;
				}
				const id = slug(text);
				section = text;
				if (depth === 2) {
					toc.push({ id, html });
				}
				return `<h${depth} id="${id}"><a href="#${id}">${html}</a></h${depth}>\n`;
			},
			link({ href, tokens }) {
				return `<a href="${escape(resolve(href, 'html'))}">${this.parser.parseInline(tokens)}</a>`;
			},
			table(token) {
				const label = section ? `${section}, table` : 'Table';
				return `<div class="table" role="region" tabindex="0" aria-label="${escape(label)}">\n${Renderer.prototype.table.call(this, token)}</div>\n`;
			},
		},
	});

	const source = read(page.file);
	const content = marked.parse(source);
	const lead = marked.lexer(source).find((token) => token.type === 'paragraph');
	const description = lead.text.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1').replace(/[`*]/g, '').replace(/\s+/g, ' ').trim();
	return { title, content, toc, description };
}

function nav(current, toc) {
	const items = pages.map((page) => {
		if (page !== current) {
			return `\t\t\t\t<li><a href="${page.path}">${page.label}</a></li>`;
		}
		const sections = toc.map(({ id, html }) => `\t\t\t\t\t\t<li><a href="#${id}">${html}</a></li>`).join('\n');
		return `\t\t\t\t<li>\n\t\t\t\t\t<a href="${page.path}" aria-current="page">${page.label}</a>\n\t\t\t\t\t<ul class="docs-nav__toc">\n${sections}\n\t\t\t\t\t</ul>\n\t\t\t\t</li>`;
	});
	return `\t\t\t<ul class="docs-nav__pages">\n${items.join('\n')}\n\t\t\t</ul>`;
}

rmSync(join(ROOT, 'site/docs'), { recursive: true, force: true });

for (const page of pages) {
	const { title, content, toc, description } = render(page);
	const values = {
		title: escape(title),
		description: escape(description),
		canonical: `${SITE}${page.path}`,
		markdown: page.markdown,
		source: `${SOURCE}/${page.file}`,
		version,
		nav: nav(page, toc),
		content: content.trim(),
	};
	write(`site${page.path}index.html`, template.replace(/\{\{(\w+)\}\}/g, (match, key) => values[key] ?? match));
	write(`site${page.markdown}`, markdownFor(page));
}

const links = pages.map((page) => `- [${page.label}](${SITE}${page.markdown}): ${page.summary.charAt(0).toUpperCase()}${page.summary.slice(1)}`);
write('site/llms.txt', `# Silvertrail\n\n> ${SUMMARY}\n\nThe plugin ships the same pages in its folder under \`docs/\`, for the version it runs.\n\n## Docs\n\n${links.join('\n')}\n\n## Optional\n\n- [README](${SOURCE}/README.md): What Silvertrail does, how to install it and the honour licence.\n- [WordPress.org](https://wordpress.org/plugins/silvertrail/): The plugin's page and its changelog.\n`);

console.log(`Built ${pages.length} pages for Silvertrail ${version}.`);
