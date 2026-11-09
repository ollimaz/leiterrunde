/* SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { h, api, toast, l10n } from './lr-common.mjs';
const { t: _t, n: _n } = l10n('leiterrunde');
const LBL = {
	'Leaders': _t('Leaders'), 'Board': _t('Board'), 'Treasurer': _t('Treasurer'),
	'Training officers': _t('Training officers'), 'Conduct certificate access': _t('Conduct certificate access'),
	'Leiterrunde admin': _t('Leiterrunde admin'),
	'App access': _t('App access'), 'Read people': _t('Read people'), 'Write people': _t('Write people'),
	'Import people': _t('Import people'), 'Export people': _t('Export people'), 'Delete people': _t('Delete people'),
	'Read change log': _t('Read change log'), 'Manage settings': _t('Manage settings'),
	'Certificates of conduct': _t('Certificates of conduct'), 'Receipts': _t('Receipts'),
	'Person files': _t('Person files'),
};
const lbl = (x) => LBL[x] || _t(x);

const cap = (p, o) => api('leiterrunde', p, o);
const msg = (el, ok, text) => { el.textContent = text; el.className = ok ? 'lr-ok' : 'lr-err'; };

const ROLE_DESCR = {
	leiter: _t('All supervised leaders'),
	vorstand: _t('Leadership team / board'),
	kasse: _t('Treasurer (female role)'),
	schulung: _t('Responsible for records and checks'),
	fz: _t('Only role with access to certificates of conduct'),
	admin: _t('Full administration of the Leiterrunde app'),
};
const WARN_LABELS = { training: _t('Training'), hours: _t('Training hours'), finance: _t('Finances'), fz: _t('Conduct certificate access') };

async function rolesPage() {
	const root = document.getElementById('lr-settings-roles');
	if (!root) return;
	root.prepend(h('p', { class: 'settings-hint' },
		h('a', { href: '#lr-settings-roles' }, _t('Roles & permissions')), ' · ',
		h('a', { href: '#lr-settings-storage' }, _t('Document storage')), ' · ',
		h('a', { href: '#lr-settings-notify' }, _t('Notifications'))));
	const [data, groups] = await Promise.all([cap('/settings/roles'), cap('/groups')]);
	const roleIds = Object.keys(data.roles);
	const msgEl = h('span', { style: 'margin-left:8px' });

	// Warnungen
	const warn = document.getElementById('lr-roles-warnings');
	if (warn && data.warnings) data.warnings.forEach(wtxt => warn.append(
		h('p', { class: 'lr-warn' }, wtxt)));

	// role-to-group rows (chips + add select)
	const groupDiv = document.getElementById('lr-roles-groups');
	const roleGroups = {};
	for (const r of roleIds) roleGroups[r] = [...(data.role_groups[r] || [])];
	const list = h('div', { class: 'lr-rolelist' });
	for (const r of roleIds) {
		const chips = h('div', { class: 'lr-chips' });
		const render = () => {
			chips.textContent = '';
			for (const g of roleGroups[r]) chips.append(h('span', { class: 'lr-chip' }, g,
				h('button', { class: 'lr-chip-x', title: _t('Remove'), onclick: () => { roleGroups[r] = roleGroups[r].filter(x => x !== g); render(); } }, '×')));
			const remaining = groups.filter(g => !roleGroups[r].includes(g));
			if (remaining.length) {
				const sel = h('select', { class: 'lr-addgroup' }, h('option', { value: '' }, _t('Add group…')),
					...remaining.map(g => h('option', { value: g }, g)));
				sel.addEventListener('change', () => { if (sel.value) { roleGroups[r].push(sel.value); render(); } });
				chips.append(sel);
			}
		};
		render();
		const cnt = h('span', { class: 'lr-pill' }, '…');
		Promise.all(roleGroups[r].map(async g => {
			try { return (await fetch('/ocs/v2.php/cloud/groups/' + g + '/users?format=json', { headers: { 'OCS-APIRequest': 'true' } })).json(); } catch { return null; }
		})).then(rs => { const n = rs.filter(Boolean).reduce((a, x) => a + (x?.ocs?.data?.users?.length || 0), 0); cnt.textContent = _n('%n member', '%n members', n); });
		list.append(h('div', { class: 'lr-rolerow' },
			h('div', { class: 'lr-rolename' }, h('strong', {}, lbl(data.roles[r])), cnt),
			h('div', { class: 'lr-roledesc settings-hint' }, ROLE_DESCR[r] || ''),
			chips));
	}
	groupDiv.append(h('h3', { class: 'lr-subhead' }, _t('Roles and groups')), list);

	// Berechtigungs-Matrix
	const matrix = document.getElementById('lr-roles-matrix');
	const cb = {};
	const cols = [...roleIds, 'any'];
	const colLabel = (r) => r === 'any' ? _t('All members') : lbl(data.roles[r]);
	const table = h('table', { class: 'lr-matrix' });
	const head = h('tr', {}, h('th', { class: 'lr-capcol' }, _t('Capability')),
		...cols.map(r => h('th', { class: 'lr-rolecol', title: colLabel(r) }, colLabel(r).replace(/([a-z])([A-Z])/g, '$1\u00AD$2'))));
	table.append(h('thead', {}, head));
	const tbody = h('tbody', {});
	let lastGroup = null;
	for (const c of data.capabilities) {
		if (c.group !== lastGroup) {
			lastGroup = c.group;
			tbody.append(h('tr', { class: 'lr-grphead' }, h('td', { colspan: cols.length + 1 }, c.group)));
		}
		const tr = h('tr', { class: c.locked ? 'locked' : '' });
		const capCell = h('td', { class: 'lr-capcol' }, lbl(c.label),
			h('br'), h('code', { class: 'lr-capid' }, c.id));
		if (c.locked) {
			const NS = 'http://www.w3.org/2000/svg';
			const lock = document.createElementNS(NS, 'svg');
			lock.setAttribute('viewBox', '0 0 16 16');
			lock.setAttribute('class', 'lr-lock');
			lock.setAttribute('aria-hidden', 'true');
			const p = document.createElementNS(NS, 'path');
			p.setAttribute('d', 'M8 1a3 3 0 0 0-3 3v2H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-1V4a3 3 0 0 0-3-3zm-1.5 3a1.5 1.5 0 1 1 3 0v2h-3z');
			p.setAttribute('fill', 'currentColor');
			lock.append(p);
			capCell.append(h('div', { class: 'lr-lockhint' }, lock, ' ' + _t('Locked to conduct certificate access')));
		}
		tr.append(capCell);
		for (const r of cols) {
			const box = h('input', { type: 'checkbox', disabled: !!c.locked });
			box.id = 'cb-' + c.id + '-' + r;
			box.checked = (data.role_caps[c.id] || []).includes(r) || (c.locked && (c.locked || []).includes(r));
			cb[c.id + ':' + r] = box;
			tr.append(h('td', { class: 'lr-cell' }, box, h('label', { class: 'checkbox-label', for: box.id })));
		}
		
		tbody.append(tr);
	}
	table.append(tbody);
	matrix.append(h('h3', { class: 'lr-subhead' }, _t('Permissions')), h('div', { class: 'lr-matrixwrap' }, table));
	const save = async () => {
		const role_groups = {}, role_caps = {};
		roleIds.forEach(r => { role_groups[r] = [...roleGroups[r]]; });
		for (const c of data.capabilities) {
			if (c.locked) { role_caps[c.id] = c.locked; continue; }
			role_caps[c.id] = [...roleIds, 'any'].filter(r => cb[c.id + ':' + r].checked);
			if ((c.defaults || []).includes('nca') || (data.role_caps[c.id] || []).includes('nca')) {
				role_caps[c.id].push('nca');
			}
		}
		try {
			await cap('/settings/roles', { method: 'PUT', json: { role_groups, role_caps } });
			msg(msgEl, true, _t('Saved'));
		} catch (e) { msg(msgEl, false, e.message); }
	};
	const restoreBtn = h('button', { class: 'lr-btn secondary', onclick: () => {
		for (const c of data.capabilities) {
			for (const r of [...roleIds, 'any']) cb[c.id + ':' + r].checked = (c.locked ? c.locked : (c.defaults || [])).includes(r);
		}
		msg(msgEl, true, _t('Reset to defaults – the change takes effect only after "Save"'));
	} }, _t('Restore defaults'));
	const mainSave = document.getElementById('lr-roles-save');
	if (mainSave) mainSave.addEventListener('click', save);
	root.append(h('p', {}, restoreBtn, msgEl));
}

async function storagePage() {
	const root = document.getElementById('lr-settings-storage');
	if (!root) return;
	const s = await cap('/settings/storage');
	const form = document.getElementById('lr-storage-form');
	const msgEl = h('span', { style: 'margin-left:8px' });
	let uid = s.storage_user || '';
	const uidEl = window.LeiterrundeUI.userPicker({ value: uid || null, onChange: (id) => { uid = id || ''; } });
	const uidHint = h('p', { class: 'settings-hint' }, _t('Leave empty to store files in the uploader’s own account.'));
	form.append(h('label', {}, _t('Storage user')), uidEl, uidHint);
	const sanitize = (val) => {
		let out = String(val ?? '').replace(/[/\\\0\x00-\x1F]+/g, '_').trim();
		return (out === '' || out === '.' || out === '..') ? '_' : out;
	};
	const example = { jahr: new Date().getFullYear(), monat: String(new Date().getMonth() + 1).padStart(2, '0'), datum: new Date().toISOString().slice(0, 10), vorname: 'Max', nachname: 'Mustermann', typ: 'Erste Hilfe', kategorie: 'Material', veranstaltung: 'Sommerlager', id: '42' };
	const renderPath = (tpl, vars) => {
		if (tpl.startsWith('/') || tpl.split('/').some(seg => seg === '..')) return null;
		return tpl.replace(/\{(\w+)\}/g, (m, k) => sanitize(vars[k] ?? ''));
	};
	const pathInputs = {};
	for (const t of s.types) {
		const input = h('input', { type: 'text', class: 'lr-path', value: t.path });
		pathInputs[t.id] = input;
		const preview = h('code', { class: 'settings-hint' });
		const update = () => {
			const unknown = [...input.value.matchAll(/\{(\w+)\}/g)].map((m) => m[1]).filter((k) => !t.placeholders.includes(k));
			const r = renderPath(input.value, example);
			const invalid = r === null || unknown.length > 0;
			preview.textContent = r === null
				? _t('Invalid: ".." or leading "/"')
				: unknown.length ? _t('Unknown placeholder: ') + unknown.map((k) => '{' + k + '}').join(', ') : _t('Preview: ') + r;
			preview.style.color = invalid ? 'var(--color-element-error)' : '';
		};
		input.addEventListener('input', update);
		update();
		const chips = h('div', {}, ...t.placeholders.map(ph => h('button', {
			class: 'lr-btn secondary lr-btn-sm', style: 'margin:2px',
			onclick: (e) => { e.preventDefault(); input.setRangeText('{' + ph + '}', input.selectionStart, input.selectionEnd, 'end'); update(); }
		}, '{' + ph + '}')));
		const reset = h('button', { class: 'lr-btn secondary lr-btn-sm', onclick: (e) => { e.preventDefault(); input.value = t.defaultPath || t.path; update(); } }, _t('Default'));
		form.append(h('div', { class: 'lr-docpath' }, h('div', { class: 'lr-subhead' }, lbl(t.label)), chips,
			h('div', { class: 'lr-path-row' }, input, reset), h('p', {}, preview)));
	}
	document.getElementById('lr-storage-save')?.addEventListener('click', async () => {
		try {
			const paths = {};
			for (const t of s.types) paths[t.id] = pathInputs[t.id].value;
			await cap('/settings/storage', { method: 'PUT', json: { storage_user: uid, paths } });
			msg(msgEl, true, _t('Saved'));
		} catch (e) { msg(msgEl, false, e.message); }
	});
	document.getElementById('lr-storage-save')?.after(msgEl);
}

async function notifyPage() {
	const root = document.getElementById('lr-settings-notify');
	if (!root) return;
	const [s, roles] = await Promise.all([cap('/settings'), cap('/settings/roles')]);
	const form = document.getElementById('lr-notify-form');
	const msgEl = h('span', { style: 'margin-left:8px' });
	const toggles = [['notify_mail', _t('Notify by email')], ['notify_nc', _t('Send Nextcloud notification')], ['notify_person', _t('Also notify the affected person')]];
	for (const [k, label] of toggles) {
		const c = h('input', { type: 'checkbox', class: 'checkbox' });
		c.checked = s[k] === '1';
		c.id = 'lr-' + k;
		form.append(h('p', {}, h('label', { class: 'lr-check' }, c, ' ' + label)));
	}
	const roleIds = Object.keys(roles.roles);
	const roleSels = {};
	for (const [t, label] of [['training', WARN_LABELS.training], ['hours', WARN_LABELS.hours], ['finance', WARN_LABELS.finance], ['fz', _t('Certificates of conduct (locked: conduct certificate access)')]]) {
		const boxes = roleIds.map(r => {
			const c = h('input', { type: 'checkbox', class: 'checkbox', disabled: t === 'fz' });
			c.checked = t === 'fz' ? r === 'fz' : (JSON.parse(s['notify_roles_' + t] || '[]')).includes(r);
			return c;
		});
		roleSels[t] = boxes;
		form.append(h('p', {}, h('strong', {}, label + ': '), ...roleIds.map((r, i) => h('label', { class: 'lr-check', style: 'margin:0 12px 0 0; display:inline-flex' }, boxes[i], ' ' + lbl(roles.roles[r])))));
	}
	document.getElementById('lr-notify-save')?.addEventListener('click', async () => {
		try {
			const body = {};
			for (const [k] of toggles) body[k] = document.getElementById('lr-' + k).checked ? '1' : '0';
			for (const t of ['training', 'hours', 'finance']) {
				body['notify_roles_' + t] = JSON.stringify(roleIds.filter((r, i) => roleSels[t][i].checked));
			}
			await cap('/settings', { method: 'PUT', json: body });
			msg(msgEl, true, _t('Saved'));
		} catch (e) { msg(msgEl, false, e.message); }
	});
	document.getElementById('lr-notify-save')?.after(msgEl);
}

async function modulesPage() {
	const root = document.getElementById('lr-settings-modules');
	if (root) root.remove();
}

rolesPage(); storagePage(); notifyPage(); modulesPage();
