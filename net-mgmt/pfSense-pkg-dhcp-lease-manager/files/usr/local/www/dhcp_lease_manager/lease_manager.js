/*
 * lease_manager.js
 *
 * Copyright (c) 2026 Branson Matheson
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

(function () {
	'use strict';

	var STORE_KEY = 'dlm.prefs';
	var state = {
		data: null,
		sortKey: 'age',
		sortDir: 1,
		vendor: '',
		chart: 'type',
		selected: {},      /* key -> true */
		confirming: null,  /* pending static-mapping removal */
		timer: null
	};

	var $ = function (id) { return document.getElementById(id); };
	var tbody = document.querySelector('#dlm-table tbody');
	var menu = $('dlm-menu');

	/* ------------------------------------------------------------ prefs */
	function loadPrefs() {
		try {
			var p = JSON.parse(localStorage.getItem(STORE_KEY) || '{}');
			if (p.sortKey) { state.sortKey = p.sortKey; state.sortDir = p.sortDir || 1; }
			if (p.refresh) { $('dlm-refresh').value = String(p.refresh); }
			if (p.chart) { state.chart = p.chart; }
		} catch (e) { /* storage unavailable */ }
	}
	function savePrefs() {
		try {
			localStorage.setItem(STORE_KEY, JSON.stringify({
				sortKey: state.sortKey, sortDir: state.sortDir, chart: state.chart,
				refresh: Number($('dlm-refresh').value)
			}));
		} catch (e) { /* ignore */ }
	}

	/* -------------------------------------------------------- formatting */
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function duration(sec) {
		if (sec == null) { return ''; }
		var neg = sec < 0; sec = Math.abs(sec);
		var d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60);
		var s = d ? d + 'd ' + h + 'h' : h ? h + 'h ' + m + 'm' : m ? m + 'm' : Math.floor(sec) + 's';
		return neg ? '-' + s : s;
	}
	function ipKey(ip) {
		return ip.split('.').reduce(function (a, o) { return a * 256 + (parseInt(o, 10) || 0); }, 0);
	}
	function now() { return Math.floor(Date.now() / 1000); }
	function keyOf(l) { return l.mac + '|' + l.ip; }
	function typeInfo(t) { return state.data.types[t] || state.data.types.unknown; }
	/* Server enforces these too; the UI just hides what the user cannot do. */
	function can(what) { return !!(state.data.perms && state.data.perms[what]); }

	function setData(d) {
		state.data = d;
		d.leases.forEach(function (l, i) {
			l._idx = i;
			l.type_label = typeInfo(l.type).label;
		});
		/* Drop selections for leases that no longer exist. */
		var live = {};
		d.leases.forEach(function (l) { live[keyOf(l)] = true; });
		Object.keys(state.selected).forEach(function (k) { if (!live[k]) { delete state.selected[k]; } });
	}

	/* ----------------------------------------------------------- sorting */
	function sortValue(l, key) {
		switch (key) {
		case 'ip': return ipKey(l.ip);
		case 'age': return l.age == null ? Infinity : l.age;
		case 'ends': return l.ends == null ? Infinity : l.ends;
		case 'online': return l.online ? 0 : 1;
		case 'type_label': return l.type === 'unknown' ? '￿' : l.type_label.toLowerCase();
		default: return String(l[key] || '￿').toLowerCase();
		}
	}
	function compare(a, b) {
		var k = state.sortKey, va = sortValue(a, k), vb = sortValue(b, k);
		if (va < vb) { return -state.sortDir; }
		if (va > vb) { return state.sortDir; }
		return ipKey(a.ip) - ipKey(b.ip);
	}

	/* --------------------------------------------------------- filtering */
	function filtered() {
		var q = $('dlm-search').value.trim().toLowerCase();
		var iface = $('dlm-iface').value, st = $('dlm-state').value, ty = $('dlm-type').value;
		return state.data.leases.filter(function (l) {
			if (state.vendor && l.vendor !== state.vendor) { return false; }
			if (ty && l.type !== ty) { return false; }
			if (iface && l.if !== iface) { return false; }
			if (st === 'online' && !l.online) { return false; }
			if (st === 'dynamic' && l.is_static) { return false; }
			if (st === 'static' && !l.is_static) { return false; }
			if (st && ['online', 'dynamic', 'static'].indexOf(st) === -1 && l.state !== st) { return false; }
			if (q) {
				var hay = [l.ip, l.mac, l.hostname, l.descr, l.vendor, l.if_descr, l.type_label].join(' ').toLowerCase();
				if (hay.indexOf(q) === -1) { return false; }
			}
			return true;
		});
	}

	/* --------------------------------------------------------- rendering */
	var shown = [];

	function renderRows() {
		shown = filtered().sort(compare);
		var t = now(), html = [];
		shown.forEach(function (l) {
			var ti = typeInfo(l.type);
			var expires = l.ends == null ? (l.is_static ? '—' : '') : duration(l.ends - t);
			var vendorCls = l.vendor_kind === 'private' ? 'dlm-private' : l.vendor_kind === 'unknown' ? 'text-muted' : '';
			var typeTitle = l.type_source === 'manual' ? 'Set manually' : l.type_source === 'rule' ? 'Guessed from hostname/manufacturer' : '';
			var sel = !!state.selected[keyOf(l)];
			html.push(
				'<tr data-idx="' + l._idx + '" class="' + (l.state === 'expired' ? 'dlm-expired ' : '') + (sel ? 'dlm-selected' : '') + '">' +
				'<td><input type="checkbox" class="dlm-sel"' + (sel ? ' checked' : '') + ' /></td>' +
				'<td><i class="fa-solid fa-circle ' + (l.online ? 'text-success' : 'text-muted') + '" title="' + (l.online ? 'online' : 'offline') + '"></i></td>' +
				'<td>' + esc(l.ip) + '</td>' +
				'<td class="dlm-mono">' + esc(l.mac) + '</td>' +
				'<td class="dlm-type' + (l.type === 'unknown' ? ' text-muted' : '') + (l.type_source === 'manual' ? ' dlm-manual' : '') + '" title="' + typeTitle + '">' +
					'<i class="fa-solid ' + esc(ti.icon) + ' fa-fw"></i> ' + esc(ti.label) + '</td>' +
				'<td class="' + vendorCls + '">' + esc(l.vendor) + '</td>' +
				'<td>' + esc(l.hostname) + '</td>' +
				'<td>' + esc(l.descr) + '</td>' +
				'<td>' + esc(l.if_descr) + '</td>' +
				'<td title="' + (l.starts ? esc(new Date(l.starts * 1000).toLocaleString()) : '') + '">' + duration(l.age) + '</td>' +
				'<td title="' + (l.ends ? esc(new Date(l.ends * 1000).toLocaleString()) : '') + '">' + expires + '</td>' +
				'<td><span class="label ' + stateLabel(l) + '">' + esc(l.is_static && l.state !== 'static' ? l.state + ' · static' : l.state) + '</span></td>' +
				'</tr>'
			);
		});
		tbody.innerHTML = html.join('') || '<tr><td colspan="12" class="text-center text-muted">No leases match.</td></tr>';
		$('dlm-count').textContent = shown.length + ' of ' + state.data.leases.length + ' leases';

		var nSel = shown.filter(function (l) { return state.selected[keyOf(l)]; }).length;
		$('dlm-all').checked = shown.length > 0 && nSel === shown.length;
		$('dlm-all').indeterminate = nSel > 0 && nSel < shown.length;

		document.querySelectorAll('#dlm-table th[data-key]').forEach(function (th) {
			th.classList.remove('dlm-asc', 'dlm-desc');
			if (th.dataset.key === state.sortKey) { th.classList.add(state.sortDir > 0 ? 'dlm-asc' : 'dlm-desc'); }
		});
		renderBulk();
	}
	function stateLabel(l) {
		return { active: 'label-success', static: 'label-info', expired: 'label-default', declined: 'label-danger' }[l.state] || 'label-warning';
	}

	function renderChart() {
		var byType = state.chart === 'type', counts = {};
		state.data.leases.forEach(function (l) {
			var k = byType ? l.type : l.vendor;
			counts[k] = (counts[k] || 0) + 1;
		});
		var list = Object.keys(counts).map(function (v) { return [v, counts[v]]; })
			.sort(function (a, b) { return b[1] - a[1] || a[0].localeCompare(b[0]); });
		var max = list.length ? list[0][1] : 1, top = list.slice(0, 15), rest = list.slice(15);
		var active = byType ? $('dlm-type').value : state.vendor;
		var html = top.map(function (e) {
			var label = byType ? '<i class="fa-solid ' + esc(typeInfo(e[0]).icon) + ' fa-fw"></i> ' + esc(typeInfo(e[0]).label) : esc(e[0]);
			return '<button type="button" class="dlm-vbar' + (active === e[0] ? ' active' : '') + '" data-key="' + esc(e[0]) + '">' +
				'<span class="dlm-vname">' + label + '</span>' +
				'<span class="dlm-vtrack"><span class="dlm-vfill" style="width:' + (100 * e[1] / max).toFixed(1) + '%"></span></span>' +
				'<span class="dlm-vcount">' + e[1] + '</span></button>';
		});
		if (rest.length) {
			html.push('<div class="text-muted dlm-vrest">+ ' + rest.length + ' more (' +
				rest.reduce(function (a, e) { return a + e[1]; }, 0) + ' leases)</div>');
		}
		if (active) {
			html.push('<button type="button" class="btn btn-xs btn-default dlm-vclear">Clear filter</button>');
		}
		$('dlm-vendors').innerHTML = html.join('');
		document.querySelectorAll('.dlm-chart-tabs a').forEach(function (a) {
			a.classList.toggle('active', a.dataset.chart === state.chart);
		});
	}

	function fillSelect(sel, entries) {
		var cur = sel.value;
		while (sel.options.length > 1) { sel.remove(1); }
		entries.forEach(function (e) { sel.add(new Option(e[1], e[0])); });
		sel.value = cur;
		if (sel.value !== cur) { sel.value = ''; }
	}
	function renderSelects() {
		var ifs = {}, types = {};
		state.data.leases.forEach(function (l) {
			if (l.if) { ifs[l.if] = l.if_descr || l.if; }
			types[l.type] = typeInfo(l.type).label;
		});
		fillSelect($('dlm-iface'), Object.keys(ifs).sort().map(function (k) { return [k, ifs[k]]; }));
		fillSelect($('dlm-type'), Object.keys(types).sort(function (a, b) { return types[a].localeCompare(types[b]); })
			.map(function (k) { return [k, types[k]]; }));
	}

	function render() { renderChart(); renderRows(); }

	/* -------------------------------------------------- selection + bulk */
	function selectedLeases() {
		return state.data.leases.filter(function (l) { return state.selected[keyOf(l)]; });
	}
	function staticItems(leases) {
		return leases.filter(function (l) { return l.is_static && l.if && l.staticmap_index != null; })
			.map(function (l) { return { if: l.if, id: l.staticmap_index, mac: l.mac, label: l.hostname || l.ip }; });
	}
	function typeOptions(selectedType) {
		return Object.keys(state.data.types).filter(function (k) { return k !== 'unknown'; })
			.map(function (k) {
				return '<option value="' + esc(k) + '"' + (k === selectedType ? ' selected' : '') + '>' + esc(state.data.types[k].label) + '</option>';
			}).join('');
	}

	function renderBulk() {
		var bar = $('dlm-bulk');
		if (state.confirming) {
			var items = state.confirming;
			bar.className = 'alert alert-warning dlm-bulk';
			bar.innerHTML = '<strong>Remove ' + items.length + ' static mapping' + (items.length === 1 ? '' : 's') + '?</strong> ' +
				esc(items.slice(0, 6).map(function (i) { return i.label; }).join(', ')) + (items.length > 6 ? ', …' : '') +
				'<br /><span class="dlm-note">The devices return to dynamic addressing. The change is saved to the configuration; ' +
				'then click <em>Apply Changes</em> to reload the DHCP server.</span> ' +
				'<span class="dlm-actions"><button type="button" class="btn btn-sm btn-danger" data-act="confirm-del">' +
				'<i class="fa-solid fa-trash-can"></i> Remove</button> ' +
				'<button type="button" class="btn btn-sm btn-default" data-act="cancel-del">Cancel</button></span>';
			bar.style.display = 'block';
			return;
		}
		var sel = selectedLeases();
		if (!sel.length) { bar.style.display = 'none'; return; }
		var nStatic = staticItems(sel).length;
		bar.className = 'alert alert-info dlm-bulk';
		bar.innerHTML = '<strong>' + sel.length + ' selected</strong>' +
			'<span class="dlm-actions">' +
			(can('write') ?
				'<select class="form-control input-sm" data-act="bulk-type"><option value="">Set type…</option>' + typeOptions('') + '</select> ' +
				'<button type="button" class="btn btn-sm btn-default" data-act="bulk-reset-type"><i class="fa-solid fa-rotate-left"></i> Reset type</button> ' : '') +
			(can('static') ?
				'<button type="button" class="btn btn-sm btn-danger" data-act="bulk-del"' + (nStatic ? '' : ' disabled') + '>' +
				'<i class="fa-solid fa-trash-can"></i> Clear static mapping' + (nStatic === 1 ? '' : 's') + ' (' + nStatic + ')</button> ' : '') +
			'<button type="button" class="btn btn-sm btn-link" data-act="bulk-clear">Clear selection</button>' +
			'</span>';
		bar.style.display = 'block';
	}

	function message(kind, text) {
		$('dlm-msg').innerHTML = text ? '<div class="alert alert-' + kind + '">' + esc(text) + '</div>' : '';
	}

	/* ------------------------------------------------------------- server */
	function post(fields) {
		var body = new URLSearchParams(fields);
		if (window.csrfMagicName && window.csrfMagicToken) { body.set(window.csrfMagicName, window.csrfMagicToken); }
		return fetch(location.pathname, {
			method: 'POST', credentials: 'same-origin', body: body,
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
		}).then(function (r) {
			return r.json().catch(function () { throw new Error('Unexpected response (' + r.status + '). Your session may have expired; reload the page.'); });
		});
	}

	function setType(leases, type) {
		var macs = leases.map(function (l) { return l.mac; }).filter(Boolean);
		post({ dlm_action: 'settype', type: type, macs: JSON.stringify(macs) })
			.then(function (r) {
				if (!r.ok) { throw new Error(r.error || 'Failed to save'); }
				message('', '');
				refresh();
			})
			.catch(function (e) { message('danger', e.message); });
	}

	function removeStatic(items) {
		post({ dlm_action: 'delstatic', items: JSON.stringify(items.map(function (i) { return { if: i.if, id: i.id, mac: i.mac }; })) })
			.then(function (r) {
				if (!r.ok) { throw new Error((r.errors || []).join(' ') || r.error || 'Nothing was removed'); }
				/* Reload (GET) so pfSense's Apply Changes banner appears. */
				location.href = location.pathname;
			})
			.catch(function (e) { state.confirming = null; renderBulk(); message('danger', e.message); });
	}

	/* ------------------------------------------------------ context menu */
	function staticUrl(l) {
		/* services_dhcp_edit.php pre-fills these from the query string; the
		 * IP is left blank because the editor rejects addresses inside the
		 * dynamic pool and does not read ipaddr from the request anyway. */
		var p = new URLSearchParams({ if: l.if, mac: l.mac });
		if (l.cid) { p.set('cid', l.cid); }
		if (l.hostname) { p.set('hostname', l.hostname); }
		if (l.descr) { p.set('descr', l.descr); }
		return '/services_dhcp_edit.php?' + p.toString();
	}

	function menuItems(l) {
		var items = [];
		if (!can('static')) {
			/* no static mapping actions */
		} else if (!l.is_static && l.if) {
			items.push(['fa-plus', 'Add static mapping…', function () { location.href = staticUrl(l); }]);
		} else if (l.is_static && l.if && l.staticmap_index != null) {
			items.push(['fa-pencil', 'Edit static mapping', function () {
				location.href = '/services_dhcp_edit.php?if=' + encodeURIComponent(l.if) + '&id=' + encodeURIComponent(l.staticmap_index);
			}]);
			items.push(['fa-trash-can', 'Remove static mapping…', function () {
				state.confirming = staticItems([l]); renderBulk();
				$('dlm-bulk').scrollIntoView({ block: 'nearest' });
			}, 'text-danger']);
		} else {
			items.push(['fa-ban', 'Interface unknown – cannot add static mapping', null]);
		}
		if (items.length) { items.push(null); }
		if (can('write')) {
			items.push(['fa-tag', 'Set device type…', function () { openMenu(lastEvent, typeMenuItems(l)); return true; }]);
		}
		if (can('write') && l.type_source === 'manual') {
			items.push(['fa-rotate-left', 'Reset type to automatic', function () { setType([l], ''); }]);
		}
		items.push(['fa-filter', 'Show only ' + l.vendor, function () { state.vendor = l.vendor; state.chart = 'vendor'; render(); }]);
		items.push(['fa-filter', 'Show only ' + l.type_label, function () { $('dlm-type').value = l.type; state.chart = 'type'; render(); }]);
		items.push(null);
		items.push([state.selected[keyOf(l)] ? 'fa-square-minus' : 'fa-square-check',
			state.selected[keyOf(l)] ? 'Deselect' : 'Select', function () { toggleSel(l); renderRows(); }]);
		items.push(['fa-copy', 'Copy IP address', function () { copy(l.ip); }]);
		items.push(['fa-copy', 'Copy MAC address', function () { copy(l.mac); }]);
		return items;
	}

	function typeMenuItems(l) {
		var targets = state.selected[keyOf(l)] && selectedLeases().length > 1 ? selectedLeases() : [l];
		var items = [['fa-arrow-left', 'Back', function () { openMenu(lastEvent, menuItems(l)); return true; }], null];
		if (targets.length > 1) { items.push(['fa-circle-info', 'Applies to ' + targets.length + ' selected', null]); }
		Object.keys(state.data.types).forEach(function (k) {
			if (k === 'unknown') { return; }
			var t = state.data.types[k];
			items.push([t.icon, t.label + (l.type === k && targets.length === 1 ? '  ✓' : ''), function () { setType(targets, k); }]);
		});
		return items;
	}

	var lastEvent = null;
	function openMenu(ev, items) {
		lastEvent = { clientX: ev.clientX, clientY: ev.clientY };
		menu.innerHTML = '';
		items.forEach(function (it) {
			var li = document.createElement('li');
			if (!it) { li.className = 'divider'; menu.appendChild(li); return; }
			var a = document.createElement('a');
			a.href = '#';
			a.innerHTML = '<i class="fa-solid ' + esc(it[0]) + ' fa-fw"></i> ' + esc(it[1]);
			if (it[3]) { a.className = it[3]; }
			if (it[2]) {
				a.addEventListener('click', function (e) {
					e.preventDefault(); e.stopPropagation();
					if (it[2]() !== true) { closeMenu(); }   /* true = submenu replaced menu */
				});
			} else {
				li.className = 'disabled';
				a.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); });
			}
			li.appendChild(a);
			menu.appendChild(li);
		});
		menu.style.display = 'block';
		menu.style.maxHeight = (window.innerHeight - 16) + 'px';
		var w = menu.offsetWidth, h = menu.offsetHeight;
		menu.style.left = Math.max(8, Math.min(ev.clientX, window.innerWidth - w - 8)) + 'px';
		menu.style.top = Math.max(8, Math.min(ev.clientY, window.innerHeight - h - 8)) + 'px';
	}
	function closeMenu() { menu.style.display = 'none'; }
	function copy(text) {
		if (navigator.clipboard) { navigator.clipboard.writeText(text).catch(function () {}); }
	}
	function toggleSel(l, on) {
		var k = keyOf(l);
		if (on === undefined) { on = !state.selected[k]; }
		if (on) { state.selected[k] = true; } else { delete state.selected[k]; }
	}

	/* ----------------------------------------------------------- refresh */
	function refresh() {
		fetch(location.pathname + '?format=json', { credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
			.then(function (d) { setData(d); renderSelects(); render(); })
			.catch(function () { /* session expired or network error: keep last data */ });
	}
	function scheduleRefresh() {
		clearInterval(state.timer);
		var s = Number($('dlm-refresh').value);
		if (s > 0) {
			state.timer = setInterval(function () {
				if (!state.confirming && menu.style.display !== 'block') { refresh(); }
			}, s * 1000);
		}
		savePrefs();
	}

	/* ------------------------------------------------------------ wiring */
	document.querySelectorAll('#dlm-table th[data-key]').forEach(function (th) {
		th.addEventListener('click', function () {
			var k = th.dataset.key;
			if (state.sortKey === k) { state.sortDir = -state.sortDir; } else { state.sortKey = k; state.sortDir = 1; }
			savePrefs();
			renderRows();
		});
	});
	['dlm-search', 'dlm-iface', 'dlm-state'].forEach(function (id) {
		$(id).addEventListener('input', renderRows);
		$(id).addEventListener('change', renderRows);
	});
	$('dlm-type').addEventListener('change', render);
	$('dlm-refresh').addEventListener('change', scheduleRefresh);

	$('dlm-all').addEventListener('change', function () {
		var on = this.checked;
		shown.forEach(function (l) { toggleSel(l, on); });
		renderRows();
	});
	var lastClicked = null;
	tbody.addEventListener('click', function (e) {
		var cb = e.target.closest('.dlm-sel');
		if (!cb) { return; }
		var tr = cb.closest('tr[data-idx]'), l = state.data.leases[Number(tr.dataset.idx)];
		var pos = shown.indexOf(l);
		if (e.shiftKey && lastClicked !== null && shown[lastClicked]) {
			/* Shift-click selects the range. */
			var a = Math.min(lastClicked, pos), b = Math.max(lastClicked, pos);
			for (var i = a; i <= b; i++) { toggleSel(shown[i], cb.checked); }
		} else {
			toggleSel(l, cb.checked);
		}
		lastClicked = pos;
		renderRows();
	});

	$('dlm-bulk').addEventListener('click', function (e) {
		var b = e.target.closest('[data-act]');
		if (!b || b.tagName === 'SELECT') { return; }
		switch (b.dataset.act) {
		case 'bulk-reset-type': setType(selectedLeases(), ''); break;
		case 'bulk-del': state.confirming = staticItems(selectedLeases()); renderBulk(); break;
		case 'bulk-clear': state.selected = {}; renderRows(); break;
		case 'cancel-del': state.confirming = null; renderBulk(); break;
		case 'confirm-del': b.disabled = true; removeStatic(state.confirming); break;
		}
	});
	$('dlm-bulk').addEventListener('change', function (e) {
		if (e.target.dataset.act === 'bulk-type' && e.target.value) { setType(selectedLeases(), e.target.value); }
	});

	$('dlm-vendors').addEventListener('click', function (e) {
		var b = e.target.closest('.dlm-vbar');
		if (b) {
			if (state.chart === 'type') {
				$('dlm-type').value = $('dlm-type').value === b.dataset.key ? '' : b.dataset.key;
			} else {
				state.vendor = state.vendor === b.dataset.key ? '' : b.dataset.key;
			}
			render();
			return;
		}
		if (e.target.closest('.dlm-vclear')) {
			if (state.chart === 'type') { $('dlm-type').value = ''; } else { state.vendor = ''; }
			render();
		}
	});
	document.querySelectorAll('.dlm-chart-tabs a').forEach(function (a) {
		a.addEventListener('click', function (e) {
			e.preventDefault();
			state.chart = a.dataset.chart;
			savePrefs();
			renderChart();
		});
	});

	tbody.addEventListener('contextmenu', function (e) {
		var tr = e.target.closest('tr[data-idx]');
		if (!tr) { return; }
		e.preventDefault();
		openMenu(e, menuItems(state.data.leases[Number(tr.dataset.idx)]));
	});
	document.addEventListener('click', function (e) { if (!menu.contains(e.target)) { closeMenu(); } });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeMenu(); } });
	window.addEventListener('scroll', function (e) { if (!menu.contains(e.target)) { closeMenu(); } }, true);

	loadPrefs();
	setData(JSON.parse($('dlm-data').textContent));
	renderSelects();
	render();
	scheduleRefresh();
}());
