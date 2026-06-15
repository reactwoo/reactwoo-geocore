/**
 * Human-friendly visibility rule builder (syncs with portable rule-set JSON).
 *
 * @package ReactWoo_Geo_Core
 */
(function (window, document, $) {
	'use strict';

	var SCHEMA = 2;

	function t(key) {
		var i18n = window.rwgcRuleBuilderI18n || {};
		return i18n[key] || key;
	}

	function ctx() {
		var c = window.rwgcRuleBuilderContext || window.rwgcPortableTargetingAssist || {};
		if (typeof c !== 'object' || !c) {
			c = {};
		}
		if ((!c.countries || !c.countries.length) && window.rwgcGeoCountryOptions && typeof window.rwgcGeoCountryOptions === 'object') {
			var co = [];
			Object.keys(window.rwgcGeoCountryOptions).forEach(function (code) {
				co.push({ code: String(code).toUpperCase(), label: String(window.rwgcGeoCountryOptions[code] || code) });
			});
			co.sort(function (a, b) {
				return a.label.localeCompare(b.label);
			});
			c = Object.assign({}, c, { countries: co });
		}
		return c;
	}

	function uid() {
		return 'c_' + Math.random().toString(36).slice(2, 10);
	}

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : String(s);
		return d.innerHTML;
	}

	var PAGE_VERSION_PATTERN = /^[a-zA-Z0-9_-]{1,80}$/;

	var FIELD_DEFS = [
		{ key: 'country', portableType: 'country', pro: false, multi: true },
		{ key: 'page_version_url', portableType: 'page_version_url', pro: false, multi: false },
		{ key: 'ga4_audience', portableType: 'audience', pro: true, multi: true },
		{ key: 'ads_campaign', portableType: 'campaign', pro: true, multi: true },
		{ key: 'utm_campaign', portableType: 'utm_campaign', pro: true, multi: false },
		{ key: 'utm_source', portableType: 'utm_source', pro: true, multi: false },
		{ key: 'utm_medium', portableType: 'utm_medium', pro: true, multi: false },
		{ key: 'device_type', portableType: 'device_type', pro: false, multi: true },
		{ key: 'logged_in', portableType: 'logged_in', pro: false, multi: false },
		{ key: 'weather_facet', portableType: 'weather_facet', pro: true, multi: true, requiresWeather: true },
	];

	var DEVICE_OPTIONS = [
		{ v: 'mobile', l: 'Mobile' },
		{ v: 'tablet', l: 'Tablet' },
		{ v: 'desktop', l: 'Desktop' },
	];

	function fieldMeta(key) {
		for (var i = 0; i < FIELD_DEFS.length; i++) {
			if (FIELD_DEFS[i].key === key) {
				return FIELD_DEFS[i];
			}
		}
		return null;
	}

	function uiLabelForField(key) {
		switch (key) {
			case 'country':
				return t('fieldCountry');
			case 'ga4_audience':
				return t('fieldGa4Audience');
			case 'ads_campaign':
				return t('fieldAdsCampaign');
			case 'utm_campaign':
				return t('fieldUtmCampaign');
			case 'utm_source':
				return t('fieldUtmSource');
			case 'utm_medium':
				return t('fieldUtmMedium');
			case 'device_type':
				return t('fieldDevice');
			case 'logged_in':
				return t('fieldLoggedIn');
			case 'page_version_url':
				return t('fieldPageVersion');
			case 'weather_facet':
				return t('fieldWeatherFacet');
			default:
				return key;
		}
	}

	function portableTypeToField(pt) {
		var map = {
			country: 'country',
			audience: 'ga4_audience',
			campaign: 'ads_campaign',
			utm_campaign: 'utm_campaign',
			utm_source: 'utm_source',
			utm_medium: 'utm_medium',
			device: 'device_type',
			device_type: 'device_type',
			logged_in: 'logged_in',
			page_version_url: 'page_version_url',
			weather_facet: 'weather_facet',
		};
		return map[pt] || null;
	}

	function pageVersionCtx(c) {
		var pv = c.page_version || {};
		return {
			document: pv.document || null,
			pages: Array.isArray(pv.pages) ? pv.pages : [],
		};
	}

	function defaultPageVersionPageId(c) {
		var pvc = pageVersionCtx(c);
		if (pvc.document && pvc.document.id) {
			return parseInt(pvc.document.id, 10) || 0;
		}
		return 0;
	}

	function pagePathById(pageId, c) {
		var id = parseInt(pageId, 10) || 0;
		if (!id) {
			return '';
		}
		var pvc = pageVersionCtx(c);
		if (pvc.document && parseInt(pvc.document.id, 10) === id && pvc.document.path) {
			return pvc.document.path;
		}
		var pages = pvc.pages || [];
		for (var i = 0; i < pages.length; i++) {
			if (parseInt(pages[i].id, 10) === id) {
				return pages[i].path || '';
			}
		}
		return '';
	}

	function sanitizePageVersionInput(raw, basePath) {
		var s = String(raw || '').trim();
		if (!s) {
			return { version: '', error: t('pageVersionInvalid') };
		}
		if (s.indexOf('://') !== -1) {
			try {
				var u = document.createElement('a');
				u.href = s;
				s = (u.pathname || '').replace(/^\//, '');
			} catch (e) {
				/* keep s */
			}
		}
		s = s.replace(/^\/+/, '');
		var gc = '/_gc/';
		var gcIdx = s.indexOf(gc);
		if (gcIdx !== -1) {
			s = s.slice(gcIdx + gc.length);
		} else if (s.indexOf('_gc/') === 0) {
			s = s.slice(4);
		}
		if (basePath) {
			var bp = String(basePath).replace(/^\/+|\/+$/g, '');
			if (bp && s.indexOf(bp + '/_gc/') === 0) {
				s = s.slice((bp + '/_gc/').length);
			} else if (bp && s.indexOf(bp + '/') === 0) {
				s = s.slice(bp.length + 1);
			}
		}
		s = s.split('/').pop() || s;
		s = s.replace(/^\/+/, '').replace(/\/+$/, '');
		if (!s) {
			return { version: '', error: t('pageVersionInvalid') };
		}
		if (s.length > 80) {
			return { version: '', error: t('pageVersionLength') };
		}
		if (!PAGE_VERSION_PATTERN.test(s)) {
			return { version: '', error: t('pageVersionChars') };
		}
		return { version: s, error: '' };
	}

	function pageVersionPathPrefix(basePath) {
		var base = String(basePath || '');
		return base === '/' ? '' : base;
	}

	function pageVersionPreviewPath(pageId, version, c) {
		var base = pagePathById(pageId, c);
		if (!base || !version) {
			return '';
		}
		return pageVersionPathPrefix(base) + '/_gc/' + version;
	}

	function pageVersionAbsoluteUrl(relativePath, c) {
		if (!relativePath) {
			return '';
		}
		var site = (c && c.site_url) ? String(c.site_url) : '';
		if (!site && typeof window !== 'undefined' && window.location && window.location.origin) {
			site = window.location.origin + '/';
		}
		if (!site) {
			return '/' + String(relativePath).replace(/^\/+/, '');
		}
		try {
			return new URL(String(relativePath).replace(/^\//, ''), site).href;
		} catch (e) {
			return site.replace(/\/?$/, '/') + String(relativePath).replace(/^\/+/, '');
		}
	}

	function opUiToPortable(ui) {
		var m = {
			is: 'is',
			is_not: 'is_not',
			includes: 'in',
			excludes: 'not_in',
			empty: 'empty',
			not_empty: 'not_empty',
		};
		return m[ui] || 'in';
	}

	function opPortableToUi(p) {
		var m = {
			is: 'is',
			is_not: 'is_not',
			in: 'includes',
			not_in: 'excludes',
			empty: 'empty',
			not_empty: 'not_empty',
		};
		return m[p] || 'includes';
	}

	function parseDoc(raw) {
		var s = typeof raw === 'string' ? raw.trim() : '';
		if (!s) {
			return defaultDoc();
		}
		try {
			var d = JSON.parse(s);
			if (!d || typeof d !== 'object') {
				return defaultDoc();
			}
			return normalizeDocShape(d);
		} catch (e) {
			return { error: true, parseError: e };
		}
	}

	function defaultDoc() {
		return {
			schema_version: SCHEMA,
			enabled: true,
			mode: 'show_if',
			match: 'all',
			rules: [
				{
					id: 'rule_main',
					label: '',
					match: 'all',
					conditions: [],
				},
			],
		};
	}

	function normalizeDocShape(d) {
		var out = defaultDoc();
		out.enabled = !!d.enabled;
		out.mode = d.mode === 'hide_if' || d.mode === 'hide' ? 'hide_if' : 'show_if';
		out.match = d.match === 'any' ? 'any' : 'all';
		out.schema_version = typeof d.schema_version === 'number' ? d.schema_version : SCHEMA;
		if (Array.isArray(d.rules) && d.rules.length) {
			out.rules = d.rules.map(function (r, idx) {
				if (!r || typeof r !== 'object') {
					return { id: 'rule_' + idx, label: '', match: 'all', conditions: [] };
				}
				return {
					id: r.id || 'rule_' + idx,
					label: r.label || '',
					match: r.match === 'any' ? 'any' : 'all',
					conditions: Array.isArray(r.conditions) ? r.conditions.slice() : [],
				};
			});
		}
		return out;
	}

	function conditionToRow(c) {
		if (!c || typeof c !== 'object' || !c.type) {
			return { uid: uid(), field: '', uiOp: 'includes', values: [], unknown: null };
		}
		if (String(c.type) === 'page_version_url') {
			var pv = c.value && typeof c.value === 'object' ? c.value : {};
			return {
				uid: uid(),
				field: 'page_version_url',
				uiOp: 'is',
				values: [String(pv.version || '')],
				pageVersionPageId: parseInt(pv.page_id, 10) || 0,
				unknown: null,
			};
		}
		var f = portableTypeToField(String(c.type));
		var op = String(c.operator || 'in');
		if (!f) {
			return { uid: uid(), field: '', uiOp: 'includes', values: [], unknown: { type: c.type, operator: op, value: c.value } };
		}
		var meta = fieldMeta(f);
		var vals = normalizeIncomingValues(f, c.value, op);
		return { uid: uid(), field: f, uiOp: opPortableToUi(op), values: vals, unknown: null };
	}

	function normalizeIncomingValues(field, value, op) {
		if (op === 'empty' || op === 'not_empty') {
			return [];
		}
		if (field === 'logged_in') {
			var b = value === true || value === 1 || value === '1' || String(value).toLowerCase() === 'true';
			return [b ? '1' : '0'];
		}
		if (Array.isArray(value)) {
			return value.map(function (x) {
				return String(x);
			});
		}
		if (value !== undefined && value !== null && String(value) !== '') {
			return [String(value)];
		}
		return [];
	}

	function rowsFromDoc(doc) {
		if (!doc || !doc.rules || !doc.rules[0]) {
			return [];
		}
		return (doc.rules[0].conditions || []).map(conditionToRow);
	}

	function rowToCondition(row) {
		if (row.unknown) {
			return { type: row.unknown.type, operator: row.unknown.operator, value: row.unknown.value };
		}
		if (!row.field) {
			return null;
		}
		if (row.field === 'page_version_url') {
			var c = ctx();
			var pageId = row.pageVersionPageId || defaultPageVersionPageId(c);
			var ver = row.values[0] !== undefined ? String(row.values[0]) : '';
			var san = sanitizePageVersionInput(ver, pagePathById(pageId, c));
			if (!pageId || !san.version) {
				return null;
			}
			return {
				type: 'page_version_url',
				operator: 'equals',
				value: { page_id: pageId, version: san.version },
			};
		}
		var meta = fieldMeta(row.field);
		if (!meta) {
			return null;
		}
		var op = opUiToPortable(row.uiOp);
		var valOut;
		if (op === 'empty' || op === 'not_empty') {
			valOut = [];
		} else if (row.field === 'logged_in') {
			valOut = row.values[0] === '1' || row.values[0] === 'true';
		} else if (meta.multi) {
			valOut = row.values.slice();
		} else {
			var v = row.values[0] !== undefined ? String(row.values[0]) : '';
			if (op === 'in' || op === 'not_in') {
				valOut = v ? [v] : [];
			} else {
				valOut = v;
			}
		}
		return { type: meta.portableType, operator: op, value: valOut };
	}

	function docFromRows(docBase, rows, ruleMatch) {
		var d = JSON.parse(JSON.stringify(docBase || defaultDoc()));
		d.schema_version = SCHEMA;
		if (!d.rules || !d.rules.length) {
			d.rules = [{ id: 'rule_main', label: '', match: 'all', conditions: [] }];
		}
		d.rules[0].match = ruleMatch === 'any' ? 'any' : 'all';
		var conds = [];
		for (var i = 0; i < rows.length; i++) {
			var c = rowToCondition(rows[i]);
			if (c) {
				conds.push(c);
			}
		}
		d.rules[0].conditions = conds;
		return d;
	}

	function stringifyDoc(doc) {
		return JSON.stringify(doc, null, 2);
	}

	function rowIncomplete(row) {
		if (row.unknown) {
			return false;
		}
		if (!row.field) {
			return true;
		}
		var meta = fieldMeta(row.field);
		if (!meta) {
			return true;
		}
		if (row.uiOp === 'empty' || row.uiOp === 'not_empty') {
			return false;
		}
		if (meta.multi) {
			return !row.values.length;
		}
		if (row.field === 'logged_in') {
			return !row.values.length;
		}
		if (row.field === 'page_version_url') {
			var c = ctx();
			var pageId = row.pageVersionPageId || defaultPageVersionPageId(c);
			if (!pageId) {
				return true;
			}
			var san = sanitizePageVersionInput(row.values[0], pagePathById(pageId, c));
			return !san.version;
		}
		return !String(row.values[0] || '').trim();
	}

	function validateRows(rows) {
		for (var i = 0; i < rows.length; i++) {
			if (rowIncomplete(rows[i])) {
				return false;
			}
		}
		return true;
	}

	function summarize(rows, ruleMatch, c, mode) {
		if (!rows.length) {
			return { text: t('noConditionsYet'), warn: true };
		}
		var incomplete = !validateRows(rows);
		if (incomplete) {
			return { text: t('summaryIncomplete'), warn: true };
		}
		var parts = [];
		for (var i = 0; i < rows.length; i++) {
			parts.push(humanizeRow(rows[i], c));
		}
		var joiner = ruleMatch === 'any' ? ' ' + t('matchAny').toLowerCase() + ' — ' : ' ' + t('matchAll').toLowerCase() + ' — ';
		var body = parts.join(joiner);
		var prefix = mode === 'hide' ? t('summaryPrefixHide') : t('summaryPrefixShow');
		return { text: (prefix + ' ' + body).trim(), warn: false };
	}

	function chipLabelForValue(field, value, c) {
		if (field === 'country') {
			return countryLabel(value, c);
		}
		if (field === 'ga4_audience') {
			return audienceLabel(value, c);
		}
		if (field === 'ads_campaign') {
			return campaignLabel(value, c);
		}
		if (field === 'device_type') {
			for (var i = 0; i < DEVICE_OPTIONS.length; i++) {
				if (DEVICE_OPTIONS[i].v === value) {
					return DEVICE_OPTIONS[i].l;
				}
			}
		}
		if (field === 'weather_facet') {
			return weatherFacetLabel(value, c);
		}
		return value;
	}

	function renderSelectedChips(row, c, onChange) {
		var wrap = document.createElement('div');
		wrap.className = 'rwgc-rb__chips';
		if (!row.values || !row.values.length || row.uiOp === 'empty' || row.uiOp === 'not_empty') {
			return wrap;
		}
		var lab = document.createElement('span');
		lab.className = 'rwgc-rb__chips-label';
		lab.textContent = t('selectedLabel') + ':';
		wrap.appendChild(lab);
		row.values.forEach(function (val) {
			var chip = document.createElement('button');
			chip.type = 'button';
			chip.className = 'rwgc-rb__chip';
			chip.textContent = chipLabelForValue(row.field, val, c) + ' ×';
			chip.addEventListener('click', function () {
				row.values = row.values.filter(function (x) {
					return x !== val;
				});
				if (onChange) {
					onChange();
				}
			});
			wrap.appendChild(chip);
		});
		return wrap;
	}

	function humanizeRow(row, c) {
		if (row.unknown) {
			return t('unsupportedCard');
		}
		var meta = fieldMeta(row.field);
		if (!meta) {
			return '';
		}
		var opw = row.uiOp === 'includes' ? t('opIncludesAny') : row.uiOp === 'excludes' ? t('opExcludes') : row.uiOp;
		if (row.field === 'country') {
			var names = row.values.map(function (code) {
				return countryLabel(code, c);
			});
			return uiLabelForField(row.field) + ' ' + opw + ' ' + names.join(', ');
		}
		if (row.field === 'ga4_audience') {
			var an = row.values.map(function (id) {
				return audienceLabel(id, c);
			});
			return uiLabelForField(row.field) + ' (' + t('sourceGa4') + ') ' + opw + ' ' + an.join(', ');
		}
		if (row.field === 'ads_campaign') {
			var cn = row.values.map(function (id) {
				return campaignLabel(id, c);
			});
			return uiLabelForField(row.field) + ' (' + t('sourceGoogleAds') + ') ' + opw + ' ' + cn.join(', ');
		}
		if (row.field === 'logged_in') {
			return uiLabelForField(row.field) + ' ' + opw + ' ' + (row.values[0] === '1' ? t('loggedInYes') : t('loggedInNo'));
		}
		if (row.field === 'page_version_url') {
			var pageId = row.pageVersionPageId || defaultPageVersionPageId(c);
			var san = sanitizePageVersionInput(row.values[0], pagePathById(pageId, c));
			var path = pageVersionPreviewPath(pageId, san.version, c);
			if (path) {
				return uiLabelForField(row.field) + ' ' + t('opIs') + ' ' + path;
			}
			return uiLabelForField(row.field);
		}
		if (row.field === 'device_type') {
			return uiLabelForField(row.field) + ' ' + opw + ' ' + row.values.join(', ');
		}
		if (row.field === 'weather_facet') {
			var wn = row.values.map(function (slug) {
				return weatherFacetLabel(slug, c);
			});
			return uiLabelForField(row.field) + ' ' + opw + ' ' + wn.join(', ');
		}
		return uiLabelForField(row.field) + ' ' + opw + ' ' + row.values.join(', ');
	}

	function countryLabel(code, c) {
		var countries = c.countries || [];
		for (var i = 0; i < countries.length; i++) {
			if (countries[i].code === code) {
				return countries[i].label + ' (' + code + ')';
			}
		}
		return code;
	}

	function audienceLabel(id, c) {
		var list = c.audiences || [];
		for (var i = 0; i < list.length; i++) {
			if (String(list[i].id) === String(id)) {
				return list[i].name || id;
			}
		}
		return id;
	}

	function weatherFacetOptions(c) {
		var list = c.weather_facets || [];
		if (!list.length) {
			return [
				{ v: 'wet', l: 'Wet / raining' },
				{ v: 'dry', l: 'Dry' },
				{ v: 'hot', l: 'Hot' },
				{ v: 'cold', l: 'Cold' },
				{ v: 'mild', l: 'Mild / comfortable' },
				{ v: 'windy', l: 'Windy' },
				{ v: 'sunny', l: 'Sunny / bright' },
			];
		}
		return list.map(function (row) {
			return { v: String(row.slug || ''), l: String(row.label || row.slug || '') };
		});
	}

	function weatherFacetLabel(slug, c) {
		var s = String(slug || '');
		var opts = weatherFacetOptions(c);
		for (var i = 0; i < opts.length; i++) {
			if (opts[i].v === s) {
				return opts[i].l;
			}
		}
		return s;
	}

	function campaignLabel(id, c) {
		var list = c.campaigns || [];
		for (var i = 0; i < list.length; i++) {
			if (String(list[i].id) === String(id)) {
				return list[i].name || id;
			}
			if (String(list[i].name) === String(id)) {
				return list[i].name;
			}
		}
		return id;
	}

	function renderPageVersionPanel(row, c, onChange) {
		var panel = document.createElement('div');
		panel.className = 'rwgc-rb__page-version';

		var pageId = row.pageVersionPageId || defaultPageVersionPageId(c);
		var pvc = pageVersionCtx(c);
		var showPagePicker = !pvc.document || !pvc.document.id;

		if (showPagePicker && pvc.pages.length) {
			var pgWrap = document.createElement('div');
			pgWrap.className = 'rwgc-rb__page-version-page';
			var pgLab = document.createElement('label');
			pgLab.textContent = t('pageVersionPageLabel');
			var pgSel = document.createElement('select');
			pgSel.innerHTML = '<option value="">' + escapeHtml('—') + '</option>';
			pvc.pages.forEach(function (p) {
				var o = document.createElement('option');
				o.value = String(p.id);
				o.textContent = (p.title || p.path) + ' (' + p.path + ')';
				pgSel.appendChild(o);
			});
			pgSel.value = pageId ? String(pageId) : '';
			pgSel.addEventListener('change', function () {
				row.pageVersionPageId = parseInt(pgSel.value, 10) || 0;
				if (onChange) {
					onChange(true);
				}
			});
			pgWrap.appendChild(pgLab);
			pgWrap.appendChild(pgSel);
			panel.appendChild(pgWrap);
		} else if (pvc.document && pvc.document.path) {
			var cur = document.createElement('p');
			cur.className = 'rwgc-rb__page-version-current description';
			cur.textContent = pvc.document.path;
			panel.appendChild(cur);
			row.pageVersionPageId = parseInt(pvc.document.id, 10) || row.pageVersionPageId;
		}

		pageId = row.pageVersionPageId || defaultPageVersionPageId(c);
		var basePath = pagePathById(pageId, c);

		var pattern = document.createElement('p');
		pattern.className = 'rwgc-rb__page-version-pattern';
		pattern.innerHTML =
			'<strong>' +
			escapeHtml(t('pageVersionPattern')) +
			'</strong> <code class="rwgc-rb__page-version-code">' +
			escapeHtml(pageVersionPathPrefix(basePath || '/') + '/_gc/[ version-name ]') +
			'</code>';
		panel.appendChild(pattern);

		var nameWrap = document.createElement('div');
		nameWrap.className = 'rwgc-rb__page-version-name';
		var nameLab = document.createElement('label');
		nameLab.textContent = t('pageVersionNameLabel');
		var nameInp = document.createElement('input');
		nameInp.type = 'text';
		nameInp.className = 'rwgc-rb__page-version-input';
		nameInp.placeholder = t('pageVersionPlaceholder');
		nameInp.value = row.values[0] || '';
		nameInp.maxLength = 80;
		nameInp.addEventListener('input', function () {
			row.values = [nameInp.value];
			updatePageVersionFeedback();
			if (onChange) {
				onChange(false);
			}
		});
		nameInp.addEventListener('blur', function () {
			var san = sanitizePageVersionInput(nameInp.value, basePath);
			if (san.version && san.version !== nameInp.value) {
				nameInp.value = san.version;
				row.values = [san.version];
			}
			updatePageVersionFeedback();
			if (onChange) {
				onChange(false);
			}
		});
		nameWrap.appendChild(nameLab);
		nameWrap.appendChild(nameInp);
		panel.appendChild(nameWrap);

		var helper = document.createElement('p');
		helper.className = 'description rwgc-rb__page-version-helper';
		helper.textContent = t('pageVersionHelper');
		if (basePath) {
			var ex = document.createElement('p');
			ex.className = 'description rwgc-rb__page-version-example';
			ex.textContent = 'Example: ' + pageVersionPathPrefix(basePath) + '/_gc/campaign_name';
			panel.appendChild(helper);
			panel.appendChild(ex);
		} else {
			panel.appendChild(helper);
		}

		var err = document.createElement('p');
		err.className = 'rwgc-rb__page-version-error';
		err.hidden = true;

		var summary = document.createElement('p');
		summary.className = 'rwgc-rb__page-version-summary description';

		var urlBox = document.createElement('div');
		urlBox.className = 'rwgc-rb__page-version-urlbox';
		urlBox.hidden = true;
		var urlLab = document.createElement('label');
		urlLab.className = 'rwgc-rb__page-version-urlbox-label';
		urlLab.textContent = t('pageVersionFullUrl');
		var urlRow = document.createElement('div');
		urlRow.className = 'rwgc-rb__page-version-urlbox-row';
		var urlInp = document.createElement('input');
		urlInp.type = 'text';
		urlInp.className = 'rwgc-rb__page-version-urlbox-input';
		urlInp.readOnly = true;
		var copyBtn = document.createElement('button');
		copyBtn.type = 'button';
		copyBtn.className = 'button button-small rwgc-rb__page-version-copy';
		copyBtn.textContent = t('pageVersionCopy');
		var openBtn = document.createElement('a');
		openBtn.className = 'button button-small rwgc-rb__page-version-open';
		openBtn.target = '_blank';
		openBtn.rel = 'noopener noreferrer';
		openBtn.textContent = t('pageVersionOpen');
		urlRow.appendChild(urlInp);
		urlRow.appendChild(copyBtn);
		urlRow.appendChild(openBtn);
		urlBox.appendChild(urlLab);
		urlBox.appendChild(urlRow);

		panel.appendChild(err);
		panel.appendChild(summary);
		panel.appendChild(urlBox);

		copyBtn.addEventListener('click', function () {
			var val = urlInp.value;
			if (!val) {
				return;
			}
			function done() {
				copyBtn.textContent = t('pageVersionCopied');
				setTimeout(function () {
					copyBtn.textContent = t('pageVersionCopy');
				}, 1600);
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(val).then(done).catch(function () {
					urlInp.select();
					try {
						document.execCommand('copy');
						done();
					} catch (e2) {
						/* ignore */
					}
				});
			} else {
				urlInp.select();
				try {
					document.execCommand('copy');
					done();
				} catch (e3) {
					/* ignore */
				}
			}
		});

		function updatePageVersionFeedback() {
			if (!pageId) {
				err.textContent = t('pageVersionPickPage');
				err.hidden = false;
				summary.textContent = '';
				return;
			}
			var san = sanitizePageVersionInput(row.values[0], basePath);
			if (san.error) {
				err.textContent = san.error;
				err.hidden = !String(row.values[0] || '').trim();
			} else {
				err.hidden = true;
			}
			var preview = pageVersionPreviewPath(pageId, san.version, c);
			if (preview) {
				summary.textContent = t('pageVersionSummary').replace('%s', preview);
				var abs = pageVersionAbsoluteUrl(preview, c);
				urlInp.value = abs;
				openBtn.href = abs;
				urlBox.hidden = false;
			} else {
				summary.textContent = '';
				urlInp.value = '';
				openBtn.removeAttribute('href');
				urlBox.hidden = true;
			}
		}

		updatePageVersionFeedback();
		return panel;
	}

	function mount(options) {
		var textarea = options.textarea;
		if (!textarea || textarea.getAttribute('data-rwgc-rb-mounted')) {
			return;
		}
		textarea.setAttribute('data-rwgc-rb-mounted', '1');
		var wrap = textarea.closest('.elementor-control-field') || textarea.parentNode;
		if (wrap && wrap.classList) {
			wrap.closest('.elementor-control') && wrap.closest('.elementor-control').classList.add('rwgc-rb-textarea-hidden');
		}
		var mountWrap = textarea.closest('.rwgc-rb-mount-wrap');
		if (mountWrap && mountWrap.classList) {
			mountWrap.classList.add('rwgc-rb-textarea-hidden');
		}

		var root = document.createElement('div');
		root.className = 'rwgc-rb';
		textarea.parentNode.insertBefore(root, textarea);

		var getMode = options.getMode || function () {
			return 'show_if';
		};
		var setMode = options.setMode;

		var state = {
			rows: [],
			ruleMatch: 'all',
			docBase: null,
			advancedOpen: false,
			jsonDraft: '',
			parseError: null,
		};
		var syncingFromState = false;

		function readDocFromTextarea() {
			var p = parseDoc(textarea.value);
			if (p.error) {
				state.parseError = p.parseError;
				state.docBase = defaultDoc();
				state.rows = [];
				state.ruleMatch = 'all';
				return;
			}
			state.parseError = null;
			state.docBase = p;
			state.ruleMatch = p.rules && p.rules[0] ? p.rules[0].match || 'all' : 'all';
			state.localMode = p.mode === 'hide_if' || p.mode === 'hide' ? 'hide_if' : 'show_if';
			state.rows = rowsFromDoc(p);
			if (p.rules && p.rules.length > 1) {
				state.multiRules = true;
			} else {
				state.multiRules = false;
			}
		}

		function writeTextareaFromState() {
			var mode = state.localMode !== undefined ? state.localMode : getMode();
			var d = docFromRows(state.docBase, state.rows, state.ruleMatch);
			d.mode = mode === 'hide_if' || mode === 'hide' ? 'hide_if' : 'show_if';
			if (state.docBase && state.docBase.rules && state.docBase.rules.length > 1) {
				d.rules = d.rules.concat(state.docBase.rules.slice(1));
			}
			var json = stringifyDoc(d);
			if (textarea.value !== json) {
				syncingFromState = true;
				textarea.value = json;
				$(textarea).trigger('input').trigger('change');
				syncingFromState = false;
			}
			if (setMode) {
				setMode(mode);
			}
			if (typeof options.onChange === 'function') {
				options.onChange(json);
			}
		}

		function applyLibraryRule(json) {
			if (!json) {
				return;
			}
			var p = parseDoc(json);
			if (p.error) {
				return;
			}
			state.parseError = null;
			state.docBase = p;
			state.ruleMatch = p.rules && p.rules[0] ? p.rules[0].match || 'all' : 'all';
			state.localMode = p.mode === 'hide_if' || p.mode === 'hide' ? 'hide_if' : 'show_if';
			state.rows = rowsFromDoc(p);
			state.multiRules = !!(p.rules && p.rules.length > 1);
			writeTextareaFromState();
			render();
		}

		function renderLibraryPicker(c) {
			var lib = c.visibility_library || [];
			if (!lib.length) {
				return null;
			}
			var wrap = document.createElement('div');
			wrap.className = 'rwgc-rb__library';
			var lab = document.createElement('label');
			lab.textContent = t('libraryLabel');
			var sel = document.createElement('select');
			sel.className = 'rwgc-rb__library-select';
			sel.innerHTML = '<option value="">' + escapeHtml(t('libraryNone')) + '</option>';
			lib.forEach(function (item) {
				var o = document.createElement('option');
				o.value = String(item.id);
				o.textContent = item.title || 'Rule #' + item.id;
				sel.appendChild(o);
			});
			sel.addEventListener('change', function () {
				var id = parseInt(sel.value, 10);
				sel.value = '';
				if (!id) {
					return;
				}
				var hit = null;
				lib.forEach(function (item) {
					if (parseInt(item.id, 10) === id) {
						hit = item;
					}
				});
				if (hit && hit.json) {
					applyLibraryRule(hit.json);
				}
			});
			var help = document.createElement('p');
			help.className = 'description rwgc-rb__library-help';
			help.textContent = t('libraryHelp');
			wrap.appendChild(lab);
			wrap.appendChild(sel);
			wrap.appendChild(help);
			return wrap;
		}

		function render() {
			var c = ctx();
			root.innerHTML = '';
			var h = document.createElement('div');
			h.className = 'rwgc-rb__title';
			h.textContent = t('whoHeading');
			root.appendChild(h);

			var libPicker = renderLibraryPicker(c);
			if (libPicker) {
				root.appendChild(libPicker);
			}

			if (!c.advanced_targeting && !options.isPlayground && !options.allowAllConditionTypes) {
				var advNote = document.createElement('p');
				advNote.className = 'description rwgc-rb__advanced-notice';
				advNote.textContent = t('advancedTargetingNotice');
				root.appendChild(advNote);
			}

			if (options.isPlayground) {
				var intro = document.createElement('p');
				intro.className = 'rwgc-rb__playground-intro description';
				intro.textContent = t('playgroundIntro');
				root.appendChild(intro);
			}

			if (options.showVisibilityMode) {
				var vis = document.createElement('div');
				vis.className = 'rwgc-rb__visibility';
				var vl = document.createElement('label');
				vl.textContent = t('visibilityModeLabel');
				vis.appendChild(vl);
				var vs = document.createElement('select');
				vs.innerHTML =
					'<option value="show_if">' +
					escapeHtml(t('visibilityShow')) +
					'</option><option value="hide_if">' +
					escapeHtml(t('visibilityHide')) +
					'</option>';
				var curMode = state.localMode !== undefined ? state.localMode : getMode();
				vs.value = curMode === 'hide_if' || curMode === 'hide' ? 'hide_if' : 'show_if';
				vs.addEventListener('change', function () {
					state.localMode = vs.value;
					writeTextareaFromState();
					render();
				});
				vis.appendChild(vs);
				root.appendChild(vis);
			}

			if (state.parseError) {
				var pe = document.createElement('div');
				pe.className = 'rwgc-rb__err';
				pe.textContent = t('jsonInvalid');
				root.appendChild(pe);
			}

			if (c.pro && (!c.audiences || !c.audiences.length)) {
				root.appendChild(emptyGaBlock(c));
			}
			if (c.pro && (!c.campaigns || !c.campaigns.length)) {
				root.appendChild(emptyAdsBlock(c));
			}

			var matchWrap = document.createElement('div');
			matchWrap.className = 'rwgc-rb__match';
			var ml = document.createElement('label');
			ml.textContent = t('matchConditionsLabel');
			var ms = document.createElement('select');
			ms.innerHTML =
				'<option value="all">' +
				escapeHtml(t('matchAll')) +
				'</option><option value="any">' +
				escapeHtml(t('matchAny')) +
				'</option>';
			ms.value = state.ruleMatch === 'any' ? 'any' : 'all';
			ms.addEventListener('change', function () {
				state.ruleMatch = ms.value;
				writeTextareaFromState();
				render();
			});
			matchWrap.appendChild(ml);
			matchWrap.appendChild(ms);
			root.appendChild(matchWrap);

			state.rows.forEach(function (row, idx) {
				root.appendChild(renderRow(row, idx, c));
			});

			var addBtn = document.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'rwgc-rb__btn';
			addBtn.textContent = t('addCondition');
			addBtn.addEventListener('click', function () {
				state.rows.push({ uid: uid(), field: '', uiOp: 'includes', values: [], unknown: null });
				writeTextareaFromState();
				render();
			});
			root.appendChild(addBtn);

			var clearBtn = document.createElement('button');
			clearBtn.type = 'button';
			clearBtn.className = 'rwgc-rb__btn rwgc-rb__btn--danger';
			clearBtn.style.marginLeft = '6px';
			clearBtn.textContent = t('clearRule');
			clearBtn.addEventListener('click', function () {
				state.rows = [];
				state.docBase = defaultDoc();
				state.ruleMatch = 'all';
				writeTextareaFromState();
				render();
			});
			root.appendChild(clearBtn);

			var sumMode = state.localMode !== undefined ? state.localMode : getMode();
			var sum = summarize(state.rows, state.ruleMatch, c, sumMode);
			var sumEl = document.createElement('div');
			sumEl.className = 'rwgc-rb__summary' + (sum.warn ? ' rwgc-rb__summary--warn' : '');
			sumEl.textContent = sum.text;
			root.appendChild(sumEl);

			if (state.multiRules) {
				var mr = document.createElement('div');
				mr.className = 'rwgc-rb__err';
				mr.textContent = t('summaryMultiRules');
				root.appendChild(mr);
			}

			var adv = document.createElement('div');
			adv.className = 'rwgc-rb__advanced';
			var det = document.createElement('details');
			var sm = document.createElement('summary');
			sm.textContent = t('advancedToggle');
			det.appendChild(sm);
			var aw = document.createElement('div');
			aw.className = 'rwgc-rb__advanced-warn';
			aw.textContent = t('advancedWarning');
			det.appendChild(aw);
			var jta = document.createElement('textarea');
			jta.className = 'rwgc-rb__json';
			jta.value = textarea.value;
			jta.addEventListener('input', function () {
				var p = parseDoc(jta.value);
				if (p.error) {
					jta.setAttribute('aria-invalid', 'true');
					return;
				}
				jta.removeAttribute('aria-invalid');
				textarea.value = jta.value;
				$(textarea).trigger('input').trigger('change');
				readDocFromTextarea();
				render();
			});
			det.appendChild(jta);
			det.open = state.advancedOpen;
			det.addEventListener('toggle', function () {
				state.advancedOpen = det.open;
				if (det.open) {
					jta.value = textarea.value;
				}
			});
			adv.appendChild(det);
			root.appendChild(adv);
		}

		function emptyGaBlock(c) {
			var el = document.createElement('div');
			el.className = 'rwgc-rb__empty';
			el.innerHTML = '<strong>' + escapeHtml(t('noAudiencesTitle')) + '</strong>';
			var act = document.createElement('div');
			act.className = 'rwgc-rb__empty-actions';
			var u = c.help_urls || {};
			if (u.integrations_ga) {
				act.innerHTML +=
					'<a class="button button-small" href="' +
					escapeHtml(u.integrations_ga) +
					'">' +
					escapeHtml(t('connectGa4')) +
					'</a>';
			}
			if (u.integrations_ga) {
				act.innerHTML +=
					'<a class="button button-small" href="' +
					escapeHtml(u.integrations_ga) +
					'">' +
					escapeHtml(t('syncAudiences')) +
					'</a>';
			}
			if (u.targeting_help) {
				act.innerHTML +=
					'<a class="button button-small" href="' +
					escapeHtml(u.targeting_help) +
					'" target="_blank" rel="noopener noreferrer">' +
					escapeHtml(t('learnAudiences')) +
					'</a>';
			}
			el.appendChild(act);
			return el;
		}

		function emptyAdsBlock(c) {
			var el = document.createElement('div');
			el.className = 'rwgc-rb__empty';
			el.innerHTML = '<strong>' + escapeHtml(t('noCampaignsTitle')) + '</strong>';
			var act = document.createElement('div');
			act.className = 'rwgc-rb__empty-actions';
			var u = c.help_urls || {};
			if (u.integrations_ads) {
				act.innerHTML +=
					'<a class="button button-small" href="' +
					escapeHtml(u.integrations_ads) +
					'">' +
					escapeHtml(t('connectAds')) +
					'</a>';
			}
			if (u.integrations_ads) {
				act.innerHTML +=
					'<a class="button button-small" href="' +
					escapeHtml(u.integrations_ads) +
					'">' +
					escapeHtml(t('syncCampaigns')) +
					'</a>';
			}
			el.appendChild(act);
			return el;
		}

		function renderRow(row, index, c) {
			var wrap = document.createElement('div');
			wrap.className = 'rwgc-rb__row' + (rowIncomplete(row) ? ' rwgc-rb__row--invalid' : '');
			if (row.unknown) {
				wrap.classList.add('rwgc-rb__row--unknown');
				wrap.textContent = t('unsupportedCard');
				return wrap;
			}

			var head = document.createElement('div');
			head.className = 'rwgc-rb__row-head';

			var fWrap = document.createElement('div');
			fWrap.className = 'rwgc-rb__field';
			var fl = document.createElement('label');
			fl.textContent = t('fieldLabel');
			var fs = document.createElement('select');
			fs.innerHTML = '<option value="">' + escapeHtml('—') + '</option>';
			FIELD_DEFS.forEach(function (fd) {
				if (
					fd.key !== 'country' &&
					!c.advanced_targeting &&
					!options.isPlayground &&
					!options.allowAllConditionTypes
				) {
					return;
				}
				if (fd.pro && !c.pro) {
					return;
				}
				if (fd.requiresWeather && !c.weather_connected) {
					return;
				}
				var o = document.createElement('option');
				o.value = fd.key;
				o.textContent = uiLabelForField(fd.key);
				fs.appendChild(o);
			});
			fs.value = row.field || '';
			fs.addEventListener('change', function () {
				row.field = fs.value;
				row.values = [];
				row.uiOp = fs.value === 'page_version_url' ? 'is' : 'includes';
				if (fs.value === 'page_version_url') {
					row.pageVersionPageId = defaultPageVersionPageId(c);
					row.values = [''];
				}
				writeTextareaFromState();
				render();
			});
			fWrap.appendChild(fl);
			fWrap.appendChild(fs);
			head.appendChild(fWrap);

			var oWrap = document.createElement('div');
			oWrap.className = 'rwgc-rb__op';
			var ol = document.createElement('label');
			ol.textContent = t('operatorLabel');
			var os = document.createElement('select');
			os.innerHTML = buildOpOptions(row.field);
			os.value = row.uiOp;
			os.addEventListener('change', function () {
				row.uiOp = os.value;
				writeTextareaFromState();
				render();
			});
			oWrap.appendChild(ol);
			oWrap.appendChild(os);
			if (row.field === 'page_version_url') {
				oWrap.style.display = 'none';
			}
			head.appendChild(oWrap);

			var vWrap = document.createElement('div');
			vWrap.className = 'rwgc-rb__val';
			if (row.field !== 'page_version_url') {
				var vl = document.createElement('label');
				vl.textContent = t('valueLabel');
				vWrap.appendChild(vl);
				vWrap.appendChild(renderValueEditor(row, c));
				head.appendChild(vWrap);
			}

			wrap.appendChild(head);

			if (row.field === 'page_version_url') {
				wrap.appendChild(
					renderPageVersionPanel(row, c, function (rebuildUi) {
						writeTextareaFromState();
						if (rebuildUi !== false) {
							render();
						}
					})
				);
			}

			var rowMeta = fieldMeta(row.field);
			if (row.field && row.field !== 'logged_in' && rowMeta && rowMeta.multi) {
				wrap.appendChild(
					renderSelectedChips(row, c, function () {
						writeTextareaFromState();
						render();
					})
				);
			}

			var actions = document.createElement('div');
			actions.className = 'rwgc-rb__actions';
			var dup = document.createElement('button');
			dup.type = 'button';
			dup.className = 'rwgc-rb__btn';
			dup.textContent = t('duplicate');
			dup.addEventListener('click', function () {
				var copy = JSON.parse(JSON.stringify(row));
				copy.uid = uid();
				state.rows.splice(index + 1, 0, copy);
				writeTextareaFromState();
				render();
			});
			var rem = document.createElement('button');
			rem.type = 'button';
			rem.className = 'rwgc-rb__btn rwgc-rb__btn--danger';
			rem.textContent = t('remove');
			rem.addEventListener('click', function () {
				state.rows.splice(index, 1);
				writeTextareaFromState();
				render();
			});
			actions.appendChild(dup);
			actions.appendChild(rem);
			wrap.appendChild(actions);

			return wrap;
		}

		function buildOpOptions(field) {
			var html = '';
			if (!field) {
				return '<option value="includes">' + escapeHtml(t('opIncludesAny')) + '</option>';
			}
			if (field === 'logged_in' || field === 'page_version_url') {
				html +=
					'<option value="is">' +
					escapeHtml(t('opIs')) +
					'</option><option value="is_not">' +
					escapeHtml(t('opIsNot')) +
					'</option>';
				return html;
			}
			if (field === 'utm_campaign' || field === 'utm_source' || field === 'utm_medium') {
				html +=
					'<option value="is">' +
					escapeHtml(t('opIs')) +
					'</option><option value="is_not">' +
					escapeHtml(t('opIsNot')) +
					'</option>' +
					'<option value="includes">' +
					escapeHtml(t('opIncludesAny')) +
					'</option>' +
					'<option value="excludes">' +
					escapeHtml(t('opExcludes')) +
					'</option>' +
					'<option value="empty">' +
					escapeHtml(t('opEmpty')) +
					'</option>' +
					'<option value="not_empty">' +
					escapeHtml(t('opNotEmpty')) +
					'</option>';
				return html;
			}
			html +=
				'<option value="includes">' +
				escapeHtml(t('opIncludesAny')) +
				'</option>' +
				'<option value="excludes">' +
				escapeHtml(t('opExcludes')) +
				'</option>' +
				'<option value="is">' +
				escapeHtml(t('opIs')) +
				'</option>' +
				'<option value="is_not">' +
				escapeHtml(t('opIsNot')) +
				'</option>';
			return html;
		}

		function renderValueEditor(row, c) {
			var frag = document.createDocumentFragment();
			if (!row.field || row.uiOp === 'empty' || row.uiOp === 'not_empty') {
				var em = document.createElement('em');
				em.style.opacity = '0.7';
				em.textContent = '—';
				frag.appendChild(em);
				return frag;
			}
			if (row.field === 'country') {
				frag.appendChild(multiCountryPicker(row, c));
				return frag;
			}
			if (row.field === 'ga4_audience') {
				frag.appendChild(multiEntityPicker(row, c.audiences || [], 'ga4'));
				return frag;
			}
			if (row.field === 'ads_campaign') {
				frag.appendChild(multiEntityPicker(row, c.campaigns || [], 'ads'));
				return frag;
			}
			if (row.field === 'utm_campaign' || row.field === 'utm_source' || row.field === 'utm_medium') {
				var inp = document.createElement('input');
				inp.type = 'text';
				inp.value = row.values[0] || '';
				inp.placeholder = t('searchPlaceholder');
				inp.addEventListener('input', function () {
					row.values = [inp.value];
					writeTextareaFromState();
				});
				frag.appendChild(inp);
				return frag;
			}
			if (row.field === 'weather_facet') {
				if (!c.weather_connected) {
					var wEmpty = document.createElement('div');
					wEmpty.className = 'rwgc-rb__empty';
					wEmpty.innerHTML = '<strong>' + escapeHtml(t('noWeatherTitle')) + '</strong>';
					var wAct = document.createElement('div');
					wAct.className = 'rwgc-rb__empty-actions';
					var wu = c.help_urls || {};
					if (wu.integrations_weather) {
						wAct.innerHTML +=
							'<a class="button button-small" href="' +
							escapeHtml(wu.integrations_weather) +
							'">' +
							escapeHtml(t('connectWeather')) +
							'</a>';
					}
					wEmpty.appendChild(wAct);
					frag.appendChild(wEmpty);
					return frag;
				}
				var wBox = document.createElement('div');
				wBox.className = 'rwgc-rb__multi';
				weatherFacetOptions(c).forEach(function (d) {
					if (!d.v) {
						return;
					}
					var lab = document.createElement('label');
					var cb = document.createElement('input');
					cb.type = 'checkbox';
					cb.value = d.v;
					cb.checked = row.values.indexOf(d.v) !== -1;
					cb.addEventListener('change', function () {
						if (cb.checked) {
							if (row.values.indexOf(d.v) === -1) {
								row.values.push(d.v);
							}
						} else {
							row.values = row.values.filter(function (x) {
								return x !== d.v;
							});
						}
						writeTextareaFromState();
					});
					lab.appendChild(cb);
					lab.appendChild(document.createTextNode(d.l));
					wBox.appendChild(lab);
				});
				frag.appendChild(wBox);
				return frag;
			}
			if (row.field === 'device_type') {
				var box = document.createElement('div');
				box.className = 'rwgc-rb__multi';
				DEVICE_OPTIONS.forEach(function (d) {
					var lab = document.createElement('label');
					var cb = document.createElement('input');
					cb.type = 'checkbox';
					cb.value = d.v;
					cb.checked = row.values.indexOf(d.v) !== -1;
					cb.addEventListener('change', function () {
						if (cb.checked) {
							if (row.values.indexOf(d.v) === -1) {
								row.values.push(d.v);
							}
						} else {
							row.values = row.values.filter(function (x) {
								return x !== d.v;
							});
						}
						writeTextareaFromState();
					});
					lab.appendChild(cb);
					lab.appendChild(document.createTextNode(d.l));
					box.appendChild(lab);
				});
				frag.appendChild(box);
				return frag;
			}
			if (row.field === 'page_version_url') {
				var em = document.createElement('em');
				em.style.opacity = '0.7';
				em.textContent = '—';
				frag.appendChild(em);
				return frag;
			}
			if (row.field === 'logged_in') {
				var sel = document.createElement('select');
				sel.innerHTML =
					'<option value="">' +
					escapeHtml('—') +
					'</option><option value="1">' +
					escapeHtml(t('loggedInYes')) +
					'</option><option value="0">' +
					escapeHtml(t('loggedInNo')) +
					'</option>';
				sel.value = row.values[0] === '0' ? '0' : row.values[0] === '1' ? '1' : '';
				sel.addEventListener('change', function () {
					row.values = sel.value ? [sel.value] : [];
					writeTextareaFromState();
					render();
				});
				frag.appendChild(sel);
				var hint = document.createElement('p');
				hint.className = 'description';
				hint.style.marginTop = '4px';
				hint.textContent = t('booleanHint');
				frag.appendChild(hint);
				return frag;
			}
			return frag;
		}

		function multiCountryPicker(row, c) {
			var box = document.createElement('div');
			box.className = 'rwgc-rb__multi';
			var search = document.createElement('input');
			search.type = 'search';
			search.className = 'rwgc-rb__search';
			search.placeholder = t('pickCountries');
			box.appendChild(search);
			var list = document.createElement('div');
			var countries = c.countries || [];
			function paint(filter) {
				list.innerHTML = '';
				var f = (filter || '').toLowerCase();
				countries.forEach(function (co) {
					if (f && co.label.toLowerCase().indexOf(f) === -1 && co.code.toLowerCase().indexOf(f) === -1) {
						return;
					}
					var lab = document.createElement('label');
					var cb = document.createElement('input');
					cb.type = 'checkbox';
					cb.value = co.code;
					cb.checked = row.values.indexOf(co.code) !== -1;
					cb.addEventListener('change', function () {
						var acc = [];
						list.querySelectorAll('input[type=checkbox]').forEach(function (x) {
							if (x.checked) {
								acc.push(x.value);
							}
						});
						row.values = acc;
						writeTextareaFromState();
						render();
					});
					lab.appendChild(cb);
					lab.appendChild(document.createTextNode(co.label + ' (' + co.code + ')'));
					list.appendChild(lab);
				});
			}
			search.addEventListener('input', function () {
				paint(search.value);
			});
			paint('');
			box.appendChild(list);
			return box;
		}

		function multiEntityPicker(row, list, kind) {
			var box = document.createElement('div');
			box.className = 'rwgc-rb__multi';
			var search = document.createElement('input');
			search.type = 'search';
			search.className = 'rwgc-rb__search';
			search.placeholder = kind === 'ga4' ? t('pickAudiences') : t('pickCampaigns');
			box.appendChild(search);
			var pill = document.createElement('span');
			pill.className = 'rwgc-rb__pill ' + (kind === 'ga4' ? 'rwgc-rb__pill--ga4' : 'rwgc-rb__pill--ads');
			pill.textContent = kind === 'ga4' ? t('sourceGa4') : t('sourceGoogleAds');
			box.appendChild(pill);
			var listEl = document.createElement('div');
			function paint(filter) {
				listEl.innerHTML = '';
				var f = (filter || '').toLowerCase();
				list.forEach(function (item) {
					var nm = String(item.name || item.id || '');
					var id = String(item.id || item.name || '');
					if (!id) {
						return;
					}
					if (f && nm.toLowerCase().indexOf(f) === -1 && id.toLowerCase().indexOf(f) === -1) {
						return;
					}
					var lab = document.createElement('label');
					var cb = document.createElement('input');
					cb.type = 'checkbox';
					cb.value = id;
					cb.checked = row.values.indexOf(id) !== -1;
					cb.addEventListener('change', function () {
						var acc = [];
						listEl.querySelectorAll('input[type=checkbox]').forEach(function (x) {
							if (x.checked) {
								acc.push(x.value);
							}
						});
						row.values = acc;
						writeTextareaFromState();
						render();
					});
					var line = (item.status ? nm + ' — ' + item.status : nm) || id;
					lab.appendChild(cb);
					lab.appendChild(document.createTextNode(line));
					listEl.appendChild(lab);
				});
			}
			search.addEventListener('input', function () {
				paint(search.value);
			});
			paint('');
			box.appendChild(listEl);
			return box;
		}

		readDocFromTextarea();
		render();

		$(textarea).on('input.rwgcRb change.rwgcRb', function () {
			if (syncingFromState) {
				return;
			}
			readDocFromTextarea();
			render();
		});

		if (options.observeMode) {
			state.lastMode = getMode();
			setInterval(function () {
				var m = getMode();
				if (m !== state.lastMode) {
					state.lastMode = m;
					writeTextareaFromState();
				}
			}, 600);
		}
	}

	function elementorPortableControlPair() {
		var $panel = $('#elementor-panel-inner');
		var pairs = [
			{
				toggle: '.elementor-control-rwgc_enable_visibility_rules',
				textarea: '.elementor-control-rwgc_portable_geo_targeting textarea',
				mode: '.elementor-control-rwgc_visibility_rules_mode select, .elementor-control-rwgc_visibility_mode select, .elementor-control-rwgc_geo_mode select',
			},
			{
				toggle: '.elementor-control-rwgc_use_portable_geo_targeting',
				textarea: '.elementor-control-rwgc_portable_geo_targeting textarea',
				mode: '.elementor-control-rwgc_visibility_rules_mode select, .elementor-control-rwgc_visibility_mode select, .elementor-control-rwgc_geo_mode select',
			},
			{
				toggle: '.elementor-control-egp_use_portable_geo_targeting',
				textarea: '.elementor-control-egp_portable_geo_targeting textarea',
				mode: '.elementor-control-rwgc_visibility_rules_mode select, .elementor-control-rwgc_visibility_mode select, .elementor-control-rwgc_geo_mode select',
			},
		];
		for (var i = 0; i < pairs.length; i++) {
			var p = pairs[i];
			var $toggle = $panel.find(p.toggle);
			var $input = $toggle.find('input[type=checkbox], input[type=hidden]');
			var active = $input.filter('[type=checkbox]').length
				? $input.filter('[type=checkbox]').prop('checked')
				: $input.val() === 'yes';
			if ($input.length && active) {
				var $ta = $panel.find(p.textarea);
				if ($ta.length) {
					return { textarea: $ta, modeSel: p.mode ? $panel.find(p.mode) : null };
				}
			}
		}
		return null;
	}

	function setValue(textarea, json) {
		if (!textarea) {
			return;
		}
		var text = typeof json === 'string' ? json : stringifyDoc(json);
		if (textarea.value === text) {
			return;
		}
		textarea.value = text;
		$(textarea).trigger('input.rwgcRb').trigger('change');
	}

	function mountElementor() {
		function tryMount() {
			var pair = elementorPortableControlPair();
			if (!pair || !pair.textarea.length) {
				return;
			}
			var el = pair.textarea.get(0);
			if (el.getAttribute('data-rwgc-rb-mounted')) {
				return;
			}
			var c = ctx();
			mount({
				textarea: el,
				observeMode: true,
				allowAllConditionTypes: !!(c.advanced_targeting || c.pro),
				getMode: function () {
					if (pair.modeSel && pair.modeSel.length) {
						return pair.modeSel.val() || 'show_if';
					}
					return 'show_if';
				},
				setMode: function (mode) {
					var normalized = mode === 'hide_if' || mode === 'hide' ? 'hide_if' : 'show_if';
					if (pair.modeSel && pair.modeSel.length && pair.modeSel.val() !== normalized) {
						pair.modeSel.val(normalized).trigger('change');
					}
					var $panel = $('#elementor-panel-inner');
					var $legacy = $panel.find('.elementor-control-rwgc_visibility_mode input');
					if ($legacy.length) {
						$legacy.val(normalized).trigger('input').trigger('change');
					}
				},
			});
			if (window.ReactWooRuleBuilder && typeof window.ReactWooRuleBuilder.parseDoc === 'function') {
				try {
					var initial = window.ReactWooRuleBuilder.parseDoc(el.value || '');
					if (initial && !initial.error && initial.mode && pair.modeSel && pair.modeSel.length) {
						var initialMode = initial.mode === 'hide_if' || initial.mode === 'hide' ? 'hide_if' : 'show_if';
						if (pair.modeSel.val() !== initialMode) {
							pair.modeSel.val(initialMode).trigger('change');
						}
					}
				} catch (e2) {
					/* ignore */
				}
			}
		}

		$(window).on('elementor:init', function () {
			tryMount();
		});
		var root = document.getElementById('elementor-panel-inner');
		if (root) {
			var mo = new MutationObserver(function () {
				tryMount();
			});
			mo.observe(root, { childList: true, subtree: true });
		}
		setTimeout(tryMount, 400);
	}

	window.ReactWooRuleBuilder = {
		mount: mount,
		mountElementor: mountElementor,
		setValue: setValue,
		parseDoc: parseDoc,
		stringifyDoc: stringifyDoc,
	};
})(window, document, jQuery);
