/**
 * Runs the shop script's alphabet reset and cart-drawer scroll.
 * A passing name search is not enough: each case calls the function.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'configurator.js'), 'utf8');

function fail(message) {
	process.stderr.write(message + '\n');
	process.exit(1);
}

function extractFunction(name) {
	const marker = 'function ' + name;
	const start = source.indexOf(marker);
	if (start < 0) {
		fail('MISSING_FUNCTION ' + name);
	}
	const later = source.indexOf(marker, start + marker.length);
	if (later !== -1) {
		fail('DUPLICATE_FUNCTION ' + name);
	}
	const open = source.indexOf('{', start);
	let depth = 0;
	for (let i = open; i < source.length; i += 1) {
		const ch = source[i];
		if (ch === '{') {
			depth += 1;
		} else if (ch === '}') {
			depth -= 1;
			if (depth === 0) {
				return source.slice(start, i + 1);
			}
		}
	}
	fail('UNCLOSED_FUNCTION ' + name);
}

function successHandler() {
	const marker = 'return refreshCartWidgets().then(function () {';
	const start = source.indexOf(marker);
	if (start < 0) {
		fail('MISSING_CART_SUCCESS');
	}
	const open = source.indexOf('{', start);
	let depth = 0;
	for (let i = open; i < source.length; i += 1) {
		if (source[i] === '{') {
			depth += 1;
		} else if (source[i] === '}') {
			depth -= 1;
			if (depth === 0) {
				return source.slice(open, i + 1);
			}
		}
	}
	fail('UNCLOSED_CART_SUCCESS');
}

const added = successHandler();
if (added.indexOf('showCartNotice(root, null)') < 0 || added.indexOf('openKadenceCart()') < 0 || added.indexOf('revealAddedCartLine(previousKeys, addedText, originScroll)') < 0) {
	fail('CART_SUCCESS_MISSING_STEPS ' + added);
}
if (added.indexOf('resetConfigurator') !== -1 || added.indexOf('form.reset') !== -1) {
	fail('CART_SUCCESS_STILL_RESETS');
}
if (source.indexOf('function resetConfigurator') !== -1 || source.indexOf('form.reset') !== -1) {
	fail('CONFIGURATOR_STILL_RESETS');
}

const resetSource = extractFunction('resetFieldsAfterScript');
const restoreSource = extractFunction('restoreInitialValue');
const revealSource = extractFunction('revealAddedCartLine');
const drawerSource = extractFunction('cartDrawerOpen');

function element(extra) {
	const node = {
		value: '',
		type: 'text',
		checked: false,
		attributes: {},
		getAttribute(name) {
			return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null;
		},
		setAttribute(name, value) {
			this.attributes[name] = String(value);
		},
		hasAttribute(name) {
			return Object.prototype.hasOwnProperty.call(this.attributes, name);
		}
	};
	return Object.assign(node, extra || {});
}

function plateRoot(options) {
	const colors = options.colors || {};
	const rows = options.rows || [];
	const swatches = options.swatches || [];
	return {
		querySelector(selector) {
			if (selector === '[name="apd_text"]') {
				return options.text || null;
			}
			if (selector === '[name="apd_comment"]') {
				return options.comment || null;
			}
			if (selector === '[data-apd-frame-plain]') {
				return options.plain || null;
			}
			const color = selector.match(/^\[data-apd-color="([^"]+)"\]$/);
			if (color) {
				return colors[color[1]] || null;
			}
			return null;
		},
		querySelectorAll(selector) {
			if (selector === '[data-apd-plate-row]') {
				return rows;
			}
			if (selector === '[data-apd-color]') {
				return Object.keys(colors).map(function (name) {
					return colors[name];
				});
			}
			if (selector === '[data-apd-swatch]') {
				return swatches;
			}
			return [];
		}
	};
}

const runReset = vm.runInNewContext(
	'function run(cfg, root, scriptName) {\n' +
	'  var scriptWarning = true;\n' +
	'  var calls = [];\n' +
	'  function currentScript() { return scriptName; }\n' +
	'  function upperPlateControl(input) { calls.push("upper"); input.value = String(input.value).toLocaleUpperCase("bg"); }\n' +
	'  function stripWrongScript() { calls.push("strip"); return false; }\n' +
	'  function syncSuvRows() { calls.push("rows"); }\n' +
	'  function syncSideFields() { calls.push("sides"); }\n' +
	'  function updateCount() { calls.push("count"); }\n' +
	restoreSource + '\n' + resetSource + '\n' +
	'  resetFieldsAfterScript();\n' +
	'  return { scriptWarning: scriptWarning, calls: calls };\n' +
	'}\n' +
	'run\n'
);

const both = { scripts: ['latin', 'cyrillic'], default_text: 'ca 7777 kk', default_text_cyrillic: 'кирилица' };
const text = element({ value: 'TYPED' });
const comment = element({ value: 'остава' });
const plain = element({ checked: true, attributes: { 'data-apd-initial-on': '0' } });
const textColor = element({ value: '#E33D00', attributes: { 'data-apd-initial': '#000000', 'data-apd-color': 'apd_text_color' } });
const swatch = element({ attributes: { 'data-apd-swatch': 'apd_text_color', 'data-apd-hex': '#000000', 'aria-pressed': 'false' } });
const root = plateRoot({ text: text, comment: comment, plain: plain, colors: { apd_text_color: textColor }, swatches: [swatch] });
const cyrillic = runReset(both, root, 'cyrillic');

if (text.value !== 'КИРИЛИЦА' || textColor.value !== '#000000' || plain.checked !== false || comment.value !== 'остава' || cyrillic.scriptWarning !== false || cyrillic.calls.indexOf('strip') < 0 || cyrillic.calls.indexOf('upper') < 0 || swatch.getAttribute('aria-pressed') !== 'true') {
	fail('SCRIPT_SAMPLE_CYRILLIC text=' + text.value + ' color=' + textColor.value + ' plain=' + plain.checked + ' warning=' + cyrillic.scriptWarning);
}

const latinText = element({ value: 'КИРИЛИЦА' });
runReset(both, plateRoot({ text: latinText }), 'latin');
if (latinText.value !== 'CA 7777 KK') {
	fail('SCRIPT_SAMPLE_LATIN ' + latinText.value);
}

const row1 = element({ value: 'OLD' });
const row2 = element({ value: 'ROW' });
const hidden = element({ value: 'HIDDEN', type: 'hidden' });
runReset(
	{ scripts: ['latin', 'cyrillic'], default_text: 'AA\n1234', default_text_cyrillic: '' },
	plateRoot({ text: hidden, rows: [row1, row2] }),
	'latin'
);
if (row1.value !== 'AA' || row2.value !== '1234' || hidden.value !== 'HIDDEN') {
	fail('SCRIPT_SAMPLE_ROWS ' + row1.value + '/' + row2.value + '/' + hidden.value);
}

const kept = element({ value: 'KEEP ME' });
runReset({ scripts: ['latin'], default_text: 'OTHER', default_text_cyrillic: 'ДРУГО' }, plateRoot({ text: kept }), 'cyrillic');
if (kept.value !== 'KEEP ME') {
	fail('SCRIPT_SAMPLE_SINGLE ' + kept.value);
}

const empty = element({ value: 'FILLED' });
runReset({ scripts: ['latin', 'cyrillic'], default_text: 'CA 7777 KK', default_text_cyrillic: '' }, plateRoot({ text: empty }), 'cyrillic');
if (empty.value !== '') {
	fail('SCRIPT_SAMPLE_EMPTY_CYRILLIC ' + empty.value);
}

function cartItem(key, text, offset, height, list) {
	return {
		textContent: text,
		querySelector(selector) {
			if (selector === '[data-cart_item_key]') {
				return { getAttribute() { return key; } };
			}
			return null;
		},
		getBoundingClientRect() {
			const top = 100 + offset - list.scrollTop;
			return { top: top, bottom: top + height, height: height };
		}
	};
}

function runReveal(options) {
	const writes = [];
	const list = {
		clientHeight: options.height || 377,
		_scroll: options.scroll || 0,
		writes: writes,
		get scrollTop() {
			return this._scroll;
		},
		set scrollTop(value) {
			this._scroll = value;
			writes.push(value);
		}
	};
	const items = (options.items || []).map(function (item) {
		return cartItem(item.key, item.text, item.offset, item.height, list);
	});
	list.querySelectorAll = function () {
		return items;
	};
	list.getBoundingClientRect = function () {
		return { top: 100, bottom: 100 + list.clientHeight, height: list.clientHeight };
	};
	const queue = [];
	let now = 0;
	const context = {
		document: {
			body: {
				classList: {
					contains(name) {
						return !options.closed && (name === 'showing-popup-drawer-from-right' || name === 'showing-popup-drawer-from-cart');
					}
				}
			},
			querySelector(selector) {
				if (selector === '#cart-drawer ul.woocommerce-mini-cart') {
					return options.missingList ? null : list;
				}
				return null;
			}
		},
		window: {
			requestAnimationFrame(fn) {
				queue.push(fn);
				return queue.length;
			},
			matchMedia(query) {
				return { matches: !!options.reduced && query.indexOf('prefers-reduced-motion') !== -1 };
			}
		}
	};
	context.window.requestAnimationFrame = context.window.requestAnimationFrame;
	vm.runInNewContext(
		drawerSource + '\n' + revealSource + '\nrevealAddedCartLine(previousKeys, plateText, originScroll);',
		Object.assign(context, {
			previousKeys: options.previousKeys || [],
			plateText: options.plateText || '',
			originScroll: options.origin
		})
	);
	let guard = 0;
	while (queue.length && guard < 80) {
		const fn = queue.shift();
		now += 16;
		fn(now);
		guard += 1;
	}
	if (queue.length) {
		fail('CART_SCROLL_DID_NOT_FINISH');
	}
	return { scroll: list.scrollTop, writes: writes.slice() };
}

const hiddenLine = runReveal({
	previousKeys: ['old'],
	origin: 0,
	items: [
		{ key: 'old', text: 'Стойка', offset: 0, height: 80 },
		{ key: 'new', text: 'Текст: RR 4040 RR', offset: 500, height: 400 }
	]
});
const between = hiddenLine.writes.filter(function (value) {
	return value > 0 && value < 500;
});
if (hiddenLine.scroll !== 500 || between.length < 3 || hiddenLine.writes[0] !== 0 || hiddenLine.writes[hiddenLine.writes.length - 1] !== 500) {
	fail('CART_SCROLL_HIDDEN writes=' + hiddenLine.writes.join(','));
}

const visibleLine = runReveal({
	previousKeys: [],
	origin: 0,
	items: [{ key: 'new', text: 'Текст: SHORT', offset: 0, height: 80 }]
});
if (visibleLine.writes.length !== 0 || visibleLine.scroll !== 0) {
	fail('CART_SCROLL_VISIBLE writes=' + visibleLine.writes.join(','));
}

const jumped = runReveal({
	previousKeys: ['old'],
	origin: 0,
	scroll: 500,
	items: [
		{ key: 'old', text: 'Стойка', offset: 0, height: 80 },
		{ key: 'new', text: 'Текст: RR 4040 RR', offset: 500, height: 400 }
	]
});
if (jumped.writes[0] !== 0 || jumped.scroll !== 500 || jumped.writes.filter(function (value) { return value > 0 && value < 500; }).length < 3) {
	fail('CART_SCROLL_REPLAY writes=' + jumped.writes.join(','));
}

const byText = runReveal({
	previousKeys: ['same'],
	origin: 0,
	plateText: 'QQ 6060 QQ',
	items: [{ key: 'same', text: 'Текст: QQ 6060 QQ', offset: 420, height: 120 }]
});
if (byText.scroll !== 163) {
	fail('CART_SCROLL_TEXT ' + byText.scroll);
}

const reduced = runReveal({
	previousKeys: [],
	origin: 0,
	reduced: true,
	items: [{ key: 'new', text: 'Текст: RR 4040 RR', offset: 500, height: 400 }]
});
if (reduced.writes.length !== 1 || reduced.scroll !== 500) {
	fail('CART_SCROLL_REDUCED writes=' + reduced.writes.join(','));
}

const none = runReveal({
	previousKeys: ['kept'],
	origin: 12,
	scroll: 12,
	plateText: 'MISSING',
	items: [{ key: 'kept', text: 'Текст: OTHER', offset: 0, height: 400 }]
});
if (none.writes.length !== 0 || none.scroll !== 12) {
	fail('CART_SCROLL_NO_TARGET writes=' + none.writes.join(','));
}

process.stdout.write('SHOP_BEHAVIOR_OK\n');
