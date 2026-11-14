/* SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { renderChart, lrPageHeader, lrSection, lrFigures, badge as sharedBadge, h, appUrl, formatDate, l10n, lrLocale, BADGE_LABELS, toast, icon, skeleton, emptyState, setupMobileNav, enhanceTables } from './lr-common.mjs';
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
	{ route: '#/meine', label: _t('My profile'), cap: null, icon: 'account' },
];

function renderNav() {
	const nav = document.getElementById('lr-nav');
	nav.textContent = '';
	for (const item of NAV) {
		if (item.cap && !ME.caps[item.cap]) continue;
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
	v.textContent = '';
	v.append(skeleton());
	const personMatch = hash.match(/^#\/adressen\/(\d+)$/);
	try {
		if (personMatch) return await viewPersonDetail(parseInt(personMatch[1]));
		const fn = routes[hash] || viewUebersicht;
		await fn();
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

function personCard(p, columns) {
	const tel = intlPhone(p.mobile || p.phone);
	const addr = personAddress(p);
	const dlRow = (label, value) => value ? [h('dt', {}, label), h('dd', {}, value)] : [];
	const quick = h('div', { class: 'lr-pcard-actions' },
		tel ? h('a', { class: 'lr-iconbtn', href: 'tel:' + tel, title: _t('Call'), 'aria-label': _t('Call') }, icon('phone')) : null,
		tel ? h('a', { class: 'lr-iconbtn wa', href: 'https://wa.me/' + tel.slice(1), target: '_blank', rel: 'noopener noreferrer', title: 'WhatsApp', 'aria-label': 'WhatsApp' }, icon('whatsapp')) : null,
		addr ? h('a', { class: 'lr-iconbtn', href: 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(addr), target: '_blank', rel: 'noopener noreferrer', title: _t('Navigate'), 'aria-label': _t('Navigate') }, icon('map-marker')) : null,
		p.email ? h('a', { class: 'lr-iconbtn', href: 'mailto:' + p.email, title: p.email, 'aria-label': _t('Email') }, icon('email')) : null);
	const details = h('details', { class: 'lr-details lr-pcard-more' },
		h('summary', {}, _t('Details')),
		h('dl', { class: 'lr-pcard-dl' },
			...dlRow(_t('Email'), p.email),
			...dlRow(_t('Phone'), p.phone),
			...dlRow(_t('Mobile'), p.mobile),
			...dlRow(_t('Address'), addr),
			...dlRow(_t('Date of birth'), p.birthdate ? formatDate(p.birthdate) : (p.birth_year || '')),
			...dlRow(_t('Function'), p.function_label),
			...dlRow(_t('Notes'), p.notes),
			...columns.flatMap(f => dlRow(f.label, personFieldValue(p, f)))),
		h('a', { class: 'lr-btn quiet', href: '#/adressen/' + p.id }, _t('Open person')));
	const meta = [p.function_label, p.city].filter(Boolean).join(' · ');
	return h('div', { class: 'lr-pcard' },
		h('div', { class: 'lr-pcard-head' },
			h('a', { class: 'lr-pcard-name', href: '#/adressen/' + p.id }, p.last_name + ', ' + p.first_name),
			statusBadge(p.status)),
		meta ? h('div', { class: 'lr-small lr-muted' }, meta) : null,
		quick, details);
}

async function viewAdressen() {
	const allFields = await api('/persons/fields');
	const columns = allFields.filter(f => !f.plugin);
	const search = h('input', { placeholder: _t('Search …'), type: 'search' });
	const statusSel = h('select', {},
		h('option', { value: 'active', selected: true }, _t('Active only')),
		h('option', { value: '' }, _t('All statuses')),
		...personStatuses().map(s => h('option', { value: s }, statusLabelOf(s))));
	const list = h('div', {});
	let sortKey = 'last_name', sortDir = 1, persons = [];
	const sortVal = (p, k) => k === 'status' ? statusLabelOf(p.status || '')
		: (p[k] || '').toLowerCase();
	const sortSel = h('select', { 'aria-label': _t('Sort by'), onchange: e => {
		const [k, dir] = e.target.value.split(':');
		sortKey = k; sortDir = +dir; render();
	} },
		h('option', { value: 'last_name:1' }, _t('Last name A–Z')),
		h('option', { value: 'last_name:-1' }, _t('Last name Z–A')),
		h('option', { value: 'first_name:1' }, _t('First name A–Z')),
		h('option', { value: 'first_name:-1' }, _t('First name Z–A')),
		h('option', { value: 'status:1' }, _t('Status')),
		h('option', { value: 'function_label:1' }, _t('Function')));
	const render = () => {
		const sorted = [...persons].sort((a, b) => sortDir * String(sortVal(a, sortKey)).localeCompare(String(sortVal(b, sortKey)), lrLocale(), { numeric: true }));
		list.textContent = '';
		list.append(persons.length
			? h('div', { class: 'lr-cardsgrid' }, ...sorted.map(p => personCard(p, columns)))
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
	search.addEventListener('input', () => reload());
	statusSel.addEventListener('change', () => reload());
	const actions = [];
	if (ME.caps['settings.manage']) actions.push(h('button', { class: 'lr-btn secondary', onclick: viewPersonColumns }, _t('Person columns')));
	if (ME.caps['settings.manage']) actions.push(h('button', { class: 'lr-btn secondary', onclick: viewPersonStatuses }, _t('Statuses')));
	if (ME.caps['persons.import']) actions.push(h('button', { class: 'lr-btn secondary', onclick: importCsv }, _t('Import CSV')));
	if (ME.caps['persons.write']) actions.push(h('button', { class: 'lr-btn', onclick: () => viewPersonForm(null) }, _t('New person')));
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
	const nodes = [lrPageHeader({ kicker: _t('Leiterrunde · People'), title: p.first_name + ' ' + p.last_name, lede: statusBadge(p.status), actions }), statusFlow(p.status), grid];
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
					h('table', { class: 'lr-table' },
						h('thead', {}, h('tr', {}, h('th', {}, _t('Time')), h('th', {}, _t('User')), h('th', {}, _t('Field')), h('th', {}, _t('Old')), h('th', {}, _t('New')))),
						h('tbody', {}, ...audit.map(a => h('tr', {},
							h('td', {}, new Date(a.created_at * 1000).toLocaleString(lrLocale())),
							h('td', {}, a.user_id), h('td', {}, FIELD[a.field] || a.field),
							h('td', {}, shown(a.field, a.old_value)), h('td', {}, shown(a.field, a.new_value)))))));
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
	setMode(yearOnly);

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
	const days = h('input', { type: 'number', value: 30, min: 1, max: 365 });
	const list = h('div', {});
	const reload = async () => {
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

async function viewMeine() {
	const p = ME.person;
	const nodes = [lrPageHeader({ title: _t('My profile') })];
	nodes.push(p
		? h('p', {}, _t('Linked person') + ': ' + p.first_name + ' ' + p.last_name + ' (#' + p.id + ')')
		: h('p', {}, _t('Your account is not linked to a person.')));
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
