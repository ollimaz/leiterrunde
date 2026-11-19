/* SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { renderChart, lrPageHeader, lrSection, lrFigures, badge as sharedBadge, h, appUrl, formatDate, l10n, lrLocale, BADGE_LABELS, toast, icon, skeleton, emptyState, setupMobileNav, enhanceTables, viewState, saveScroll, restoreScroll, persistForm, menuButton, listView, field, checkField, formActions } from './lr-common.mjs';
const lh = h;
const { t: _t, n: _n } = l10n('leiterrunde');
const API = OC.generateUrl('/apps/leiterrunde/api');

let ME = null;

async function modApi(appId, path, opts = {}) {
	const res = await fetch(OC.generateUrl('/apps/' + appId + '/api' + path), { headers: { 'requesttoken': OC.requestToken }, ...opts });
	const data = await res.json().catch(() => ({}));
	if (!res.ok) throw new Error(data.error || _t('Error') + ' ' + res.status);
	return data;
}

async function api(path, opts = {}) {
	const headers = { 'requesttoken': OC.requestToken || document.head?.dataset?.requesttoken || '' };
	if (opts.json !== undefined) {
		headers['Content-Type'] = 'application/json';
		opts.body = JSON.stringify(opts.json);
		delete opts.json;
	}
	const res = await fetch(API + path, { headers, ...opts });
	const ct = res.headers.get('content-type') || '';
	const data = ct.includes('json') ? await res.json() : await res.text();
	if (!res.ok) {
		throw new Error((data && data.error) || (_t('Error') + ' ' + res.status));
	}
	return data;
}

function view(...nodes) {
	const v = document.getElementById('lr-view');
	v.textContent = '';
	v.append(...nodes.filter((n) => n !== null && n !== undefined && n !== false));
	enhanceTables(v);
}

const NAV = [
	{ route: '#/uebersicht', label: _t('Overview'), cap: null, icon: 'dashboard' },
	{ route: '#/adressen', label: _t('People'), cap: 'persons.read', icon: 'people' },
	{ route: '#/geburtstage', label: _t('Birthdays'), cap: 'persons.read', icon: 'gift' },
	{ route: '#/dokumente', label: _t('Documents'), caps: ['documents.manage', 'documents.view'], icon: 'files' },
	{ route: '#/meine', label: _t('My profile'), cap: null, icon: 'account' },
];

function renderNav() {
	const nav = document.getElementById('lr-nav');
	nav.textContent = '';
	for (const item of NAV) {
		const need = item.caps || (item.cap ? [item.cap] : []);
		if (need.length && !need.some(c => ME.caps[c])) continue;
		nav.append(h('li', {},
			h('a', { href: item.route, class: location.hash === item.route ? 'active' : '' },
				icon(item.icon), h('span', { class: 'lr-nav-label' }, item.label))));
	}
}

const routes = {
	'': viewUebersicht,
	'#/': viewUebersicht,
	'#/adressen': viewAdressen,
	'#/geburtstage': viewGeburtstage,
	'#/dokumente': viewDokumente,
	'#/meine': viewMeine,
	'#/uebersicht': viewUebersicht,
};

function go(hash) {
	if (location.hash === hash) route();
	else location.hash = hash;
}

async function route() {
	const hash = location.hash || '#/uebersicht';
	renderNav();
	const v = document.getElementById('lr-view');
	saveScroll();
	v.textContent = '';
	v.append(skeleton());
	const personMatch = hash.match(/^#\/adressen\/(\d+)$/);
	try {
		if (personMatch) { await viewPersonDetail(parseInt(personMatch[1])); restoreScroll(); return; }
		const fn = routes[hash] || viewUebersicht;
		await fn();
		restoreScroll();
	} catch (e) {
		console.error('route error', e);
		toast(e.message);
	}
}

/* ---------- Adressen ---------- */

function personFieldValue(p, f) {
	const value = p.custom_fields?.[f.key];
	return f.type === 'select' ? (f.options.find(o => String(typeof o === 'object' ? o.value : o) === value)?.label || value || '') : (value || '');
}

const STATUS_COLORS = { ok: _t('Green'), info: _t('Blue'), warn: _t('Orange'), fail: _t('Red'), missing: _t('Grey') };

async function viewPersonStatuses() {
	const defs = statusDefs().map(d => ({ ...d }));
	const rows = h('div', {});
	const flow = h('div', { class: 'lr-statusflow' });
	const refreshFlow = () => {
		flow.textContent = '';
		flow.append(...defs.flatMap((d, i) => [
			i ? h('span', { class: 'lr-arrow' }, '→') : null,
			h('span', { class: 'lr-badge ' + d.color }, d.label || BADGE_LABELS[d.key] || d.key || '?'),
		]).filter(Boolean));
	};
	const previewOf = (d) => h('span', { class: 'lr-badge ' + d.color }, d.label || BADGE_LABELS[d.key] || d.key || '?');
	const move = (i, dir) => {
		const j = i + dir;
		if (j < 0 || j >= defs.length) return;
		[defs[i], defs[j]] = [defs[j], defs[i]];
		renderRows();
	};
	const renderRows = () => {
		rows.textContent = '';
		defs.forEach((d, i) => {
			const locked = d.key === 'aktiv' || d.key === 'ehemalig';
			const preview = previewOf(d);
			const refresh = () => { preview.className = 'lr-badge ' + d.color; preview.textContent = d.label || BADGE_LABELS[d.key] || d.key || '?'; refreshFlow(); };
			rows.append(h('div', { class: 'lr-search', style: 'gap:8px; align-items:center' },
				h('button', { class: 'lr-btn quiet', 'aria-label': _t('Move up'), disabled: i === 0 || undefined, onclick: () => move(i, -1) }, '↑'),
				h('button', { class: 'lr-btn quiet', 'aria-label': _t('Move down'), disabled: i === defs.length - 1 || undefined, onclick: () => move(i, 1) }, '↓'),
				h('input', { value: d.key, 'aria-label': _t('Key'), disabled: locked || undefined, style: 'width:150px', oninput: e => { d.key = e.target.value; refresh(); } }),
				h('input', { value: d.label, placeholder: BADGE_LABELS[d.key] || d.key, 'aria-label': _t('Label'), oninput: e => { d.label = e.target.value; refresh(); } }),
				h('select', { 'aria-label': _t('Color'), onchange: e => { d.color = e.target.value; refresh(); } },
					...Object.keys(STATUS_COLORS).map(c => h('option', { value: c, selected: d.color === c }, STATUS_COLORS[c]))),
				preview,
				h('button', { class: 'lr-btn quiet', disabled: locked || undefined, title: locked ? _t('Required status') : undefined, onclick: () => { defs.splice(i, 1); renderRows(); } }, _t('Remove'))));
		});
		refreshFlow();
	};
	renderRows();
	view(lrPageHeader({ title: _t('Person statuses') }),
		h('p', { class: 'settings-hint' }, _t('One row per status: key (CSV/API value), display label, badge color. "aktiv" and "ehemalig" are required — new people start as aktiv, archiving sets ehemalig. Order defines the workflow.')),
		flow,
		rows,
		h('button', { class: 'lr-btn secondary', onclick: () => { defs.push({ key: '', label: '', color: 'info' }); renderRows(); } }, _t('Add status')),
		' ',
		h('button', { class: 'lr-btn', onclick: async () => {
			try {
				await api('/settings', { method: 'PUT', json: { person_statuses: defs } });
				ME = await api('/me');
				toast(_t('Saved'), false);
				await viewAdressen();
			} catch (e) { toast(e.message); }
		} }, _t('Save')),
		h('button', { class: 'lr-btn quiet', onclick: viewAdressen }, _t('Cancel')));
}

async function viewPersonColumns() {
	const settings = await api('/settings');
	const fields = JSON.parse(settings.person_fields || '[]');
	const rows = h('div', {});
	const render = () => {
		rows.textContent = '';
		fields.forEach(f => {
			const label = h('input', { value: f.label, maxlength: 100, 'aria-label': _t('Column name'), oninput: e => { f.label = e.target.value; } });
			const options = h('input', { value: (f.options || []).join(', '), placeholder: _t('Options separated by commas'), oninput: e => { f.options = e.target.value.split(',').map(s => s.trim()).filter(Boolean); } });
			rows.append(h('div', { class: 'lr-search' }, label, h('span', {}, f.key),
				h('select', { onchange: e => { f.type = e.target.value; render(); } }, ...['text', 'select'].map(type => h('option', { value: type, selected: f.type === type }, type === 'text' ? _t('Text') : _t('Select')))),
				f.type === 'select' ? options : null));
		});
	};
	render();
	view(lrPageHeader({ title: _t('Person columns') }), rows,
		h('button', { class: 'lr-btn secondary', onclick: () => {
			if (fields.length >= 30) return;
			let i = 1;
			while (fields.some(f => f.key === 'custom_' + i)) i++;
			fields.push({ key: 'custom_' + i, label: '', type: 'text' }); render();
		} }, _t('Add column')),
		h('button', { class: 'lr-btn', onclick: async () => {
			try { await api('/settings', { method: 'PUT', json: { person_fields: JSON.stringify(fields) } }); await viewAdressen(); }
			catch (e) { toast(e.message); }
		} }, _t('Save')),
		h('button', { class: 'lr-btn quiet', onclick: viewAdressen }, _t('Cancel')));
}

const personStatuses = () => (ME.statuses && ME.statuses.length) ? ME.statuses : ['aktiv', 'nwl', 'inaktiv', 'ehemalig'];
const statusDefs = () => ME.status_defs || [];
const statusDef = (s) => statusDefs().find(d => d.key === s);
const statusLabelOf = (s) => statusDef(s)?.label || BADGE_LABELS[s] || s;
const statusBadge = (s) => {
	const d = statusDef(s);
	return d ? h('span', { class: 'lr-badge ' + d.color }, statusLabelOf(s)) : sharedBadge(s);
};
const statusFlow = (currentKey) => h('div', { class: 'lr-statusflow' },
	...statusDefs().flatMap((d, i) => [
		i ? h('span', { class: 'lr-arrow' }, '→') : null,
		h('span', { class: 'lr-badge ' + d.color + (currentKey && d.key !== currentKey ? ' faded' : '') }, d.label || BADGE_LABELS[d.key] || d.key),
	]).filter(Boolean));

const intlPhone = (raw) => {
	let d = String(raw || '').replace(/[^\d+]/g, '');
	if (d.startsWith('00')) d = '+' + d.slice(2);
	else if (d.startsWith('0')) d = '+49' + d.slice(1);
	else if (d.startsWith('49')) d = '+' + d;
	else if (d && !d.startsWith('+')) d = '+49' + d;
	return d;
};
const personAddress = (p) => [
	[p.street, p.house_number].filter(Boolean).join(' '),
	[p.zip, p.city].filter(Boolean).join(' '),
].filter(Boolean).join(', ');

function personCard(p) {
	const tel = intlPhone(p.mobile || p.phone);
	const addr = personAddress(p);
	const quick = h('div', { class: 'lr-pcard-actions' },
		tel ? h('a', { class: 'lr-iconbtn', href: 'tel:' + tel, title: _t('Call'), 'aria-label': _t('Call') }, icon('phone')) : null,
		tel ? h('a', { class: 'lr-iconbtn wa', href: 'https://wa.me/' + tel.slice(1), target: '_blank', rel: 'noopener noreferrer', title: 'WhatsApp', 'aria-label': 'WhatsApp' }, icon('whatsapp')) : null,
		addr ? h('a', { class: 'lr-iconbtn', href: 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(addr), target: '_blank', rel: 'noopener noreferrer', title: _t('Navigate'), 'aria-label': _t('Navigate') }, icon('map-marker')) : null,
		p.email ? h('a', { class: 'lr-iconbtn', href: 'mailto:' + p.email, title: p.email, 'aria-label': _t('Email') }, icon('email')) : null);
	const meta = [p.function_label, p.city].filter(Boolean).join(' · ');
	return h('div', { class: 'lr-pcard' },
		h('div', { class: 'lr-pcard-head' },
			h('a', { class: 'lr-pcard-name', href: '#/adressen/' + p.id }, p.last_name + ', ' + p.first_name),
			statusBadge(p.status)),
		meta ? h('div', { class: 'lr-small lr-muted' }, meta) : null,
		quick);
}

async function viewAdressen() {
	const allFields = await api('/persons/fields');
	const vs = viewState('adressen');
	const st = vs.get();
	const statusVal = st.status === undefined ? 'active' : st.status;
	const search = h('input', { placeholder: _t('Search …'), type: 'search', value: st.q || '' });
	const statusSel = h('select', {},
		h('option', { value: 'active', selected: statusVal === 'active' }, _t('Active only')),
		h('option', { value: '', selected: statusVal === '' }, _t('All statuses')),
		...personStatuses().map(s => h('option', { value: s, selected: statusVal === s }, statusLabelOf(s))));
	const list = h('div', {});
	let sortKey = 'last_name', sortDir = 1, persons = [];
	const sortVal = (p, k) => k === 'status' ? statusLabelOf(p.status || '')
		: (p[k] || '').toLowerCase();
	const sortSel = h('select', { 'aria-label': _t('Sort by'), onchange: e => {
		const [k, dir] = e.target.value.split(':');
		sortKey = k; sortDir = +dir; persist(); render();
	} },
		h('option', { value: 'last_name:1', selected: (st.sort || 'last_name:1') === 'last_name:1' }, _t('Last name A–Z')),
		h('option', { value: 'last_name:-1', selected: st.sort === 'last_name:-1' }, _t('Last name Z–A')),
		h('option', { value: 'first_name:1', selected: st.sort === 'first_name:1' }, _t('First name A–Z')),
		h('option', { value: 'first_name:-1', selected: st.sort === 'first_name:-1' }, _t('First name Z–A')),
		h('option', { value: 'status:1', selected: st.sort === 'status:1' }, _t('Status')),
		h('option', { value: 'function_label:1', selected: st.sort === 'function_label:1' }, _t('Function')));
	if (st.sort) [sortKey, sortDir] = [st.sort.split(':')[0], +st.sort.split(':')[1] || 1];
	const persist = () => vs.set({ q: search.value, status: statusSel.value, sort: sortSel.value });
	const render = () => {
		const sorted = [...persons].sort((a, b) => sortDir * String(sortVal(a, sortKey)).localeCompare(String(sortVal(b, sortKey)), lrLocale(), { numeric: true }));
		list.textContent = '';
		list.append(persons.length
			? h('div', { class: 'lr-cardsgrid' }, ...sorted.map(p => personCard(p)))
			: emptyState(_t('No people found.')));
	};
	const reload = async () => {
		const q = new URLSearchParams();
		if (search.value) q.set('search', search.value);
		if (statusSel.value === 'active') q.set('active', '1');
		else if (statusSel.value) q.set('status', statusSel.value);
		persons = await api('/persons?' + q);
		render();
	};
	search.addEventListener('input', () => { persist(); reload(); });
	statusSel.addEventListener('change', () => { persist(); reload(); });
	const menu = [];
	if (ME.caps['settings.manage']) menu.push({ label: _t('Person columns'), onclick: viewPersonColumns });
	if (ME.caps['settings.manage']) menu.push({ label: _t('Statuses'), onclick: viewPersonStatuses });
	if (ME.caps['persons.import']) menu.push({ label: _t('Import CSV'), onclick: importCsv });
	if (ME.caps['persons.write']) menu.push({ label: _t('Match accounts'), onclick: viewKontomapping });
	if (ME.caps['persons.write']) menu.push({ label: _t('New person'), onclick: () => viewPersonForm(null) });
	const actions = menu.length > 1
		? [menuButton(_t('Actions'), menu)]
		: menu.map(m => h('button', { class: 'lr-btn', onclick: m.onclick }, m.label));
	const customCols = allFields.map(f => f.plugin ? _t('Default Kirchort') : f.label).join(';');
	const importHint = ME.caps['persons.import'] ? h('p', { class: 'lr-small lr-muted' },
		_t('Import columns:'), ' ', h('code', {}, IMPORT_CSV_HEADER + (customCols ? ';' + customCols : '')), ' ',
		h('a', { href: '#', onclick: (e) => { e.preventDefault(); downloadCsvTemplate(); } }, _t('Download template'))) : null;
	view(lrPageHeader({ title: _t('People'), actions }),
		h('div', { class: 'lr-search' }, search, statusSel, sortSel), statusFlow(null), importHint, list);
	await reload();
}

async function viewPersonDetail(id) {
	const p = await api('/persons/' + id);
	const age = (d) => {
		const b = new Date(d + 'T00:00:00'), now = new Date();
		return now.getFullYear() - b.getFullYear() - (now < new Date(now.getFullYear(), b.getMonth(), b.getDate()) ? 1 : 0);
	};
	const birth = p.birthdate
		? formatDate(p.birthdate) + ' · ' + _n('%n year old', '%n years old', age(p.birthdate))
		: (p.birth_year ? _t('Born in {year}', { year: p.birth_year }) : null);
	const account = p.nc_user_id ? (p.nc_user_display_name ? p.nc_user_display_name + ' (' + p.nc_user_id + ')' : _t('{uid} (account no longer exists)', { uid: p.nc_user_id })) : null;
	const rows = [
		[_t('Email'), p.email], [_t('Phone'), p.phone], [_t('Mobile'), p.mobile],
		[_t('Address'), [[p.street, p.house_number].filter(Boolean).join(' '), [p.zip, p.city].filter(Boolean).join(' ')].filter(Boolean).join(', ')],
		[_t('Date of birth'), birth], [_t('Function'), p.function_label],
		[_t('Nextcloud user'), account], [_t('Notes'), p.notes],
		...(await api('/persons/fields')).map(f => [f.plugin ? _t('Default Kirchort') : f.label, personFieldValue(p, f)]),
	].filter(r => r[1]);
	const grid = h('dl', { class: 'lr-facts' },
		...rows.flatMap(([k, v]) => [h('dt', {}, k), h('dd', {}, String(v))]));
	const actions = [];
	if (ME.caps['persons.write']) actions.push(h('button', { class: 'lr-btn', onclick: () => viewPersonForm(p) }, _t('Edit')));
	if (p.status !== 'ehemalig' && ME.caps['persons.write']) actions.push(h('button', { class: 'lr-btn quiet', onclick: async () => {
		if (!confirm(_t('Archive {name}? The person is kept with status “ehemalig” and can be restored by editing.', { name: p.first_name + ' ' + p.last_name }))) return;
		try { await api('/persons/' + id, { method: 'PUT', json: { status: 'ehemalig' } }); toast(_t('Archived'), false); go('#/adressen'); }
		catch (e) { toast(e.message); }
	} }, _t('Archive')));
	if (p.status === 'ehemalig' && ME.caps['persons.delete']) actions.push(h('button', { class: 'lr-btn quiet', onclick: async () => {
		if (!confirm(_t('Permanently delete {name}? Linked records (finances, events, trainings) lose their person reference. This cannot be undone.', { name: p.first_name + ' ' + p.last_name }))) return;
		try { await api('/persons/' + id, { method: 'DELETE' }); toast(_t('Deleted'), false); go('#/adressen'); }
		catch (e) { toast(e.message); }
	} }, _t('Delete')));
	const nodes = [lrPageHeader({ kicker: _t('Leiterrunde · People'), title: p.first_name + ' ' + p.last_name, actions }), statusFlow(p.status), grid];
	// Schulungsstatus (Modul-App, wenn aktiviert)
	try {
		if (!(ME.modules || []).includes('leiterrunde_schulungen')) throw new Error('off');
		const st = await modApi('leiterrunde_schulungen', '/persons/' + id + '/training-status');
		nodes.push(h('h2', { class: 'lr-section' }, _t('Training status')),
			h('table', { class: 'lr-table' },
				h('thead', {}, h('tr', {}, h('th', {}, _t('Check')), h('th', {}, _t('Status')), h('th', {}, _t('Valid until')))),
				h('tbody', {},
					...st.types.map(t => h('tr', {}, h('td', {}, t.name), h('td', {}, sharedBadge(t.state)), h('td', {}, t.expires ? formatDate(t.expires) : '–'))),
					h('tr', {}, h('td', {}, _t('Hours balance ({hours}/{required} h)', { hours: st.hours.hours, required: st.hours.required })), h('td', {}, sharedBadge(st.hours.state)), h('td', {}, '–')),
					h('tr', {}, h('td', {}, _t('Certificate of conduct')), h('td', {}, sharedBadge(st.fz.state)), h('td', {}, st.fz.expires ? formatDate(st.fz.expires) : '–')))));
	} catch (e) { /* kein Status-Zugriff */ }
	if (ME.caps['documents.manage'] || ME.caps['documents.view']) {
		try {
			const canManage = !!ME.caps['documents.manage'];
			const dreqs = await api('/persons/' + id + '/documents');
			if (dreqs.length) {
				nodes.push(h('h2', { class: 'lr-section' }, _t('Required documents')),
					...dreqs.map(r => docReqCard({ ...r, person_name: p.first_name + ' ' + p.last_name }, canManage, () => viewPersonDetail(id))));
			}
		} catch (e) {}
	}
	if (ME.caps['audit.read']) {
		try {
			const FIELD = {
				created: _t('Created'), first_name: _t('First name'), last_name: _t('Last name'), email: _t('Email'),
				phone: _t('Phone'), mobile: _t('Mobile'), street: _t('Street'), house_number: _t('House number'), zip: _t('Postcode'), city: _t('Place'),
				birthdate: _t('Date of birth'), birth_year: _t('Birth year'), status: _t('Status'),
				function_label: _t('Function'), notes: _t('Notes'), nc_user_id: _t('Nextcloud user'),
			};
			const shown = (f, v) => (v === null || v === undefined || v === '') ? '' : (f === 'birthdate' ? formatDate(v) : (f === 'status' ? (BADGE_LABELS[v] || v) : v));
			const audit = (await api('/persons/' + id + '/audit')).filter(a => a.field !== 'updated_at');
			if (audit.length) {
				nodes.push(h('h2', { class: 'lr-section' }, _t('Change log')),
					h('div', { class: 'lr-log' }, ...audit.map(a => {
						const ov = shown(a.field, a.old_value), nv = shown(a.field, a.new_value);
						return h('div', { class: 'lr-log-row' },
							h('span', { class: 'lr-log-when' }, new Date(a.created_at * 1000).toLocaleString(lrLocale(), { dateStyle: 'short', timeStyle: 'short' })),
							h('span', { class: 'lr-log-what' }, a.user_id + ' · ' + (FIELD[a.field] || a.field) + (ov || nv ? ': ' + (ov || '–') + ' → ' + (nv || '–') : '')));
					})));
			}
		} catch (e) {}
	}
	view(...nodes);
}

async function viewPersonForm(p) {
	const columns = await api('/persons/fields');
	const customInputs = new Map(columns.map(f => {
		const value = p?.custom_fields?.[f.key] || '';
		const options = (f.options || []).map(o => typeof o === 'object' ? o : { value: o, label: o });
		if (value && !options.some(o => o.value === value)) options.push({ value, label: value });
		return [f.key, f.type === 'select' ? h('select', {}, h('option', { value: '' }, '–'), ...options.map(o => h('option', { value: o.value, selected: o.value === value }, o.label))) : h('input', { value, maxlength: 2000 })];
	}));
	const { userPicker } = window.LeiterrundeUI;
	const yearOnly = !!(p && !p.birthdate && p.birth_year);
	const inp = (key, attrs = {}, span = 6) => {
		const i = h('input', { value: p && p[key] != null ? String(p[key]) : '', ...attrs });
		i.dataset.key = key;
		return h('div', { class: 'lr-f col-' + span }, h('label', { class: 'lr-flabel' }, attrs._label || key), i);
	};
	const firstName = inp('first_name', { _label: _t('First name'), required: true }, 6);
	const lastName = inp('last_name', { _label: _t('Last name'), required: true }, 6);
	const email = inp('email', { _label: _t('Email'), type: 'email' }, 6);
	const phone = inp('phone', { _label: _t('Phone'), type: 'tel' }, 3);
	const mobile = inp('mobile', { _label: _t('Mobile'), type: 'tel' }, 3);
	const street = inp('street', { _label: _t('Street') }, 4);
	const houseNumber = inp('house_number', { _label: _t('House number'), maxlength: 20 }, 2);
	const zip = inp('zip', { _label: _t('Postcode'), inputmode: 'numeric', maxlength: 10 }, 2);
	const city = inp('city', { _label: _t('Place') }, 4);

	// birthdate / birth-year-only
	const today = new Date().toISOString().slice(0, 10);
	const bd = h('input', { type: 'date', min: '1900-01-01', max: today, value: p?.birthdate || '' });
	const by = h('input', { type: 'number', min: 1900, max: new Date().getFullYear(), value: p?.birth_year || '' });
	const yearCb = h('input', { type: 'checkbox', checked: yearOnly });
	const bdLabel = h('label', { class: 'lr-flabel' }, _t('Date of birth'));
	const setMode = (y) => {
		bd.style.display = y ? 'none' : '';
		by.style.display = y ? '' : 'none';
		bdLabel.textContent = y ? _t('Birth year') : _t('Date of birth');
	};
	yearCb.addEventListener('change', () => setMode(yearCb.checked));
	const bdWrap = h('div', { class: 'lr-f col-4' },
		bdLabel, bd, by,
		h('label', { class: 'lr-check' }, yearCb, _t('Only birth year known')));

	const statusSel = h('select', {},
		...personStatuses().map(s =>
			h('option', { value: s, selected: p && p.status === s }, statusLabelOf(s))));
	const statusWrap = h('div', { class: 'lr-f col-4' }, h('label', { class: 'lr-flabel' }, _t('Status')), statusSel);
	const func = inp('function_label', { _label: _t('Function') }, 4);

	let ncUid = p?.nc_user_id || null;
	const picker = userPicker({
		value: ncUid, label: p?.nc_user_display_name || ncUid,
		onChange: (id) => { ncUid = id; },
	});
	const userWrap = h('div', { class: 'lr-f col-6' },
		h('label', { class: 'lr-flabel' }, _t('Nextcloud user')), picker,
		h('p', { class: 'lr-small lr-muted' }, _t('Links this person to a Nextcloud account (used for “My profile”, notifications and own submissions). Without a selection, the person is matched by the email address of the Nextcloud account.')));

	const notes = h('textarea', { rows: 3 }, p ? (p.notes || '') : '');
	const notesWrap = h('div', { class: 'lr-f col-12' }, h('label', { class: 'lr-flabel' }, _t('Notes')), notes);

	const form = h('div', { class: 'lr-form lr-formgrid' },
		firstName, lastName, email, phone, mobile, street, houseNumber, zip, city,
		bdWrap, statusWrap, func, userWrap, notesWrap,
		...columns.map(f => h('div', { class: 'lr-f col-6' }, h('label', { class: 'lr-flabel' }, f.plugin ? _t('Default Kirchort') : f.label), customInputs.get(f.key))));
	bd.dataset.key = 'birthdate'; by.dataset.key = 'birth_year'; yearCb.dataset.key = 'year_only';
	statusSel.dataset.key = 'status'; notes.dataset.key = 'notes';
	for (const [key, input] of customInputs) input.dataset.key = 'custom_' + key;
	const draft = p ? null : persistForm(form, 'person-new');
	setMode(yearCb.checked);

	const save = h('button', { class: 'lr-btn', onclick: async () => {
		const body = { status: statusSel.value, notes: notes.value || null, nc_user_id: ncUid,
			custom_fields: Object.fromEntries([...customInputs].map(([key, input]) => [key, input.value || null])) };
		for (const el of form.querySelectorAll('input[data-key]')) {
			body[el.dataset.key] = el.value === '' ? null : el.value;
		}
		if (!body.first_name || !body.last_name) {
			toast(_t('First and last name are required'));
			(!body.first_name ? firstName : lastName).querySelector('input').classList.add('lr-error');
			return;
		}
		if (yearCb.checked) { body.birthdate = null; body.birth_year = by.value ? parseInt(by.value) : null; }
		else { body.birthdate = bd.value || null; body.birth_year = null; }
		try {
			if (p) await api('/persons/' + p.id, { method: 'PUT', json: body });
			else await api('/persons', { method: 'POST', json: body });
			draft?.clear();
			toast(_t('Saved'), false);
			go(p ? '#/adressen/' + p.id : '#/adressen');
		} catch (e) { toast(e.message); }
	} }, _t('Save'));
	const cancel = h('button', { class: 'lr-btn secondary', onclick: () => go(p ? '#/adressen/' + p.id : '#/adressen') }, _t('Cancel'));
	form.append(h('div', { class: 'lr-form-actions' }, save, cancel));
	view(lrPageHeader({ kicker: _t('Leiterrunde · People'), title: p ? _t('Edit {name}', { name: p.first_name + ' ' + p.last_name }) : _t('New person') }), form);
}

function importCsv() {
	const input = h('input', { type: 'file', accept: '.csv' });
	input.addEventListener('change', async () => {
		if (!input.files[0]) return;
		const fd = new FormData();
		fd.append('file', input.files[0]);
		try {
			const res = await api('/persons/import', { method: 'POST', body: fd });
			toast(_t('Import: {created} new, {updated} updated, {skipped} skipped', { created: res.created, updated: res.updated, skipped: res.skipped }) + (res.errors.length ? ', ' + res.errors.length + ' ' + _t('Error') : ''), res.errors.length > 0);
			route();
		} catch (e) { toast(e.message); }
	});
	input.click();
}

const IMPORT_CSV_HEADER = 'Vorname;Nachname;Stra\u00dfe;Hausnummer;PLZ;Ort;Telefon;Mobil;Geburtstag;E-Mail';

function downloadCsvTemplate() {
	const blob = new Blob(['\uFEFF' + IMPORT_CSV_HEADER + '\n'], { type: 'text/csv;charset=utf-8' });
	const url = URL.createObjectURL(blob);
	const a = h('a', { href: url, download: 'adressen-import.csv' });
	a.click();
	URL.revokeObjectURL(url);
}

/* ---------- Geburtstage ---------- */

async function viewGeburtstage() {
	const vs = viewState('geburtstage');
	const days = h('input', { type: 'number', value: vs.get().days || 30, min: 1, max: 365 });
	const list = h('div', {});
	const reload = async () => {
		vs.set({ days: +days.value || 30 });
		const rows = await api('/persons/birthdays?days=' + (days.value || 30));
		list.textContent = '';
		list.append(rows.length
			? h('table', { class: 'lr-table' },
				h('thead', {}, h('tr', {}, h('th', {}, _t('Date')), h('th', {}, _t('Name')), h('th', {}, _t('Age')))),
				h('tbody', {}, ...rows.map(r => h('tr', {},
					h('td', {}, r.date),
					h('td', {}, h('a', { href: '#/adressen/' + r.person_id }, r.last_name + ', ' + r.first_name)),
					h('td', {}, _t('turns {age}', { age: r.age }))))))
			: emptyState(_t('No birthdays in this period.')));
	};
	days.addEventListener('change', reload);
	view(lrPageHeader({ title: _t('Birthdays') }), h('div', { class: 'lr-search' }, h('label', {}, _t('Days')), days), list);
	await reload();
}

/* ---------- Schulungen ---------- */

function factsGrid(p) {
	if (!p) return null;
	const custom = typeof p.custom_fields === 'string' ? (JSON.parse(p.custom_fields || '{}') || {}) : (p.custom_fields || {});
	const rows = [
		[_t('First name'), p.first_name], [_t('Last name'), p.last_name],
		[_t('Email'), p.email], [_t('Phone'), p.phone], [_t('Mobile'), p.mobile],
		[_t('Address'), [[p.street, p.house_number].filter(Boolean).join(' '), [p.zip, p.city].filter(Boolean).join(' ')].filter(Boolean).join(', ')],
		[_t('Date of birth'), p.birthdate ? formatDate(p.birthdate) : null],
		[_t('Birth year'), p.birth_year],
		[_t('Status'), p.status ? statusLabelOf(p.status) : null],
		[_t('Function'), p.function_label],
		[_t('Nextcloud user'), p.nc_user_id],
		[_t('Notes'), p.notes],
	];
	for (const f of (ME.field_defs || [])) {
		const v = custom[f.key];
		if (v === undefined || v === null || v === '') continue;
		const opt = (f.options || []).find(o => String(typeof o === 'object' ? o.value : o) === String(v));
		rows.push([f.plugin ? _t('Default Kirchort') : f.label, opt ? (typeof opt === 'object' ? opt.label : opt) : v]);
	}
	return h('dl', { class: 'lr-facts' },
		...rows.filter(r => r[1] !== null && r[1] !== undefined && r[1] !== '').flatMap(([k, v]) => [h('dt', {}, k), h('dd', {}, String(v))]));
}

const docMethodLabel = (r) => r.method === 'upload' ? _t('Uploaded file') : (r.method === 'draw' ? _t('Drawn signature') : (r.method === 'accept' ? _t('Confirmed') : '–'));

/** Hidden file input posting a signed artifact for the request. */
function docReqFileUp(r, refresh) {
	const fileUp = h('input', { type: 'file', style: 'display:none', accept: '.pdf,.jpg,.jpeg,.png,.heic', onchange: async () => {
		if (!fileUp.files?.length) return;
		const fd = new FormData(); fd.append('file', fileUp.files[0]);
		try {
			const res = await fetch(API + '/documents/' + r.document_id + '/requests/' + r.id + '/file', { method: 'POST', headers: { 'requesttoken': OC.requestToken }, body: fd });
			const data = await res.json().catch(() => ({}));
			if (!res.ok) throw new Error(data.error || _t('Error') + ' ' + res.status);
			toast(_t('Signed'), false); refresh();
		} catch (e) { toast(e.message); }
	} });
	return fileUp;
}

/** Admin actions menu for a request — shared by table row and card. */
function docReqMenu(r, canManage, fileUp, refresh) {
	if (!canManage) return null;
	const docId = r.document_id;
	return menuButton(_t('Actions'), [
		...(r.status === 'signed'
			? [{ label: _t('Reset to pending'), onclick: async () => {
				try { await api('/documents/' + docId + '/requests/' + r.id, { method: 'PUT', json: { status: 'pending' } }); toast(_t('Reset'), false); refresh(); }
				catch (e) { toast(e.message); }
			} }]
			: [{ label: _t('Mark as signed'), onclick: async () => {
				const name = prompt(_t('Recorded signature for {name} (e.g. their name or “signed on paper”)', { name: r.person_name || '#' + r.person_id }), r.person_name || '');
				if (!name?.trim()) return;
				try { await api('/documents/' + docId + '/requests/' + r.id, { method: 'PUT', json: { status: 'signed', signature_text: name.trim() } }); toast(_t('Signed'), false); refresh(); }
				catch (e) { toast(e.message); }
			} }]),
		{ label: r.file_id ? _t('Replace signed file') : _t('Upload signed file'), onclick: () => fileUp.click() },
		{ label: _t('Remove'), danger: true, onclick: async () => {
			if (!confirm(_t('Remove request for {name}?', { name: r.person_name || '#' + r.person_id }))) return;
			try { await api('/documents/' + docId + '/requests/' + r.id, { method: 'DELETE' }); toast(_t('Removed'), false); refresh(); }
			catch (e) { toast(e.message); }
		} },
	]);
}

/** One request row: firstCell(r) renders the lead cell (person or document). */
function docReqRow(r, firstCell, canManage, refresh) {
	const docId = r.document_id;
	const fileUp = docReqFileUp(r, refresh);
	return h('tr', {},
		firstCell(r),
		h('td', {}, r.status === 'signed' ? h('span', { class: 'lr-badge ok' }, _t('Signed')) : h('span', { class: 'lr-badge info' }, _t('Pending'))),
		h('td', {}, r.signed_at ? new Date(r.signed_at * 1000).toLocaleDateString(lrLocale()) : '–'),
		h('td', {}, docMethodLabel(r), r.signed_by ? h('span', { class: 'lr-muted lr-small' }, ' · ' + r.signed_by) : null),
		h('td', {},
			r.signature_text ? h('em', {}, r.signature_text) : null,
			r.file_id ? h('a', { href: OC.generateUrl('/apps/leiterrunde/api/documents/' + docId + '/requests/' + r.id + '/file'), target: '_blank' }, _t('Open signature')) : (!r.signature_text ? '–' : null)),
		h('td', {}, fileUp, docReqMenu(r, canManage, fileUp, refresh)));
}

/** Compact card for a request — used on the person page instead of a table row. */
function docReqCard(r, canManage, refresh) {
	const fileUp = docReqFileUp(r, refresh);
	const meta = [];
	if (r.status === 'signed') {
		meta.push(_t('Signed on {date} ({method})', { date: new Date(r.signed_at * 1000).toLocaleDateString(lrLocale()), method: r.method === 'upload' ? _t('uploaded file') : (r.method === 'draw' ? _t('drawn signature') : _t('confirmed digitally')) }));
		if (r.signed_by) meta.push(r.signed_by);
		if (r.signature_text) meta.push(h('em', {}, r.signature_text));
		if (r.file_id) meta.push(h('a', { href: OC.generateUrl('/apps/leiterrunde/api/documents/' + r.document_id + '/requests/' + r.id + '/file'), target: '_blank' }, _t('Open signature')));
	}
	return h('div', { class: 'lr-docreq' }, fileUp,
		h('div', { class: 'lr-docreq-head' },
			h('span', { class: 'lr-docreq-title' }, r.title, r.required ? ' ' : '', r.required ? h('span', { class: 'lr-badge warn' }, _t('Required')) : null,
				r.doc_has_file ? [' · ', h('a', { href: OC.generateUrl('/apps/leiterrunde/api/documents/' + r.document_id + '/file'), target: '_blank' }, _t('Open'))] : null),
			h('span', { class: 'lr-docreq-acts' },
				r.status === 'signed' ? h('span', { class: 'lr-badge ok' }, _t('Signed')) : h('span', { class: 'lr-badge info' }, _t('Pending')),
				docReqMenu(r, canManage, fileUp, refresh))),
		meta.length ? h('div', { class: 'lr-small lr-muted lr-docreq-meta' }, ...meta.flatMap((m, i) => i ? [' · ', m] : [m])) : null);
}

async function signDocRow(r, refresh) {
	const row = h('div', { class: 'lr-docreq' });
	const signed = r.status === 'signed';
	const head = h('div', { class: 'lr-docreq-head' },
		h('span', { class: 'lr-docreq-title' }, r.title, r.required ? ' ' : '', r.required ? h('span', { class: 'lr-badge warn' }, _t('Required')) : null),
		signed ? h('span', { class: 'lr-badge ok' }, _t('Signed')) : h('span', { class: 'lr-badge info' }, _t('Pending')));
	row.append(head);
	if (signed) {
		row.append(h('div', { class: 'lr-small lr-muted' },
			_t('Signed on {date} ({method})', { date: new Date(r.signed_at * 1000).toLocaleDateString(lrLocale()), method: r.method === 'upload' ? _t('uploaded file') : (r.method === 'draw' ? _t('drawn signature') : _t('confirmed digitally')) }),
			r.signature_text ? [' — ', h('em', {}, r.signature_text)] : [],
			r.file_id ? [' ', h('a', { href: OC.generateUrl('/apps/leiterrunde/api/documents/' + r.document_id + '/requests/' + r.id + '/file'), target: '_blank' }, _t('Open file'))] : []));
		return row;
	}
	const body = h('div', { class: 'lr-docreq-body', style: 'display:none' });
	const toggle = h('button', { class: 'lr-btn lr-docreq-toggle', onclick: () => { body.style.display = body.style.display === 'none' ? '' : 'none'; } }, icon('pencil'), ' ', _t('Read & sign'));
	row.append(toggle, body);
	if (r.doc_has_file) {
		body.append(h('p', {}, h('a', { href: OC.generateUrl('/apps/leiterrunde/api/documents/' + r.document_id + '/file'), target: '_blank' }, _t('Open document (PDF/file)'))));
	}
	if (r.body_text) {
		body.append(h('div', { class: 'lr-doctext' }, r.body_text));
	}
	const sig = h('input', { placeholder: _t('Type your full name as signature'), 'aria-label': _t('Signature') });
	const fileIn = h('input', { type: 'file', accept: '.pdf,.jpg,.jpeg,.png,.heic' });
	const acceptPanel = h('div', {},
		h('label', { class: 'lr-flabel' }, _t('Confirm digitally')), sig,
		h('div', { class: 'lr-form-actions' },
			h('button', { class: 'lr-btn', onclick: async () => {
				if (!sig.value.trim()) { toast(_t('Please type your name as signature')); return; }
				try { await api('/documents/' + r.document_id + '/sign', { method: 'POST', json: { signature_text: sig.value.trim() } }); toast(_t('Signed'), false); await refresh(); }
				catch (e) { toast(e.message); }
			} }, _t('Accept and sign'))));
	const uploadPanel = h('div', {},
		h('label', { class: 'lr-flabel' }, _t('Upload signed file')), fileIn,
		h('div', { class: 'lr-form-actions' },
			h('button', { class: 'lr-btn secondary', onclick: async () => {
				if (!fileIn.files?.length) { toast(_t('Please choose a file')); return; }
				try {
					const fd = new FormData(); fd.append('file', fileIn.files[0]);
					const res = await fetch(API + '/documents/' + r.document_id + '/sign-file', { method: 'POST', headers: { 'requesttoken': OC.requestToken }, body: fd });
					const data = await res.json().catch(() => ({}));
					if (!res.ok) throw new Error(data.error || _t('Error') + ' ' + res.status);
					toast(_t('Signed'), false); await refresh();
				} catch (e) { toast(e.message); }
			} }, _t('Upload'))));
	const pad = h('canvas', { class: 'lr-signpad', 'aria-label': _t('Draw signature'), width: 640, height: 180 });
	const padCtx = pad.getContext('2d');
	padCtx.lineWidth = 3; padCtx.lineCap = 'round'; padCtx.lineJoin = 'round';
	let drawing = false, dirty = false;
	const padPos = (e) => { const b = pad.getBoundingClientRect(); return [(e.clientX - b.left) * (pad.width / b.width), (e.clientY - b.top) * (pad.height / b.height)]; };
	pad.addEventListener('pointerdown', (e) => { e.preventDefault(); drawing = true; dirty = true; pad.setPointerCapture(e.pointerId); padCtx.beginPath(); padCtx.moveTo(...padPos(e)); });
	pad.addEventListener('pointermove', (e) => { if (!drawing) return; padCtx.lineTo(...padPos(e)); padCtx.stroke(); });
	for (const ev of ['pointerup', 'pointercancel', 'pointerleave']) pad.addEventListener(ev, () => { drawing = false; });
	const drawPanel = h('div', {},
		pad,
		h('div', { class: 'lr-form-actions' },
			h('button', { class: 'lr-btn quiet', onclick: () => { padCtx.clearRect(0, 0, pad.width, pad.height); dirty = false; } }, _t('Clear')),
			h('button', { class: 'lr-btn', onclick: async () => {
				if (!dirty) { toast(_t('Please draw your signature')); return; }
				try {
					const blob = await new Promise(res => pad.toBlob(res, 'image/png'));
					if (!blob) throw new Error(_t('Error'));
					const fd = new FormData(); fd.append('file', blob, 'signature.png'); fd.append('via', 'draw');
					const res = await fetch(API + '/documents/' + r.document_id + '/sign-file', { method: 'POST', headers: { 'requesttoken': OC.requestToken }, body: fd });
					const data = await res.json().catch(() => ({}));
					if (!res.ok) throw new Error(data.error || _t('Error') + ' ' + res.status);
					toast(_t('Signed'), false); await refresh();
				} catch (e) { toast(e.message); }
			} }, _t('Sign with drawing'))));

	const methods = {
		draw: { label: _t('Draw signature'), el: drawPanel },
		accept: { label: _t('Type name'), el: acceptPanel },
		upload: { label: _t('Upload signed file'), el: uploadPanel },
	};
	let active = 'draw';
	const panelBox = h('div', {});
	const menuBox = h('div', {});
	const labelEl = h('span', { class: 'lr-flabel' });
	const show = (key) => {
		active = key;
		labelEl.textContent = methods[key].label;
		panelBox.textContent = '';
		panelBox.append(methods[key].el);
		menuBox.textContent = '';
		menuBox.append(menuButton(_t('Options'), Object.keys(methods)
			.filter(k => k !== key)
			.map(k => ({ label: methods[k].label, onclick: () => show(k) }))));
	};
	show('draw');
	body.append(h('div', { class: 'lr-docreq-sign' },
		h('div', { class: 'lr-signhead' }, labelEl, menuBox), panelBox));
	return row;
}

async function docRequestsSection() {
	const sec = h('div', {});
	const render = async () => {
		const reqs = await api('/documents/mine').catch(() => []);
		sec.textContent = '';
		if (!reqs.length) return;
		sec.append(h('h2', { class: 'lr-section' }, _t('Documents to sign')));
		const pending = reqs.filter(r => r.status !== 'signed');
		const done = reqs.filter(r => r.status === 'signed');
		for (const r of [...pending, ...done]) sec.append(await signDocRow(r, render));
	};
	await render();
	return sec;
}

async function viewMeine() {
	const p = ME.person;
	const nodes = [lrPageHeader({ title: _t('My profile') })];
	if (!p) {
		nodes.push(h('p', {}, _t('Your account is not linked to a person.')));
	} else {
		nodes.push(h('p', {}, _t('Linked person') + ': ' + p.first_name + ' ' + p.last_name + ' (#' + p.id + ')'),
			factsGrid(p),
			await docRequestsSection());
	}
	const tiles = await api('/overview').catch(() => []);
	const self = tiles.filter(t => /mein|schulungsstand/i.test(t.title));
	if (self.length) {
		nodes.push(h('h2', { class: 'lr-section' }, _t('My status')));
		const grid = h('div', { class: 'lr-tiles' });
		for (const t of self) {
			grid.append(h('div', { class: 'lr-tile lr-tile-' + t.severity },
				h('h3', {}, t.title),
				h('ul', {}, ...t.items.slice(0, 8).map(i => h('li', {}, i.label, i.state ? ' ' : '', i.state ? sharedBadge(i.state) : null)))));
		}
		nodes.push(grid);
	}
	view(...nodes);
}

/* ---------- Dokumente & Signaturen ---------- */

async function viewDokumente() {
	const canManage = !!ME.caps['documents.manage'];
	const docs = await api('/documents');
	const targetOpts = canManage ? await api('/documents/targets').catch(() => null) : null;
	const nodes = [lrPageHeader({ title: _t('Required documents'), lede: _t('Documents people must acknowledge or sign, e.g. privacy form or terms of use.') })];

	const ruleSummary = (t, opts) => {
		const parts = [];
		if (t?.all) parts.push(_t('all persons'));
		for (const s of t?.statuses || []) parts.push(opts?.statuses?.find(d => d.key === s)?.label || statusLabelOf(s));
		for (const g of t?.nc_groups || []) parts.push((opts?.nc_groups?.find(x => x.gid === g)?.display || g) + ' (NC)');
		return parts.join(', ');
	};

	const lv = listView({
		scope: 'dokumente',
		search: { placeholder: _t('Search documents …'), text: (d) => d.title },
		sorts: [
			{ key: 'title', label: _t('Title'), value: d => d.title },
			{ key: 'signed', label: _t('Signed'), value: d => d.signed, dirs: [-1, 1], labelDesc: _t('Signed (asc)') },
		],
		headers: [_t('Title'), _t('Kind'), _t('Signed'), ''],
		row: (d) => h('tr', {},
			h('td', {}, h('a', { href: '#', onclick: (e) => { e.preventDefault(); expand(d); } }, d.title)),
			h('td', {}, d.mode === 'file' ? _t('File (PDF/image)') : _t('Text'), d.required ? ' · ' + _t('required') : ''),
			h('td', {}, `${d.signed}/${d.requests}`),
			h('td', {}, menuButton(_t('Actions'), [
				{ label: _t('Requests'), onclick: () => expand(d) },
				...(canManage ? [{ label: _t('Delete'), danger: true, onclick: async () => {
					if (!confirm(_t('Delete document “{title}” with all requests and signatures?', { title: d.title }))) return;
					try { await api('/documents/' + d.id, { method: 'DELETE' }); toast(_t('Deleted'), false); viewDokumente(); }
					catch (e) { toast(e.message); }
				} }] : []),
			]))),
		empty: _t('No documents yet.'),
	});
	lv.setRows(docs);

	const detail = h('div', {});
	const expand = async (d) => {
		const full = await api('/documents/' + d.id);
		detail.textContent = '';
		const reqs = full.requests || [];
		const reqRows = h('tbody', {}, ...reqs.map(r =>
			docReqRow(r, rr => h('td', {}, h('a', { href: '#/adressen/' + rr.person_id }, rr.person_name)), canManage, () => expand(d))));

		const parts = [
			d.body_text ? h('div', { class: 'lr-doctext' }, d.body_text) : null,
			d.file_id ? h('p', {}, h('a', { href: OC.generateUrl('/apps/leiterrunde/api/documents/' + d.id + '/file'), target: '_blank' }, _t('Open document file'))) : null,
			h('h3', {}, _t('Requests')),
			reqs.length ? h('table', { class: 'lr-table' }, h('thead', {}, h('tr', {}, h('th', {}, _t('Person')), h('th', {}, _t('Status')), h('th', {}, _t('Signed at')), h('th', {}, _t('Method')), h('th', {}, _t('Signature')), h('th', {}, ''))), reqRows) : h('p', { class: 'lr-muted' }, _t('No requests yet.')),
		];
		const spec = full.target || {};
		if (ruleSummary(spec, targetOpts)) {
			parts.push(h('p', { class: 'lr-small lr-muted' },
				_t('Auto-request: {rule} (also for people added later)', { rule: ruleSummary(spec, targetOpts) }),
				canManage ? [' ', h('button', { class: 'lr-btn quiet', onclick: async () => {
					try { await api('/documents/' + d.id + '/requests', { method: 'POST', json: { target: null } }); toast(_t('Rule removed'), false); expand(d); }
					catch (e) { toast(e.message); }
				} }, _t('Remove rule'))] : null));
		}

		if (canManage) {
			const persons = await api('/persons/options').catch(() => []);
			const requested = new Set(reqs.map(r => r.person_id));
			const selectable = persons.filter(pp => !requested.has(pp.id));
			const picked = new Map();
			const chips = h('div', { class: 'lr-picked' });
			const addPick = (pp) => {
				if (!pp || picked.has(pp.id)) return;
				picked.set(pp.id, pp);
				const chip = h('span', { class: 'lr-chip' }, pp.last_name + ', ' + pp.first_name,
					h('button', { type: 'button', class: 'lr-chip-x', 'aria-label': _t('Remove'), onclick: () => { picked.delete(pp.id); chip.remove(); } }, '×'));
				chips.append(chip);
			};
			const sel = h('select', { 'aria-label': _t('Person') }, h('option', { value: '' }, _t('– choose person –')),
				...selectable.map(pp => h('option', { value: pp.id }, pp.last_name + ', ' + pp.first_name)));
			const picker = h('div', { class: 'lr-search' }, sel,
				h('button', { class: 'lr-btn secondary', onclick: () => {
					addPick(selectable.find(pp => pp.id === +sel.value)); sel.value = '';
				} }, _t('Add')));

			const cb = (labelTxt, extraClass) => {
				const i = h('input', { type: 'checkbox' });
				const l = h('label', { class: extraClass || 'lr-check' }, i, ' ', labelTxt);
				return { i, l };
			};
			const allCb = cb(_t('All persons'));
			const statusCbs = (targetOpts?.statuses || []).map(sd => ({ key: sd.key, ...cb(sd.label || sd.key) }));
			const groupCbs = (targetOpts?.nc_groups || []).map(g => ({ key: g.gid, ...cb(g.display + ' (' + g.gid + ')') }));
			parts.push(
				h('h3', {}, _t('Request signatures from')),
				picker, chips,
				h('div', { class: 'lr-targets' },
					h('h4', { class: 'lr-muted' }, _t('Or whole groups (applies automatically, also to people added later):')),
					allCb.l,
					statusCbs.length ? h('div', { class: 'lr-targets-row' }, h('span', { class: 'lr-muted' }, _t('Statuses:')), ...statusCbs.map(c => c.l)) : null,
					groupCbs.length ? h('div', { class: 'lr-targets-row' }, h('span', { class: 'lr-muted' }, _t('Nextcloud groups:')), ...groupCbs.map(c => c.l)) : null),
				h('button', { class: 'lr-btn', style: 'margin-top:8px', onclick: async () => {
					const target = {
						all: allCb.i.checked || undefined,
						statuses: statusCbs.filter(c => c.i.checked).map(c => c.key),
						nc_groups: groupCbs.filter(c => c.i.checked).map(c => c.key),
					};
					const hasTarget = target.all || target.statuses.length || target.nc_groups.length;
					if (!picked.size && !hasTarget) { toast(_t('Select people or a group')); return; }
					try {
						const res = await api('/documents/' + d.id + '/requests', { method: 'POST', json: { person_ids: [...picked.keys()], ...(hasTarget ? { target } : {}) } });
						toast(_t('{n} requests created', { n: res.created }), false);
						expand(d);
					} catch (e) { toast(e.message); }
				} }, _t('Request signature')));
		}
		detail.append(lrSection({ title: d.title, aside: h('div', {}, ...parts) }));
		enhanceTables(detail);
		detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
	};

	if (canManage) {
		const titleIn = h('input', { required: true, maxlength: 255 });
		const bodyIn = h('textarea', { rows: 6, maxlength: 100000 });
		const fileIn = h('input', { type: 'file', accept: '.pdf,.jpg,.jpeg,.png,.heic' });
		const modeSel = h('select', { onchange: () => { bodyWrap.style.display = modeSel.value === 'text' ? '' : 'none'; fileWrap.style.display = modeSel.value === 'file' ? '' : 'none'; } },
			h('option', { value: 'text' }, _t('Text to accept')),
			h('option', { value: 'file' }, _t('File (PDF/image)')));
		const reqCb = h('input', { type: 'checkbox', checked: true });
		const bodyWrap = field(_t('Document text (plain text)'), bodyIn, 12);
		const fileWrap = field(_t('Document file'), fileIn, 12); fileWrap.style.display = 'none';
		const form = h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Title'), titleIn, 6), field(_t('Kind'), modeSel, 6),
			bodyWrap, fileWrap, checkField(_t('Required (must be signed)'), reqCb));
		const docDraft = persistForm(form, 'doc-new');
		nodes.push(lrSection({ title: _t('New document'), aside: h('div', {}, form,
			formActions(h('button', { class: 'lr-btn', onclick: async () => {
				if (!titleIn.value.trim()) { toast(_t('Title required')); return; }
				try {
					const d = await api('/documents', { method: 'POST', json: { title: titleIn.value.trim(), mode: modeSel.value, body_text: bodyIn.value || null, required: !!reqCb.checked } });
					if (fileIn.files?.length) {
						const fd = new FormData(); fd.append('file', fileIn.files[0]);
						const res = await fetch(API + '/documents/' + d.id + '/file', { method: 'POST', headers: { 'requesttoken': OC.requestToken }, body: fd });
						const data = await res.json().catch(() => ({}));
						if (!res.ok) throw new Error(data.error || _t('Error') + ' ' + res.status);
					}
					docDraft.clear(); toast(_t('Created'), false); viewDokumente();
				} catch (e) { toast(e.message); }
			} }, _t('Create document')))) }));
	}

	nodes.push(lv.el, detail);
	view(...nodes);
}

/* ---------- Konto-Zuordnung ---------- */

async function viewKontomapping() {
	const rows = await api('/persons/map-accounts');
	const nodes = [lrPageHeader({ title: _t('Match accounts'), lede: _t('Links people to their Nextcloud account via an exact e-mail match. Links are saved permanently.') })];
	if (!rows.length) {
		nodes.push(emptyState(_t('All people with an e-mail address are already linked.')));
		view(...nodes);
		return;
	}
	const picks = new Map();
	const tbody = h('tbody', {});
	for (const r of rows) {
		const sel = h('select', { 'aria-label': _t('Account for {name}', { name: r.name }), onchange: () => {
			if (sel.value) picks.set(r.person_id, sel.value); else picks.delete(r.person_id);
		} },
			h('option', { value: '' }, r.matches.length ? _t('– choose –') : _t('No account found')),
			...r.matches.map(m => h('option', { value: m.uid, selected: r.matches.length === 1 }, m.display_name + ' (' + m.uid + ')')));
		if (r.matches.length === 1) picks.set(r.person_id, r.matches[0].uid);
		tbody.append(h('tr', {},
			h('td', {}, h('a', { href: '#/adressen/' + r.person_id }, r.name)),
			h('td', {}, r.email),
			h('td', {}, sel)));
	}
	nodes.push(h('table', { class: 'lr-table' },
		h('thead', {}, h('tr', {}, h('th', {}, _t('Person')), h('th', {}, _t('E-mail')), h('th', {}, _t('Nextcloud account')))), tbody),
		h('button', { class: 'lr-btn', style: 'margin-top:8px', onclick: async () => {
			if (!picks.size) { toast(_t('Nothing to apply')); return; }
			try {
				const res = await api('/persons/map-accounts', { method: 'POST', json: { pairs: [...picks.entries()].map(([person_id, uid]) => ({ person_id, uid })) } });
				toast(_t('{n} accounts linked', { n: res.applied }) + (res.errors?.length ? ' · ' + _t('{n} failed', { n: res.errors.length }) : ''), !!res.errors?.length);
				viewKontomapping();
			} catch (e) { toast(e.message); }
		} }, _t('Apply links')));
	view(...nodes);
}

async function viewUebersicht() {
	const tiles = await api('/overview').catch(() => []);
	const name = (ME.user_name || '').split(' ')[0];
	const hr = new Date().getHours();
	const greet = hr < 11 ? _t('Good morning') : hr < 18 ? _t('Good afternoon') : _t('Good evening');
	const find = (t) => tiles.find(x => x.id === t);
	const cnt = (t) => find(t)?.count ?? 0;
	const actions = [];
	if (cnt('training.pending')) actions.push([_t('Review training records'), cnt('training.pending'), '/apps/leiterrunde_schulungen/#/nachweise']);
	if (cnt('finance.expenses.open')) actions.push([_t('Approve expenses'), cnt('finance.expenses.open'), '/apps/leiterrunde_finanzen/#/auslagen']);
	if (cnt('finance.obligations.overdue')) actions.push([_t('Overdue payments'), cnt('finance.obligations.overdue'), '/apps/leiterrunde_finanzen/#/zahlungen']);
	if (cnt('events.syncerrors')) actions.push([_t('Fix sync errors'), cnt('events.syncerrors'), '/apps/leiterrunde_veranstaltungen/#/liste']);
	if (find('finance.imap')?.severity === 'fail') actions.push([_t('IMAP fetch failed'), null, '/apps/leiterrunde_finanzen/#/import']);
	const nextEv = tiles.find(t => t.title === _t('Upcoming events'))?.items?.[0];
	const aktCnt = find('core.persons.active')?.kpi?.value;
	const lede = actions.length
		? actions.map(a => `${a[1] !== null ? a[1] + ' ' : ''}${a[0].toLowerCase()}`).join(', ') + '.'
		: (aktCnt ? aktCnt + ' ' + _t('active people') : '') + (nextEv ? ' · ' + _t('next event: {event} on {date}', { event: nextEv.label, date: formatDate(nextEv.date || (nextEv.sublabel || '').split(' ')[0]) }) + '.' : aktCnt ? '.' : _t('No open approvals.'));
	const nodes = [lrPageHeader({ kicker: _t('Leiterrunde · Overview'), title: `${greet}${name ? ', ' + name : ''}`, lede })];

	// KPI strip
	const kpis = [];
	const kp = tiles.filter(t => t.kpi).map(t => t.kpi);
	const akt = find('core.persons.active')?.kpi;
	if (akt) kpis.push({ label: _t('Active people'), value: akt.value, note: akt.label });
	const eins = find('training.status')?.kpi;
	if (eins) kpis.push({ label: _t('Cleared'), value: eins.value, note: eins.label });
	const off = find('finance.obligations.status')?.kpi;
	if (off) kpis.push({ label: _t('Open amount'), value: off.value, note: off.label, state: find('finance.obligations.status').severity === 'fail' ? 'warn' : undefined });
	if (kpis.length) nodes.push(lrFigures(kpis));

	const grid = lh('div', { class: 'lr-grid' });
	const chartOf = (t) => { const c = find(t)?.chart; return c ? renderChart(c) : null; };
	const money = chartOf('finance.cashflow');
	const todoList = lh('ul', { class: 'lr-actions-list' }, ...(actions.length
		? actions.map(([l, c, u]) => lh('li', {}, lh('a', { href: appUrl(u) }, l), lh('span', { class: 'cnt' }, c ?? '')))
		: [lh('li', {}, lh('span', { class: 'lr-muted' }, _t('All done. No open approvals.')))]));
	if (money) grid.append(lrSection({ kicker: _t('Finances'), title: _t('Income and expenditure, last 12 months'), span: 8, aside: money }));
	if (money || actions.length) grid.append(lrSection({ kicker: _t('To do'), span: money ? 4 : 12, aside: todoList }));
	const schul = find('training.status');
	const ev = find('events.registrations');
	const abl = find('training.expiring');
	const schulContent = lh('div', {});
	if (schul?.chart) schulContent.append(renderChart(schul.chart));
	if (abl?.items?.length) {
		schulContent.append(lh('table', { class: 'lr-table', style: 'margin-top:16px' },
			lh('thead', {}, lh('tr', {}, lh('th', {}, _t('Person')), lh('th', {}, _t('Check')), lh('th', {}, _t('Status')), lh('th', { class: 'num' }, _t('Valid until')))),
			lh('tbody', {}, ...abl.items.slice(0, 5).map(i => lh('tr', {},
				lh('td', {}, i.label), lh('td', {}, i.check || ''), lh('td', {}, sharedBadge(i.state)), lh('td', { class: 'num' }, i.valid ? formatDate(i.valid) : '–'))))));
	}
	const hasSchul = schulContent.childNodes.length > 0;
	if (hasSchul) grid.append(lrSection({ kicker: _t('Training'), title: _t('Training status'), span: ev?.chart ? 7 : 12, aside: schulContent }));
	if (ev?.chart) grid.append(lrSection({ kicker: _t('Events'), title: _t('Upcoming registrations'), span: hasSchul ? 5 : 12, aside: renderChart(ev.chart) }));
	const geb = find('core.birthdays');
	const stat = find('core.persons.status');
	const pflege = lh('div', {});
	const ohne = find('core.persons.noaccount');
	if (ohne?.items?.length) pflege.append(lh('p', { class: 'lr-small' }, _t('People without a Nextcloud account — {n}', { n: ohne.count })),
		lh('ul', { class: 'lr-actions-list' }, ...ohne.items.slice(0, 4).map(i => lh('li', {}, lh('a', { href: appUrl(ohne.url) }, i.label)))));
	const imap = find('finance.imap')?.items?.[0]?.label;
	if (imap) pflege.append(lh('p', { class: 'lr-small lr-muted' }, imap));
	const small = [geb, stat, pflege.childNodes.length ? true : null].filter(Boolean).length;
	if (geb || stat || pflege.childNodes.length) {
		const sp = small === 3 ? 4 : (small === 2 ? 6 : 12);
		if (geb) grid.append(lrSection({ kicker: _t('Birthdays · 14 days'), span: sp, aside: geb.items.length
			? lh('ul', { class: 'lr-actions-list' }, ...geb.items.map(i => lh('li', {}, i.label, lh('span', { class: 'cnt' },
				(i.date ? new Date(i.date + 'T00:00:00').toLocaleDateString(lrLocale(), { day: '2-digit', month: '2-digit' }) + ' · ' : '')
				+ (i.age ? _t('turns {age}', { age: i.age }) + ' · ' : '') + _n('in %n day', 'in %n days', i.days ?? 0)))))
			: lh('p', { class: 'lr-empty' }, _t('Nobody has a birthday in the next 14 days.')) }));
		if (stat?.chart) grid.append(lrSection({ kicker: _t('People by status'), span: sp, aside: renderChart(stat.chart) }));
		if (pflege.childNodes.length) grid.append(lrSection({ kicker: _t('Data maintenance'), span: sp, aside: pflege }));
	}
	nodes.push(grid);
	view(...nodes);
}

async function boot() {
	document.querySelector('.lr-modname') && (document.querySelector('.lr-modname').textContent = _t('People'));
	setupMobileNav(document.getElementById('leiterrunde-app'));
	ME = await api('/me');
	renderNav();
	window.addEventListener('hashchange', route);
	await route();
}

boot().catch(e => {
	console.error(e);
	document.getElementById('lr-view').textContent = _t('Error: ') + e.message;
});
