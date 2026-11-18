// Loaded by silvertrail.js only in browsers that offer WebMCP.
export function register({ config, consentIds, serviceIds, has, decided, trail, reset, choose }) {
	const name = (id) => (config.services[id] ? config.services[id].name : id);

	function consentState() {
		return {
			decided: decided(),
			gpc: config.gpc && navigator.globalPrivacyControl === true,
			services: consentIds.map((id) => {
				const { group, provider, purpose } = config.services[id];
				return { id, name: name(id), group, ...(provider && { provider }), ...(purpose && { purpose }), allowed: has(id) };
			}),
			essential: config.essential.map(name),
		};
	}

	function pageTrail() {
		const { held, loaded } = trail();
		return {
			held: held.map((id) => ({ id, name: name(id) })),
			loaded: loaded.map(({ host, service }) => (service ? { host, service, allowed: has(service) } : { host })),
		};
	}

	const none = { type: 'object', properties: {} };
	const tools = [
		{
			name: 'get_cookie_consent',
			title: 'Read cookie consent',
			description:
				'Lists the services on this site that need consent, what each one is for and whether it is allowed, and whether a choice was made in this browser. Call it before allow_only_essential or set_cookie_consent, and to check the state after a choice.',
			inputSchema: none,
			annotations: { readOnlyHint: true },
			execute: async () => consentState(),
		},
		{
			name: 'allow_only_essential',
			title: 'Allow only essential services',
			description:
				'Stores the choice "only essential" in this browser and closes the cookie banner, so the page loads nothing that needs consent. Call it to get past the banner when the person has not asked for more. It needs no confirmation, because nothing extra loads.',
			inputSchema: none,
			annotations: { readOnlyHint: false, consequentialHint: false },
			execute: async () => {
				choose([], { source: 'agent' });
				return { allowed: [] };
			},
		},
		{
			name: 'set_cookie_consent',
			title: 'Set cookie consent',
			description:
				'Stores which services the person allows and closes the cookie banner. Allowed services load at once. Ask the person first and pass only what they agreed to. The list replaces the whole choice, and an empty list allows only essential services.',
			inputSchema: {
				type: 'object',
				properties: {
					services: {
						type: 'array',
						items: { type: 'string', enum: consentIds },
						description: 'Service ids from get_cookie_consent, for example ["youtube"].',
					},
				},
				required: ['services'],
			},
			annotations: { consequentialHint: true },
			execute: async ({ services }) => {
				const ids = typeof services === 'string' ? [services] : services;
				const unknown = Array.isArray(ids) ? ids.filter((id) => !serviceIds.includes(id)) : null;
				// Chrome hands an agent a thrown error without its message, so the error goes back as a result.
				if (unknown === null || unknown.length > 0) {
					const error = unknown === null ? 'Pass services as a list of service ids.' : `Unknown service ${unknown.join(', ')}.`;
					return { error, services: consentIds };
				}
				const allowed = consentIds.filter((id) => ids.includes(id));
				choose(allowed, { source: 'agent' });
				return { allowed };
			},
		},
		{
			name: 'get_page_trail',
			title: 'List what the page loaded',
			description:
				'Lists the services this page holds back until consent, and the other hosts it has loaded from so far. A host of a known service names it and says whether it is allowed. Use it to check that nothing loads before consent, and that an allowed service did load.',
			inputSchema: none,
			annotations: { readOnlyHint: true },
			execute: async () => pageTrail(),
		},
		{
			name: 'reset_cookie_consent',
			title: 'Forget the cookie choice',
			description:
				'Deletes the stored choice in this browser, so the next page load counts as a first visit and the banner opens again. Scripts that already run keep running until the page reloads. Meant for testing the site.',
			inputSchema: none,
			annotations: { readOnlyHint: false, consequentialHint: false, debugging: true },
			execute: async () => {
				reset();
				return { decided: false, next: 'Reload the page to see it as a first-time visitor.' };
			},
		},
	];

	for (const tool of tools) {
		Promise.resolve()
			.then(() => document.modelContext.registerTool(tool))
			.catch(() => {});
	}
}
