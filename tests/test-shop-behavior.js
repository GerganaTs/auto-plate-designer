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
	const marker = 'function ' + name + '(';
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
	'function run(cfg, root, scriptName, textOnly) {\n' +
	'  var scriptWarning = true;\n' +
	'  var calls = [];\n' +
	'  function currentScript() { return scriptName; }\n' +
	'  function upperPlateControl(input) { calls.push("upper"); input.value = String(input.value).toLocaleUpperCase("bg"); }\n' +
	'  function stripWrongScript() { calls.push("strip"); return false; }\n' +
	'  function syncSuvRows() { calls.push("rows"); }\n' +
	'  function syncSideFields() { calls.push("sides"); }\n' +
	'  function updateCount() { calls.push("count"); }\n' +
	restoreSource + '\n' + resetSource + '\n' +
	'  resetFieldsAfterScript(textOnly);\n' +
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

const restoredText = element({ value: 'CA 7777 KK' });
const restoredColor = element({ value: '#E33D00', attributes: { 'data-apd-initial': '#000000', 'data-apd-color': 'apd_text_color' } });
const restoredPlain = element({ checked: true, attributes: { 'data-apd-initial-on': '0' } });
runReset(both, plateRoot({ text: restoredText, plain: restoredPlain, colors: { apd_text_color: restoredColor } }), 'cyrillic', true);
if (restoredText.value !== 'КИРИЛИЦА' || restoredColor.value !== '#E33D00' || restoredPlain.checked !== true) {
	fail('RESTORED_SCRIPT_SAMPLE text=' + restoredText.value + ' color=' + restoredColor.value + ' plain=' + restoredPlain.checked);
}

const restoredLatin = element({ value: 'КИРИЛИЦА 12' });
runReset(both, plateRoot({ text: restoredLatin }), 'latin', true);
if (restoredLatin.value !== 'CA 7777 KK') {
	fail('RESTORED_LATIN_SAMPLE ' + restoredLatin.value);
}

function mismatchRoot(value, type) {
	return {
		querySelectorAll() {
			return [{ type: type || 'text', value: value }];
		}
	};
}
const mismatchScript = 'function isHolderFamily(type){ return type === "holder" || type === "holder_moto" || type === "holder_d"; }\n' + extractFunction('scriptGuardsText') + '\n' + extractFunction('scriptRejected') + '\n' + extractFunction('plateTextMismatchesScript');
function mismatches(scriptName, value, scripts, type) {
	return vm.runInNewContext(
		'function currentScript() { return scriptName; }\n' + mismatchScript + '\nplateTextMismatchesScript(root);',
		{
			cfg: { scripts: scripts || ['latin', 'cyrillic'], format: { letter_rule: 'count' } },
			scriptName: scriptName,
			root: mismatchRoot(value, type)
		}
	);
}
if (!mismatches('cyrillic', 'CA 7777 KK') || !mismatches('latin', 'КИРИЛИЦА 12') || mismatches('cyrillic', 'КИРИЛИЦА') || mismatches('cyrillic', '7777') || mismatches('cyrillic', 'CA 7777 KK', ['cyrillic']) || mismatches('cyrillic', 'CA 7777 KK', ['latin', 'cyrillic'], 'hidden')) {
	fail('RESTORED_SCRIPT_MISMATCH');
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

const wideNames = ['legalNarrowGlyph', 'legalWideGlyph', 'legalPlateGlyph', 'glyphInk', 'cyrillicPlate', 'cyrillicTailLetter', 'cyrillicCrownLetter', 'measureInk', 'registrationFace', 'frenchFont', 'fontSizeForInk', 'layoutLegalLine'];
const wideSource = wideNames.map(extractFunction).join('\n');
const widths = { '0': 20, H: 22, L: 10, K: 20, W: 36, M: 34, A: 28 };
function measureLetter(ch) {
	const ink = widths[ch] || 20;
	return {
		width: ink,
		actualBoundingBoxLeft: 0,
		actualBoundingBoxRight: ink,
		actualBoundingBoxAscent: 40,
		actualBoundingBoxDescent: 0
	};
}
const wideLine = vm.runInNewContext(
	'function currentScript() { return "latin"; }\n' +
	wideSource + '\n' +
	'layoutLegalLine(ctx, ["L", "K", "0", "W", "M", "A"], { family: "UK", weight: 400, style: "normal" }, { height: 79, width: 50, narrow: 14, gap: 11, group: 50 }, 79);\n',
	{ ctx: { measureText: measureLetter, font: '' } }
);
const wideGlyphs = {};
wideLine.glyphs.forEach(function (item) {
	if (item.ch) {
		wideGlyphs[item.ch] = item;
	}
});
const digitScale = wideGlyphs['0'].sx;
['W', 'M', 'A'].forEach(function (ch) {
	const squeezed = wideGlyphs['0'].adv / widths[ch];
	if (Math.abs(wideGlyphs[ch].sx - digitScale) > 0.001 || wideGlyphs[ch].adv <= wideGlyphs['0'].adv || wideGlyphs[ch].sx <= squeezed + 0.001) {
		fail('WIDE_GLYPH_SQUEEZED ' + ch + ' sx=' + wideGlyphs[ch].sx + ' adv=' + wideGlyphs[ch].adv);
	}
});
if (Math.abs(wideGlyphs.L.adv - wideGlyphs['0'].adv) > 0.001 || Math.abs(wideGlyphs.K.adv - wideGlyphs['0'].adv) > 0.001) {
	fail('NARROW_GLYPH_CELL L=' + wideGlyphs.L.adv + ' K=' + wideGlyphs.K.adv + ' 0=' + wideGlyphs['0'].adv);
}

const cartEditSource = extractFunction('cartEditSignature') + '\n' + extractFunction('syncCartEditButton');
function cartField(extra) {
	return Object.assign({
		name: '',
		type: 'text',
		value: '',
		checked: false,
		disabled: false
	}, extra || {});
}
function cartRoot(fields) {
	return {
		querySelectorAll(selector) {
			if (selector === 'input, select, textarea') {
				return fields;
			}
			return [];
		}
	};
}
const cartButton = {
	type: 'submit',
	textContent: 'Добавяне в количката',
	disabled: true,
	attributes: {},
	setAttribute(name, value) {
		this.attributes[name] = String(value);
	},
	removeAttribute(name) {
		delete this.attributes[name];
	},
	getAttribute(name) {
		return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null;
	}
};
const cartFields = [
	cartField({ name: 'apd_text', value: 'CA 7777 KK' }),
	cartField({ name: 'apd_comment', value: '' }),
	cartField({ name: 'apd_frame_plain', type: 'checkbox', value: '1', checked: false }),
	cartField({ name: 'apd_nonce', value: 'secret' }),
	cartField({ name: 'apd_cart_item', value: 'line-key' })
];
const cartState = vm.runInNewContext(
	'var cfg = { i18n: { backToCart: "Към количка", refreshCart: "Опресняване на количката" }, cart_url: "/kolichka/" };\n' +
	cartEditSource + '\n' +
	'var saved = cartEditSignature(root);\n' +
	'syncCartEditButton(button, saved, saved);\n' +
	'var clean = { type: button.type, text: button.textContent, back: button.getAttribute("data-apd-cart-back"), disabled: button.disabled };\n' +
	'fields[0].value = "CA 7778 KK";\n' +
	'syncCartEditButton(button, saved, cartEditSignature(root));\n' +
	'var dirty = { type: button.type, text: button.textContent, back: button.getAttribute("data-apd-cart-back") };\n' +
	'({ saved: saved, clean: clean, dirty: dirty });\n',
	{ root: cartRoot(cartFields), button: cartButton, fields: cartFields }
);
if (cartState.saved.indexOf('apd_nonce') !== -1 || cartState.saved.indexOf('apd_cart_item') !== -1 || cartState.saved.indexOf('quantity') !== -1) {
	fail('CART_EDIT_SIGNATURE_LEAK ' + cartState.saved);
}
if (cartState.clean.type !== 'button' || cartState.clean.text !== 'Към количка' || cartState.clean.back !== '1' || cartState.clean.disabled !== false) {
	fail('CART_EDIT_CLEAN ' + JSON.stringify(cartState.clean));
}
if (cartState.dirty.type !== 'submit' || cartState.dirty.text !== 'Опресняване на количката' || cartState.dirty.back !== null) {
	fail('CART_EDIT_DIRTY ' + JSON.stringify(cartState.dirty));
}
if (source.indexOf('cartEditSignature(root)') < 0 || source.indexOf('cartEditSignature(form)') !== -1 || source.indexOf('cartEditSignature(cartForm)') !== -1) {
	fail('CART_EDIT_QTY_SCOPE');
}
if (source.indexOf('data-apd-cart-back') < 0 || source.indexOf('cfg.cart_url') < 0) {
	fail('CART_EDIT_NAV_MISSING');
}

const cornerSource = extractFunction('plateCornerRadius') + '\n' + extractFunction('insetCornerRadius');
const corners = vm.runInNewContext(cornerSource + '\n({ outer: plateCornerRadius(110), short: plateCornerRadius(40), inner: insetCornerRadius(110, 4), flush: insetCornerRadius(110, 0), thick: insetCornerRadius(110, 12) });');
if (corners.outer !== 10 || Math.abs(corners.short - 4.8) > 0.001 || corners.inner !== 6 || corners.flush !== 10 || corners.thick !== 0) {
	fail('CORNER_RADIUS ' + JSON.stringify(corners));
}
const framePaint = extractFunction('paintAdminFrame');
if (framePaint.indexOf("fill('evenodd')") < 0 || framePaint.indexOf('insetCornerRadius(h, border)') < 0 || framePaint.indexOf('border / 2') !== -1 || framePaint.indexOf('.stroke()') !== -1) {
	fail('FRAME_RING');
}
if (framePaint.indexOf('sharedFrame && !cfg.lock_colors') < 0) {
	fail('LOCK_FRAME_COLOR');
}
if (source.indexOf('roundRect(ctx, 1, 1, w - 2, h - 2, radius)') !== -1) {
	fail('FILL_INSET');
}
const bandPlace = extractFunction('placeBandLayer');
const bandClip = extractFunction('clipCountryBand');
if (bandPlace.indexOf('cornerRadiusCss(') < 0 || bandClip.indexOf('bandCornerRadius(') < 0 || bandPlace.indexOf('plateCornerRadius(h) * scale') !== -1) {
	fail('BAND_CORNER');
}
const bandRadius = vm.runInNewContext(
	extractFunction('plateCornerRadius') + '\n' + extractFunction('insetCornerRadius') + '\n' + extractFunction('bandCornerRadius') + '\n' + extractFunction('cornerRadiusCss') + '\n' +
	'({ eu: bandCornerRadius(110, 4, 4), suv: bandCornerRadius(200, 6.12, 6), mid: bandCornerRadius(200, 6, 100), css: cornerRadiusCss(6, 6), ellipse: cornerRadiusCss(3.88, 4) });'
);
if (bandRadius.eu !== 6 || Math.abs(bandRadius.suv - 3.88) > 0.02 || bandRadius.mid !== 0 || bandRadius.css !== '6px' || bandRadius.ellipse !== '3.88px 4px') {
	fail('BAND_RADIUS ' + JSON.stringify(bandRadius));
}
const euroSource = extractFunction('euroBandSplit') + '\n' + extractFunction('drawEuroBand');
function euroCalls(dw, dh) {
	const calls = [];
	vm.runInNewContext(euroSource + '\ndrawEuroBand(ctx, img, 0, 0, dw, dh);', {
		ctx: { drawImage() { calls.push(Array.prototype.slice.call(arguments)); } },
		img: { naturalWidth: 69, naturalHeight: 140, _apdBandSplit: 80 },
		dw: dw,
		dh: dh
	});
	return calls;
}
const narrowEuro = euroCalls(40, 102);
const wideEuro = euroCalls(80, 102);
const narrowScale = 40 / 69;
const narrowTop = 80 * narrowScale;
const narrowBot = 60 * narrowScale;
if (narrowEuro.length !== 2 || narrowEuro[0].length !== 9 || narrowEuro[1].length !== 9) {
	fail('EURO_BAND_SPLIT ' + JSON.stringify(narrowEuro));
}
if (narrowEuro[0][1] !== 0 || narrowEuro[0][3] !== 69 || narrowEuro[1][1] !== 0 || narrowEuro[1][3] !== 69) {
	fail('EURO_BAND_CROP ' + JSON.stringify(narrowEuro));
}
if (Math.abs(narrowEuro[0][6]) > 0.001 || Math.abs(narrowEuro[0][8] - narrowTop) > 0.001 || Math.abs(narrowEuro[1][6] - (102 - narrowBot)) > 0.001) {
	fail('EURO_BAND_GAP ' + JSON.stringify(narrowEuro));
}
if ((narrowEuro[1][6] - (narrowEuro[0][6] + narrowEuro[0][8])) < 10) {
	fail('EURO_BAND_GAP_SMALL ' + JSON.stringify(narrowEuro));
}
const bleed = extractFunction('bleedEuroBandPixels');
function px(r, g, b, a) {
	return [r, g, b, a];
}
const bw = 8;
const bh = 10;
const raw = new Uint8ClampedArray(bw * bh * 4);
function put(x, y, r, g, b, a) {
	const i = (y * bw + x) * 4;
	raw[i] = r;
	raw[i + 1] = g;
	raw[i + 2] = b;
	raw[i + 3] = a;
}
for (let y = 0; y < bh; y++) {
	for (let x = 0; x < bw; x++) {
		const edge = x === 0 || y === 0 || x === bw - 1 || y === bh - 1;
		const stroke = x === 1 || y === 1 || x === bw - 2 || y === bh - 2;
		if (edge) {
			put(x, y, 0, 0, 0, 0);
		} else if (stroke) {
			put(x, y, 0, 13, 38, 255);
		} else if (x === 3 && y === 4) {
			put(x, y, 0, 0, 0, 255);
		} else {
			put(x, y, 0, 51, 153, 255);
		}
	}
}
const cleaned = vm.runInNewContext(bleed + '\nbleedEuroBandPixels(data, sw, sh);', {
	data: raw,
	sw: bw,
	sh: bh,
	Uint8Array: Uint8Array,
	Uint8ClampedArray: Uint8ClampedArray
});
function at(buf, x, y) {
	const i = (y * bw + x) * 4;
	return [buf[i], buf[i + 1], buf[i + 2], buf[i + 3]];
}
const strokePx = at(cleaned, 1, 3);
const insideBlack = at(cleaned, 3, 4);
const clearPx = at(cleaned, 0, 0);
if (strokePx[2] < 100 || insideBlack[0] !== 0 || insideBlack[1] !== 0 || insideBlack[2] !== 0 || clearPx[3] !== 0) {
	fail('PRESET_FRINGE ' + JSON.stringify({ strokePx: strokePx, insideBlack: insideBlack, clearPx: clearPx }));
}
const jw = 12;
const jh = 10;
const jpeg = new Uint8ClampedArray(jw * jh * 4);
function paint(x, y, r, g, b) {
	const i = (y * jw + x) * 4;
	jpeg[i] = r;
	jpeg[i + 1] = g;
	jpeg[i + 2] = b;
	jpeg[i + 3] = 255;
}
for (let y = 0; y < jh; y++) {
	for (let x = 0; x < jw; x++) {
		const matte = y < 2 || y > 7 || x === jw - 1;
		if (matte) {
			paint(x, y, 255, 255, 255);
		} else if (x === 2 && y === 2) {
			paint(x, y, 97, 129, 190);
		} else if (x >= 3 && x <= 5 && y >= 4 && y <= 5) {
			paint(x, y, 255, 255, 255);
		} else if (x === 7 && y === 4) {
			paint(x, y, 0, 0, 0);
		} else if (x === 4 && y === 6) {
			paint(x, y, 214, 37, 17);
		} else if (x === 6 && y === 3) {
			paint(x, y, 16, 65, 160);
		} else if (x === 8 && y === 3) {
			paint(x, y, 209, 217, 236);
		} else if (x === 8 && y === 5) {
			paint(x, y, 209, 217, 236);
		} else if (x === 9 && y === 5) {
			paint(x, y, 255, 255, 255);
		} else {
			paint(x, y, 0, 51, 153);
		}
	}
}
const jpegClean = vm.runInNewContext(bleed + '\nbleedEuroBandPixels(data, sw, sh);', {
	data: jpeg,
	sw: jw,
	sh: jh,
	Uint8Array: Uint8Array,
	Uint8ClampedArray: Uint8ClampedArray
});
function jpegAt(x, y) {
	const i = (y * jw + x) * 4;
	return [jpegClean[i], jpegClean[i + 1], jpegClean[i + 2], jpegClean[i + 3]];
}
const jpegCorner = jpegAt(0, 0);
const jpegHalo = jpegAt(2, 2);
const jpegStripe = jpegAt(4, 5);
const jpegInk = jpegAt(7, 4);
const jpegEdge = jpegAt(jw - 1, 5);
const jpegFlag = jpegAt(4, 6);
const jpegField = jpegAt(6, 3);
const jpegBubble = jpegAt(8, 3);
const jpegLetterEdge = jpegAt(8, 5);
const jpegLetter = jpegAt(9, 5);
if (jpegCorner[2] < 100 || jpegCorner[0] > 80 || jpegHalo[2] < 100 || jpegStripe[0] !== 255 || jpegStripe[1] !== 255 || jpegStripe[2] !== 255 || jpegInk[0] !== 0 || jpegInk[1] !== 0 || jpegInk[2] !== 0 || jpegEdge[2] < 100 || jpegFlag[0] !== 214 || jpegBubble[0] > 40 || jpegBubble[2] < 140 || jpegField[0] !== 0 || jpegField[1] !== 51 || jpegField[2] !== 153 || jpegLetterEdge[0] !== 209 || jpegLetter[0] !== 255 || jpegLetter[1] !== 255 || jpegLetter[2] !== 255) {
	fail('PRESET_JPEG_MATTE ' + JSON.stringify({ jpegCorner: jpegCorner, jpegHalo: jpegHalo, jpegStripe: jpegStripe, jpegInk: jpegInk, jpegEdge: jpegEdge, jpegFlag: jpegFlag, jpegBubble: jpegBubble, jpegField: jpegField, jpegLetterEdge: jpegLetterEdge, jpegLetter: jpegLetter }));
}
const singleBand = vm.runInNewContext(
	extractFunction('fitSinglePresetBand') + '\nfitSinglePresetBand(band, 520);',
	{
		cfg: { format: { presets: [{ image_url: 'de.png' }], border_width: 4 } },
		imageCache: { 'de.png': { naturalWidth: 69, naturalHeight: 140 } },
		frameThickness: function () { return 4; },
		band: { x: 4, y: 4, width: 36, height: 102, side: 'left' }
	}
);
const manyBand = vm.runInNewContext(
	extractFunction('fitSinglePresetBand') + '\nfitSinglePresetBand(band, 520);',
	{
		cfg: { format: { presets: [{ image_url: 'de.png' }, { image_url: 'bg.png' }] } },
		imageCache: { 'de.png': { naturalWidth: 69, naturalHeight: 140 } },
		frameThickness: function () { return 4; },
		band: { x: 4, y: 4, width: 48, height: 102, side: 'left' }
	}
);
const plate300 = vm.runInNewContext(
	extractFunction('fitSinglePresetBand') + '\nfitSinglePresetBand(band, 300);',
	{
		cfg: { format: { type: 'eu_300', presets: [{ image_url: 'bg.png' }], border_width: 4 } },
		imageCache: { 'bg.png': { naturalWidth: 69, naturalHeight: 140 } },
		frameThickness: function () { return 4; },
		band: { x: 4, y: 4, width: 52, height: 142, side: 'left' }
	}
);
const mid300 = vm.runInNewContext(
	extractFunction('fitSinglePresetBand') + '\nfitSinglePresetBand(band, 300);',
	{
		cfg: { format: { type: 'eu_300', presets: [{ image_url: 'bg.png' }] } },
		imageCache: { 'bg.png': { naturalWidth: 69, naturalHeight: 140 } },
		frameThickness: function () { return 4; },
		band: { x: 4, y: 4, width: 40, height: 100, side: 'left' }
	}
);
if (Math.abs(singleBand.width - (102 * 69 / 140)) > 0.01 || singleBand.x !== 4 || Math.abs(manyBand.width - 48) > 0.01 || Math.abs(plate300.width - 63.3) > 0.01 || plate300.x !== 4 || Math.abs(mid300.width - (100 * 69 / 140)) > 0.01) {
	fail('SINGLE_PRESET_BAND ' + JSON.stringify({ singleBand: singleBand, manyBand: manyBand, plate300: plate300, mid300: mid300 }));
}
const wideScale = 102 / 140;
const wideW = 69 * wideScale;
if (wideEuro.length !== 1 || wideEuro[0].length !== 5 || Math.abs(wideEuro[0][1] - ((80 - wideW) / 2)) > 0.001 || Math.abs(wideEuro[0][3] - wideW) > 0.001 || Math.abs(wideEuro[0][4] - 102) > 0.001) {
	fail('EURO_BAND_WIDE ' + JSON.stringify(wideEuro));
}
['paintLockedBand', 'paintOpenBand', 'plateSnapshot'].forEach(function (name) {
	const body = extractFunction(name);
	if (body.indexOf('drawEuroBand(') < 0 || body.indexOf('drawImageCover(') !== -1 || body.indexOf('drawImageContain(') !== -1) {
		fail('EURO_BAND_CALL ' + name);
	}
});
const miniDraw = extractFunction('drawMiniPlate');
if (miniDraw.indexOf('strokeStyle') !== -1 || miniDraw.indexOf('lineWidth') !== -1 || miniDraw.indexOf('.stroke()') !== -1) {
	fail('MINI_CANVAS_RIM');
}
const austriaDraw = extractFunction('drawAustriaPlate');
if (austriaDraw.indexOf('strokeStyle') !== -1 || austriaDraw.indexOf('lineWidth') !== -1 || austriaDraw.indexOf('.stroke()') !== -1) {
	fail('AUSTRIA_CANVAS_RIM');
}
if (extractFunction('drawEnglishPlate').indexOf('strokeEnglishRim(') < 0 || extractFunction('drawFrenchPlate').indexOf('lineWidth = 1.4') < 0) {
	fail('OTHER_RIM_MISSING');
}

const letterModeSource = 'function currentScript(){ return scriptName; }\n' + extractFunction('letterMode');
function plateMode(scriptName, text, digitalOnly, rows) {
	return vm.runInNewContext(letterModeSource + '\nletterMode(text);', {
		cfg: {
			digital_only: !!digitalOnly,
			format: { letter_rule: 'count', embossed_chars: 9, embossed_rows: rows || [5, 4] }
		},
		scriptName: scriptName,
		text: text
	});
}
if (plateMode('latin', 'AB\n12', false) !== 'embossed' || plateMode('latin', 'AB\n12', true) !== 'digital' || plateMode('latin', 'CA', false, [9, 0]) !== 'embossed' || plateMode('cyrillic', 'А', false) !== 'digital') {
	fail('DIGITAL_ONLY_MODE');
}
const noticeSource = extractFunction('syncLetterChrome');
if (noticeSource.indexOf('cfg.digital_only') < 0 || noticeSource.indexOf('cyrillicNotice') < noticeSource.indexOf('cfg.digital_only')) {
	fail('DIGITAL_ONLY_NOTICE');
}

const holderScriptSource = 'function currentScript(){ return scriptName; }\n' +
	'function isHolderFamily(type){ return type === "holder" || type === "holder_moto" || type === "holder_d"; }\n' +
	extractFunction('scriptGuardsText') + '\n' +
	extractFunction('stripWrongScript') + '\n' +
	extractFunction('holderRunFont');
function strippedHolder(scriptName, value, type, letterRule) {
	const input = { type: 'text', value: value, selectionStart: value.length, setSelectionRange: function () {} };
	vm.runInNewContext(holderScriptSource + '\nstripWrongScript(input);', {
		cfg: { scripts: ['latin', 'cyrillic', 'combined'], format: { type: type, letter_rule: letterRule || '' } },
		scriptName: scriptName,
		input: input
	});
	return input.value;
}
if (strippedHolder('latin', 'АБ12', 'holder', '') !== '12' || strippedHolder('cyrillic', 'AB12', 'holder', '') !== '12' || strippedHolder('combined', 'АБAB', 'holder', '') !== 'АБAB' || strippedHolder('latin', 'А', 'uk', '') !== 'А') {
	fail('HOLDER_SCRIPT_STRIP');
}
const latinFace = { family: 'Oswald' };
const cyrFace = { family: 'Roboto' };
const cyrRun = vm.runInNewContext(holderScriptSource + '\nholderRunFont(ch, latinFace, cyrFace);', {
	ch: 'А',
	latinFace: latinFace,
	cyrFace: cyrFace,
	cfg: { scripts: ['combined'], format: { type: 'holder', letter_rule: '' } },
	scriptName: 'combined'
});
const latinRun = vm.runInNewContext(holderScriptSource + '\nholderRunFont(ch, latinFace, cyrFace);', {
	ch: 'A',
	latinFace: latinFace,
	cyrFace: cyrFace,
	cfg: { scripts: ['combined'], format: { type: 'holder', letter_rule: '' } },
	scriptName: 'combined'
});
if (!cyrRun || cyrRun.family !== 'Roboto' || !latinRun || latinRun.family !== 'Oswald') {
	fail('HOLDER_RUN_FONT');
}
const shiftSource = extractFunction('holderInkCenterShift');
const highShift = vm.runInNewContext(shiftSource + '\nholderInkCenterShift(20, 4);');
const lowShift = vm.runInNewContext(shiftSource + '\nholderInkCenterShift(10, 16);');
if (highShift !== 8 || lowShift !== -3) {
	fail('HOLDER_INK_CENTER');
}
const holderDrawAt = source.indexOf('if (isHolderFamily(type))');
const holderDraw = holderDrawAt < 0 ? '' : source.slice(holderDrawAt, holderDrawAt + 1400);
if (holderDraw.indexOf('drawHolderMixed(') < 0 || holderDraw.indexOf('drawTextInBox(') >= 0 || holderDraw.indexOf("currentScript() === 'combined' ? currentCyrFont(root) : font") < 0 || source.indexOf('cursor + (line.above + line.below) / 2') < 0) {
	fail('HOLDER_MIXED_DRAW');
}
const upperSource = 'function isHolderFamily(type){ return type === "holder" || type === "holder_moto" || type === "holder_d"; }\n' + extractFunction('upperPlateControl');
function keepCase(type, value) {
	const input = { type: 'text', value: value, selectionStart: value.length, selectionEnd: value.length, setSelectionRange: function () {} };
	vm.runInNewContext(upperSource + '\nupperPlateControl(input);', { cfg: { format: { type: type } }, input: input });
	return input.value;
}
if (keepCase('holder', 'ab') !== 'ab' || keepCase('holder_moto', 'аб') !== 'аб' || keepCase('holder_d', 'Ab') !== 'Ab' || keepCase('eu', 'ab') !== 'AB' || keepCase('mini', 'ca') !== 'CA') {
	fail('HOLDER_CASE');
}

process.stdout.write('SHOP_BEHAVIOR_OK\n');
