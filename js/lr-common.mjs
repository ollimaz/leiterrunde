/* SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export function l10n(appId) {
	return {
		t: (text, vars) => window.t(appId, text, vars, undefined, { escape: false, sanitize: false }),
		n: (one, many, count, vars) => window.n(appId, one, many, count, vars, { escape: false, sanitize: false }),
	};
}
export function lrLocale() {
	return (OC.getLocale?.() || OC.getLanguage?.() || 'en').replace('_', '-');
}
const { t: _t, n: _n } = l10n('leiterrunde');

export function appUrl(path) {
	if (path.startsWith('#') || /^https?:\/\//.test(path)) return path;
	return OC.generateUrl(path);
}

export function h(tag, attrs = {}, ...children) {
	const el = document.createElement(tag);
	for (const [k, v] of Object.entries(attrs || {})) {
		if (v === null || v === undefined || v === false) continue;
		if (k === 'class') el.className = v;
		else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
		else el.setAttribute(k, v === true ? '' : v);
	}
	for (const c of children.flat()) {
		if (c === null || c === undefined || c === false) continue;
		el.append(c.nodeType ? c : document.createTextNode(String(c)));
	}
	return el;
}

const BASE = OC.generateUrl('/apps/');

export function api(appId, path, opts = {}) {
	const headers = { 'requesttoken': OC.requestToken || document.head?.dataset?.requesttoken || '' };
	if (opts.json !== undefined) {
		headers['Content-Type'] = 'application/json';
		opts.body = JSON.stringify(opts.json);
		delete opts.json;
	}
	return fetch(BASE + appId + '/api' + path, { headers, ...opts }).then(async (res) => {
		const ct = res.headers.get('content-type') || '';
		const data = ct.includes('json') ? await res.json() : await res.text();
		if (!res.ok) throw new Error((data && data.error) || (_t('Error ') + res.status));
		return data;
	});
}

export const BADGE_LABELS = { ok: _t('OK'), warn: _t('Expiring'), fail: _t('Expired'), missing: _t('Missing'), info: _t('Info'), pending: _t('To review'), approved: _t('Approved'), rejected: _t('Rejected'), paid: _t('Paid'), open: _t('Open'), partial: _t('Partial'), overdue: _t('Overdue'), cancelled: _t('Cancelled'), draft: _t('Draft'), published: _t('Published'), waived: _t('Waived'), aktiv: _t('Active'), nwl: _t('NWL'), inaktiv: _t('Inactive'), ehemalig: _t('Former') };
export function badge(state, variant) {
	if (variant === 'overall') {
		const m = { ok: _t('Cleared'), warn: _t('Expiring'), fail: _t('Not cleared'), missing: _t('Missing') };
		return h('span', { class: 'lr-badge ' + state }, (m[state] ? _t(m[state]) : (BADGE_LABELS[state] || _t(state))));
	}
	return h('span', { class: 'lr-badge ' + state }, (BADGE_LABELS[state] || _t(state)));
}

export function toast(msg, isError = true) {
	const T = window.OCP?.Toast;
	if (T) {
		(isError ? T.error : T.success).call(T, String(msg));
		return;
	}
	let t = document.getElementById('lr-toast');
	if (!t) {
		t = h('div', { id: 'lr-toast', class: 'lr-toast' });
		document.body.prepend(t);
	}
	t.textContent = msg;
	t.className = 'lr-toast ' + (isError ? 'error' : 'ok');
	t.style.display = 'block';
	clearTimeout(t._timer);
	t._timer = setTimeout(() => { t.style.display = 'none'; }, 5000);
}

export function formatDate(d) {
	return d ? new Date(d + (String(d).length === 10 ? 'T00:00:00' : '')).toLocaleDateString(lrLocale(), { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';
}

export function formatMoney(v) {
	return new Intl.NumberFormat(lrLocale(), { style: 'currency', currency: 'EUR' }).format(Number(v)).replace('-', '−');
}

let _me = null;
export async function me() {
	if (_me === null) {
		_me = await api('leiterrunde', '/me');
	}
	return _me;
}

const ICONS = {
	'menu': 'M3,6H21V8H3V6M3,11H21V13H3V11M3,16H21V18H3V16Z',
	'close': 'M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z',
	'check': 'M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z',
	'plus': 'M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z',
	'pencil': 'M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z',
	'trash': 'M9,3V4H4V6H5V19A2,2 0 0,0 7,21H17A2,2 0 0,0 19,19V6H20V4H15V3H9M7,6H17V19H7V6M9,8V17H11V8H9M13,8V17H15V8H13Z',
	'magnify': 'M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z',
	'arrow-left': 'M20,11V13H8L13.5,18.5L12.08,19.92L4.16,12L12.08,4.08L13.5,5.5L8,11H20Z',
	'dots-vertical': 'M12,16A2,2 0 0,1 14,18A2,2 0 0,1 12,20A2,2 0 0,1 10,18A2,2 0 0,1 12,16M12,10A2,2 0 0,1 14,12A2,2 0 0,1 12,14A2,2 0 0,1 10,12A2,2 0 0,1 12,10M12,4A2,2 0 0,1 14,6A2,2 0 0,1 12,8A2,2 0 0,1 10,6A2,2 0 0,1 12,4Z',
	'file': 'M13,9V3.5L18.5,9M6,2C4.89,2 4,2.89 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2H6Z',
	'sync': 'M12,18A6,6 0 0,1 6,12C6,11 6.25,10.03 6.7,9.2L5.24,7.74C4.46,8.97 4,10.43 4,12A8,8 0 0,0 12,20V23L16,19L12,15M12,4V1L8,5L12,9V6A6,6 0 0,1 18,12C18,13 17.75,13.97 17.3,14.8L18.76,16.26C19.54,15.03 20,13.57 20,12A8,8 0 0,0 12,4Z',
	'open-in-new': 'M14,3V5H17.59L7.76,14.83L9.17,16.24L19,6.41V10H21V3M19,19H5V5H12V3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V12H19V19Z',
	'upload': 'M9,16V10H5L12,3L19,10H15V16M5,20V18H19V20H5Z',
	'download': 'M5,20H19V18H5M19,9H15V3H9V9H5L12,16L19,9Z',
	'import': 'M14,12L10,8V11H2V13H10V16M20,18V6C20,4.89 19.1,4 18,4H6A2,2 0 0,0 4,6V9H6V6H18V18H6V15H4V18A2,2 0 0,0 6,20H18A2,2 0 0,0 20,18Z',
	'dashboard': 'M13,3V9H21V3M13,3V9H21V3M13,21H21V11H13M13,21H21V11H13M3,21H11V15H3M3,21H11V15H3M3,13H11V3H3M3,13H11V3H3V13Z',
	'people': 'M16,13C15.71,13 15.38,13 15.03,13.05C16.19,13.89 17,15.02 17,16.5V19H23V16.5C23,14.17 18.33,13 16,13M8,13C5.67,13 1,14.17 1,16.5V19H15V16.5C15,14.17 10.33,13 8,13M8,11A3,3 0 0,0 11,8A3,3 0 0,0 8,5A3,3 0 0,0 5,8A3,3 0 0,0 8,11M16,11A3,3 0 0,0 19,8A3,3 0 0,0 16,5A3,3 0 0,0 13,8A3,3 0 0,0 16,11Z',
	'account': 'M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z',
	'gift': 'M22,12V20A2,2 0 0,1 20,22H4A2,2 0 0,1 2,20V12A1,1 0 0,1 1,11V8A2,2 0 0,1 3,6H6.17C6.06,5.69 6,5.35 6,5A3,3 0 0,1 9,2C10,2 10.88,2.5 11.43,3.24V3.23L12,4L12.57,3.23V3.24C13.12,2.5 14,2 15,2A3,3 0 0,1 18,5C18,5.35 17.94,5.69 17.83,6H21A2,2 0 0,1 23,8V11A1,1 0 0,1 22,12M4,20H11V12H4V20M20,20V12H13V20H20M9,4A1,1 0 0,0 8,5A1,1 0 0,0 9,6A1,1 0 0,0 10,5A1,1 0 0,0 9,4M15,4A1,1 0 0,0 14,5A1,1 0 0,0 15,6A1,1 0 0,0 16,5A1,1 0 0,0 15,4M3,8V10H11V8H3M13,8V10H21V8H13Z',
	'school': 'M12,3L1,9L5,11.18V17.18L12,21L19,17.18V11.18L21,10.09V17H23V9L12,3M18.82,9L12,12.72L5.18,9L12,5.28L18.82,9M17,16L12,18.72L7,16V12.27L12,15L17,12.27V16Z',
	'shield-check': 'M10,17L6,13L7.41,11.59L10,14.17L16.59,7.58L18,9L10,17M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1Z',
	'clipboard': 'M17,9H7V7H17M17,13H7V11H17M14,17H7V15H14M12,3A1,1 0 0,1 13,4A1,1 0 0,1 12,5A1,1 0 0,1 11,4A1,1 0 0,1 12,3M19,3H14.82C14.4,1.84 13.3,1 12,1C10.7,1 9.6,1.84 9.18,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3Z',
	'list': 'M7,5H21V7H7V5M7,13V11H21V13H7M4,4.5A1.5,1.5 0 0,1 5.5,6A1.5,1.5 0 0,1 4,7.5A1.5,1.5 0 0,1 2.5,6A1.5,1.5 0 0,1 4,4.5M4,10.5A1.5,1.5 0 0,1 5.5,12A1.5,1.5 0 0,1 4,13.5A1.5,1.5 0 0,1 2.5,12A1.5,1.5 0 0,1 4,10.5M7,19V17H21V19H7M4,16.5A1.5,1.5 0 0,1 5.5,18A1.5,1.5 0 0,1 4,19.5A1.5,1.5 0 0,1 2.5,18A1.5,1.5 0 0,1 4,16.5Z',
	'swap': 'M21,9L17,5V8H10V10H17V13M7,11L3,15L7,19V16H14V14H7V11Z',
	'clock': 'M12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22C6.47,22 2,17.5 2,12A10,10 0 0,1 12,2M12.5,7V12.25L17,14.92L16.25,16.15L11,13V7H12.5Z',
	'receipt': 'M3,22L4.5,20.5L6,22L7.5,20.5L9,22L10.5,20.5L12,22L13.5,20.5L15,22L16.5,20.5L18,22L19.5,20.5L21,22V2L19.5,3.5L18,2L16.5,3.5L15,2L13.5,3.5L12,2L10.5,3.5L9,2L7.5,3.5L6,2L4.5,3.5L3,2M18,9H6V7H18M18,13H6V11H18M18,17H6V15H18V17Z',
	'bank': 'M11.5,1L2,6V8H21V6M16,10V17H19V10M2,22H21V19H2M10,10V17H13V10M5,10V17H8V10H5Z',
	'calendar': 'M19,19H5V8H19M16,1V3H8V1H6V3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3H18V1M17,12H12V17H17V12Z',
	'alert': 'M13,13H11V7H13M13,17H11V15H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z',
	'phone': 'M6.62,10.79C8.06,13.62 10.38,15.94 13.21,17.38L15.41,15.18C15.69,14.9 16.08,14.82 16.43,14.93C17.55,15.3 18.75,15.5 20,15.5A1,1 0 0,1 21,16.5V20A1,1 0 0,1 20,21A17,17 0 0,1 3,4A1,1 0 0,1 4,3H7.5A1,1 0 0,1 8.5,4C8.5,5.25 8.7,6.45 9.07,7.57C9.18,7.92 9.1,8.31 8.82,8.59L6.62,10.79Z',
	'whatsapp': 'M12.04,2C6.58,2 2.13,6.45 2.13,11.91C2.13,13.66 2.59,15.36 3.45,16.86L2.05,22L7.3,20.62C8.75,21.41 10.38,21.83 12.04,21.83C17.5,21.83 21.95,17.38 21.95,11.92C21.95,9.27 20.92,6.78 19.05,4.91C17.18,3.03 14.69,2 12.04,2M12.05,3.67C14.25,3.67 16.31,4.53 17.87,6.09C19.42,7.65 20.28,9.72 20.28,11.92C20.28,16.46 16.58,20.15 12.04,20.15C10.56,20.15 9.11,19.76 7.85,19L7.55,18.83L4.43,19.65L5.26,16.61L5.06,16.29C4.24,15 3.8,13.47 3.8,11.91C3.81,7.37 7.5,3.67 12.05,3.67M8.53,7.33C8.37,7.33 8.1,7.39 7.87,7.64C7.65,7.89 7,8.5 7,9.71C7,10.93 7.89,12.1 8,12.27C8.14,12.44 9.76,14.94 12.25,16C12.84,16.27 13.3,16.42 13.66,16.53C14.25,16.72 14.79,16.69 15.22,16.63C15.7,16.56 16.68,16.03 16.89,15.45C17.1,14.87 17.1,14.38 17.04,14.27C16.97,14.17 16.81,14.11 16.56,14C16.31,13.86 15.09,13.26 14.87,13.18C14.64,13.1 14.5,13.06 14.31,13.3C14.15,13.55 13.67,14.11 13.53,14.27C13.38,14.44 13.24,14.46 13,14.34C12.74,14.21 11.94,13.95 11,13.11C10.26,12.45 9.77,11.64 9.62,11.39C9.5,11.15 9.61,11 9.73,10.89C9.84,10.78 10,10.6 10.1,10.45C10.23,10.31 10.27,10.2 10.35,10.04C10.43,9.87 10.39,9.73 10.33,9.61C10.27,9.5 9.77,8.26 9.56,7.77C9.36,7.29 9.16,7.35 9,7.34C8.86,7.34 8.7,7.33 8.53,7.33Z',
	'map-marker': 'M12,11.5A2.5,2.5 0 0,1 9.5,9A2.5,2.5 0 0,1 12,6.5A2.5,2.5 0 0,1 14.5,9A2.5,2.5 0 0,1 12,11.5M12,2A7,7 0 0,0 5,9C5,14.25 12,22 12,22C12,22 19,14.25 19,9A7,7 0 0,0 12,2Z',
	'email': 'M20,8L12,13L4,8V6L12,11L20,6M20,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V6C22,4.89 21.1,4 20,4Z',
	'cellphone': 'M17,19H7V5H17M17,1H7C5.89,1 5,1.89 5,3V21A2,2 0 0,0 7,23H17A2,2 0 0,0 19,21V3C19,1.89 18.1,1 17,1Z',
};

export function icon(name) {
	const el = h('span', { class: 'lr-ico', 'aria-hidden': 'true' });
	if (ICONS[name]) el.innerHTML = `<svg viewBox="0 0 24 24"><path d="${ICONS[name]}"/></svg>`;
	return el;
}

export function skeleton(lines = 3) {
	return h('div', { class: 'lr-skel', 'aria-hidden': 'true' },
		h('div', { class: 'lr-skel-line lr-skel-title' }),
		...Array.from({ length: lines }, () => h('div', { class: 'lr-skel-line' })));
}

export function emptyState(text) {
	return h('p', { class: 'lr-empty' }, text);
}

/** Hamburger toggle + backdrop for the off-canvas app navigation on small screens. */
export function setupMobileNav(root) {
	if (!root.querySelector('#app-navigation-toggle')) {
		root.prepend(h('div', { id: 'app-navigation-toggle', role: 'button', tabindex: '0', 'aria-label': _t('Navigation'), title: _t('Navigation') }, icon('menu')));
	}
	if (!root.querySelector('.lr-nav-backdrop')) {
		root.append(h('div', { class: 'lr-nav-backdrop' }));
	}
	const toggleNav = () => root.classList.toggle('lr-nav-open');
	const closeNav = () => root.classList.remove('lr-nav-open');
	const toggle = root.querySelector('#app-navigation-toggle');
	toggle.addEventListener('click', toggleNav);
	toggle.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleNav(); } });
	root.querySelector('.lr-nav-backdrop').addEventListener('click', closeNav);
	window.addEventListener('hashchange', closeNav);
}

/**
 * mountApp({ appId, title, nav: [{route,label,cap,section,icon}], routes: {hash: fn}, defaultRoute })
 * Rendert NC-Layout: #app-navigation links, #app-content rechts.
 */

/* ---- combobox ---- */
export function combobox({ search, value = null, placeholder = '', onChange, minChars = 1 }) {
	const wrap = h('div', { class: 'lr-combo' });
	const input = h('input', {
		type: 'text', class: 'lr-input', placeholder,
		role: 'combobox', 'aria-expanded': 'false', 'aria-controls': 'lr-cb-list', autocomplete: 'off',
	});
	let box = null, items = [], active = -1, timer = null, ctrl = null;

	function close() {
		if (box) box.remove();
		box = null; active = -1;
		input.setAttribute('aria-expanded', 'false');
	}
	function statusRow(txt) {
		box.replaceChildren(h('div', { class: 'lr-combo-status' }, txt));
	}
	function open(rows) {
		if (!box) {
			box = h('div', { class: 'lr-combo-list', role: 'listbox' });
			wrap.append(box);
		}
		box.replaceChildren(...rows);
		input.setAttribute('aria-expanded', 'true');
	}
	async function run(q) {
		ctrl?.abort(); ctrl = new AbortController();
		open([]); statusRow(_t ? _t('Searching …') : 'Searching …');
		try {
			const res = await search(q, ctrl.signal);
			if (ctrl.signal.aborted) return;
			items = res || [];
			if (!items.length) { open([h('div', { class: 'lr-combo-status' }, _t('No results'))]); return; }
			open(items.map((it, i) => {
				const o = h('div', {
					class: 'lr-combo-option' + (it.disabled ? ' disabled' : ''),
					role: 'option', 'aria-disabled': it.disabled ? 'true' : 'false',
					onmousedown: (e) => { e.preventDefault(); if (!it.disabled) select(it); },
					onmousemove: () => setActive(i),
				});
				if (it.avatarUid) o.append(h('img', { class: 'lr-combo-avatar', src: OC.generateUrl('/avatar/' + it.avatarUid + '/32') }));
				const t = h('span', {}, it.label);
				o.append(t);
				if (it.sublabel || it.note) o.append(h('span', { class: 'lr-combo-sub' }, it.note || it.sublabel));
				return o;
			}));
			active = -1;
		} catch (e) {
			if (ctrl.signal.aborted) return;
			open([h('div', { class: 'lr-combo-status' }, _t('Search failed'))]);
		}
	}
	function setActive(i) {
		active = i;
		box?.querySelectorAll('.lr-combo-option').forEach((o, j) => o.classList.toggle('active', j === i));
	}
	function showChip(item) {
		wrap.querySelector('.lr-combo-chip')?.remove();
		if (!item) return;
		const chip = h('div', { class: 'lr-combo-chip', onclick: () => { chip.remove(); input.style.display = ''; input.focus(); } });
		if (item.avatarUid) chip.append(h('img', { class: 'lr-combo-avatar', src: OC.generateUrl('/avatar/' + item.avatarUid + '/32') }));
		chip.append(h('span', {}, item.label));
		if (item.sublabel) chip.append(h('span', { class: 'lr-combo-sub' }, item.sublabel));
		chip.append(h('button', { type: 'button', class: 'lr-combo-x', 'aria-label': 'clear', onclick: (e) => { e.stopPropagation(); select(null); } }, '×'));
		wrap.prepend(chip);
	}
	function select(it) {
		close();
		if (it) { input.style.display = 'none'; input.value = ''; } else { input.style.display = ''; }
		showChip(it);
		onChange?.(it);
	}
	input.addEventListener('input', () => {
		clearTimeout(timer);
		const q = input.value.trim();
		if (q.length < minChars) { close(); return; }
		timer = setTimeout(() => run(q), 200);
	});
	input.addEventListener('keydown', (e) => {
		if (!box) return;
		if (e.key === 'ArrowDown') { e.preventDefault(); setActive(Math.min(active + 1, items.length - 1)); }
		else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(Math.max(active - 1, 0)); }
		else if (e.key === 'Enter') { e.preventDefault(); const it = items[active >= 0 ? active : 0]; if (it && !it.disabled) select(it); }
		else if (e.key === 'Escape') { close(); }
	});
	document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) close(); });
	wrap.append(input);
	if (value) { input.style.display = 'none'; showChip(value); }
	return wrap;
}

export function userPicker({ value = null, label = null, onChange }) {
	const linked = (v) => (p, uid) => p?.nc_user_id === uid;
	return combobox({
		value: value ? { id: value, label: label || value, avatarUid: value } : null,
		placeholder: _t('Search users …'),
		onChange: (it) => onChange?.(it ? it.id : null),
		search: async (q, signal) => {
			const r = await api('leiterrunde', '/users?search=' + encodeURIComponent(q) + '&limit=10', { signal });
			return r.map(u => ({
				id: u.id, label: u.display_name || u.id, sublabel: u.email || undefined,
				avatarUid: u.id,
				disabled: !!(u.linked_person && u.linked_person.id !== valuePersonId),
				note: u.linked_person && u.linked_person.id !== valuePersonId ? _t('linked to {name}', { name: u.linked_person.name }) : undefined,
			}));
		},
	});
}
let valuePersonId = null;
export function personPicker({ persons, value = null, onChange }) {
	const norm = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
	return combobox({
		value: value ? (() => { const p = persons.find(x => x.id === value); return p ? { id: p.id, label: p.last_name + ', ' + p.first_name, sublabel: p.picker_sublabel || p.email || undefined } : null; })() : null,
		placeholder: _t('Search persons …'),
		onChange: (it) => onChange?.(it ? it.id : null),
		search: async (q) => {
			const n = norm(q);
			return persons.filter(p => norm(p.first_name + ' ' + p.last_name).includes(n) || norm(p.email).includes(n))
				.map(p => ({ id: p.id, label: p.last_name + ', ' + p.first_name, sublabel: p.picker_sublabel || p.email || undefined }));
		},
	});
}

export function mountApp({ appId, title, nav, routes, defaultRoute, navigationHeader }) {
	const mePromise = me();
	let root = document.getElementById('leiterrunde-app');
	if (!root) {
		root = h('div', { id: 'leiterrunde-app' });
		(document.getElementById('content') || document.body).append(root);
	}
	root.classList.add('lr');
	if (!document.getElementById('lr-nav')) {
		root.textContent = '';
		root.append(
			h('div', { id: 'app-navigation', class: 'lr-nav' }, h('div', { class: 'lr-navtitle' }, h('div', { class: 'lr-overline' }, _t('Leiterrunde')), h('div', { class: 'lr-modname' }, title)), h('ul', { id: 'lr-nav' })),
			h('div', { id: 'app-content' },
				h('div', { id: 'lr-toast', class: 'lr-toast', style: 'display:none' }),
				h('div', { id: 'lr-view' })));
	}
	setupMobileNav(root);

	function renderNav(caps) {
		const navEl = document.getElementById('lr-nav');
		navEl.textContent = '';
		let lastSection = null;
		for (const item of nav) {
			if (item.cap && !(caps || {})[item.cap]) continue;
			if (item.section && item.section !== lastSection) {
				lastSection = item.section;
				navEl.append(h('li', { class: 'lr-nav-section' }, item.section));
			}
			const cur = location.hash || (nav[0] ? nav[0].route : '');
			navEl.append(h('li', {}, h('a', {
				href: item.route,
				class: (cur === item.route || cur.startsWith(item.route + '/')) ? 'active' : '',
			}, item.icon ? icon(item.icon) : null, h('span', { class: 'lr-nav-label' }, item.label))));
		}
	}

	async function route() {
		const ME = await mePromise;
		if (navigationHeader) {
			let header = document.getElementById('lr-navigation-header');
			if (!header) {
				header = h('div', { id: 'lr-navigation-header', class: 'lr-navtitle' });
				document.getElementById('lr-nav').before(header);
			}
			put(header, await navigationHeader(ME, { api: (p, o) => api(appId, p, o), refresh: route }));
		}
		renderNav(ME.caps);
		const allowed = nav.filter((i) => !i.cap || (ME.caps || {})[i.cap]);
		const navItem = nav.find((i) => i.route === defaultRoute);
		const start = (navItem && !allowed.includes(navItem) && allowed[0]) ? allowed[0].route : defaultRoute;
		if (!location.hash) history.replaceState(null, '', start);
		const hash = location.hash || start;
		const target = routes[hash] || routes[hash.split('/').slice(0, -1).join('/')] || routes[defaultRoute];
		const v = document.getElementById('lr-view');
		v.textContent = '';
		v.append(skeleton());
		try {
			await target(v, ME, { h, api: (p, o) => api(appId, p, o), coreApi: (p, o) => api('leiterrunde', p, o), badge, toast, formatDate, formatMoney });
		} catch (e) {
			toast(e.message);
		} finally {
			v.querySelector('.lr-skel')?.remove();
		}
	}

	window.addEventListener('hashchange', route);
	return route();
}

/** Append children, skipping null/undefined/false. */
export function put(parent, ...children) {
	parent.replaceChildren(...children.filter((c) => c !== null && c !== undefined && c !== false));
	enhanceTables(parent);
	return parent;
}

/* ---- responsive tables ----------------------------------------------------
   Every .lr-table gets wrapped in a scrollable .lr-tablewrap and each cell
   is stamped with its column header as data-label. When the table's natural
   width exceeds the wrapper, .lr-cards switches it to a stacked card layout
   (see leiterrunde.css). Tables marked .lr-matrix keep their column layout
   and scroll horizontally instead. A ResizeObserver re-evaluates on size
   changes; a MutationObserver catches tables added or re-rendered later. */

const _lrTableWraps = new Set();
const _lrTableRO = typeof ResizeObserver !== 'undefined'
	? new ResizeObserver((entries) => { for (const e of entries) _fitTable(e.target._lrWrap || e.target); })
	: null;

function _fitTable(wrap) {
	const table = wrap._lrTable;
	if (!wrap.isConnected || !table) {
		if (table) _lrTableRO?.unobserve(table);
		_lrTableRO?.unobserve(wrap);
		_lrTableWraps.delete(wrap);
		return;
	}
	const cards = wrap.classList.contains('lr-cards');
	if (cards) wrap.classList.remove('lr-cards');
	if (table.classList.contains('lr-matrix') || wrap.classList.contains('lr-matrix')) return;
	const need = Math.max(table.offsetWidth, table.scrollWidth);
	if (need > wrap.clientWidth + 1) wrap.classList.add('lr-cards');
}

function _enhanceTable(table) {
	let wrap = table._lrWrap;
	if (wrap && !wrap.contains(table)) wrap = table._lrWrap = null;
	if (!wrap) {
		wrap = table.closest('.lr-tablewrap');
		if (!wrap) {
			wrap = h('div', { class: 'lr-tablewrap' });
			table.replaceWith(wrap);
			wrap.append(table);
			for (const p of ['Top', 'Right', 'Bottom', 'Left']) {
				const m = table.style['margin' + p];
				if (m) { wrap.style['margin' + p] = m; table.style['margin' + p] = ''; }
			}
		}
		wrap._lrTable = table;
		table._lrWrap = wrap;
		_lrTableWraps.add(wrap);
		_lrTableRO?.observe(wrap);
		_lrTableRO?.observe(table);
	}
	const heads = [...table.querySelectorAll(':scope > thead th')].map(th => th.textContent.trim());
	for (const tr of table.querySelectorAll(':scope > tbody > tr')) {
		const cells = [...tr.children];
		const loneSpan = cells.length === 1 ? (+cells[0].getAttribute('colspan') || 1) : 0;
		if (cells.some(c => c.tagName === 'TH') || (heads.length && loneSpan >= heads.length)) {
			tr.classList.add('lr-rowhead');
			continue;
		}
		tr.classList.remove('lr-rowhead');
		let col = 0;
		for (const td of cells) {
			td.setAttribute('data-label', heads[col] || '');
			col += +(td.getAttribute('colspan') || 1);
		}
	}
	if (wrap.clientWidth) _fitTable(wrap);
}

export function enhanceTables(rootEl) {
	for (const wrap of [..._lrTableWraps]) {
		if (!wrap.isConnected) {
			if (wrap._lrTable) _lrTableRO?.unobserve(wrap._lrTable);
			_lrTableRO?.unobserve(wrap);
			_lrTableWraps.delete(wrap);
		}
	}
	if (rootEl?.nodeType !== 1) return;
	const tables = rootEl.matches('table.lr-table') ? [rootEl] : rootEl.querySelectorAll('table.lr-table');
	for (const t of tables) _enhanceTable(t);
}

let _lrTableMO = null;
function _initResponsiveTables() {
	if (_lrTableMO || typeof MutationObserver === 'undefined') return;
	enhanceTables(document.documentElement);
	_lrTableMO = new MutationObserver((records) => {
		for (const r of records) for (const n of r.addedNodes) {
			if (n.nodeType !== 1) continue;
			const t = n.closest('table.lr-table');
			if (t) _enhanceTable(t); else enhanceTables(n);
		}
	});
	_lrTableMO.observe(document.documentElement, { childList: true, subtree: true });
}
_initResponsiveTables();

window.LeiterrundeUI = { field, checkField, formActions, combobox, BADGE_LABELS, userPicker, personPicker, h, api, badge, toast, formatDate, formatMoney, mountApp, me, put, appUrl, lrPageHeader, lrSection, lrFigures, renderChart, l10n, lrLocale, icon, skeleton, emptyState, setupMobileNav, enhanceTables };

const css = (v) => getComputedStyle(document.querySelector('.lr') || document.documentElement).getPropertyValue(v).trim() || '#888';
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const expenseColor = () => {
	const c = css('--color-element-error');
	const m = c.match(/^#([0-9a-f]{6})$/i);
	if (m) {
		const v = parseInt(m[1], 16);
		if ((v >> 16) < 0xe0) return c;
	}
	return '#d32f2f';
};
export const STATUS_LABELS = {
	open: _t('Open'), partial: _t('Partially paid'), paid: _t('Paid'), overdue: _t('Overdue'), cancelled: _t('Cancelled'),
	aktiv: _t('Active'), nwl: _t('NWL'), inaktiv: _t('Inactive'), ehemalig: _t('Former'),
	ok: _t('Cleared'), warn: _t('Expiring'), fail: _t('Expired'), missing: _t('Missing'),
};
export const statusLabel = (k) => STATUS_LABELS[k] || k;
const STATE_COLOR = () => ({
	ok: css('--color-element-success'), warn: css('--color-element-warning'), fail: css('--color-element-error'),
	missing: css('--color-text-maxcontrast'), info: css('--color-element-info'),
});
const svg = (w, hgt, title, kids) => {
	const el = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
	el.setAttribute('viewBox', `0 0 ${w} ${hgt}`); el.setAttribute('width', '100%');
	const t = document.createElementNS('http://www.w3.org/2000/svg', 'title');
	t.textContent = title; el.append(t, ...kids);
	return el;
};
const sn = (tag, attrs, text) => {
	const el = document.createElementNS('http://www.w3.org/2000/svg', tag);
	for (const k in attrs) el.setAttribute(k, attrs[k]);
	if (text !== undefined) el.textContent = text;
	return el;
};

const monthShort = (i) => new Date(2020, i, 1).toLocaleDateString(lrLocale(), { month: 'short' }).replace('.','');
const STACK_COLOR = () => ({ ok: css('--color-element-success'), warn: css('--color-element-warning'), fail: 'url(#lr-hatch)', missing: css('--color-background-darker'), info: css('--color-element-info') });

function hatchDef() {
	return `<defs><pattern id="lr-hatch" patternUnits="userSpaceOnUse" width="6" height="6" patternTransform="rotate(45)"><rect width="6" height="6" fill="var(--color-main-background)"/><line x1="0" y1="0" x2="0" y2="6" stroke="var(--color-element-error)" stroke-width="2"/></pattern></defs>`;
}

export function stackBar(series, ariaLabel) {
	const colors = STACK_COLOR();
	const labelColor = { aktiv: 'ok', nwl: 'info', inaktiv: 'missing', ehemalig: 'missing', open: 'info', partial: 'warn', overdue: 'fail', paid: 'ok', cancelled: 'missing' };
	const st = (s) => (labelColor[String(s.key || s.label).toLowerCase()] || s.state);
	const col = (s) => colors[st(s)] || colors.info;
	const total = series.reduce((a, s) => a + s.value, 0);
	const wrap = document.createElement('div');
	const bar = document.createElement('div');
	bar.style.cssText = 'display:flex;height:12px;gap:3px;width:100%;';
	const svgTitle = _t('Total {n}', { n: total });
	bar.setAttribute('role', 'img');
	bar.setAttribute('aria-label', ariaLabel + ' ' + svgTitle);
	for (const s of series) {
		const seg = document.createElement('div');
		seg.style.cssText = 'height:12px;border-radius:6px;overflow:hidden;' + (total > 0 ? `flex:${s.value};min-width:0;` : 'flex:1;');
		if (st(s) === 'fail') {
			seg.innerHTML = `<svg width="100%" height="12" style="display:block"><defs><pattern id="lr-hatch" patternUnits="userSpaceOnUse" width="4" height="4" patternTransform="rotate(45)"><rect width="4" height="4" fill="var(--color-main-background)"/><line x1="0" y1="0" x2="0" y2="4" stroke="var(--color-element-error)" stroke-width="1.5"/></pattern></defs><rect width="100%" height="12" fill="url(#lr-hatch)"/></svg>`;
		} else {
			seg.style.background = col(s);
		}
		bar.append(seg);
	}
	const legend = document.createElement('div');
	legend.style.cssText = 'display:flex;flex-wrap:wrap;gap:16px;margin-top:12px;font-size:13px;';
	for (const s of series) {
		const pct = total > 0 ? Math.round(100 * s.value / total) : 0;
		const item = document.createElement('span');
		item.style.cssText = 'display:inline-flex;align-items:center;gap:6px;';
		const sw = document.createElement('span');
		sw.style.cssText = 'width:10px;height:10px;display:inline-block;border-radius:2px;';
		if (st(s) === 'fail') sw.innerHTML = `<svg width="10" height="10" style="border-radius:2px;display:block">${hatchDef()}<rect width="10" height="10" fill="url(#lr-hatch)"/></svg>`;
		else sw.style.background = col(s);
		const lab = document.createElement('span');
		lab.innerHTML = `${esc(statusLabel(s.label))} <b class="lr-figure-m" style="font-size:14px">${esc(s.value)}</b> <span class="lr-muted">${pct} %</span>`;
		item.append(sw, lab); legend.append(item);
	}
	wrap.append(bar, legend);
	return wrap;
}

export function columns(series, unit = '€') {
	const w = 640, hgt = 150, top = 8, bottom = hgt - 30, left = 56;
	const max = Math.max(1, ...series.map(s => Math.max(s.value, s.secondary || 0)));
	const wrap = document.createElement('div');
	const legend = document.createElement('div');
	legend.className = 'lr-legend';
	const totIn = series.reduce((a, s) => a + s.value, 0);
	const totOut = series.reduce((a, s) => a + (s.secondary || 0), 0);
	const fmt = (v) => v.toLocaleString(lrLocale(), { style: 'currency', currency: 'EUR' });
	legend.innerHTML = `<span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:var(--color-primary-element)"></span> ${esc(_t('Income'))}
		<span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:var(--color-text-maxcontrast);margin-left:14px"></span> ${esc(_t('Expenditure'))}
		<span style="margin-left:auto"><span class="lr-muted lr-small">${esc(_t('Balance, last 12 months'))}</span> <span class="lr-figure-m">${esc(fmt(totIn - totOut))}</span></span>`;
	const svgEl = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
	svgEl.setAttribute('viewBox', `0 0 ${w} ${hgt}`);
	svgEl.setAttribute('width', '100%');
	svgEl.innerHTML = `<title>${esc(_t('Income and expenditure, last 12 months'))}</title>`;
	const mk = (t, a, txt) => { const e = document.createElementNS('http://www.w3.org/2000/svg', t); for (const k in a) e.setAttribute(k, a[k]); if (txt !== undefined) e.textContent = txt; return e; };
	const compact = (v) => v >= 1000 ? (v / 1000).toLocaleString(lrLocale(), { maximumFractionDigits: 1 }) + ' ' + _t('k') : Math.round(v) + ' €';
	for (let i = 0; i <= 3; i++) {
		const y = bottom - i * (bottom - top) / 3;
		svgEl.append(mk('line', { x1: left, y1: y, x2: w - 4, y2: y, stroke: 'var(--color-border)', 'stroke-width': 1 }));
		svgEl.append(mk('text', { x: left - 6, y: y + 3, 'text-anchor': 'end', 'font-size': 9, fill: 'var(--color-text-maxcontrast)' }, compact(max * i / 3)));
	}
	svgEl.append(mk('line', { x1: left, y1: bottom, x2: w - 4, y2: bottom, stroke: 'var(--color-border-dark)', 'stroke-width': 1 }));
	const bw = (w - left - 8) / series.length;
	const today = new Date();
	series.forEach((s, i) => {
		const x = left + i * bw;
		const hi = s.value / max * (bottom - top), ho = (s.secondary || 0) / max * (bottom - top);
		const [mm, yyRaw] = String(s.label).split('/');
		const isNow = (+mm === today.getMonth() + 1 && ('20' + yyRaw) === String(today.getFullYear()));
		const r1 = mk('rect', { x: x + 1, y: bottom - hi, width: bw / 2 - 1, height: hi, rx: 2, fill: 'var(--color-primary-element)' });
		const r2 = mk('rect', { x: x + bw / 2, y: bottom - ho, width: bw / 2 - 1, height: ho, rx: 2, fill: 'var(--color-text-maxcontrast)' });
		const tip = `${monthShort((+mm || 1) - 1)} ${yyRaw}: ${_t('Income')} ${fmt(s.value)}, ${_t('Expenditure')} ${fmt(s.secondary || 0)}`;
		r1.append(mk('title', {}, tip)); r2.append(mk('title', {}, tip));
		svgEl.append(r1, r2);
		if (isNow) svgEl.append(mk('rect', { x: x, y: bottom + 4, width: bw - 1, height: 3, rx: 1.5, fill: 'var(--color-primary-element)' }));
		if (bw >= 28 || i % 2 === 0) {
			svgEl.append(mk('text', { x: x + bw / 2, y: hgt - 16, 'text-anchor': 'middle', 'font-size': 9, fill: 'var(--lr-muted)' },
				monthShort((+mm || 1) - 1)));
			if (+mm === 1 || i === 0) svgEl.append(mk('text', { x: x + bw / 2, y: hgt - 5, 'text-anchor': 'middle', 'font-size': 8, fill: 'var(--lr-muted)' }, '20' + yyRaw));
		}
	});
	wrap.append(legend, svgEl);
	return wrap;
}

export function meters(rows) {
	const wrap = document.createElement('div');
	for (const r of rows) {
		const row = document.createElement('div');
		row.style.cssText = 'margin:0 0 18px;';
		const head = document.createElement('div');
		head.style.cssText = 'display:flex;justify-content:space-between;font-size:15px;';
		head.innerHTML = `<span>${esc(r.label)}</span><b class="lr-figure-m" style="font-size:15px">${esc(r.value)}${r.secondary ? ' / ' + esc(r.secondary) : ''}</b>`;
		const track = document.createElement('div');
		track.style.cssText = 'height:6px;border-radius:3px;background:var(--color-background-dark);margin-top:6px;';
		const cap = r.secondary || r.value || 1;
		const pct = Math.min(1, r.value / cap);
		const fill = document.createElement('div');
		fill.style.cssText = `height:6px;border-radius:3px;width:${pct * 100}%;background:${pct >= 0.9 ? 'var(--color-element-success)' : 'var(--color-primary-element)'};`;
		track.append(fill);
		row.append(head, track);
		wrap.append(row);
	}
	return wrap;
}

export function renderChart(chart) {
	if (!chart) return null;
	if (chart.type === 'stack' || chart.type === 'donut') return stackBar(chart.series || [], chart.title || '');
	if (chart.type === 'columns' || chart.type === 'bar') return columns(chart.series || [], chart.unit);
	if (chart.type === 'meters' || chart.type === 'progress') return meters(chart.series || []);
	return null;
}

export function lrPageHeader({ kicker, title, lede, actions }) {
	const right = h('div', { class: 'lr-pagehead-actions' }, ...(actions || []));
	return h('div', { class: 'lr-pagehead' },
		h('div', {}, h('div', { class: 'lr-overline' }, kicker), h('h1', { class: 'lr-display' }, title),
			lede ? h('p', { class: 'lr-lede' }, lede) : null),
		right);
}

/** One labelled cell of a 12-column form grid. */
export function field(label, control, cols = 6, help = null) {
	return h('div', { class: 'lr-f col-' + cols }, h('label', { class: 'lr-flabel' }, label), control,
		help ? h('p', { class: 'lr-small lr-muted' }, help) : null);
}

export function checkField(label, input) {
	return h('label', { class: 'lr-check' }, input, label);
}

export function formActions(...buttons) {
	return h('div', { class: 'lr-form-actions' }, ...buttons);
}

export function lrSection({ kicker, title, span = 12, aside }) {
	const el = h('div', { class: 'lr-section', style: `grid-column: span ${span};` },
		h('div', { class: 'lr-overline' }, kicker),
		title ? h('div', { class: 'lr-section-title' }, title) : null);
	if (aside) el.append(aside);
	return el;
}

export function lrFigures(figs) {
	return h('div', { class: 'lr-figures' }, ...figs.map(f =>
		h('div', { class: 'lr-figure' + (f.state === 'warn' ? ' lr-fig-warn' : '') },
			h('div', { class: 'lr-overline' }, f.label),
			h('div', { class: 'lr-figure-xl' }, f.value, f.state === 'fail' ? ' ' : '', f.state === 'fail' ? h('span', { class: 'lr-tag lr-tag-fail' }, f.tag || _t('Overdue')) : null),
			f.note ? h('div', { class: 'lr-fig-note' }, f.note) : null)));
}
