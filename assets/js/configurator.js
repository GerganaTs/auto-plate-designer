/**
 * Auto Plate Designer — frontend canvas configurator.
 * Enqueued only on product pages with the configurator enabled.
 */
(function () {
	'use strict';

	var cfg = window.apdConfig;
	if (!cfg || !cfg.format) {
		return;
	}

	var bandHit = null;
	var imageCache = {};
	var previewChromeTop = null;
	var previewStageFrame = 0;
	var paintCartEdit = function () {};

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function $(root, sel) {
		return root.querySelector(sel);
	}

	function countChars(value) {
		return Array.from(value).length;
	}

	function formatType() {
		return cfg.format && cfg.format.type ? cfg.format.type : '';
	}

	function formatCaps(type) {
		type = type || formatType();
		if (cfg.format && cfg.format.type === type && cfg.format.capabilities) {
			return cfg.format.capabilities;
		}
		return null;
	}

	function usesCountryBand(type) {
		type = type || formatType();
		var caps = formatCaps(type);
		if (caps) {
			return !!caps.country_band;
		}
		return type === 'eu' || type === 'moto' || type === 'moto_240' || type === 'suv_eu' || type === 'suv_eu_340' || type === 'mini' || type === 'super_mini';
	}

	function usesPaintedPlate(type) {
		type = type || formatType();
		var caps = formatCaps(type);
		if (caps) {
			return !!caps.painted;
		}
		return type === 'eu' || type === 'eu_plain' || type === 'moto' || type === 'moto_240' || type === 'moto_plain' || type === 'moto_plain_240' || type === 'suv' || type === 'suv_340' || type === 'suv_eu' || type === 'suv_eu_340' || type === 'custom' || type === 'color';
	}

	function usesBaseImage(type) {
		type = type || formatType();
		var caps = formatCaps(type);
		if (caps) {
			return !!caps.base_image;
		}
		return type === 'holder' || type === 'holder_moto' || type === 'holder_d';
	}

	function isHolderFamily(type) {
		type = type || formatType();
		return type === 'holder' || type === 'holder_moto' || type === 'holder_d';
	}

	function usesTwoRows(type) {
		type = type || formatType();
		var caps = formatCaps(type);
		if (caps) {
			return !!caps.two_row;
		}
		return type === 'moto' || type === 'moto_240' || type === 'moto_plain' || type === 'moto_plain_240' || type === 'suv' || type === 'suv_340' || type === 'suv_eu' || type === 'suv_eu_340' || type === 'eu_300';
	}

	function rowsForPlate(text) {
		var raw = String(text || '');
		var breakAt = raw.indexOf('\n');
		if (breakAt !== -1) {
			return {
				letters: raw.slice(0, breakAt),
				numbers: raw.slice(breakAt + 1)
			};
		}
		return splitPlateRows(raw);
	}

	function splitPlateRows(text) {
		var raw = String(text || '');
		var letters = '';
		var numbers = '';
		var chars = Array.from(raw);
		var i;
		var ch;
		for (i = 0; i < chars.length; i += 1) {
			ch = chars[i];
			if (/[0-9]/.test(ch)) {
				numbers += ch;
			} else if (/[\p{L}]/u.test(ch)) {
				letters += ch;
			}
		}
		var groups = [];
		for (i = 0; i < letters.length; i += 2) {
			groups.push(letters.slice(i, i + 2));
		}
		return {
			letters: groups.join(' '),
			numbers: numbers
		};
	}

	function plateRowAlign(value) {
		if (value === 'left' || value === 'right' || value === 'center' || value === 'justify') {
			return value;
		}
		return 'center';
	}

	function rowAlignValue(value) {
		if (value === 'left' || value === 'right' || value === 'center' || value === 'justify') {
			return value;
		}
		return 'justify';
	}

	function usesPlateDesigns(type) {
		type = type || formatType();
		var caps = formatCaps(type);
		if (caps) {
			return !!caps.plate_designs;
		}
		return type === 'us';
	}

	function currentFont(root) {
		var fonts = cfg.format.fonts || [];
		if (!fonts.length) {
			return { family: 'sans-serif', weight: 700, style: 'normal' };
		}
		var input = root.querySelector('[name="apd_font_id"]');
		var id = input ? input.value : fonts[0].id;
		var i;
		var matched = null;
		for (i = 0; i < fonts.length; i += 1) {
			if (fonts[i].id === id) {
				matched = fonts[i];
				break;
			}
		}
		return drawingFont(matched || fonts[0]);
	}

	function currentSloganFont(root) {
		var fonts = cfg.format.fonts || [];
		var input = root.querySelector('[name="apd_slogan_font_id"]');
		if (!input || !fonts.length) {
			return currentFont(root);
		}
		var id = input.value;
		var i;
		for (i = 0; i < fonts.length; i += 1) {
			if (fonts[i].id === id) {
				return drawingFont(fonts[i]);
			}
		}
		return currentFont(root);
	}

	function sloganFace(font, size) {
		var family = font && font.family ? font.family : 'sans-serif';
		var weight = font && font.weight ? font.weight : 400;
		var style = font && font.style === 'italic' ? 'italic' : 'normal';
		return style + ' ' + weight + ' ' + size + 'px "' + family + '", sans-serif';
	}

	function currentCyrFont(root) {
		var fonts = cfg.format.fonts || [];
		var input = root.querySelector('[name="apd_font_id_cyr"]');
		var id = input && !input.disabled ? input.value : '';
		var i;
		var matched = null;
		for (i = 0; i < fonts.length; i += 1) {
			if (fonts[i].id === id) {
				matched = fonts[i];
				break;
			}
		}
		return drawingFont(matched || fonts[0]);
	}

	function faceFamily(font) {
		var name = font && font.family ? String(font.family) : 'sans-serif';
		var id = font && font.id ? String(font.id) : '';
		if (!id || name === 'sans-serif' || name.slice(-id.length) === id) {
			return name;
		}
		return name + ' ' + id;
	}

	function drawingFont(font) {
		if (!font || !font.family) {
			return { family: 'sans-serif', weight: 400, style: 'normal' };
		}
		return {
			id: font.id,
			family: faceFamily(font),
			weight: 400,
			style: font.style === 'italic' ? 'italic' : 'normal',
			url: font.url
		};
	}

	function fontFaceSpec(font, px) {
		var size = px || 48;
		var weight = 400;
		var style = font && font.style === 'italic' ? 'italic' : 'normal';
		var family = font && font.family ? font.family : 'sans-serif';
		return style + ' ' + weight + ' ' + size + 'px "' + family + '"';
	}

	var fontFacePromises = {};

	function ensureFontLoaded(font) {
		font = drawingFont(font);
		if (!font || !font.family || font.family === 'sans-serif') {
			return Promise.resolve(true);
		}
		if (!document.fonts) {
			return Promise.resolve(false);
		}

		var key = String(font.id || font.family) + '|' + String(font.url || font.family);
		if (fontFacePromises[key]) {
			return fontFacePromises[key];
		}

		var style = font.style === 'italic' ? 'italic' : 'normal';
		var spec = fontFaceSpec(font, 48);

		if (window.FontFace && font.url) {
			var source = 'url(' + JSON.stringify(String(font.url)) + ')';
			fontFacePromises[key] = new FontFace(String(font.family), source, {
				style: style,
				weight: '400',
				display: 'swap'
			}).load().then(function (loaded) {
				document.fonts.add(loaded);
				return document.fonts.load(spec);
			}).then(function () {
				return document.fonts.check(spec);
			}).catch(function () {
				return false;
			});
			return fontFacePromises[key];
		}

		fontFacePromises[key] = document.fonts.load(spec).then(function () {
			return document.fonts.check(spec);
		}).catch(function () {
			return false;
		});
		return fontFacePromises[key];
	}

	function ensureAllFontsLoaded() {
		var fonts = cfg.format.fonts || [];
		if (!document.fonts || !fonts.length) {
			return Promise.resolve();
		}
		return Promise.all(fonts.map(function (font) {
			return ensureFontLoaded(font);
		}));
	}

	function scheduleDraw(root, cache, keepStage) {
		var font = currentFont(root);
		var sloganFont = currentSloganFont(root);
		Promise.all([ensureFontLoaded(font), ensureFontLoaded(sloganFont)]).then(function () {
			draw(root, cache, keepStage);
		});
	}

	function offersCountryPicker() {
		var presets = (cfg.format && cfg.format.presets) || [];
		if (presets.length < 2) {
			return false;
		}
		return !(cfg.format && cfg.format.capabilities && cfg.format.capabilities.country_locked);
	}

	function currentPreset() {
		if ( ! usesCountryBand() ) {
			return null;
		}
		var presets = cfg.format.presets || [];
		if (!presets.length) {
			return null;
		}
		var input = document.querySelector('[data-apd-preset]');
		var id = input ? input.value : presets[0].id;
		var i;
		for (i = 0; i < presets.length; i += 1) {
			if (presets[i].id === id) {
				return presets[i];
			}
		}
		return presets[0];
	}

	function currentDesign() {
		if (!usesPlateDesigns()) {
			return null;
		}
		var designs = cfg.format.designs || [];
		if (!designs.length) {
			return null;
		}
		var input = document.querySelector('[data-apd-design]');
		var id = input ? input.value : designs[0].id;
		var i;
		for (i = 0; i < designs.length; i += 1) {
			if (designs[i].id === id) {
				return designs[i];
			}
		}
		return designs[0];
	}

	function colorValue(name) {
		var input = document.querySelector('[data-apd-color="' + name + '"]');
		if (input && input.value) {
			return input.value;
		}
		if (name === 'apd_background_color') {
			return '#FFFFFF';
		}
		if (name === 'apd_border_color' && cfg.format && cfg.format.border_color) {
			return cfg.format.border_color;
		}
		return '#000000';
	}

	function getCanvas() {
		return document.querySelector('.apd-canvas');
	}

	function currentScript() {
		var picked = document.querySelector('[data-apd-script]:checked') || document.querySelector('[name="apd_script"]');
		return picked && picked.value ? picked.value : 'latin';
	}

	function scriptGuardsText() {
		if (!cfg.format || !cfg.scripts || !cfg.scripts.length) {
			return false;
		}
		if (currentScript() === 'combined') {
			return false;
		}
		return !!(cfg.format.letter_rule || isHolderFamily(cfg.format.type));
	}

	function currentPlateText() {
		var input = document.querySelector('[name="apd_text"]');
		return input ? input.value : '';
	}

	function letterMode(text) {
		var rule = cfg.format && cfg.format.letter_rule ? cfg.format.letter_rule : '';
		if (cfg.digital_only && (rule === 'digital' || rule === 'count')) {
			return 'digital';
		}
		if (rule === 'digital') {
			return 'digital';
		}
		if (rule !== 'count') {
			return '';
		}
		if (currentScript() === 'cyrillic') {
			return 'digital';
		}
		var rows = String(text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
		var limits = cfg.format.embossed_rows || [];
		if (limits.length >= 2 && limits[1] > 0 && rows.length >= 2) {
			if (Array.from(rows[0] || '').length > limits[0] || Array.from(rows[1] || '').length > limits[1]) {
				return 'digital';
			}
			return 'embossed';
		}
		var flat = Array.from(String(text || '').replace(/[\r\n]/g, ''));
		return flat.length > (cfg.format.embossed_chars || 9) ? 'digital' : 'embossed';
	}

	function scriptRejected(text) {
		if (!scriptGuardsText()) {
			return false;
		}
		var value = String(text || '').replace(/[\r\n]/g, '');
		if (!value) {
			return false;
		}
		if (currentScript() === 'cyrillic') {
			return /[A-Za-z\u00C0-\u024F]/.test(value);
		}
		return /[\u0400-\u04FF]/.test(value);
	}

	function plateTextMismatchesScript(root) {
		if (!root || !cfg.scripts || cfg.scripts.length < 2) {
			return false;
		}
		var mismatched = false;
		root.querySelectorAll('[name="apd_text"], [data-apd-plate-row]').forEach(function (input) {
			if (input && input.type !== 'hidden' && scriptRejected(input.value)) {
				mismatched = true;
			}
		});
		return mismatched;
	}

	function scriptPlateText(text) {
		text = String(text || '');
		if (!scriptRejected(text)) {
			return text;
		}
		if (currentScript() === 'cyrillic') {
			return text.replace(/[A-Za-z\u00C0-\u024F]/g, '');
		}
		return text.replace(/[\u0400-\u04FF]/g, '');
	}

	function stripWrongScript(input) {
		if (!input || input.type === 'hidden' || !scriptGuardsText()) {
			return false;
		}
		var value = String(input.value || '');
		var pattern = currentScript() === 'cyrillic' ? /[A-Za-z\u00C0-\u024F]/g : /[\u0400-\u04FF]/g;
		var next = value.replace(pattern, '');
		if (next === value) {
			return false;
		}
		var start = typeof input.selectionStart === 'number' ? input.selectionStart : next.length;
		var kept = value.slice(0, start).replace(pattern, '').length;
		input.value = next;
		if (typeof input.setSelectionRange === 'function') {
			input.setSelectionRange(kept, kept);
		}
		return true;
	}

	function keepsPreviewStage(node) {
		if (!node) {
			return false;
		}
		if (node.closest && node.closest('[data-apd-swatch]')) {
			return true;
		}
		return node.type === 'checkbox';
	}

	function syncFontSelect(select, script) {
		if (!select || select.tagName !== 'SELECT') {
			return;
		}
		var first = null;
		Array.prototype.forEach.call(select.options, function (option) {
			var allow = option.getAttribute('data-apd-font-for');
			var show = !allow || allow.split(/\s+/).indexOf(script) !== -1;
			option.hidden = !show;
			option.disabled = !show;
			if (show && !first) {
				first = option;
			}
		});
		if (select.selectedOptions && select.selectedOptions[0] && select.selectedOptions[0].disabled && first) {
			select.value = first.value;
		}
	}

	function syncFonts() {
		var script = currentScript();
		var combined = script === 'combined';
		var cyrWrap = document.querySelector('[data-apd-font-cyr]');
		if (cyrWrap) {
			cyrWrap.hidden = !combined;
			cyrWrap.querySelectorAll('select, input').forEach(function (field) {
				field.disabled = !combined;
			});
		}
		document.querySelectorAll('[data-apd-font-single]').forEach(function (label) {
			label.hidden = combined;
		});
		document.querySelectorAll('[data-apd-font-latin]').forEach(function (label) {
			label.hidden = !combined;
		});
		syncFontSelect(document.querySelector('#apd_font_id'), combined ? 'latin' : script);
		syncFontSelect(document.querySelector('#apd_font_id_cyr'), 'cyrillic');
	}

	function cartEditSignature(root) {
		var parts = [];
		root.querySelectorAll('input, select, textarea').forEach(function (field) {
			if (!field.name || field.disabled) {
				return;
			}
			if (field.name === 'apd_nonce' || field.name === 'apd_preview' || field.name === 'apd_cart_item' || field.name === 'apd_legacy_no_frame') {
				return;
			}
			if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
				return;
			}
			parts.push(field.name + '=' + field.value);
		});
		parts.sort();
		return parts.join('\n');
	}

	function syncCartEditButton(button, saved, current) {
		if (!button) {
			return;
		}
		var dirty = current !== saved;
		var backLabel = (cfg.i18n && cfg.i18n.backToCart) || 'Back to cart';
		var refreshLabel = (cfg.i18n && cfg.i18n.refreshCart) || 'Refresh the cart';
		button.textContent = dirty ? refreshLabel : backLabel;
		if (dirty) {
			button.type = 'submit';
			button.removeAttribute('data-apd-cart-back');
			return;
		}
		button.type = 'button';
		button.setAttribute('data-apd-cart-back', '1');
		button.disabled = false;
	}

	function inkIsShared(rule, mode) {
		if (cfg.format && cfg.format.separate_text_frame) {
			return false;
		}
		var framed = !(cfg.format && cfg.format.no_frame);
		return !!(cfg.format && cfg.format.shares_text_frame) || (framed && rule === 'count' && mode === 'digital');
	}

	function syncLetterChrome() {
		paintCartEdit();
		var text = currentPlateText();
		var mode = letterMode(text);
		var rule = cfg.format && cfg.format.letter_rule ? cfg.format.letter_rule : '';
		var shared = inkIsShared(rule, mode);
		var digital = document.querySelector('[data-apd-digital-colors]');
		var textColors = document.querySelector('[data-apd-text-colors]');
		var plain = document.querySelector('[data-apd-frame-plain]');
		var plainWrap = plain ? plain.closest('[data-apd-plain-frame]') : null;
		var colors = document.querySelector('[data-apd-frame-colors]');
		var box = document.querySelector('[data-apd-frame]');
		var note = document.querySelector('[data-apd-production]');
		var error = document.querySelector('[data-apd-script-error]');
		var bad = scriptRejected(text) || scriptWarning;
		var root = document.querySelector('[data-apd-root]');
		var submit = root && root.closest('form') ? root.closest('form').querySelector('[type="submit"]') : null;
		if (digital) {
			digital.hidden = true;
		}
		if (textColors) {
			textColors.hidden = false;
			var legend = textColors.querySelector('legend');
			if (legend) {
				if (!legend.hasAttribute('data-apd-label')) {
					legend.setAttribute('data-apd-label', legend.textContent);
				}
				legend.textContent = shared && cfg.i18n && cfg.i18n.inkFrameColor ? cfg.i18n.inkFrameColor : legend.getAttribute('data-apd-label');
			}
		}
		if (plainWrap) {
			var allowPlain = rule === 'digital' || mode === 'embossed' || mode === 'digital' || currentScript() === 'cyrillic';
			plainWrap.hidden = !allowPlain;
			if (!allowPlain && plain) {
				plain.checked = false;
			}
		}
		if (colors) {
			if (box) {
				colors.hidden = !box.checked;
			} else if (shared) {
				colors.hidden = true;
			} else if (plain) {
				colors.hidden = !!plain.checked;
			}
		}
		if (note) {
			var message = '';
			var compact = isCompactPlate();
			var digitalLetters = rule === 'digital' || (rule === 'count' && mode === 'digital');
			if (cfg.digital_only) {
				message = '';
			} else if (digitalLetters && compact && !compactHasFrame()) {
				message = (cfg.i18n && cfg.i18n.miniDigital) || '';
			} else if (rule === 'digital') {
				message = (cfg.i18n && cfg.i18n.colorNotice) || '';
			} else if (digitalLetters && currentScript() === 'cyrillic') {
				message = (cfg.i18n && cfg.i18n.cyrillicNotice) || '';
			} else if (digitalLetters) {
				message = (cfg.i18n && cfg.i18n.digitalNotice) || '';
			}
			note.hidden = !message;
			note.textContent = message;
		}
		if (error) {
			error.hidden = !bad;
			error.textContent = bad ? ((currentScript() === 'cyrillic' ? (cfg.i18n && cfg.i18n.cyrillicOnly) : (cfg.i18n && cfg.i18n.latinOnly)) || '') : '';
		}
		if (submit && !submit.getAttribute('data-apd-posting')) {
			submit.disabled = bad;
		}
		syncFonts();
	}

	function wantsNoFrame() {
		if (document.querySelector('[data-apd-legacy-no-frame]')) {
			return true;
		}
		if (!cfg.format || cfg.format.frame_choice === false || isHolderFamily(cfg.format.type) || cfg.format.type === 'us') {
			return true;
		}
		var box = document.querySelector('[data-apd-frame]');
		if (box) {
			return !box.checked;
		}
		return !!(cfg.format && cfg.format.no_frame);
	}

	function syncFrameColors() {
		syncLetterChrome();
	}

	var TEXT_FRAME_GAP_MM = 8;
	var TWO_ROW_GAP_MM = 8;

	function frameThickness() {
		if (wantsNoFrame()) {
			return 0;
		}
		var border = cfg.format && cfg.format.border_width ? cfg.format.border_width : 0;
		return border > 0 ? border : 0;
	}

	function frameTextInset() {
		var thick = frameThickness();
		if (thick <= 0 || !usesPaintedPlate()) {
			return thick;
		}
		return thick + TEXT_FRAME_GAP_MM;
	}

	function plateCornerRadius(h) {
		return Math.min(10, h * 0.12);
	}

	function insetCornerRadius(h, fromEdge) {
		return Math.max(0, plateCornerRadius(h) - Math.max(0, fromEdge));
	}

	function cornerRadiusCss(horizontal, vertical) {
		if (!(horizontal > 0) && !(vertical > 0)) {
			return '0';
		}
		if (Math.abs(horizontal - vertical) < 0.05) {
			return horizontal + 'px';
		}
		return horizontal + 'px ' + vertical + 'px';
	}

	function bandCornerRadius(h, sideInset, edgeInset) {
		return insetCornerRadius(h, Math.max(sideInset, edgeInset));
	}

	function paintAdminFrame(ctx, w, h) {
		var border = frameThickness();
		if (border <= 0 || !(w > 0) || !(h > 0)) {
			return;
		}
		var stroke = colorValue('apd_border_color');
		var rule = cfg.format && cfg.format.letter_rule ? cfg.format.letter_rule : '';
		var mode = letterMode(currentPlateText());
		var plain = document.querySelector('[data-apd-frame-plain]');
		var plainWrap = plain ? plain.closest('[data-apd-plain-frame]') : null;
		var sharedFrame = inkIsShared(rule, mode);
		if (plain && plain.checked && plainWrap && !plainWrap.hidden) {
			stroke = colorValue('apd_background_color');
		} else if (sharedFrame && !cfg.lock_colors) {
			stroke = colorValue('apd_text_color');
		}
		var holeW = w - border * 2;
		var holeH = h - border * 2;
		ctx.save();
		ctx.beginPath();
		addRoundRect(ctx, 0, 0, w, h, plateCornerRadius(h));
		if (holeW > 0 && holeH > 0) {
			addRoundRect(ctx, border, border, holeW, holeH, insetCornerRadius(h, border));
		}
		ctx.fillStyle = stroke;
		ctx.fill('evenodd');
		ctx.restore();
	}

	// A short saved band leaves white plate under the blue. The standard
	// 520×110 band already fills the frame, so this only lengthens the others.
	function extendEuroBand(band, plateH) {
		if (!band || !(plateH > 0)) {
			return band;
		}
		var thick = frameThickness();
		var top = thick;
		var bottom = plateH - thick;
		if (band.y <= top + 0.5 && band.y + band.height >= bottom - 0.5) {
			return band;
		}
		return {
			x: band.x,
			y: top,
			width: band.width,
			height: Math.max(0, bottom - top),
			side: band.side
		};
	}

	function placeBandInsideFrame(band, w, h) {
		var thick = frameThickness();
		var x = Math.max(band.x, thick);
		var y = Math.max(band.y, thick);
		var right = Math.min(band.x + band.width, w - thick);
		var bottom = Math.min(band.y + band.height, h - thick);
		return {
			x: x,
			y: y,
			width: Math.max(0, right - x),
			height: Math.max(0, bottom - y),
			side: band.side
		};
	}

	function bandLayer(canvas) {
		var frame = canvas.parentElement;
		if (!frame) {
			return null;
		}
		var img = frame.querySelector('.apd-band-layer');
		if (img) {
			return img;
		}
		img = document.createElement('img');
		img.className = 'apd-band-layer';
		img.alt = '';
		img.draggable = false;
		frame.appendChild(img);
		return img;
	}

	function placeBandLayer(canvas, band, url, w, h) {
		var img = bandLayer(canvas);
		if (!img) {
			return;
		}
		if (!band || !url || band.width <= 0 || band.height <= 0 || w <= 0 || h <= 0) {
			img.hidden = true;
			img.removeAttribute('src');
			return;
		}
		img.hidden = false;
		if (img.getAttribute('src') !== url) {
			img.src = url;
		}
		img.style.left = (band.x / w * 100) + '%';
		img.style.top = (band.y / h * 100) + '%';
		img.style.width = (band.width / w * 100) + '%';
		img.style.height = (band.height / h * 100) + '%';
		var frameEl = canvas.parentElement;
		var scale = frameEl && frameEl.clientHeight ? frameEl.clientHeight / h : 1;
		var left = band.side !== 'right';
		var sideInset = left ? band.x : Math.max(0, w - (band.x + band.width));
		var sidePx = insetCornerRadius(h, sideInset) * scale;
		var topPx = insetCornerRadius(h, band.y) * scale;
		var bottomPx = insetCornerRadius(h, h - (band.y + band.height)) * scale;
		img.style.borderTopLeftRadius = left ? cornerRadiusCss(sidePx, topPx) : '0';
		img.style.borderBottomLeftRadius = left ? cornerRadiusCss(sidePx, bottomPx) : '0';
		img.style.borderTopRightRadius = left ? '0' : cornerRadiusCss(sidePx, topPx);
		img.style.borderBottomRightRadius = left ? '0' : cornerRadiusCss(sidePx, bottomPx);
	}

	function hoistPreview(root) {
		var preview = root.querySelector('.apd-preview');
		if (!preview) {
			return;
		}

		var existing = document.querySelector('.apd-product-layout');
		if (existing) {
			var slot = existing.querySelector('.apd-product-layout__preview');
			if (slot && preview.parentNode !== slot) {
				slot.appendChild(preview);
			}
			return;
		}

		var layout = document.createElement('div');
		layout.className = 'apd-product-layout alignwide';
		var left = document.createElement('div');
		left.className = 'apd-product-layout__preview';
		var right = document.createElement('div');
		right.className = 'apd-product-layout__details';
		left.appendChild(preview);
		layout.appendChild(left);
		layout.appendChild(right);

		var columns = document.querySelector('.wp-block-columns');
		if (columns && columns.parentNode) {
			columns.parentNode.insertBefore(layout, columns);
			var title = document.querySelector('.wp-block-post-title, h1.product_title, .product_title.entry-title');
			if (title && !columns.contains(title) && !right.contains(title)) {
				right.appendChild(title);
			}
			right.appendChild(columns);
			return;
		}

		var summary = document.querySelector('div.product div.summary, div.product .entry-summary');
		if (summary && summary.parentNode) {
			summary.parentNode.insertBefore(layout, summary);
			right.appendChild(summary);
			return;
		}

		var title = document.querySelector('.wp-block-post-title, h1.product_title, .product_title.entry-title');
		if (title && title.parentNode) {
			title.parentNode.insertBefore(layout, title);
			right.appendChild(title);
		}
	}

	function desktopPreview() {
		return window.matchMedia('(min-width: 768px)').matches;
	}

	function headerClearance() {
		var chrome = 0;
		var bars = document.querySelectorAll('#wpadminbar, #masthead, header.site-header, .kadence-sticky-header');
		for (var i = 0; i < bars.length; i++) {
			var rect = bars[i].getBoundingClientRect();
			if (rect.height < 24 || rect.width < window.innerWidth * 0.5 || rect.top > 80 || rect.bottom < 24) {
				continue;
			}
			chrome = Math.max(chrome, Math.round(rect.bottom));
		}
		return Math.min(chrome > 0 ? chrome + 12 : 12, 200);
	}

	var previewStageUntil = 0;
	var scriptWarning = false;

	function holdPreviewStage() {
		previewStageUntil = Date.now() + 800;
	}

	function measurePreviewStage() {
		var preview = document.querySelector('.apd-product-layout__preview');
		if (!preview) {
			return;
		}
		if (previewStageUntil && Date.now() < previewStageUntil) {
			return;
		}
		if (!desktopPreview()) {
			preview.style.removeProperty('--apd-stage-h');
			preview.style.removeProperty('--apd-chrome-top');
			previewChromeTop = null;
			return;
		}
		var chrome = headerClearance();
		var layout = preview.parentElement;
		var details = layout ? layout.querySelector('.apd-product-layout__details') : null;
		var anchorTop = layout ? layout.getBoundingClientRect().top : 0;
		var limitBottom = details ? details.getBoundingClientRect().bottom : window.innerHeight;
		var visibleTop = Math.max(anchorTop, chrome);
		var visibleBottom = Math.min(window.innerHeight - 12, limitBottom);
		var height = Math.round(visibleBottom - visibleTop);
		previewChromeTop = chrome;
		preview.style.setProperty('--apd-chrome-top', chrome + 'px');
		if (height < 180) {
			preview.style.removeProperty('--apd-stage-h');
		} else {
			preview.style.setProperty('--apd-stage-h', height + 'px');
		}
	}

	function schedulePreviewStage() {
		if (previewStageFrame) {
			return;
		}
		previewStageFrame = window.requestAnimationFrame(function () {
			previewStageFrame = 0;
			measurePreviewStage();
		});
	}

	function paintCountryThumb(root, band) {
		var plate = getCanvas();
		var thumb = root.querySelector('[data-apd-country-thumb]');
		var wrap = root.querySelector('.apd-country-current');
		if (!plate || !thumb || !band) {
			if (wrap) {
				wrap.querySelectorAll('canvas.apd-country-thumb').forEach(function (node) {
					node.remove();
				});
			}
			return;
		}
		var cssH = plate.clientHeight;
		var cssW = Math.max(1, Math.round(plate.clientWidth * (band.width / Math.max(1, cfg.format.width || 520))));
		var maxH = 106;
		if (cssH > maxH) {
			cssW = Math.max(1, Math.round(cssW * (maxH / cssH)));
			cssH = maxH;
		}
		if (cssH < 8) {
			return;
		}
		thumb.style.width = 'auto';
		thumb.style.height = cssH + 'px';
		if (wrap) {
			wrap.style.setProperty('--apd-band-w', cssW + 'px');
			wrap.style.setProperty('--apd-band-h', cssH + 'px');
		}
		var url = thumb.getAttribute('src') || '';
		var cached = url && imageCache[url] && imageCache[url].naturalWidth ? imageCache[url] : null;
		var canvas = wrap ? wrap.querySelector('canvas.apd-country-thumb') : null;
		if (!cached) {
			if (canvas) {
				canvas.remove();
			}
			thumb.hidden = false;
			return;
		}
		if (!canvas) {
			canvas = document.createElement('canvas');
			canvas.className = 'apd-country-thumb';
			canvas.setAttribute('aria-hidden', 'true');
			thumb.parentNode.insertBefore(canvas, thumb);
		}
		var dpr = window.devicePixelRatio || 1;
		if (dpr < 2) {
			dpr = 2;
		}
		canvas.width = Math.max(1, Math.round(cssW * dpr));
		canvas.height = Math.max(1, Math.round(cssH * dpr));
		canvas.style.width = cssW + 'px';
		canvas.style.height = cssH + 'px';
		var thumbCtx = canvas.getContext('2d');
		thumbCtx.setTransform(canvas.width / cssW, 0, 0, canvas.height / cssH, 0, 0);
		thumbCtx.imageSmoothingEnabled = true;
		if ('imageSmoothingQuality' in thumbCtx) {
			thumbCtx.imageSmoothingQuality = 'high';
		}
		thumbCtx.clearRect(0, 0, cssW, cssH);
		thumbCtx.fillStyle = '#003399';
		thumbCtx.fillRect(0, 0, cssW, cssH);
		drawEuroBand(thumbCtx, cached, 0, 0, cssW, cssH);
		thumb.hidden = true;
	}

	function paintCountryOptions(root) {
		if (!root) {
			return;
		}
		root.querySelectorAll('.apd-country-option img').forEach(function (imgEl) {
			if (imgEl.dataset.apdClean === '1') {
				return;
			}
			var url = imgEl.getAttribute('src') || '';
			var cached = url && imageCache[url] && imageCache[url].naturalWidth ? imageCache[url] : null;
			if (!cached) {
				return;
			}
			var cssH = imgEl.clientHeight;
			var cssW = imgEl.clientWidth;
			if (cssH < 8 || cssW < 4) {
				return;
			}
			var canvas = imgEl.parentNode.querySelector('canvas.apd-country-thumb');
			if (!canvas) {
				canvas = document.createElement('canvas');
				canvas.className = 'apd-country-thumb';
				canvas.setAttribute('aria-hidden', 'true');
				imgEl.parentNode.insertBefore(canvas, imgEl);
			}
			var dpr = window.devicePixelRatio || 1;
			if (dpr < 2) {
				dpr = 2;
			}
			canvas.width = Math.max(1, Math.round(cssW * dpr));
			canvas.height = Math.max(1, Math.round(cssH * dpr));
			canvas.style.width = cssW + 'px';
			canvas.style.height = cssH + 'px';
			var optionCtx = canvas.getContext('2d');
			optionCtx.setTransform(canvas.width / cssW, 0, 0, canvas.height / cssH, 0, 0);
			optionCtx.clearRect(0, 0, cssW, cssH);
			optionCtx.fillStyle = '#003399';
			optionCtx.fillRect(0, 0, cssW, cssH);
			drawEuroBand(optionCtx, cached, 0, 0, cssW, cssH);
			imgEl.dataset.apdClean = '1';
			imgEl.hidden = true;
		});
	}

	function addRoundRect(ctx, x, y, w, h, r) {
		var radius = Math.max(0, Math.min(r, Math.abs(w) / 2, Math.abs(h) / 2));
		ctx.moveTo(x + radius, y);
		ctx.arcTo(x + w, y, x + w, y + h, radius);
		ctx.arcTo(x + w, y + h, x, y + h, radius);
		ctx.arcTo(x, y + h, x, y, radius);
		ctx.arcTo(x, y, x + w, y, radius);
		ctx.closePath();
	}

	function roundRect(ctx, x, y, w, h, r) {
		ctx.beginPath();
		addRoundRect(ctx, x, y, w, h, r);
	}

	function measureInk(ctx, text, size) {
		ctx.textBaseline = 'alphabetic';
		var sample = String(text || 'H');
		var measured = ctx.measureText(sample);
		var ascent = measured.actualBoundingBoxAscent;
		var descent = measured.actualBoundingBoxDescent;
		if (!isFinite(ascent) || ascent < size * 0.2) {
			ascent = measured.fontBoundingBoxAscent > 0 ? measured.fontBoundingBoxAscent * 0.72 : size * 0.72;
		}
		if (!isFinite(descent) || descent < 0) {
			descent = measured.fontBoundingBoxDescent > 0 ? measured.fontBoundingBoxDescent : size * 0.15;
		}
		return {
			width: measured.width,
			ascent: ascent,
			descent: descent
		};
	}

	function faceMetrics(ctx, size) {
		return measureInk(ctx, 'H', size);
	}

	function linesInk(ctx, lines, size) {
		var ascent = 0;
		var descent = 0;
		var width = 0;
		var i;
		for (i = 0; i < lines.length; i += 1) {
			var ink = measureInk(ctx, lines[i] || 'H', size);
			ascent = Math.max(ascent, ink.ascent);
			descent = Math.max(descent, ink.descent);
			width = Math.max(width, ink.width);
		}
		var cap = measureInk(ctx, 'H', size).ascent;
		var laidDescent = 0;
		var crown = 0;
		var tail = 0;
		var normalized = false;
		width = 0;
		for (i = 0; i < lines.length; i += 1) {
			var laid = lineSpan(ctx, lines[i] || 'H', cap);
			width = Math.max(width, laid.width);
			laidDescent = Math.max(laidDescent, laid.descent);
			crown = Math.max(crown, laid.crown || 0);
			tail = Math.max(tail, laid.tail || 0);
			if (laid.normalized) {
				normalized = true;
			}
		}
		if (cap > 0 && (normalized || ascent > cap * 1.04)) {
			ascent = cap;
		}
		if (laidDescent > 0 || tail > 0) {
			descent = laidDescent;
		}
		return {
			ascent: ascent,
			descent: descent,
			width: width,
			height: ascent + descent + Math.max(0, lines.length - 1) * size,
			crown: crown,
			tail: tail
		};
	}

	function cyrillicPlate() {
		return currentScript() === 'cyrillic';
	}

	function cyrillicTailLetter(ch) {
		return ch === '\u0426' || ch === '\u0429' || ch === '\u0414' || ch === '\u0446' || ch === '\u0449' || ch === '\u0434';
	}

	function cyrillicCrownLetter(ch) {
		return ch === '\u0419' || ch === '\u0439' || ch === '\u0401' || ch === '\u0451';
	}

	function cyrillicEdge(box) {
		var plateH = box && box.plateH ? box.plateH : 0;
		if (!box || !(plateH > 0)) {
			return { top: 0, bottom: 0 };
		}
		var pad = frameThickness() + 2;
		return {
			top: Math.max(0, box.y - pad),
			bottom: Math.max(0, plateH - (box.y + box.height) - pad)
		};
	}

	function cyrillicRoomFits(body, maxHeight, crown, tail, edge) {
		var slack = maxHeight - body;
		var roomTop = (edge && edge.top ? edge.top : 0) + Math.max(0, slack) / 2;
		var roomBottom = (edge && edge.bottom ? edge.bottom : 0) + Math.max(0, slack) / 2;
		return (crown || 0) <= roomTop + 0.5 && (tail || 0) <= roomBottom + 0.5;
	}

	function glyphInkScale(ascent, cap) {
		if (!(cap > 0) || !isFinite(ascent) || ascent <= 0) {
			return 1;
		}
		if (ascent < cap * 0.45) {
			return 1;
		}
		var ratio = ascent / cap;
		if (ratio > 1.04 || ratio < 0.96) {
			return cap / ascent;
		}
		return 1;
	}

	function lineSpan(ctx, line, cap) {
		var chars = Array.from(String(line || ''));
		var spans = [];
		var width = 0;
		var descent = 0;
		var crown = 0;
		var tail = 0;
		var normalized = false;
		var i;
		var measured;
		var scale;
		var down;
		var plate = cyrillicPlate();
		for (i = 0; i < chars.length; i += 1) {
			measured = ctx.measureText(chars[i]);
			var marked = plate && cyrillicCrownLetter(chars[i]);
			scale = marked ? 1 : glyphInkScale(measured.actualBoundingBoxAscent, cap);
			down = measured.actualBoundingBoxDescent;
			if (!isFinite(down) || down < 0) {
				down = 0;
			}
			if (plate && cyrillicTailLetter(chars[i])) {
				tail = Math.max(tail, down * scale);
				down = 0;
			}
			if (marked && cap > 0 && isFinite(measured.actualBoundingBoxAscent) && measured.actualBoundingBoxAscent > cap) {
				crown = Math.max(crown, (measured.actualBoundingBoxAscent - cap) * scale);
			}
			if (Math.abs(scale - 1) > 0.001) {
				normalized = true;
			}
			spans.push({
				scale: scale,
				width: measured.width * scale
			});
			width += measured.width * scale;
			descent = Math.max(descent, down * scale);
		}
		return {
			chars: chars,
			spans: spans,
			width: width,
			descent: descent,
			normalized: normalized,
			crown: crown,
			tail: tail
		};
	}

	var LATIN_LOOKALIKES = {
		'\u0410': 'A',
		'\u0412': 'B',
		'\u0415': 'E',
		'\u041a': 'K',
		'\u041c': 'M',
		'\u041d': 'H',
		'\u041e': 'O',
		'\u0420': 'P',
		'\u0421': 'C',
		'\u0422': 'T',
		'\u0423': 'Y',
		'\u0425': 'X',
		'\u0406': 'I',
		'\u0430': 'a',
		'\u0435': 'e',
		'\u043a': 'k',
		'\u043c': 'm',
		'\u043d': 'h',
		'\u043e': 'o',
		'\u0440': 'p',
		'\u0441': 'c',
		'\u0442': 't',
		'\u0443': 'y',
		'\u0445': 'x',
		'\u0456': 'i'
	};
	var glyphCache = {};

	function fontHasGlyph(ctx, family, weight, style, ch) {
		var key = String(family) + '|' + String(weight) + '|' + String(style) + '|' + ch;
		if (Object.prototype.hasOwnProperty.call(glyphCache, key)) {
			return glyphCache[key];
		}
		ctx.save();
		ctx.font = style + ' ' + weight + ' 100px "' + family + '"';
		var own = ctx.measureText(ch).width;
		ctx.font = style + ' ' + weight + ' 100px sans-serif';
		var generic = ctx.measureText(ch).width;
		ctx.restore();
		var has = Math.abs(own - generic) > 0.75;
		glyphCache[key] = has;
		return has;
	}

	function plateGlyphs(ctx, text, family, weight, style) {
		var chars = Array.from(String(text || ''));
		var out = '';
		var i;
		var ch;
		var latin;
		for (i = 0; i < chars.length; i += 1) {
			ch = chars[i];
			latin = LATIN_LOOKALIKES[ch];
			if (latin && !fontHasGlyph(ctx, family, weight, style, ch)) {
				out += latin;
			} else {
				out += ch;
			}
		}
		return out;
	}

	function plateWraps() {
		return !!(cfg.format && cfg.format.wrap_text);
	}

	function plateMaxLines() {
		var lines = cfg.format && cfg.format.max_lines ? parseInt(cfg.format.max_lines, 10) : 5;
		if (!lines || lines < 1) {
			return 5;
		}
		return lines;
	}

	function wrapLines(ctx, paragraphs, maxWidth, maxLines) {
		var out = [];
		var p;
		var words;
		var i;
		var word;
		var trial;
		var line;
		for (p = 0; p < paragraphs.length && out.length < maxLines; p += 1) {
			words = String(paragraphs[p] || '').trim().split(/\s+/);
			if (!words[0]) {
				continue;
			}
			line = '';
			for (i = 0; i < words.length; i += 1) {
				word = words[i];
				trial = line ? (line + ' ' + word) : word;
				if (!line || ctx.measureText(trial).width <= maxWidth) {
					line = trial;
					continue;
				}
				out.push(line);
				line = word;
				if (out.length >= maxLines) {
					out[maxLines - 1] = out[maxLines - 1] + ' ' + words.slice(i).join(' ');
					return out;
				}
			}
			if (!line) {
				continue;
			}
			if (out.length >= maxLines) {
				out[maxLines - 1] = out[maxLines - 1] + ' ' + line;
				return out;
			}
			out.push(line);
		}
		return out.length ? out : [''];
	}

	function fitText(ctx, text, family, weight, style, maxWidth, maxHeight, minSize, edge) {
		text = plateGlyphs(ctx, text, family, weight, style);
		var sourceLines = String(text).split('\n');
		var lines = sourceLines;
		var size = Math.floor(sourceLines.length === 1 ? maxHeight * 1.5 : maxHeight);
		var font;
		var face;
		ctx.textBaseline = 'alphabetic';
		while (size >= minSize) {
			font = style + ' ' + weight + ' ' + size + 'px "' + family + '", sans-serif';
			ctx.font = font;
			lines = plateWraps() ? wrapLines(ctx, sourceLines, maxWidth, plateMaxLines()) : sourceLines;
			var ink = linesInk(ctx, lines, size);
			face = faceMetrics(ctx, size);
			var extra = Math.max(0, lines.length - 1) * size;
			var blockH = lines.length === 1 ? (ink.ascent + ink.descent) : (face.ascent + extra + face.descent);
			if (ink.width <= maxWidth && blockH <= maxHeight && cyrillicRoomFits(blockH, maxHeight, ink.crown, ink.tail, edge)) {
				return { font: font, size: size, lines: lines, face: face, ink: ink };
			}
			size -= 1;
		}
		ctx.font = style + ' ' + weight + ' ' + minSize + 'px "' + family + '", sans-serif';
		lines = plateWraps() ? wrapLines(ctx, sourceLines, maxWidth, plateMaxLines()) : sourceLines;
		return {
			font: ctx.font,
			size: minSize,
			lines: lines,
			face: faceMetrics(ctx, minSize),
			ink: linesInk(ctx, lines, minSize)
		};
	}

	function resolveBand(format, preset, w, h) {
		var box = format && format.band_box;
		if (box && Number(box.width) > 0) {
			var bx = (Number(box.x) / 100) * w;
			var by = (Number(box.y) / 100) * h;
			var bw = (Number(box.width) / 100) * w;
			var bh = (Number(box.height) / 100) * h;
			return {
				x: bx,
				y: by,
				width: bw,
				height: bh,
				side: (Number(box.x) + Number(box.width) / 2) >= 50 ? 'right' : 'left'
			};
		}
		var side = preset && preset.side ? preset.side : (format.band_side || 'left');
		var ratio = format.eu_band_ratio;
		if (!(ratio > 0)) {
			ratio = 40 / 520;
		}
		var bandW = w * ratio;
		var minW = Math.max(1, w * 0.04);
		var maxW = w * 0.25;
		bandW = Math.min(maxW, Math.max(minW, bandW));
		var bandX = side === 'right' ? w - bandW : 0;
		return {
			x: bandX,
			y: 0,
			width: bandW,
			height: h,
			side: side
		};
	}

	// A single preset has no country picker. Widen its band until the preset
	// graphic fills the band height, the same fit the standard EU plate uses.
	// A 300×150 plate stops at the shop SUV band. The 340×200 SUV plates draw
	// a 63.3 mm strip; fitting this preset to the full 150 mm height is wider.
	function fitSinglePresetBand(band, plateW) {
		var presets = (cfg.format && cfg.format.presets) || [];
		if (!band || presets.length !== 1 || !(band.height > 0) || !(plateW > 0)) {
			return band;
		}
		var preset = presets[0];
		var url = preset && preset.image_url ? preset.image_url : '';
		var img = url && imageCache[url] ? imageCache[url] : null;
		var sw = img && (img.naturalWidth || img.width);
		var sh = img && (img.naturalHeight || img.height);
		if (!(sw > 0) || !(sh > 0)) {
			return band;
		}
		var needed = band.height * (sw / sh);
		if (!(needed > band.width + 0.5)) {
			return band;
		}
		var thick = frameThickness();
		var room = band.side === 'right' ? (band.x + band.width) - thick : (plateW - thick) - band.x;
		var cap = plateW * 0.25;
		if (cfg.format && cfg.format.type === 'eu_300') {
			cap = Math.min(cap, 63.3);
		}
		var width = Math.min(cap, room, needed);
		if (!(width > band.width + 0.5)) {
			return band;
		}
		return {
			x: band.side === 'right' ? (band.x + band.width - width) : band.x,
			y: band.y,
			width: width,
			height: band.height,
			side: band.side
		};
	}

	function resolveTextBox(format, design, preset, w, h, band) {
		var box = format && format.text_box ? format.text_box : { x: 9, y: 19, width: 82, height: 62, align: 'center', valign: 'middle' };
		if (design && design.text_box) {
			box = design.text_box;
		}
		var bx = (Number(box.x) / 100) * w;
		var by = (Number(box.y) / 100) * h;
		var bw = (Number(box.width) / 100) * w;
		var bh = (Number(box.height) / 100) * h;
		if (format && usesCountryBand(format.type)) {
			var slot = band || resolveBand(format, preset, w, h);
			if (bandCoversMost(slot, by, bh)) {
				var gap = 8;
				if (slot.side === 'right') {
					var maxRight = slot.x - gap;
					if (bx + bw > maxRight) {
						bw = Math.max(0, maxRight - bx);
					}
				} else {
					var minLeft = slot.x + slot.width + gap;
					if (bx < minLeft) {
						bw = Math.max(0, bw - (minLeft - bx));
						bx = minLeft;
					}
				}
			}
		}
		var resolved = {
			x: bx,
			y: by,
			width: bw,
			height: bh,
			align: box.align === 'left' || box.align === 'right' ? box.align : 'center',
			valign: box.valign === 'top' || box.valign === 'bottom' ? box.valign : 'middle',
			letter_align: usesTwoRows(format && format.type) ? rowAlignValue(box.letter_align) : '',
			number_align: usesTwoRows(format && format.type) ? rowAlignValue(box.number_align) : '',
			plateH: h
		};
		return clampBoxInsideFrame(resolved, w, h);
	}

	function resolvePercentBox(box, w, h) {
		box = box || {};
		return clampBoxInsideFrame({
			x: (Number(box.x) / 100) * w,
			y: (Number(box.y) / 100) * h,
			width: (Number(box.width) / 100) * w,
			height: (Number(box.height) / 100) * h,
			align: box.align === 'left' || box.align === 'right' ? box.align : 'center',
			valign: box.valign === 'top' || box.valign === 'bottom' ? box.valign : 'middle',
			letter_align: '',
			number_align: ''
		}, w, h);
	}

	function activeSplitSource() {
		var format = cfg.format || {};
		if (format.base_image_url) {
			if (format.split_text && format.text_box_right && format.text_box_right.width) {
				return format;
			}
			return null;
		}
		var design = currentDesign();
		if (design && design.split_text && design.text_box_right && design.text_box_right.width) {
			return design;
		}
		return null;
	}

	function sideParts(text) {
		text = String(text || '');
		var nl = text.indexOf('\n');
		if (nl === -1) {
			return { left: text, right: '' };
		}
		return { left: text.slice(0, nl), right: text.slice(nl + 1) };
	}

	function drawSideText(ctx, text, font, format, design, w, h, minSize) {
		var source = activeSplitSource();
		if (!source) {
			return;
		}
		var parts = sideParts(text);
		var leftBox = resolveTextBox(format, format.base_image_url ? null : design, null, w, h);
		var rightBox = resolvePercentBox(source.text_box_right, w, h);
		rightBox.y = leftBox.y;
		rightBox.height = leftBox.height;
		rightBox.valign = leftBox.valign;
		var fitL = fitText(ctx, parts.left || ' ', font.family, font.weight, font.style, leftBox.width, leftBox.height, minSize);
		var fitR = fitText(ctx, parts.right || ' ', font.family, font.weight, font.style, rightBox.width, rightBox.height, minSize);
		var size = Math.min(fitL.size, fitR.size);
		var color = colorValue('apd_text_color');
		if (parts.left) {
			drawTextInBox(ctx, fittedAtSize(ctx, parts.left, font.family, font.weight, font.style, size), leftBox, color);
		}
		if (parts.right) {
			drawTextInBox(ctx, fittedAtSize(ctx, parts.right, font.family, font.weight, font.style, size), rightBox, color);
		}
	}

	function clampBoxInsideFrame(box, w, h) {
		var thick = frameTextInset();
		if (!box || thick <= 0) {
			return box;
		}
		var x = Math.max(box.x, thick);
		var y = Math.max(box.y, thick);
		var right = Math.min(box.x + box.width, w - thick);
		var bottom = Math.min(box.y + box.height, h - thick);
		box.x = x;
		box.y = y;
		box.width = Math.max(0, right - x);
		box.height = Math.max(0, bottom - y);
		return box;
	}

	function justifySpread(chars) {
		var spaces = 0;
		var index;
		for (index = 0; index < chars.length; index += 1) {
			if (chars[index] === ' ') {
				spaces += 1;
			}
		}
		if (spaces > 0) {
			return { spaces: true, count: spaces };
		}
		return { spaces: false, count: chars.length > 1 ? chars.length - 1 : 0 };
	}

	function drawTextInBox(ctx, fitted, box, color) {
		if (!fitted || !box || box.width <= 0 || box.height <= 0) {
			return;
		}
		ctx.save();
		resetCanvasSpacing(ctx);
		ctx.fillStyle = color;
		ctx.font = fitted.font;
		ctx.textAlign = 'left';
		ctx.textBaseline = 'alphabetic';
		var size = fitted.size;
		var lines = fitted.lines;
		var ink = linesInk(ctx, lines, size);
		var cap = fitted.face && fitted.face.ascent > 0 ? fitted.face.ascent : ink.ascent;
		var blockH = ink.ascent + ink.descent + Math.max(0, lines.length - 1) * size;
		var top = box.y + (box.height - blockH) / 2;
		if (box.valign === 'top') {
			top = box.y;
		} else if (box.valign === 'bottom') {
			top = box.y + box.height - blockH;
		}
		var baseline = top + ink.ascent;
			var i;
		var j;
		var line;
		var chars;
		var ch;
		var x;
		var lineW;
			for (i = 0; i < lines.length; i += 1) {
			line = lines[i] || '';
			var laid = lineSpan(ctx, line, cap);
			chars = laid.chars;
			lineW = laid.width;
			var align = box.align === 'left' || box.align === 'right' || box.align === 'justify' ? box.align : 'center';
			var justifyGap = 0;
			var spread = null;
			x = box.x + (box.width - lineW) / 2;
			if (align === 'left') {
				x = box.x;
			} else if (align === 'right') {
				x = box.x + box.width - lineW;
			} else if (align === 'justify') {
				spread = justifySpread(chars);
				if (spread.count > 0) {
					x = box.x;
					justifyGap = Math.max(0, (box.width - lineW) / spread.count);
				}
			}
			for (j = 0; j < chars.length; j += 1) {
				ch = chars[j];
				var span = laid.spans[j];
				var y = baseline + i * size;
				if (span.scale < 0.999) {
					ctx.save();
					ctx.translate(x, y);
					ctx.scale(span.scale, span.scale);
					ctx.fillText(ch, 0, 0);
					ctx.restore();
				} else {
					ctx.fillText(ch, x, y);
				}
				var extra = 0;
				if (justifyGap > 0) {
					if (spread && spread.spaces) {
						extra = ch === ' ' ? justifyGap : 0;
					} else if (j + 1 < chars.length) {
						extra = justifyGap;
					}
				}
				x += span.width + extra;
			}
		}
		ctx.restore();
	}

	function fittedAtSize(ctx, line, family, weight, style, size) {
		line = plateGlyphs(ctx, line, family, weight, style);
		var font = style + ' ' + weight + ' ' + size + 'px "' + family + '", sans-serif';
		ctx.font = font;
		return {
			font: font,
			size: size,
			lines: [line],
			face: faceMetrics(ctx, size),
			ink: linesInk(ctx, [line], size)
		};
	}

	function bandCoversMost(slot, y, h) {
		if (!slot || h <= 0) {
			return false;
		}
		var overlapTop = Math.max(y, slot.y);
		var overlapBot = Math.min(y + h, slot.y + slot.height);
		return (overlapBot - overlapTop) > h * 0.75;
	}

	function insetBesideBand(box, band) {
		var gap = 8;
		if (!band || !box) {
			return;
		}
		if (band.side === 'right') {
			var maxRight = band.x - gap;
			if (box.x + box.width > maxRight) {
				box.width = Math.max(0, maxRight - box.x);
			}
		} else {
			var minLeft = band.x + band.width + gap;
			if (box.x < minLeft) {
				box.width = Math.max(0, box.width - (minLeft - box.x));
				box.x = minLeft;
			}
		}
	}

	function rowInkHeight(fit) {
		if (!fit || !fit.ink) {
			return 0;
		}
		return Math.max(0, fit.ink.ascent) + Math.max(0, fit.ink.descent);
	}

	function stackedGap(fitL, fitN) {
		var gap = TWO_ROW_GAP_MM;
		if (fitL && fitN && fitL.ink && fitN.ink) {
			var need = (fitL.ink.tail || 0) + (fitN.ink.crown || 0);
			if (need > gap) {
				gap = need;
			}
		}
		return gap;
	}

	function lineBlockHeight(fitL, fitN) {
		var ink = rowInkHeight(fitL) + rowInkHeight(fitN);
		if (fitL && fitN) {
			return ink + TWO_ROW_GAP_MM + Math.max(0, stackedGap(fitL, fitN) - TWO_ROW_GAP_MM);
		}
		return ink;
	}

	function drawConfiguredText(ctx, text, font, box, color, minSize, band) {
		resetCanvasSpacing(ctx);
		if (!box || !box.letter_align) {
			var fitted = fitText(ctx, text, font.family, font.weight, font.style, box.width, box.height, minSize, cyrillicEdge(box));
			drawTextInBox(ctx, fitted, box, color);
			return;
		}
		var parts = rowsForPlate(text);
		var letterAlign = plateRowAlign(box.letter_align);
		var numberAlign = plateRowAlign(box.number_align || box.letter_align);
		var lines = (parts.letters ? 1 : 0) + (parts.numbers ? 1 : 0);
		if (!lines) {
			return;
		}
		var letterWidth = box.width;
		var letterX = box.x;
		if (band && bandCoversMost(band, box.y, box.height / 2) && !bandCoversMost(band, box.y + box.height / 2, box.height / 2)) {
			var inset = {
				x: box.x,
				y: box.y,
				width: box.width,
				height: box.height
			};
			insetBesideBand(inset, band);
			letterX = inset.x;
			letterWidth = inset.width;
		}
		var probe = 100;
		var probeL = parts.letters ? fittedAtSize(ctx, parts.letters, font.family, font.weight, font.style, probe) : null;
		var probeN = parts.numbers ? fittedAtSize(ctx, parts.numbers, font.family, font.weight, font.style, probe) : null;
		var ratio = (rowInkHeight(probeL) + rowInkHeight(probeN)) / probe;
		var gap = (probeL && probeN) ? TWO_ROW_GAP_MM : 0;
		var size = ratio > 0 ? Math.floor((box.height - gap) / ratio) : minSize;
		if (size < minSize) {
			size = minSize;
		}
		var fitL = null;
		var fitN = null;
		var guard = 0;
		while (size >= minSize && guard < 800) {
			guard += 1;
			fitL = parts.letters ? fittedAtSize(ctx, parts.letters, font.family, font.weight, font.style, size) : null;
			fitN = parts.numbers ? fittedAtSize(ctx, parts.numbers, font.family, font.weight, font.style, size) : null;
			var widthOk = (!fitL || fitL.ink.width <= letterWidth) && (!fitN || fitN.ink.width <= box.width);
			var blockH = lineBlockHeight(fitL, fitN);
			var rowCrown = fitL && fitL.ink ? (fitL.ink.crown || 0) : (fitN && fitN.ink ? (fitN.ink.crown || 0) : 0);
			var rowTail = fitN && fitN.ink ? (fitN.ink.tail || 0) : (fitL && fitL.ink ? (fitL.ink.tail || 0) : 0);
			if (widthOk && blockH <= box.height + 0.5 && cyrillicRoomFits(blockH, box.height, rowCrown, rowTail, cyrillicEdge(box))) {
				break;
			}
			size -= 1;
		}
		if (!fitL && !fitN) {
			return;
		}
		var blockH = lineBlockHeight(fitL, fitN);
		var top = box.y + (box.height - blockH) / 2;
		if (box.valign === 'top') {
			top = box.y;
		} else if (box.valign === 'bottom') {
			top = box.y + box.height - blockH;
		}
		var cursor = top;
		if (fitL) {
			var letterInk = rowInkHeight(fitL);
			drawTextInBox(ctx, fitL, {
				x: letterX,
				y: cursor,
				width: letterWidth,
				height: Math.max(letterInk, 1),
				align: letterAlign,
				valign: 'top'
			}, color);
			cursor += letterInk;
			if (fitN) {
				cursor += stackedGap(fitL, fitN);
			}
		}
		if (fitN) {
			drawTextInBox(ctx, fitN, {
				x: box.x,
				y: cursor,
				width: box.width,
				height: Math.max(rowInkHeight(fitN), 1),
				align: numberAlign,
				valign: 'top'
			}, color);
		}
	}

	function drawImageContained(ctx, img, dx, dy, dw, dh) {
		var iw = img.naturalWidth || img.width;
		var ih = img.naturalHeight || img.height;
		if (!iw || !ih || dw <= 0 || dh <= 0) {
			return;
		}
		var ir = iw / ih;
		var br = dw / dh;
		var w = dw;
		var h = dh;
		var x = dx;
		var y = dy;
		if (ir > br) {
			h = dw / ir;
			y = dy + (dh - h) / 2;
		} else {
			w = dh * ir;
			x = dx + (dw - w) / 2;
		}
		ctx.drawImage(img, x, y, w, h);
	}

	function artworkSourceBox(img) {
		var full = {
			x: 0,
			y: 0,
			w: img.naturalWidth || img.width,
			h: img.naturalHeight || img.height
		};
		if (img._apdArt) {
			return img._apdArt;
		}
		img._apdArt = full;
		if (full.w < 2 || full.h < 2) {
			return full;
		}
		try {
			var maxSide = 480;
			var scale = Math.min(1, maxSide / Math.max(full.w, full.h));
			var sw = Math.max(1, Math.round(full.w * scale));
			var sh = Math.max(1, Math.round(full.h * scale));
			var sample = document.createElement('canvas');
			sample.width = sw;
			sample.height = sh;
			var sctx = sample.getContext('2d', { willReadFrequently: true });
			if (!sctx) {
				return full;
			}
			sctx.drawImage(img, 0, 0, sw, sh);
			var data = sctx.getImageData(0, 0, sw, sh).data;
			var seen = new Uint8Array(sw * sh);
			var queue = [];
			function isMargin(offset) {
				if (data[offset + 3] < 20) {
					return true;
				}
				return data[offset] >= 242 && data[offset + 1] >= 242 && data[offset + 2] >= 242;
			}
			function push(x, y) {
				if (x < 0 || y < 0 || x >= sw || y >= sh) {
					return;
				}
				var pixel = y * sw + x;
				if (seen[pixel] || !isMargin(pixel * 4)) {
					return;
				}
				seen[pixel] = 1;
				queue.push(pixel);
			}
			var edge;
			for (edge = 0; edge < sw; edge++) {
				push(edge, 0);
				push(edge, sh - 1);
			}
			for (edge = 0; edge < sh; edge++) {
				push(0, edge);
				push(sw - 1, edge);
			}
			var head = 0;
			while (head < queue.length) {
				var pixel = queue[head++];
				var cx = pixel % sw;
				var cy = (pixel / sw) | 0;
				push(cx - 1, cy);
				push(cx + 1, cy);
				push(cx, cy - 1);
				push(cx, cy + 1);
			}
			var minX = sw;
			var minY = sh;
			var maxX = -1;
			var maxY = -1;
			var index;
			for (index = 0; index < seen.length; index++) {
				if (seen[index]) {
					continue;
				}
				var px = index % sw;
				var py = (index / sw) | 0;
				if (px < minX) {
					minX = px;
				}
				if (py < minY) {
					minY = py;
				}
				if (px > maxX) {
					maxX = px;
				}
				if (py > maxY) {
					maxY = py;
				}
			}
			if (maxX < 0) {
				return full;
			}
			var bw = maxX - minX + 1;
			var bh = maxY - minY + 1;
			if (bw * bh < sw * sh * 0.12) {
				return full;
			}
			var x = Math.max(0, Math.floor(minX / scale) - 1);
			var y = Math.max(0, Math.floor(minY / scale) - 1);
			var right = Math.min(full.w, Math.ceil((maxX + 1) / scale) + 1);
			var bottom = Math.min(full.h, Math.ceil((maxY + 1) / scale) + 1);
			var box = { x: x, y: y, w: right - x, h: bottom - y };
			if (box.w < 2 || box.h < 2 || (x <= 2 && y <= 2 && box.w >= full.w - 4 && box.h >= full.h - 4)) {
				return full;
			}
			img._apdArt = box;
			return box;
		} catch (err) {
			return full;
		}
	}

	function drawPlateArtwork(ctx, img, dx, dy, dw, dh) {
		var box = artworkSourceBox(img);
		var iw = box.w;
		var ih = box.h;
		if (!iw || !ih || dw <= 0 || dh <= 0) {
			return;
		}
		var ir = iw / ih;
		var br = dw / dh;
		var w = dw;
		var h = dh;
		var x = dx;
		var y = dy;
		if (ir > br) {
			h = dw / ir;
			y = dy + (dh - h) / 2;
		} else {
			w = dh * ir;
			x = dx + (dw - w) / 2;
		}
		ctx.drawImage(img, box.x, box.y, box.w, box.h, x, y, w, h);
	}

	function drawBandImage(ctx, img, slot) {
		var iw = img.naturalWidth || img.width;
		var ih = img.naturalHeight || img.height;
		if (!iw || !ih || !slot || slot.width <= 0 || slot.height <= 0) {
			return;
		}
		var scale = slot.height / ih;
		var dw = iw * scale;
		var dh = slot.height;
		var dx = slot.x + (slot.width - dw) / 2;
		var dy = slot.y;
		ctx.save();
		ctx.beginPath();
		ctx.rect(slot.x, slot.y, slot.width, slot.height);
		ctx.clip();
		ctx.drawImage(img, dx, dy, dw, dh);
		ctx.restore();
	}

	function prepareCanvas(canvas, format) {
		var fw = Math.max(1, parseInt(format.width, 10) || 520);
		var fh = Math.max(1, parseInt(format.height, 10) || 110);
		var dpr = window.devicePixelRatio || 1;
		if (dpr < 2) {
			dpr = 2;
		}
		var frame = canvas.parentElement;
		var cssW = (frame && frame.clientWidth) || canvas.clientWidth || fw;
		var cssH = (frame && frame.clientHeight) || canvas.clientHeight || Math.round(cssW * fh / fw);
		if (cssH < 1) {
			cssH = Math.round(cssW * fh / fw);
		}
		canvas.style.width = cssW + 'px';
		canvas.style.height = cssH + 'px';
		var bufW = Math.max(1, Math.round(cssW * dpr));
		var bufH = Math.max(1, Math.round(cssH * dpr));
		if (canvas.width !== bufW || canvas.height !== bufH) {
			canvas.width = bufW;
			canvas.height = bufH;
		}
		var ctx = canvas.getContext('2d');
		ctx.setTransform(bufW / fw, 0, 0, bufH / fh, 0, 0);
		ctx.imageSmoothingEnabled = true;
		if ('imageSmoothingQuality' in ctx) {
			ctx.imageSmoothingQuality = 'high';
		}
		resetCanvasSpacing(ctx);
		return { ctx: ctx, w: fw, h: fh };
	}

	function resetCanvasSpacing(ctx) {
		if (!ctx) {
			return;
		}
		if ('letterSpacing' in ctx) {
			ctx.letterSpacing = '0px';
		}
		if ('wordSpacing' in ctx) {
			ctx.wordSpacing = '0px';
		}
	}

	function loadImage(url, cache) {
		if (!url) {
			return Promise.resolve(null);
		}
		if (cache[url]) {
			return cache[url];
		}
		cache[url] = new Promise(function (resolve) {
			var img = new Image();
			img.onload = function () {
				resolve(img);
			};
			img.onerror = function () {
				resolve(null);
			};
			img.src = url;
		});
		return cache[url];
	}

	function paintHolderBody(ctx, canvas, img, w, h, color) {
		var off = document.createElement('canvas');
		off.width = canvas.width;
		off.height = canvas.height;
		var octx = off.getContext('2d');
		if (!octx || w < 1 || h < 1) {
			drawImageContained(ctx, img, 0, 0, w, h);
			return;
		}
		octx.setTransform(canvas.width / w, 0, 0, canvas.height / h, 0, 0);
		drawImageContained(octx, img, 0, 0, w, h);
		octx.setTransform(1, 0, 0, 1, 0, 0);
		octx.globalCompositeOperation = 'source-in';
		octx.fillStyle = color || '#000000';
		octx.fillRect(0, 0, off.width, off.height);
		ctx.save();
		ctx.setTransform(1, 0, 0, 1, 0, 0);
		ctx.drawImage(off, 0, 0);
		ctx.restore();
	}

	function plainHolderOn(root) {
		var box = root.querySelector('[data-apd-plain]');
		return !!(box && box.checked && cfg.format && isHolderFamily(cfg.format.type));
	}

	function plainStripHex() {
		if (cfg.format && cfg.format.type === 'holder') {
			return '#7D7D7D';
		}
		return '#000000';
	}

	function applyPlainHolder(root) {
		var on = plainHolderOn(root);
		root.querySelectorAll('[data-apd-personal]').forEach(function (el) {
			el.hidden = on;
		});
		var bg = root.querySelector('[data-apd-color="apd_background_color"]');
		if (!bg) {
			return;
		}
		if (on) {
			if (!bg.hasAttribute('data-apd-plain-saved')) {
				bg.setAttribute('data-apd-plain-saved', bg.value || '');
			}
			bg.value = plainStripHex();
			return;
		}
		if (bg.hasAttribute('data-apd-plain-saved')) {
			bg.value = bg.getAttribute('data-apd-plain-saved');
			bg.removeAttribute('data-apd-plain-saved');
		}
	}

	function frenchFont(style, weight, size, family) {
		return style + ' ' + weight + ' ' + size + 'px "' + family + '", sans-serif';
	}

	function lockedBand(format, w, h) {
		var preset = currentPreset();
		return fitSinglePresetBand(placeBandInsideFrame(resolveBand(format, preset, w, h), w, h), w);
	}

	function paintLockedBand(ctx, canvas, band, w, h, code) {
		var preset = currentPreset();
		var url = preset && preset.image_url ? preset.image_url : '';
		var img = url && imageCache[url] && imageCache[url].naturalWidth ? imageCache[url] : null;
		var radius = Math.min(10, h * 0.12);
		bandHit = null;
		if (band && (img || !url)) {
			ctx.save();
			roundRect(ctx, 0, 0, w, h, radius);
			ctx.clip();
			clipCountryBand(ctx, band, h, w);
			ctx.fillStyle = '#003399';
			ctx.fillRect(band.x, band.y, band.width, band.height);
			if (img) {
				drawEuroBand(ctx, img, band.x, band.y, band.width, band.height);
			} else {
				ctx.fillStyle = '#ffffff';
				ctx.textAlign = 'center';
				ctx.textBaseline = 'middle';
				ctx.font = '700 ' + Math.max(10, Math.round(band.height * 0.22)) + 'px sans-serif';
				ctx.fillText(code || '', band.x + band.width / 2, band.y + band.height * 0.72);
				ctx.textAlign = 'left';
				ctx.textBaseline = 'alphabetic';
			}
			ctx.restore();
			placeBandLayer(canvas, null, '', w, h);
			return;
		}
		placeBandLayer(canvas, band, url, w, h);
	}

	function paintFlippedRegionBand(ctx, leftBand, w, h) {
		var band = {
			x: w - leftBand.x - leftBand.width,
			y: leftBand.y,
			width: leftBand.width,
			height: leftBand.height
		};
		var radius = Math.min(10, h * 0.12);
		band.side = 'right';
		ctx.save();
		roundRect(ctx, 0, 0, w, h, radius);
		ctx.clip();
		clipCountryBand(ctx, band, h, w);
		ctx.fillStyle = '#003399';
		ctx.fillRect(band.x, band.y, band.width, band.height);
		ctx.restore();
	}

	function drawRegionDigits(ctx, root, w, h, font) {
		var input = root.querySelector('[name="apd_fr_region"]');
		var text = input ? String(input.value || '').replace(/\D/g, '').slice(0, 2) : '';
		if (!text) {
			return;
		}
		var format = cfg.format || {};
		var saved = format.fr_region_box || { x: 92.3, y: 60.4, width: 7.7, height: 21.9, align: 'center', valign: 'middle' };
		var box = percentBoxes([saved], w, h)[0];
		var family = font && font.family ? font.family : 'sans-serif';
		var weight = font && font.weight ? font.weight : 400;
		var style = font && font.style ? font.style : 'normal';
		var size = Math.floor(box.height * 0.92);
		var minSize = 8;
		ctx.save();
		ctx.fillStyle = '#003399';
		ctx.fillRect(box.x, box.y, box.width, box.height);
		while (size > minSize) {
			ctx.font = frenchFont(style, weight, size, family);
			var ink = measureInk(ctx, '0', size);
			if (ink.ascent + ink.descent <= box.height && ctx.measureText(text).width <= box.width * 0.9) {
				break;
			}
			size -= 1;
		}
		ctx.font = frenchFont(style, weight, size, family);
		var face = measureInk(ctx, '0', size);
		ctx.fillStyle = '#ffffff';
		ctx.textAlign = 'center';
		ctx.textBaseline = 'alphabetic';
		ctx.fillText(text, box.x + box.width / 2, box.y + (box.height - (face.ascent + face.descent)) / 2 + face.ascent);
		ctx.restore();
	}

	function frenchDrawGroups(root) {
		var mark = cfg.format && cfg.format.fr_mark ? String(cfg.format.fr_mark) : '';
		var nodes = root.querySelectorAll('[data-apd-fr-group]');
		var groups = [];
		var i;
		if (mark) {
			groups.push(mark);
		}
		for (i = 0; i < nodes.length; i += 1) {
			groups.push(String(nodes[i].value || ''));
		}
		return groups;
	}

	function measureFrenchRun(ctx, groups, size, family, weight, style) {
		ctx.font = frenchFont(style, weight, size, family);
		var dashNatural = ctx.measureText('-').width;
		var dashW = size * 0.19;
		var dashScale = dashNatural > 0 ? dashW / dashNatural : 1;
		var gap = size * 0.06;
		var slot = Math.max(ctx.measureText('A').width, ctx.measureText('0').width);
		var labels = [];
		var width = 0;
		var i;
		for (i = 0; i < groups.length; i += 1) {
			var label = groups[i] ? plateGlyphs(ctx, groups[i], family, weight, style) : '';
			labels.push(label);
			width += label ? ctx.measureText(label).width : slot;
			if (i + 1 < groups.length) {
				width += gap + dashW + gap;
			}
		}
		var ink = measureInk(ctx, 'H', size);
		return {
			width: width,
			dashW: dashW,
			dashScale: dashScale,
			gap: gap,
			slot: slot,
			labels: labels,
			height: ink.ascent + ink.descent,
			ascent: ink.ascent,
			descent: ink.descent,
			font: ctx.font,
			size: size,
			family: family,
			weight: weight,
			style: style
		};
	}

	function fitFrenchGroups(ctx, groups, font, maxWidth, maxHeight, minSize) {
		resetCanvasSpacing(ctx);
		var family = font && font.family ? font.family : 'sans-serif';
		var weight = font && font.weight ? font.weight : 400;
		var style = font && font.style ? font.style : 'normal';
		var size = Math.floor(maxHeight * 1.35);
		var laid;
		while (size >= minSize) {
			laid = measureFrenchRun(ctx, groups, size, family, weight, style);
			if (laid.width <= maxWidth && laid.height <= maxHeight) {
				return laid;
			}
			size -= 1;
		}
		return measureFrenchRun(ctx, groups, minSize, family, weight, style);
	}

	function drawFrenchGroups(ctx, fitted, areaLeft, areaW, h, ink) {
		ctx.font = fitted.font;
		ctx.fillStyle = ink;
		ctx.textAlign = 'left';
		ctx.textBaseline = 'alphabetic';
		var baseline = (h - (fitted.ascent + fitted.descent)) / 2 + fitted.ascent;
		var x = areaLeft + Math.max(0, (areaW - fitted.width) / 2);
		var i;
		for (i = 0; i < fitted.labels.length; i += 1) {
			var label = fitted.labels[i];
			var piece = label ? ctx.measureText(label).width : fitted.slot;
			if (label) {
				ctx.fillText(label, x, baseline);
			}
			x += piece;
			if (i + 1 < fitted.labels.length) {
				x += fitted.gap;
				ctx.save();
				ctx.translate(x, baseline);
				ctx.scale(fitted.dashScale, 1);
				ctx.fillText('-', 0, 0);
				ctx.restore();
				x += fitted.dashW + fitted.gap;
			}
		}
	}

	function drawFrenchDate(ctx, root, fitted, w, h, ink) {
		var monthInput = root.querySelector('[name="apd_fr_month"]');
		var yearInput = root.querySelector('[name="apd_fr_year"]');
		var month = monthInput ? String(monthInput.value || '') : '';
		var year = yearInput ? String(yearInput.value || '') : '';
		if (!month && !year) {
			return;
		}
		var size = Math.max(8, Math.round(fitted.size * 0.22));
		var inset = 8;
		var format = cfg.format || {};
		var baseline = (h - (fitted.ascent + fitted.descent)) / 2 + fitted.ascent;
		var anchorTop = baseline - fitted.ascent;
		var anchorBottom = baseline + fitted.descent;
		var monthRight = w - inset;
		var yearRight = w - inset;
		var saved = format.fr_date;
		if (format.fr_date_custom && saved && saved.month && saved.year) {
			var monthBox = percentBoxes([saved.month], w, h)[0];
			var yearBox = percentBoxes([saved.year], w, h)[0];
			anchorTop = monthBox.y;
			anchorBottom = yearBox.y + yearBox.height;
			monthRight = monthBox.x + monthBox.width;
			yearRight = yearBox.x + yearBox.width;
		}
		ctx.font = frenchFont(fitted.style, fitted.weight, size, fitted.family);
		ctx.fillStyle = ink;
		ctx.textAlign = 'right';
		ctx.textBaseline = 'alphabetic';
		var face = measureInk(ctx, '0', size);
		if (month) {
			ctx.fillText(month, monthRight, anchorTop + face.ascent);
		}
		if (year) {
			ctx.fillText(year, yearRight, anchorBottom - face.descent);
		}
		ctx.textAlign = 'left';
	}

	function percentBoxes(list, w, h) {
		if (!list || !list.length) {
			return [];
		}
		return list.map(function (box) {
			return {
				x: w * Number(box.x) / 100,
				y: h * Number(box.y) / 100,
				width: w * Number(box.width) / 100,
				height: h * Number(box.height) / 100,
				align: box.align || 'center',
				valign: box.valign || 'middle'
			};
		});
	}

	function fitFrenchBoxes(ctx, groups, font, boxes, minSize) {
		resetCanvasSpacing(ctx);
		var family = font && font.family ? font.family : 'sans-serif';
		var weight = font && font.weight ? font.weight : 400;
		var style = font && font.style ? font.style : 'normal';
		var maxHeight = boxes.reduce(function (min, box) {
			return Math.min(min, box.height);
		}, boxes[0].height);
		var size = Math.floor(maxHeight * 1.2);
		var measure = function (next) {
			ctx.font = frenchFont(style, weight, next, family);
			var ink = measureInk(ctx, 'H', next);
			var labels = [];
			var widths = [];
			var i;
			for (i = 0; i < groups.length; i += 1) {
				var label = groups[i] ? plateGlyphs(ctx, groups[i], family, weight, style) : '';
				labels.push(label);
				widths.push(label ? ctx.measureText(label).width : Math.max(ctx.measureText('A').width, ctx.measureText('0').width));
			}
			return {
				size: next,
				font: ctx.font,
				family: family,
				weight: weight,
				style: style,
				ascent: ink.ascent,
				descent: ink.descent,
				height: ink.ascent + ink.descent,
				labels: labels,
				widths: widths,
				dashW: next * 0.19,
				dashScale: (function () {
					var natural = ctx.measureText('-').width;
					return natural > 0 ? (next * 0.19) / natural : 1;
				}())
			};
		};
		var laid;
		while (size >= minSize) {
			laid = measure(size);
			var fits = laid.height <= maxHeight;
			var n;
			for (n = 0; fits && n < boxes.length && n < laid.widths.length; n += 1) {
				if (laid.widths[n] > boxes[n].width) {
					fits = false;
				}
			}
			if (fits) {
				return laid;
			}
			size -= 1;
		}
		return measure(minSize);
	}

	function drawBoxedFrenchGroups(ctx, fitted, groups, boxes, ink) {
		ctx.font = fitted.font;
		ctx.fillStyle = ink;
		ctx.textBaseline = 'alphabetic';
		var i;
		for (i = 0; i < boxes.length && i < fitted.labels.length; i += 1) {
			var box = boxes[i];
			var label = fitted.labels[i];
			var baseline = box.y + (box.height - fitted.height) / 2 + fitted.ascent;
			if (box.valign === 'top') {
				baseline = box.y + fitted.ascent;
			} else if (box.valign === 'bottom') {
				baseline = box.y + box.height - fitted.descent;
			}
			var x = box.x + box.width / 2;
			ctx.textAlign = 'center';
			if (box.align === 'left') {
				ctx.textAlign = 'left';
				x = box.x;
			} else if (box.align === 'right') {
				ctx.textAlign = 'right';
				x = box.x + box.width;
			}
			if (label) {
				// fr keeps the natural width. fr_region shares one horizontal
				// squeeze so a narrower field does not shrink the font size.
				if (fitted.scaleX && fitted.scaleX < 0.999) {
					ctx.save();
					ctx.translate(x, baseline);
					ctx.scale(fitted.scaleX, 1);
					ctx.fillText(label, 0, 0);
					ctx.restore();
				} else {
					ctx.fillText(label, x, baseline);
				}
			}
			if (i + 1 < boxes.length) {
				var next = boxes[i + 1];
				var dashX = box.x + box.width + Math.max(0, next.x - (box.x + box.width)) / 2;
				ctx.save();
				ctx.translate(dashX, baseline);
				ctx.scale(fitted.dashScale, 1);
				ctx.textAlign = 'center';
				ctx.fillText('-', 0, 0);
				ctx.restore();
			}
		}
		ctx.textAlign = 'left';
	}

	function standardFrenchGroupBoxes(w, h, band) {
		var edge = 8;
		var left = (band ? band.x + band.width : 0) + edge;
		var span = Math.max(24, w - edge - left);
		var dash = w * 0.032;
		var inner = Math.max(18, span - dash * 2);
		var w1 = inner * 2 / 7;
		var w2 = inner * 3 / 7;
		var w3 = inner - w1 - w2;
		var cluster = w1 + dash + w2 + dash + w3;
		var start = left + (span - cluster) / 2;
		var y = edge;
		var boxH = Math.max(8, h - edge * 2);
		return [
			{ x: start, y: y, width: w1, height: boxH },
			{ x: start + w1 + dash, y: y, width: w2, height: boxH },
			{ x: start + w1 + dash + w2 + dash, y: y, width: w3, height: boxH }
		];
	}

	function frenchGroupSqueeze(fitted, boxes) {
		var scale = 1;
		var n;
		if (!fitted || !fitted.widths || !boxes) {
			return 1;
		}
		for (n = 0; n < boxes.length && n < fitted.widths.length; n += 1) {
			if (fitted.widths[n] > boxes[n].width && fitted.widths[n] > 0) {
				scale = Math.min(scale, boxes[n].width / fitted.widths[n]);
			}
		}
		return scale;
	}

	function drawFrenchPlate(ctx, canvas, root, w, h, font, minSize) {
		var format = cfg.format || {};
		var temporary = format.type === 'fr_ww';
		var fill = format.fr_fill || (temporary ? '#F19DC4' : '#FFFFFF');
		var ink = format.fr_ink || '#000000';
		var radius = Math.min(10, h * 0.12);
		var band = lockedBand(format, w, h);
		ctx.save();
		roundRect(ctx, 0, 0, w, h, radius);
		ctx.clip();
		ctx.fillStyle = fill;
		ctx.fillRect(0, 0, w, h);
		var groups = frenchDrawGroups(root);
		var boxes = percentBoxes(format.text_boxes, w, h);
		var fitted;
		// Temporary plates keep the existing centered inscription. Only the date moves.
		// The region plate uses the standard French box for the font size, then one
		// shared squeeze if a group is wider than the narrower region field.
		if (format.type === 'fr_region' && boxes.length >= groups.length && boxes.length > 0) {
			fitted = fitFrenchBoxes(ctx, groups, font, standardFrenchGroupBoxes(w, h, band), minSize);
			fitted.scaleX = frenchGroupSqueeze(fitted, boxes);
			drawBoxedFrenchGroups(ctx, fitted, groups, boxes, ink);
		} else if (!temporary && boxes.length >= groups.length && boxes.length > 0) {
			fitted = fitFrenchBoxes(ctx, groups, font, boxes, minSize);
			drawBoxedFrenchGroups(ctx, fitted, groups, boxes, ink);
		} else {
			var dateCol = temporary ? Math.max(22, h * 0.22) : 0;
			var areaLeft = (band ? band.x + band.width : 0) + 6;
			var areaRight = w - (temporary ? dateCol : 6);
			fitted = fitFrenchGroups(ctx, groups, font, Math.max(20, areaRight - areaLeft), h * 0.72, minSize);
			drawFrenchGroups(ctx, fitted, areaLeft, Math.max(20, areaRight - areaLeft), h, ink);
		}
		if (temporary) {
			drawFrenchDate(ctx, root, fitted, w, h, ink);
		}
		ctx.restore();
		paintLockedBand(ctx, canvas, band, w, h, 'F');
		if (format.type === 'fr_region' && band) {
			paintFlippedRegionBand(ctx, band, w, h);
			drawRegionDigits(ctx, root, w, h, font);
		}
		ctx.save();
		ctx.strokeStyle = '#111111';
		ctx.lineWidth = 1.4;
		roundRect(ctx, 0.7, 0.7, Math.max(1, w - 1.4), Math.max(1, h - 1.4), radius);
		ctx.stroke();
		ctx.restore();
	}

	function austriaFields(root) {
		var leftInput = root.querySelector('[name="apd_at_left"]');
		var rightInput = root.querySelector('[name="apd_at_right"]');
		return {
			left: leftInput ? String(leftInput.value || '') : 'WE',
			right: rightInput ? String(rightInput.value || '') : '000 CF'
		};
	}

	function drawAustriaStripes(ctx, w, h, color) {
		var outer = 3.2;
		var gap = 1.8;
		var inner = 2.8;
		ctx.fillStyle = color || '#C7282D';
		ctx.fillRect(0, 0, w, outer);
		ctx.fillRect(0, h - outer, w, outer);
		ctx.fillRect(0, outer + gap, w, inner);
		ctx.fillRect(0, h - outer - gap - inner, w, inner);
	}

	function fitAustriaPair(ctx, left, right, font, leftW, rightW, maxHeight, minSize) {
		resetCanvasSpacing(ctx);
		var family = font && font.family ? font.family : 'sans-serif';
		var weight = font && font.weight ? font.weight : 400;
		var style = font && font.style ? font.style : 'normal';
		var size = Math.floor(maxHeight * 1.2);
		var laid;
		var measure = function (next) {
			ctx.font = frenchFont(style, weight, next, family);
			var ink = measureInk(ctx, 'H', next);
			return {
				size: next,
				font: ctx.font,
				ascent: ink.ascent,
				descent: ink.descent,
				leftW: left ? ctx.measureText(plateGlyphs(ctx, left, family, weight, style)).width : 0,
				rightW: right ? ctx.measureText(plateGlyphs(ctx, right, family, weight, style)).width : 0,
				left: left ? plateGlyphs(ctx, left, family, weight, style) : '',
				right: right ? plateGlyphs(ctx, right, family, weight, style) : ''
			};
		};
		while (size >= minSize) {
			laid = measure(size);
			if (laid.leftW <= leftW && laid.rightW <= rightW && (laid.ascent + laid.descent) <= maxHeight) {
				return laid;
			}
			size -= 1;
		}
		return measure(minSize);
	}

	function drawAustriaBoxText(ctx, text, box, font, ink, minSize) {
		resetCanvasSpacing(ctx);
		var family = font && font.family ? font.family : 'sans-serif';
		var weight = font && font.weight ? font.weight : 400;
		var style = font && font.style ? font.style : 'normal';
		var size = Math.floor(box.height * 1.15);
		var laid;
		var measure = function (next) {
			ctx.font = frenchFont(style, weight, next, family);
			var face = measureInk(ctx, 'H', next);
			var glyphs = text ? plateGlyphs(ctx, text, family, weight, style) : '';
			return {
				size: next,
				font: ctx.font,
				ascent: face.ascent,
				descent: face.descent,
				height: face.ascent + face.descent,
				width: glyphs ? ctx.measureText(glyphs).width : 0,
				glyphs: glyphs
			};
		};
		while (size >= minSize) {
			laid = measure(size);
			if (laid.height <= box.height && laid.width <= box.width) {
				break;
			}
			size -= 1;
			laid = null;
		}
		if (!laid) {
			laid = measure(minSize);
		}
		if (!laid.glyphs) {
			return;
		}
		var baseline = box.y + (box.height - laid.height) / 2 + laid.ascent;
		if (box.valign === 'top') {
			baseline = box.y + laid.ascent;
		} else if (box.valign === 'bottom') {
			baseline = box.y + box.height - laid.descent;
		}
		var x = box.x + box.width / 2;
		ctx.font = laid.font;
		ctx.fillStyle = ink;
		ctx.textBaseline = 'alphabetic';
		ctx.textAlign = 'center';
		if (box.align === 'left') {
			ctx.textAlign = 'left';
			x = box.x;
		} else if (box.align === 'right') {
			ctx.textAlign = 'right';
			x = box.x + box.width;
		}
		ctx.fillText(laid.glyphs, x, baseline);
		ctx.textAlign = 'left';
	}

	function drawAustriaPlate(ctx, canvas, root, w, h, font, minSize) {
		var format = cfg.format || {};
		var fill = format.at_fill || '#FFFFFF';
		var ink = format.at_ink || '#000000';
		var region = format.at_region || { x: 33.5, y: 8.7, width: 10.9, height: 82.1, fill: '#B0B0B0' };
		var radius = Math.min(10, h * 0.12);
		var band = lockedBand(format, w, h);
		var rx = w * Number(region.x) / 100;
		var ry = h * Number(region.y) / 100;
		var rw = w * Number(region.width) / 100;
		var rh = h * Number(region.height) / 100;
		var fields = austriaFields(root);
		var boxes = percentBoxes(format.text_boxes, w, h);
		ctx.save();
		roundRect(ctx, 0, 0, w, h, radius);
		ctx.clip();
		ctx.fillStyle = fill;
		ctx.fillRect(0, 0, w, h);
		drawAustriaStripes(ctx, w, h, format.at_stripe || '#C7282D');
		ctx.fillStyle = region.fill || '#B0B0B0';
		ctx.fillRect(rx, ry, rw, rh);
		if (boxes.length >= 2) {
			drawAustriaBoxText(ctx, fields.left, boxes[0], font, ink, minSize);
			drawAustriaBoxText(ctx, fields.right, boxes[1], font, ink, minSize);
		} else {
			var gap = 8;
			var areaLeft = (band ? band.x + band.width : 0) + gap;
			var leftW = Math.max(8, rx - areaLeft - gap);
			var rightX = rx + rw + gap;
			var rightW = Math.max(8, w - rightX - gap);
			var fitted = fitAustriaPair(ctx, fields.left, fields.right, font, leftW, rightW, Math.max(8, h - 16), minSize);
			var baseline = (h - (fitted.ascent + fitted.descent)) / 2 + fitted.ascent;
			ctx.font = fitted.font;
			ctx.fillStyle = ink;
			ctx.textBaseline = 'alphabetic';
			if (fitted.left) {
				ctx.textAlign = 'center';
				ctx.fillText(fitted.left, areaLeft + leftW / 2, baseline);
			}
			if (fitted.right) {
				ctx.textAlign = 'left';
				ctx.fillText(fitted.right, rightX, baseline);
			}
			ctx.textAlign = 'left';
		}
		ctx.restore();
		paintLockedBand(ctx, canvas, band, w, h, format.at_mark || 'A');
	}

	function paintOpenBand(ctx, canvas, band, w, h) {
		var preset = currentPreset();
		var url = preset && preset.image_url ? preset.image_url : '';
		var img = url && imageCache[url] && imageCache[url].naturalWidth ? imageCache[url] : null;
		var radius = Math.min(10, h * 0.12);
		if (!band) {
			placeBandLayer(canvas, null, '', w, h);
			return;
		}
		ctx.save();
		roundRect(ctx, 0, 0, w, h, radius);
		ctx.clip();
		clipCountryBand(ctx, band, h, w);
		ctx.fillStyle = '#003399';
		ctx.fillRect(band.x, band.y, band.width, band.height);
		if (img) {
			drawEuroBand(ctx, img, band.x, band.y, band.width, band.height);
		} else if (preset && preset.country_code) {
			ctx.fillStyle = '#ffffff';
			ctx.textAlign = 'center';
			ctx.textBaseline = 'middle';
			ctx.font = '700 ' + Math.max(10, Math.round(band.height * 0.22)) + 'px sans-serif';
			ctx.fillText(preset.country_code, band.x + band.width / 2, band.y + band.height * 0.72);
			ctx.textAlign = 'left';
			ctx.textBaseline = 'alphabetic';
		}
		ctx.restore();
		placeBandLayer(canvas, null, '', w, h);
	}

	// Product-page registration. Stretch each glyph into the legal character
	// cell (The Road Vehicles (Display of Registration Marks) Regulations 2001,
	// Schedule 3). A short line stays at that cell. The whole line shrinks
	// only when it does not fit the clearance box. New format drawers must
	// call this helper instead of fillText or drawConfiguredText.
	var LEGAL_CAR = { height: 79, width: 50, narrow: 14, gap: 11, group: 50 };
	var LEGAL_MOTO = { height: 64, width: 44, narrow: 10, gap: 10, group: 44 };

	function legalNarrowGlyph(ch) {
		return ch === 'I' || ch === 'i' || ch === '1' || ch === '\u0406' || ch === '\u0456';
	}

	function legalWideGlyph(ch) {
		return ch === 'W' || ch === 'w' || ch === 'M' || ch === 'm' || ch === 'A' || ch === 'a';
	}

	function legalPlateGlyph(ch) {
		return /[0-9A-Za-z\u0400-\u04FF]/.test(ch);
	}

	function glyphInk(measured) {
		var left = measured.actualBoundingBoxLeft;
		var right = measured.actualBoundingBoxRight;
		var ink = (isFinite(left) ? left : 0) + (isFinite(right) ? right : 0);
		if (!(ink > 0.5)) {
			ink = measured.width > 0 ? measured.width : 1;
			left = 0;
			right = ink;
		}
		return {
			left: isFinite(left) ? left : 0,
			right: isFinite(right) ? right : ink,
			ink: ink
		};
	}

	function registrationFace(ctx, chars) {
		var ascent = 0;
		var descent = 0;
		var crown = 0;
		var tail = 0;
		var seen = false;
		var plate = cyrillicPlate();
		var cap = 0;
		var i;
		if (plate) {
			var capInk = ctx.measureText('H');
			cap = isFinite(capInk.actualBoundingBoxAscent) ? capInk.actualBoundingBoxAscent : 0;
		}
		for (i = 0; i < chars.length; i += 1) {
			if (chars[i] === ' ') {
				continue;
			}
			var measured = ctx.measureText(chars[i]);
			var up = measured.actualBoundingBoxAscent;
			var down = measured.actualBoundingBoxDescent;
			if (plate && cyrillicCrownLetter(chars[i]) && cap > 0 && isFinite(up) && up > cap) {
				crown = Math.max(crown, up - cap);
				up = cap;
			}
			if (plate && cyrillicTailLetter(chars[i]) && isFinite(down) && down > 0) {
				tail = Math.max(tail, down);
				down = 0;
			}
			if (isFinite(up) && up > ascent) {
				ascent = up;
			}
			if (isFinite(down) && down > descent) {
				descent = down;
			}
			seen = true;
		}
		if (!seen || !(ascent > 0)) {
			return measureInk(ctx, 'H', 0);
		}
		return {
			ascent: ascent,
			descent: descent,
			crown: crown,
			tail: tail
		};
	}

	function fontSizeForInk(ctx, font, target, chars) {
		var family = font && font.family ? font.family : 'sans-serif';
		var weight = font && font.weight ? font.weight : 400;
		var style = font && font.style ? font.style : 'normal';
		var size = Math.max(8, Math.round(target));
		var sample = chars && chars.length ? chars : ['H'];
		var step;
		for (step = 0; step < 6; step += 1) {
			ctx.font = frenchFont(style, weight, size, family);
			var ink = registrationFace(ctx, sample);
			var height = ink.ascent + ink.descent;
			if (!(height > 0)) {
				return size;
			}
			var next = Math.max(8, Math.round(size * target / height));
			if (Math.abs(next - size) <= 1) {
				ctx.font = frenchFont(style, weight, next, family);
				var check = registrationFace(ctx, sample);
				if (check.ascent + check.descent > target + 0.6) {
					return Math.max(8, next - 1);
				}
				return next;
			}
			size = next;
		}
		return size;
	}

	function layoutLegalLine(ctx, chars, font, law, target) {
		var family = font && font.family ? font.family : 'sans-serif';
		var weight = font && font.weight ? font.weight : 400;
		var style = font && font.style ? font.style : 'normal';
		var size = fontSizeForInk(ctx, font, target, chars);
		ctx.font = frenchFont(style, weight, size, family);
		var face = registrationFace(ctx, chars);
		var inkH = face.ascent + face.descent;
		var ratio = law.height > 0 ? inkH / law.height : 1;
		var full = law.width * ratio;
		var narrow = law.narrow * ratio;
		var gap = law.gap * ratio;
		var group = law.group * ratio;
		// Same horizontal scale as a normal figure. A proportional "1" or "I"
		// stays at that scale when it is wider than the legal stroke, so the
		// stem is not crushed into the stroke width. W, M and A stay at that
		// scale when they are wider than the cell, so they are not squeezed.
		var uniform = full;
		var ref = glyphInk(ctx.measureText('0'));
		if (ref.ink > 0.5) {
			uniform = full / ref.ink;
		}
		var glyphs = [];
		var total = 0;
		var i;
		for (i = 0; i < chars.length; i += 1) {
			var ch = chars[i];
			if (ch === ' ') {
				glyphs.push({ space: true, adv: group });
				total += group;
				continue;
			}
			var bounds = glyphInk(ctx.measureText(ch));
			var ink = bounds.ink;
			var cell = ink;
			if (legalPlateGlyph(ch)) {
				if (legalNarrowGlyph(ch)) {
					var natural = ink * uniform;
					cell = natural > narrow ? natural : narrow;
				} else if (legalWideGlyph(ch)) {
					var wide = ink * uniform;
					cell = wide > full ? wide : full;
				} else {
					cell = full;
				}
			}
			glyphs.push({
				ch: ch,
				sx: ink > 0 ? cell / ink : 1,
				adv: cell,
				center: (bounds.right - bounds.left) / 2
			});
			total += cell;
			if (chars[i + 1] && chars[i + 1] !== ' ') {
				glyphs.push({ gap: true, adv: gap });
				total += gap;
			}
		}
		return {
			glyphs: glyphs,
			total: total,
			face: face,
			font: ctx.font
		};
	}

	function legalBodyFits(inner, laid) {
		var face = laid && laid.face ? laid.face : null;
		if (!face) {
			return true;
		}
		var crown = face.crown || 0;
		var tail = face.tail || 0;
		if (!(crown > 0) && !(tail > 0)) {
			return true;
		}
		var body = face.ascent + face.descent;
		var top = inner.y + (inner.height - body) / 2;
		return top - crown >= inner.y - 0.5 && top + body + tail <= inner.y + inner.height + 0.5;
	}

	function drawLegalRegistration(ctx, text, font, box, color, law, minSize) {
		if (!box || box.width <= 0 || box.height <= 0) {
			return;
		}
		var family = font && font.family ? font.family : 'sans-serif';
		var weight = font && font.weight ? font.weight : 400;
		var style = font && font.style ? font.style : 'normal';
		var raw = plateGlyphs(ctx, String(text || ''), family, weight, style);
		var chars = Array.from(raw).filter(function (ch) {
			return ch !== '\n' && ch !== '\r';
		});
		if (!chars.length) {
			return;
		}
		resetCanvasSpacing(ctx);
		// Keep the stroke inside the clearance box. The plate clips to that
		// box, and a glyph sized from "H" alone was sliced at the top and bottom.
		var pad = Math.min(box.width, box.height) * 0.02;
		var inner = {
			x: box.x + pad,
			y: box.y + pad,
			width: Math.max(1, box.width - pad * 2),
			height: Math.max(1, box.height - pad * 2)
		};
		var target = Math.min(law.height, inner.height);
		if (!(target > 1)) {
			target = Math.max(minSize || 8, 8);
		}
		var laid = layoutLegalLine(ctx, chars, font, law, target);
		var guard = 0;
		while (laid.total > inner.width + 0.5 && laid.total > 0 && guard < 3) {
			target = Math.max(1, target * (inner.width / laid.total));
			laid = layoutLegalLine(ctx, chars, font, law, target);
			guard += 1;
		}
		var overflowGuard = 0;
		while (!legalBodyFits(inner, laid) && overflowGuard < 8) {
			target = Math.max(1, target * 0.94);
			laid = layoutLegalLine(ctx, chars, font, law, target);
			overflowGuard += 1;
		}
		ctx.save();
		ctx.fillStyle = color;
		ctx.font = laid.font;
		ctx.textAlign = 'left';
		ctx.textBaseline = 'alphabetic';
		var inkH = laid.face.ascent + laid.face.descent;
		var baseline = inner.y + (inner.height - inkH) / 2 + laid.face.ascent;
		var x = inner.x + Math.max(0, (inner.width - laid.total) / 2);
		var g;
		for (g = 0; g < laid.glyphs.length; g += 1) {
			var item = laid.glyphs[g];
			if (item.ch) {
				ctx.save();
				ctx.translate(x + item.adv / 2, baseline);
				ctx.scale(item.sx, 1);
				ctx.fillText(item.ch, -item.center, 0);
				ctx.restore();
			}
			x += item.adv;
		}
		ctx.restore();
	}

	function strokeEnglishRim(ctx, x, y, w, h, radius, gapLeft, gapRight, color, lineWidth, flatSide) {
		var right = x + w;
		var bottom = y + h;
		var rL = flatSide === 'left' ? 0 : Math.min(radius, w / 2, h / 2);
		var rR = flatSide === 'right' ? 0 : Math.min(radius, w / 2, h / 2);
		var open = gapRight > gapLeft + 4;
		ctx.save();
		ctx.strokeStyle = color;
		ctx.lineWidth = lineWidth;
		ctx.lineJoin = 'miter';
		ctx.lineCap = 'butt';
		ctx.beginPath();
		if (!open) {
			ctx.moveTo(x + rL, y);
			ctx.lineTo(right - rR, y);
			if (rR) {
				ctx.arcTo(right, y, right, y + rR, rR);
			} else {
				ctx.lineTo(right, y);
			}
			ctx.lineTo(right, bottom - rR);
			if (rR) {
				ctx.arcTo(right, bottom, right - rR, bottom, rR);
			} else {
				ctx.lineTo(right, bottom);
			}
			ctx.lineTo(x + rL, bottom);
			if (rL) {
				ctx.arcTo(x, bottom, x, bottom - rL, rL);
			} else {
				ctx.lineTo(x, bottom);
			}
			ctx.lineTo(x, y + rL);
			if (rL) {
				ctx.arcTo(x, y, x + rL, y, rL);
			} else {
				ctx.lineTo(x, y);
			}
			ctx.closePath();
			ctx.stroke();
			ctx.restore();
			return;
		}
		var openLeft = Math.max(x + rL, gapLeft);
		var openRight = Math.min(right - rR, gapRight);
		ctx.moveTo(openLeft, bottom);
		ctx.lineTo(x + rL, bottom);
		if (rL) {
			ctx.arcTo(x, bottom, x, bottom - rL, rL);
		} else {
			ctx.lineTo(x, bottom);
		}
		ctx.lineTo(x, y + rL);
		if (rL) {
			ctx.arcTo(x, y, x + rL, y, rL);
		} else {
			ctx.lineTo(x, y);
		}
		ctx.lineTo(right - rR, y);
		if (rR) {
			ctx.arcTo(right, y, right, y + rR, rR);
		} else {
			ctx.lineTo(right, y);
		}
		ctx.lineTo(right, bottom - rR);
		if (rR) {
			ctx.arcTo(right, bottom, right - rR, bottom, rR);
		} else {
			ctx.lineTo(right, bottom);
		}
		ctx.lineTo(openRight, bottom);
		ctx.stroke();
		ctx.restore();
	}

	function drawEnglishPlate(ctx, canvas, root, w, h, font, minSize) {
		var margin = 7;
		var line = 1.4;
		var radius = Math.min(10, h * 0.12);
		var type = cfg.format.type;
		var preset = type === 'uk' ? currentPreset() : null;
		var rawBand = type === 'uk' ? fitSinglePresetBand(resolveBand(cfg.format, preset, w, h), w) : null;
		bandHit = rawBand;
		var plateColor = colorValue('apd_background_color');
		ctx.save();
		roundRect(ctx, 0, 0, w, h, radius);
		ctx.clip();
		ctx.fillStyle = plateColor;
		ctx.fillRect(0, 0, w, h);
		ctx.restore();
		var inset = margin + line / 2;
		var placed = (cfg.format && cfg.format.slogan_box && Number(cfg.format.slogan_box.width) > 0)
			? percentBoxes([cfg.format.slogan_box], w, h)[0]
			: null;
		var lineY = h - inset;
		if (placed && placed.height > 0) {
			lineY = placed.y + placed.height / 2;
		}
		lineY = Math.max(inset + 12, Math.min(h - inset, lineY));
		var sloganInput = root.querySelector('[name="apd_slogan"]');
		var slogan = sloganInput ? String(sloganInput.value || '') : '';
		var bandOnLeft = !!(rawBand && rawBand.width > 0 && rawBand.side !== 'right');
		var bandOnRight = !!(rawBand && rawBand.width > 0 && rawBand.side === 'right');
		var left = bandOnLeft ? (rawBand.x + rawBand.width + inset) : inset;
		var right = bandOnRight ? (rawBand.x - inset) : (w - inset);
		var top = inset;
		var innerW = Math.max(8, right - left);
		var sloganFont = currentSloganFont(root);
		var sloganSize = 11;
		var sloganWidth = 0;
		var textX = left;
		if (slogan) {
			var span = placed && placed.width > 8 ? Math.max(20, placed.width - 8) : innerW - 16;
			ctx.font = sloganFace(sloganFont, sloganSize);
			sloganWidth = ctx.measureText(slogan).width;
			while (sloganSize > 6 && sloganWidth > span) {
				sloganSize -= 0.5;
				ctx.font = sloganFace(sloganFont, sloganSize);
				sloganWidth = ctx.measureText(slogan).width;
			}
			var slotX = placed && placed.width > 8 ? placed.x : left;
			var slotW = placed && placed.width > 8 ? placed.width : innerW;
			textX = slotX + Math.max(0, (slotW - sloganWidth) / 2);
		}
		var pad = 3.5;
		var gapLeft = slogan ? textX - pad : 0;
		var gapRight = slogan ? textX + sloganWidth + pad : 0;
		paintOpenBand(ctx, canvas, rawBand, w, h);
		bandHit = rawBand;
		strokeEnglishRim(
			ctx,
			left,
			top,
			innerW,
			Math.max(8, lineY - top),
			Math.max(2, radius - margin),
			gapLeft,
			gapRight,
			colorValue('apd_border_color'),
			line,
			''
		);
		var textPad = 2.5;
		var textLeft = left + line / 2 + textPad;
		var textRight = right - line / 2 - textPad;
		var textTop = top + line / 2 + textPad;
		var sloganClear = slogan ? Math.max(7, sloganSize * 0.8) : textPad;
		var textBottom = lineY - line / 2 - sloganClear;
		var textInput = root.querySelector('[name="apd_text"]');
		var text = textInput ? textInput.value : '';
		var registration = (cfg.format && cfg.format.text_box && Number(cfg.format.text_box.width) > 0 && Number(cfg.format.text_box.height) > 0)
			? percentBoxes([cfg.format.text_box], w, h)[0]
			: null;
		if (!registration || !(registration.width > 0) || !(registration.height > 0)) {
			registration = {
				x: textLeft,
				y: textTop,
				width: Math.max(8, textRight - textLeft),
				height: Math.max(8, textBottom - textTop)
			};
		}
		if (text) {
			ctx.save();
			ctx.beginPath();
			ctx.rect(registration.x, registration.y, registration.width, registration.height);
			ctx.clip();
			drawLegalRegistration(ctx, text, font, registration, colorValue('apd_text_color'), LEGAL_CAR, minSize);
			ctx.restore();
		}
		if (slogan) {
			ctx.save();
			ctx.fillStyle = colorValue('apd_slogan_color');
			ctx.font = sloganFace(sloganFont, sloganSize);
			ctx.textAlign = 'left';
			ctx.textBaseline = 'alphabetic';
			ctx.fillText(slogan, textX, lineY + sloganSize * 0.12);
			ctx.restore();
		}
	}

	function isCompactPlate(type) {
		type = type || (cfg.format && cfg.format.type);
		return type === 'mini' || type === 'mini_plain' || type === 'super_mini' || type === 'super_mini_plain';
	}

	function compactHasFrame() {
		return !!(cfg.format && !cfg.format.no_frame && cfg.format.border_width > 0);
	}

	function drawMiniPlate(ctx, canvas, root, w, h, font, minSize) {
		var radius = plateCornerRadius(h);
		var type = cfg.format.type;
		var banded = usesCountryBand(type);
		var preset = banded ? currentPreset() : null;
		var rawBand = banded ? fitSinglePresetBand(placeBandInsideFrame(resolveBand(cfg.format, preset, w, h), w, h), w) : null;
		bandHit = rawBand;
		ctx.save();
		roundRect(ctx, 0, 0, w, h, radius);
		ctx.clip();
		ctx.fillStyle = colorValue('apd_background_color');
		ctx.fillRect(0, 0, w, h);
		ctx.restore();
		paintOpenBand(ctx, canvas, rawBand, w, h);
		bandHit = rawBand;
		paintCountryThumb(root, rawBand);
		var textInput = root.querySelector('[name="apd_text"]');
		var text = scriptPlateText(textInput ? textInput.value : '');
		var inset = frameTextInset();
		var gap = inset > 0 ? inset : 8;
		if (text.replace(/[\s\r\n]/g, '')) {
			var left = rawBand ? Math.max(gap, rawBand.x + rawBand.width + 8) : gap;
			var right = w - gap;
			drawLegalRegistration(ctx, text, font, {
				x: left,
				y: gap,
				width: Math.max(8, right - left),
				height: Math.max(8, h - gap * 2)
			}, colorValue('apd_text_color'), LEGAL_MOTO, minSize);
		}
		paintAdminFrame(ctx, w, h);
	}

	function holderRunFont(ch, latin, cyr) {
		if (/[\u0400-\u04FF]/.test(ch)) {
			return cyr && cyr.family ? cyr : latin;
		}
		return latin;
	}

	function holderInkCenterShift(up, down) {
		return (up - down) / 2;
	}

	function drawHolderMixed(ctx, text, latin, cyr, box, color, minSize) {
		if (!latin || !box || box.width <= 0 || box.height <= 0) {
			return;
		}
		var lines = String(text || '').split('\n');
		function faceCss(face, px) {
			return (face.style || 'normal') + ' ' + (face.weight || 400) + ' ' + px + 'px "' + face.family + '", sans-serif';
		}
		function inkOf(ch, face, px) {
			var drawn = plateGlyphs(ctx, ch, face.family, face.weight, face.style);
			ctx.font = faceCss(face, px);
			var measured = ctx.measureText(drawn);
			var up = measured.actualBoundingBoxAscent;
			var down = measured.actualBoundingBoxDescent;
			if (!isFinite(up) || up < px * 0.2) {
				up = px * 0.72;
			}
			if (!isFinite(down) || down < 0) {
				down = 0;
			}
			return { text: drawn, up: up, down: down, width: measured.width };
		}
		function layout(px) {
			return lines.map(function (line) {
				var glyphs = Array.from(line).map(function (ch) {
					var face = holderRunFont(ch, latin, cyr);
					return {
						ch: ch,
						face: face,
						ink: inkOf(ch, face, px),
						cyr: /[\u0400-\u04FF]/.test(ch),
						letter: /[A-Za-z\u00C0-\u024F\u0400-\u04FF]/.test(ch),
						digit: /[0-9]/.test(ch)
					};
				});
				function union(list) {
					var up = 0;
					var down = 0;
					list.forEach(function (glyph) {
						up = Math.max(up, glyph.ink.up);
						down = Math.max(down, glyph.ink.down);
					});
					return { up: up, down: down };
				}
				var latinGroup = glyphs.filter(function (glyph) { return glyph.letter && !glyph.cyr; });
				if (!latinGroup.length) {
					latinGroup = glyphs.filter(function (glyph) { return glyph.digit; });
				}
				var cyrGroup = glyphs.filter(function (glyph) { return glyph.cyr; });
				var latinInk = union(latinGroup);
				var cyrInk = union(cyrGroup);
				var above = Math.max(latinInk.up, cyrInk.up);
				var below = Math.max(latinInk.down, cyrInk.down);
				if (above <= 0 && below <= 0) {
					above = px * 0.72;
				}
				var width = 0;
				glyphs.forEach(function (glyph) {
					width += glyph.ink.width;
				});
				return {
					glyphs: glyphs,
					width: width,
					above: above,
					below: below,
					latinShift: holderInkCenterShift(latinInk.up, latinInk.down),
					cyrShift: holderInkCenterShift(cyrInk.up, cyrInk.down),
					hasLatin: latinGroup.length > 0,
					hasCyr: cyrGroup.length > 0
				};
			});
		}
		var size = Math.max(minSize, Math.floor(box.height * 1.5));
		var guard = 0;
		var laid = layout(size);
		while (size > minSize && guard < 500) {
			var wide = false;
			var block = 0;
			var n;
			for (n = 0; n < laid.length; n += 1) {
				if (laid[n].width > box.width) {
					wide = true;
				}
				block += laid[n].above + laid[n].below;
			}
			block += Math.max(0, laid.length - 1) * size * 0.15;
			if (!wide && block <= box.height) {
				break;
			}
			size -= 1;
			guard += 1;
			laid = layout(size);
		}
		ctx.save();
		resetCanvasSpacing(ctx);
		ctx.fillStyle = color;
		ctx.textAlign = 'left';
		ctx.textBaseline = 'alphabetic';
		var blockH = 0;
		laid.forEach(function (line, index) {
			blockH += line.above + line.below;
			if (index > 0) {
				blockH += size * 0.15;
			}
		});
		var top = box.y + (box.height - blockH) / 2;
		if (box.valign === 'top') {
			top = box.y;
		} else if (box.valign === 'bottom') {
			top = box.y + box.height - blockH;
		}
		var cursor = top;
		laid.forEach(function (line) {
			var lineCenter = cursor + (line.above + line.below) / 2;
			var latinBase = lineCenter + (line.hasLatin ? line.latinShift : line.cyrShift);
			var cyrBase = lineCenter + (line.hasCyr ? line.cyrShift : line.latinShift);
			var x = box.x + (box.width - line.width) / 2;
			if (box.align === 'left') {
				x = box.x;
			} else if (box.align === 'right') {
				x = box.x + box.width - line.width;
			}
			line.glyphs.forEach(function (glyph) {
				ctx.font = faceCss(glyph.face, size);
				ctx.fillText(glyph.ink.text, x, glyph.cyr ? cyrBase : latinBase);
				x += glyph.ink.width;
			});
			cursor += line.above + line.below + size * 0.15;
		});
		ctx.restore();
	}

	function draw(root, cache, keepStage) {
		if (cache && cache.images) {
			imageCache = cache.images;
		}
		paintCountryOptions(root);
		if (!keepStage) {
			measurePreviewStage();
		}
		var canvas = getCanvas();
		if (!canvas) {
			return;
		}
		var format = cfg.format;
		var packed = prepareCanvas(canvas, format);
		var ctx = packed.ctx;
		var w = packed.w;
		var h = packed.h;
		applyPlainHolder(root);
		var textInput = root.querySelector('[name="apd_text"]');
		var text = textInput ? textInput.value : '';
		if (plainHolderOn(root)) {
			text = '';
		} else {
			text = scriptPlateText(text);
		}
		var font = currentFont(root);
		var minSize = cfg.minFont || 12;
		var type = format.type;

		ctx.fillStyle = '#ffffff';
		ctx.fillRect(0, 0, w, h);
		bandHit = null;

		if (type === 'fr' || type === 'fr_ww' || type === 'fr_region') {
			drawFrenchPlate(ctx, canvas, root, w, h, font, minSize);
			return;
		}

		if (type === 'austria') {
			drawAustriaPlate(ctx, canvas, root, w, h, font, minSize);
			return;
		}

		if (type === 'uk' || type === 'uk_plain') {
			drawEnglishPlate(ctx, canvas, root, w, h, font, minSize);
			return;
		}

		if (isCompactPlate(type)) {
			drawMiniPlate(ctx, canvas, root, w, h, font, minSize);
			return;
		}

		if (usesPlateDesigns(type) || usesBaseImage(type)) {
			placeBandLayer(canvas, null, '', w, h);
			var design = usesPlateDesigns(type) ? currentDesign() : null;
			var plateUrl = format.base_image_url || '';
			if (!plateUrl && design && design.image_url) {
				plateUrl = design.image_url;
			}
			if (plateUrl && format.base_image_url) {
				design = null;
			}
			var img = plateUrl ? cache.images[plateUrl] : null;
			if (img && img.tagName === 'IMG') {
				if (type === 'holder') {
					paintHolderBody(ctx, canvas, img, w, h, colorValue('apd_holder_color'));
				} else if (type === 'us') {
					drawPlateArtwork(ctx, img, 0, 0, w, h);
			} else {
					drawImageContained(ctx, img, 0, 0, w, h);
				}
			}

			if (isHolderFamily(type)) {
				var strip = format.strip_box && format.strip_box.width ? format.strip_box : null;
				if (!strip && type === 'holder') {
					strip = { x: 2.73, y: 77.3, width: 94.34, height: 6.26 };
				}
				if (strip) {
					var stripX = w * strip.x / 100;
					var stripY = h * strip.y / 100;
					var stripW = w * strip.width / 100;
					var stripH = h * strip.height / 100;
					var radiusFactor = format.strip_radius ? Number(format.strip_radius) : 0.25;
					ctx.fillStyle = plainHolderOn(root) ? plainStripHex() : colorValue('apd_background_color');
					ctx.beginPath();
					roundRect(ctx, stripX, stripY, stripW, stripH, stripH * radiusFactor);
					ctx.fill();
				}
				if (text) {
					var holderBox = resolveTextBox(format, null, null, w, h);
					var holderCyr = currentScript() === 'combined' ? currentCyrFont(root) : font;
					drawHolderMixed(ctx, text, font, holderCyr, holderBox, colorValue('apd_text_color'), minSize);
				}
				return;
			}

			if (type === 'us' && activeSplitSource()) {
				drawSideText(ctx, text, font, format, design, w, h, minSize);
			} else if (text) {
				var usBox = resolveTextBox(format, design, null, w, h);
				if (usBox.letter_align) {
					drawConfiguredText(ctx, text, font, usBox, colorValue('apd_text_color'), minSize);
				} else {
				var fittedU = fitText(ctx, text, font.family, font.weight, font.style, usBox.width, usBox.height, minSize);
				drawTextInBox(ctx, fittedU, usBox, colorValue('apd_text_color'));
				}
			}
			paintAdminFrame(ctx, w, h);
			return;
		}

		var radius = plateCornerRadius(h);
		var border = wantsNoFrame() ? 0 : ((cfg.format && cfg.format.border_width) || 8);
		var preset = usesCountryBand(type) ? currentPreset() : null;
		var rawBand = usesCountryBand(type) ? resolveBand(format, preset, w, h) : null;
		var band = rawBand ? extendEuroBand(fitSinglePresetBand(placeBandInsideFrame(rawBand, w, h), w), h) : null;
		bandHit = band;
		var bandUrl = preset && preset.image_url ? preset.image_url : '';

		ctx.save();
		roundRect(ctx, 0, 0, w, h, radius);
		ctx.clip();
		ctx.fillStyle = colorValue('apd_background_color');
		ctx.fillRect(0, 0, w, h);

		var textX = border + 8;
		var textW = w - border - textX;
		if (band) {
			textX = band.side === 'right' ? border + 8 : band.x + band.width + 8;
			textW = band.side === 'right' ? band.x - textX : w - border - textX;
		}
		if (text) {
			var euBox = resolveTextBox(format, null, preset, w, h, band);
			if (euBox.width <= 0) {
				euBox = clampBoxInsideFrame({
					x: textX,
					y: h * 0.19,
					width: Math.max(0, textW),
					height: h * 0.62,
					align: 'center',
					valign: 'middle',
					letter_align: usesTwoRows(type) ? 'justify' : '',
					number_align: usesTwoRows(type) ? 'justify' : '',
					plateH: h
				}, w, h);
			}
			var ink = colorValue('apd_text_color');
			drawConfiguredText(ctx, text, font, euBox, ink, minSize, band);
		}
		ctx.restore();

		paintAdminFrame(ctx, w, h);
		var bandImg = bandUrl && imageCache[bandUrl] && imageCache[bandUrl].naturalWidth ? imageCache[bandUrl] : null;
		if (band && (bandImg || (!bandUrl && preset && preset.country_code))) {
			ctx.save();
			roundRect(ctx, 0, 0, w, h, radius);
			ctx.clip();
			clipCountryBand(ctx, band, h, w);
			ctx.fillStyle = '#003399';
			ctx.fillRect(band.x, band.y, band.width, band.height);
			if (bandImg) {
				drawEuroBand(ctx, bandImg, band.x, band.y, band.width, band.height);
			} else {
				ctx.fillStyle = '#ffffff';
				ctx.textAlign = 'center';
				ctx.textBaseline = 'middle';
				ctx.font = '700 ' + Math.round(band.height * 0.22) + 'px sans-serif';
				ctx.fillText(preset.country_code, band.x + band.width / 2, band.y + band.height * 0.72);
				ctx.textAlign = 'left';
				ctx.textBaseline = 'alphabetic';
			}
			ctx.restore();
			placeBandLayer(canvas, null, '', w, h);
		} else {
			placeBandLayer(canvas, band, bandUrl, w, h);
		}
		paintCountryThumb(root, band);
	}

	function syncSuvRows(root) {
		var first = root.querySelector('[name="apd_text_row_1"]');
		var second = root.querySelector('[name="apd_text_row_2"]');
		var hidden = root.querySelector('[name="apd_text"]');
		if (!first || !second || !hidden) {
			return;
		}
		hidden.value = first.value + '\n' + second.value;
	}

	function syncSideFields(root) {
		var left = root.querySelector('[name="apd_text_left"]');
		var right = root.querySelector('[name="apd_text_right"]');
		var hidden = root.querySelector('[name="apd_text"]');
		if (!left || !right || !hidden) {
			return;
		}
		hidden.value = left.value + '\n' + right.value;
	}

	function plateCharCount(root) {
		var sides = root.querySelectorAll('[data-apd-plate-side]');
		if (sides.length >= 2) {
			return countChars(sides[0].value) + countChars(sides[1].value);
		}
		var rows = root.querySelectorAll('[data-apd-plate-row]');
		if (rows.length >= 2) {
			return countChars(rows[0].value) + countChars(rows[1].value);
		}
		var input = root.querySelector('[name="apd_text"]');
		return countChars(input ? input.value : '');
	}

	function plateTextEmpty(root) {
		var sides = root.querySelectorAll('[data-apd-plate-side]');
		if (sides.length >= 2) {
			return !String(sides[0].value || '').trim() && !String(sides[1].value || '').trim();
		}
		var rows = root.querySelectorAll('[data-apd-plate-row]');
		if (rows.length >= 2) {
			return !String(rows[0].value || '').trim() && !String(rows[1].value || '').trim();
		}
		var input = root.querySelector('[name="apd_text"]');
		return !String(input ? input.value : '').trim();
	}

	function rowLimit(index) {
		var rows = cfg.format && cfg.format.row_max_chars;
		if (rows && rows.length > index && rows[index] > 0) {
			return rows[index];
		}
		return cfg.format && cfg.format.max_chars ? cfg.format.max_chars : 12;
	}

	function paintCount(counter, count, max) {
		var template = (cfg.i18n && cfg.i18n.chars) || '%1$s / %2$s characters';
		counter.textContent = template.replace('%1$s', String(count)).replace('%2$s', String(max));
		counter.classList.toggle('is-over', count > max);
	}

	function sideLimit(index) {
		var source = activeSplitSource();
		var limits = source && source.side_max_chars;
		if (limits && limits.length > index && limits[index] > 0) {
			return limits[index];
		}
		return index === 1 ? 4 : 3;
	}

	function sideOverLimit(root) {
		var inputs = root.querySelectorAll('[data-apd-plate-side]');
		if (inputs.length < 2) {
			return false;
		}
		return countChars(inputs[0].value) > sideLimit(0) || countChars(inputs[1].value) > sideLimit(1);
	}

	function updateSloganCount(root) {
		var sloganInput = root.querySelector('[name="apd_slogan"]');
		var sloganCount = root.querySelector('[data-apd-slogan-count]');
		if (!sloganInput || !sloganCount) {
			return;
		}
		var cap = cfg.format && cfg.format.slogan_max ? cfg.format.slogan_max : parseInt(sloganInput.getAttribute('maxlength'), 10);
		if (!cap || cap < 1) {
			cap = 40;
		}
		paintCount(sloganCount, countChars(sloganInput.value), cap);
	}

	function updateCount(root) {
		updateSloganCount(root);
		var frenchFields = root.querySelectorAll('[data-apd-fr], [data-apd-at]');
		if (frenchFields.length) {
			var frenchCounts = root.querySelectorAll('[data-apd-count]');
			var n;
			for (n = 0; n < frenchFields.length && n < frenchCounts.length; n += 1) {
				var cap = parseInt(frenchFields[n].getAttribute('maxlength'), 10);
				if (!cap || cap < 1) {
					cap = 2;
				}
				paintCount(frenchCounts[n], countChars(frenchFields[n].value), cap);
			}
			return;
		}
		var sides = root.querySelectorAll('[data-apd-plate-side]');
		var counters = root.querySelectorAll('[data-apd-count]');
		if (sides.length >= 2 && counters.length >= 2) {
			var s;
			for (s = 0; s < 2; s += 1) {
				paintCount(counters[s], countChars(sides[s].value), sideLimit(s));
			}
			return;
		}
		var inputs = root.querySelectorAll('[data-apd-plate-row]');
		counters = root.querySelectorAll('[data-apd-count]');
		if (inputs.length >= 2 && counters.length >= 2) {
			var i;
			for (i = 0; i < 2; i += 1) {
				paintCount(counters[i], countChars(inputs[i].value), rowLimit(i));
			}
			return;
		}
		var input = root.querySelector('[name="apd_text"]');
		var counter = $(root, '[data-apd-count]');
		if (!input || !counter) {
			return;
		}
		paintCount(counter, plateCharCount(root), cfg.format.max_chars || 12);
	}

	function suvRowsOverLimit(root) {
		var inputs = root.querySelectorAll('[data-apd-plate-row]');
		if (inputs.length < 2) {
			return false;
		}
		return countChars(inputs[0].value) > rowLimit(0) || countChars(inputs[1].value) > rowLimit(1);
	}

	function pointerOnBand(canvas, event, w, h) {
		if (!bandHit || !canvas) {
			return false;
		}
		var rect = canvas.getBoundingClientRect();
		if (rect.width <= 0 || rect.height <= 0) {
			return false;
		}
		var x = ((event.clientX - rect.left) / rect.width) * w;
		var y = ((event.clientY - rect.top) / rect.height) * h;
		return x >= bandHit.x && x <= bandHit.x + bandHit.width && y >= bandHit.y && y <= bandHit.y + bandHit.height;
	}

	function applyCountry(root, button) {
		if (!button) {
			return;
		}
		var hiddenPreset = root.querySelector('[data-apd-preset]');
		if (hiddenPreset) {
			hiddenPreset.value = button.getAttribute('data-apd-preset-id') || '';
		}
		root.querySelectorAll('[data-apd-preset-id]').forEach(function (btn) {
			btn.setAttribute('aria-pressed', btn === button ? 'true' : 'false');
		});
		var thumb = root.querySelector('[data-apd-country-thumb]');
		var codeEl = root.querySelector('[data-apd-country-code]');
		var image = button.getAttribute('data-apd-country-image') || '';
		var code = button.getAttribute('data-apd-country-code') || '';
		if (thumb) {
			if (image) {
				thumb.src = image;
			} else {
				thumb.removeAttribute('src');
			}
			thumb.hidden = true;
		}
		if (codeEl) {
			codeEl.textContent = code;
		}
	}

	function closeCountryDialog(root) {
		var dialog = root.querySelector('[data-apd-country-dialog]');
		var opener = root.querySelector('[data-apd-country-open]');
		if (!dialog || dialog.hidden) {
			return;
		}
		dialog.hidden = true;
		dialog.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('apd-dialog-open');
		if (opener) {
			opener.setAttribute('aria-expanded', 'false');
			opener.focus();
		}
	}

	function openCountryDialog(root) {
		var dialog = root.querySelector('[data-apd-country-dialog]');
		var opener = root.querySelector('[data-apd-country-open]');
		var panel = dialog ? dialog.querySelector('.apd-dialog-panel') : null;
		if (!dialog) {
			return;
		}
		dialog.hidden = false;
		dialog.setAttribute('aria-hidden', 'false');
		document.body.classList.add('apd-dialog-open');
		paintCountryOptions(root);
		window.requestAnimationFrame(function () {
			paintCountryOptions(root);
		});
		if (opener) {
			opener.setAttribute('aria-expanded', 'true');
		}
		var selected = dialog.querySelector('[data-apd-preset-id][aria-pressed="true"]');
		var focusEl = selected || dialog.querySelector('[data-apd-preset-id]') || dialog.querySelector('[data-apd-country-dismiss]');
		if (focusEl) {
			focusEl.focus();
		}
		if (panel) {
			panel.scrollTop = 0;
		}
	}

	function drawImageCover(ctx, img, dx, dy, dw, dh) {
		var sw = img.naturalWidth;
		var sh = img.naturalHeight;
		if (!(sw > 0) || !(sh > 0) || !(dw > 0) || !(dh > 0)) {
			return;
		}
		var scale = Math.max(dw / sw, dh / sh);
		var cw = dw / scale;
		var ch = dh / scale;
		ctx.drawImage(img, (sw - cw) / 2, (sh - ch) / 2, cw, ch, dx, dy, dw, dh);
	}

	function bleedEuroBandPixels(data, sw, sh) {
		var count = sw * sh;
		var fringe = new Uint8Array(count);
		var queue = [];
		// Edge matte is transparent, a near-black export rim, or the white corners a JPEG
		// uses where a PNG would be transparent. Interior white (a flag stripe, "BG") is
		// not connected to that edge, so it stays.
		function isMatte(pixel) {
			var offset = pixel * 4;
			var red = data[offset];
			var green = data[offset + 1];
			var blue = data[offset + 2];
			var alpha = data[offset + 3];
			if (alpha < 20) {
				return true;
			}
			if (red < 48 && green < 48 && blue < 48) {
				return true;
			}
			return red > 200 && green > 200 && blue > 200;
		}
		function isCore(pixel) {
			var offset = pixel * 4;
			var red = data[offset];
			var green = data[offset + 1];
			var blue = data[offset + 2];
			var alpha = data[offset + 3];
			if (alpha < 200) {
				return false;
			}
			if (blue > 130 && red < 60 && green < 100) {
				return true;
			}
			var max = Math.max(red, green, blue);
			var min = Math.min(red, green, blue);
			return (max - min) > 110 && max < 250;
		}
		function push(x, y) {
			if (x < 0 || y < 0 || x >= sw || y >= sh) {
				return;
			}
			var pixel = y * sw + x;
			if (fringe[pixel] || !isMatte(pixel)) {
				return;
			}
			fringe[pixel] = 1;
			queue.push(pixel);
		}
		var edge;
		for (edge = 0; edge < sw; edge++) {
			push(edge, 0);
			push(edge, sh - 1);
		}
		for (edge = 0; edge < sh; edge++) {
			push(0, edge);
			push(sw - 1, edge);
		}
		var head = 0;
		while (head < queue.length) {
			var pixel = queue[head++];
			var cx = pixel % sw;
			var cy = (pixel / sw) | 0;
			push(cx - 1, cy);
			push(cx + 1, cy);
			push(cx, cy - 1);
			push(cx, cy + 1);
		}
		var pass;
		for (pass = 0; pass < 4; pass++) {
			var extra = [];
			var index;
			for (index = 0; index < count; index++) {
				if (fringe[index] || isCore(index) || isMatte(index)) {
					continue;
				}
				var ex = index % sw;
				var ey = (index / sw) | 0;
				var near = (ex > 0 && fringe[index - 1]) || (ex + 1 < sw && fringe[index + 1]) || (ey > 0 && fringe[index - sw]) || (ey + 1 < sh && fringe[index + sw]);
				if (near) {
					extra.push(index);
				}
			}
			for (index = 0; index < extra.length; index++) {
				fringe[extra[index]] = 1;
			}
		}
		var out = new Uint8ClampedArray(data);
		for (pixel = 0; pixel < count; pixel++) {
			if (!fringe[pixel] || data[pixel * 4 + 3] < 20) {
				continue;
			}
			out[pixel * 4] = 0;
			out[pixel * 4 + 1] = 51;
			out[pixel * 4 + 2] = 153;
		}
		var halo = new Uint8Array(count);
		var grow;
		for (grow = 0; grow < 6; grow++) {
			for (pixel = 0; pixel < count; pixel++) {
				if (halo[pixel]) {
					continue;
				}
				var ho = pixel * 4;
				var hr = out[ho];
				var hg = out[ho + 1];
				var hb = out[ho + 2];
				var ha = out[ho + 3];
				var hmax = Math.max(hr, hg, hb);
				var hmin = Math.min(hr, hg, hb);
				var lightBlue = hb >= hr && hb >= hg && hb > 150 && hr > 40 && hr < 210 && hg > 70;
				var pale = hmin >= 140 && (hmax - hmin) <= 90 && !(hr > 245 && hg > 245 && hb > 245);
				if (ha < 200 || (!lightBlue && !pale) || touchesGlyph(pixel)) {
					continue;
				}
				var hx = pixel % sw;
				var hy = (pixel / sw) | 0;
				var touch = (hx > 0 && (halo[pixel - 1] || isBlueOut(pixel - 1))) || (hx + 1 < sw && (halo[pixel + 1] || isBlueOut(pixel + 1))) || (hy > 0 && (halo[pixel - sw] || isBlueOut(pixel - sw))) || (hy + 1 < sh && (halo[pixel + sw] || isBlueOut(pixel + sw)));
				if (touch) {
					halo[pixel] = 1;
				}
			}
		}
		for (pixel = 0; pixel < count; pixel++) {
			if (!halo[pixel]) {
				continue;
			}
			out[pixel * 4] = 0;
			out[pixel * 4 + 1] = 51;
			out[pixel * 4 + 2] = 153;
		}
		for (pixel = 0; pixel < count; pixel++) {
			var flatAt = pixel * 4;
			var flatRed = out[flatAt];
			var flatGreen = out[flatAt + 1];
			var flatBlue = out[flatAt + 2];
			if (out[flatAt + 3] < 200 || !(flatBlue > 130 && flatRed < 60 && flatGreen < 100)) {
				continue;
			}
			if (flatRed === 0 && flatGreen === 51 && flatBlue === 153) {
				continue;
			}
			if (touchesGlyph(pixel)) {
				continue;
			}
			out[flatAt] = 0;
			out[flatAt + 1] = 51;
			out[flatAt + 2] = 153;
		}
		return out;
		function touchesGlyph(index) {
			var x = index % sw;
			var y = (index / sw) | 0;
			return (x > 0 && isGlyph(index - 1)) || (x + 1 < sw && isGlyph(index + 1)) || (y > 0 && isGlyph(index - sw)) || (y + 1 < sh && isGlyph(index + sw));
		}
		function isGlyph(index) {
			var red = out[index * 4];
			var green = out[index * 4 + 1];
			var blue = out[index * 4 + 2];
			if (out[index * 4 + 3] < 200) {
				return false;
			}
			if (red > 230 && green > 230 && blue > 230) {
				return true;
			}
			var max = Math.max(red, green, blue);
			var min = Math.min(red, green, blue);
			return (max - min) > 110 && max < 250 && !(blue > 130 && red < 60 && green < 100);
		}
		function isBlueOut(index) {
			return out[index * 4 + 3] > 200 && out[index * 4 + 2] > 130 && out[index * 4] < 60 && out[index * 4 + 1] < 100;
		}
	}

	function euroBandImage(img) {
		if (!img || img._apdBandClean) {
			return img && img._apdBandClean ? img._apdBandClean : img;
		}
		var sw = img.naturalWidth || img.width;
		var sh = img.naturalHeight || img.height;
		if (!(sw > 1) || !(sh > 1) || typeof document === 'undefined') {
			return img;
		}
		try {
			var canvas = document.createElement('canvas');
			canvas.width = sw;
			canvas.height = sh;
			var sample = canvas.getContext('2d', { willReadFrequently: true });
			if (!sample) {
				return img;
			}
			sample.drawImage(img, 0, 0, sw, sh);
			var imageData = sample.getImageData(0, 0, sw, sh);
			var next = bleedEuroBandPixels(imageData.data, sw, sh);
			var changed = false;
			var index;
			for (index = 0; index < next.length; index += 4) {
				if (next[index] !== imageData.data[index] || next[index + 1] !== imageData.data[index + 1] || next[index + 2] !== imageData.data[index + 2]) {
					changed = true;
					break;
				}
			}
			if (!changed) {
				img._apdBandClean = img;
				return img;
			}
			imageData.data.set(next);
			sample.putImageData(imageData, 0, 0);
			img._apdBandClean = canvas;
			return canvas;
		} catch (err) {
			return img;
		}
	}

	function detectEuroBandSplit(img) {
		var sw = img.naturalWidth || img.width;
		var sh = img.naturalHeight || img.height;
		if (!(sw > 0) || !(sh > 0) || typeof document === 'undefined') {
			return 0;
		}
		var probe = document.createElement('canvas');
		probe.width = sw;
		probe.height = sh;
		var probeCtx = probe.getContext('2d', { willReadFrequently: true });
		if (!probeCtx) {
			return 0;
		}
		var data;
		try {
			probeCtx.drawImage(img, 0, 0, sw, sh);
			data = probeCtx.getImageData(0, 0, sw, sh).data;
		} catch (err) {
			return 0;
		}
		var ink = new Array(sh);
		var y;
		var x;
		for (y = 0; y < sh; y++) {
			var count = 0;
			for (x = 0; x < sw; x++) {
				var i = (y * sw + x) * 4;
				var red = data[i];
				var green = data[i + 1];
				var blue = data[i + 2];
				var alpha = data[i + 3];
				if (alpha > 20 && !(blue > 120 && red < 90 && green < 120)) {
					count++;
				}
			}
			ink[y] = count > 2;
		}
		var minGap = Math.max(4, sh * 0.06);
		var groups = [];
		var start = -1;
		var empty = 0;
		for (y = 0; y <= sh; y++) {
			if (y < sh && ink[y]) {
				if (start < 0) {
					start = y;
				}
				empty = 0;
			} else if (start >= 0) {
				empty++;
				if (empty >= minGap || y === sh) {
					groups.push([start, y - empty]);
					start = -1;
				}
			}
		}
		if (groups.length < 2) {
			return 0;
		}
		return (groups[0][1] + groups[groups.length - 1][0]) / 2;
	}

	function euroBandSplit(img) {
		if (!img) {
			return 0;
		}
		if (img._apdBandSplit > 0) {
			return img._apdBandSplit;
		}
		var sample = (typeof euroBandImage === 'function') ? euroBandImage(img) : img;
		var sh = sample.naturalHeight || sample.height || img.naturalHeight || img.height || 0;
		var split = detectEuroBandSplit(sample);
		if (!(split > 0) || split >= sh) {
			split = sh * 0.57;
		}
		img._apdBandSplit = split;
		return split;
	}

	function drawEuroBand(ctx, img, dx, dy, dw, dh) {
		var source = (typeof euroBandImage === 'function') ? euroBandImage(img) : img;
		var sw = source.naturalWidth || source.width;
		var sh = source.naturalHeight || source.height;
		if (!(sw > 0) || !(sh > 0) || !(dw > 0) || !(dh > 0)) {
			return;
		}
		var scale = Math.min(dw / sw, dh / sh);
		var tw = sw * scale;
		var th = sh * scale;
		var x = dx + (dw - tw) / 2;
		var split = euroBandSplit(img);
		if (dh - th < 0.5 || !(split > 1) || split >= sh - 1) {
			ctx.drawImage(source, x, dy + (dh - th) / 2, tw, th);
			return;
		}
		var topH = split * scale;
		var botH = (sh - split) * scale;
		ctx.drawImage(source, 0, 0, sw, split, x, dy, tw, topH);
		ctx.drawImage(source, 0, split, sw, sh - split, x, dy + dh - botH, tw, botH);
	}

	function clipCountryBand(ctx, band, plateH, plateW) {
		if (!band || !(band.width > 0) || !(band.height > 0)) {
			return;
		}
		var left = band.side !== 'right';
		var sideInset = left ? band.x : Math.max(0, (plateW || 0) - (band.x + band.width));
		var top = bandCornerRadius(plateH, sideInset, band.y);
		var bottom = bandCornerRadius(plateH, sideInset, plateH - (band.y + band.height));
		clipRoundedBox(
			ctx,
			band.x,
			band.y,
			band.width,
			band.height,
			left ? top : 0,
			left ? 0 : top,
			left ? 0 : bottom,
			left ? bottom : 0
		);
	}

	function clipRoundedBox(ctx, x, y, w, h, tl, tr, br, bl) {
		var limit = Math.min(w, h) / 2;
		tl = Math.max(0, Math.min(limit, tl));
		tr = Math.max(0, Math.min(limit, tr));
		br = Math.max(0, Math.min(limit, br));
		bl = Math.max(0, Math.min(limit, bl));
		ctx.beginPath();
		ctx.moveTo(x + tl, y);
		ctx.lineTo(x + w - tr, y);
		ctx.arcTo(x + w, y, x + w, y + tr, tr);
		ctx.lineTo(x + w, y + h - br);
		ctx.arcTo(x + w, y + h, x + w - br, y + h, br);
		ctx.lineTo(x + bl, y + h);
		ctx.arcTo(x, y + h, x, y + h - bl, bl);
		ctx.lineTo(x, y + tl);
		ctx.arcTo(x, y, x + tl, y, tl);
		ctx.closePath();
		ctx.clip();
	}

	function plateSnapshot() {
		var canvas = getCanvas();
		if (!canvas) {
			return '';
		}
		try {
			var band = document.querySelector('.apd-band-layer');
			if (!band || band.hidden || !band.complete || !band.naturalWidth) {
				return canvas.toDataURL('image/png');
			}
			var copy = document.createElement('canvas');
			copy.width = canvas.width;
			copy.height = canvas.height;
			var ctx = copy.getContext('2d');
			ctx.imageSmoothingEnabled = true;
			if ('imageSmoothingQuality' in ctx) {
				ctx.imageSmoothingQuality = 'high';
			}
			ctx.drawImage(canvas, 0, 0);
			var frame = canvas.parentElement ? canvas.parentElement.getBoundingClientRect() : null;
			var br = band.getBoundingClientRect();
			if (frame && frame.width > 0 && frame.height > 0 && br.width > 0 && br.height > 0) {
				var scaleX = canvas.width / frame.width;
				var scaleY = canvas.height / frame.height;
				var dx = (br.left - frame.left) * scaleX;
				var dy = (br.top - frame.top) * scaleY;
				var dw = br.width * scaleX;
				var dh = br.height * scaleY;
				var style = window.getComputedStyle(band);
				var radius = function (value, scale) {
					var n = parseFloat(value);
					return isFinite(n) ? n * scale : 0;
				};
				ctx.save();
				clipRoundedBox(
					ctx,
					dx,
					dy,
					dw,
					dh,
					radius(style.borderTopLeftRadius, scaleX),
					radius(style.borderTopRightRadius, scaleX),
					radius(style.borderBottomRightRadius, scaleY),
					radius(style.borderBottomLeftRadius, scaleY)
				);
				if (style.backgroundColor && style.backgroundColor !== 'transparent' && style.backgroundColor !== 'rgba(0, 0, 0, 0)') {
					ctx.fillStyle = style.backgroundColor;
					ctx.fillRect(dx, dy, dw, dh);
				}
				drawEuroBand(ctx, band, dx, dy, dw, dh);
				ctx.restore();
			}
			return copy.toDataURL('image/png');
		} catch (err) {
			try {
				return canvas.toDataURL('image/png');
			} catch (again) {
				return '';
			}
		}
	}

	function plateTextControl(node) {
		if (!node || !node.name) {
			return false;
		}
		return node.name === 'apd_text' || node.name === 'apd_text_row_1' || node.name === 'apd_text_row_2' || node.name === 'apd_text_left' || node.name === 'apd_text_right' || node.name === 'apd_fr_a' || node.name === 'apd_fr_b' || node.name === 'apd_fr_c' || node.name === 'apd_fr_month' || node.name === 'apd_fr_year' || node.name === 'apd_fr_region' || node.name === 'apd_at_left' || node.name === 'apd_at_right';
	}

	function upperPlateControl(input) {
		if (!input || input.type === 'hidden') {
			return;
		}
		if (cfg.format && isHolderFamily(cfg.format.type)) {
			return;
		}
		var next = String(input.value || '').toLocaleUpperCase('bg');
		if (next === input.value) {
			return;
		}
		var start = input.selectionStart;
		var end = input.selectionEnd;
		input.value = next;
		if (typeof input.setSelectionRange === 'function' && typeof start === 'number') {
			input.setSelectionRange(start, end);
		}
	}

	function upperPlateFields(root) {
		root.querySelectorAll('[name="apd_text"], [name="apd_text_row_1"], [name="apd_text_row_2"], [name="apd_text_left"], [name="apd_text_right"]').forEach(upperPlateControl);
	}

	function rememberInitialValues(root) {
		root.querySelectorAll('[data-apd-color], [data-apd-preset], [data-apd-design]').forEach(function (input) {
			if (!input.hasAttribute('data-apd-initial')) {
				input.setAttribute('data-apd-initial', input.value);
			}
		});
		var plain = root.querySelector('[data-apd-frame-plain]');
		if (plain && !plain.hasAttribute('data-apd-initial-on')) {
			plain.setAttribute('data-apd-initial-on', plain.checked ? '1' : '0');
		}
	}

	function restoreInitialValue(input) {
		if (!input || !input.hasAttribute('data-apd-initial')) {
			return;
		}
		var initial = input.getAttribute('data-apd-initial');
		input.defaultValue = initial;
		input.value = initial;
	}

	function cartDrawerOpen() {
		return document.body.classList.contains('showing-popup-drawer-from-cart') || document.body.classList.contains('showing-popup-drawer-from-right');
	}

	function openKadenceCart() {
		if (cartDrawerOpen()) {
			return;
		}
		var nodes = document.querySelectorAll('.header-cart-button, [data-toggle-target="#cart-drawer"]');
		var i;
		for (i = 0; i < nodes.length; i += 1) {
			var box = nodes[i].getBoundingClientRect();
			if (box.width > 0 && box.height > 0) {
				nodes[i].click();
				return;
			}
		}
		var mini = document.querySelector('.wc-block-mini-cart__button');
		if (mini && mini.getAttribute('aria-expanded') !== 'true') {
			mini.click();
		}
	}

	function revealAddedCartLine(previousKeys, plateText, originScroll) {
		var tries = 0;
		var origin = typeof originScroll === 'number' ? originScroll : 0;
		var needle = String(plateText || '').replace(/\s+/g, ' ').trim();
		var stableList = null;
		var stableFrames = 0;
		function tick() {
			var list = document.querySelector('#cart-drawer ul.woocommerce-mini-cart');
			if (!list || list.clientHeight < 40 || !cartDrawerOpen()) {
				stableList = null;
				stableFrames = 0;
				if (tries < 50) {
					tries += 1;
					window.requestAnimationFrame(tick);
				}
				return;
			}
			if (list !== stableList) {
				stableList = list;
				stableFrames = 0;
				window.requestAnimationFrame(tick);
				return;
			}
			stableFrames += 1;
			if (stableFrames < 3) {
				window.requestAnimationFrame(tick);
				return;
			}
			var items = list.querySelectorAll('.woocommerce-mini-cart-item');
			var target = null;
			var i;
			for (i = 0; i < items.length; i += 1) {
				var keyNode = items[i].querySelector('[data-cart_item_key]');
				var key = keyNode ? keyNode.getAttribute('data-cart_item_key') : '';
				if (previousKeys.indexOf(key) === -1) {
					target = items[i];
				}
			}
			if (!target && needle) {
				for (i = items.length - 1; i >= 0; i -= 1) {
					if (items[i].textContent.replace(/\s+/g, ' ').indexOf(needle) !== -1) {
						target = items[i];
						break;
					}
				}
			}
			if (!target) {
				return;
			}
			var listRect = list.getBoundingClientRect();
			var itemRect = target.getBoundingClientRect();
			var fully = itemRect.top >= listRect.top - 1 && itemRect.bottom <= listRect.bottom + 1;
			if (fully && Math.abs(list.scrollTop - origin) < 8) {
				return;
			}
			var delta = itemRect.height >= listRect.height - 8 || itemRect.top < listRect.top
				? itemRect.top - listRect.top
				: itemRect.bottom - listRect.bottom;
			var top = list.scrollTop + delta;
			if (Math.abs(top - origin) < 2) {
				return;
			}
			if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
				list.scrollTop = top;
				return;
			}
			var start = origin;
			var duration = Math.min(700, Math.max(280, Math.abs(top - start) * 0.45));
			var began = 0;
			list.scrollTop = start;
			function step(now) {
				var current = document.querySelector('#cart-drawer ul.woocommerce-mini-cart');
				if (current && current !== list) {
					list = current;
					began = 0;
					list.scrollTop = start;
				}
				if (!list) {
					return;
				}
				if (!began) {
					began = now;
				}
				var progress = Math.min(1, (now - began) / duration);
				var eased = 1 - Math.pow(1 - progress, 3);
				list.scrollTop = start + (top - start) * eased;
				if (progress < 1) {
					window.requestAnimationFrame(step);
				}
			}
			window.requestAnimationFrame(step);
		}
		window.requestAnimationFrame(tick);
	}

	function bindStayOnProduct(root, cache) {
		var form = root.closest('form');
		if (!form || form.getAttribute('data-apd-stay')) {
			return;
		}
		form.setAttribute('data-apd-stay', '1');
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			if (scriptRejected(currentPlateText())) {
				syncLetterChrome();
				return;
			}
			syncSuvRows(root);
			syncSideFields(root);
			var previousKeys = [];
			document.querySelectorAll('#cart-drawer [data-cart_item_key]').forEach(function (node) {
				var key = node.getAttribute('data-cart_item_key');
				if (key) {
					previousKeys.push(key);
				}
			});
			var listBefore = document.querySelector('#cart-drawer ul.woocommerce-mini-cart');
			var originScroll = listBefore ? listBefore.scrollTop : 0;
			var addedText = currentPlateText();
			var data = new FormData(form);
			var shot = plateSnapshot();
			if (shot) {
				data.set('apd_preview', shot);
				var hiddenShot = form.querySelector('[name="apd_preview"]');
				if (!hiddenShot) {
					hiddenShot = document.createElement('input');
					hiddenShot.type = 'hidden';
					hiddenShot.name = 'apd_preview';
					form.appendChild(hiddenShot);
				}
				hiddenShot.value = shot;
			}
			var submitter = event.submitter;
			if (submitter && submitter.name) {
				data.set(submitter.name, submitter.value);
			}
			var button = submitter || form.querySelector('[type="submit"]');
			if (button) {
				button.setAttribute('data-apd-posting', '1');
				button.disabled = true;
			}
			fetch(form.getAttribute('action') || window.location.href, {
				method: 'POST',
				body: data,
				credentials: 'same-origin'
			}).then(function (response) {
				return response.text();
			}).then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				var error = doc.querySelector('.woocommerce-error, .wc-block-components-notice-banner.is-error');
				if (error) {
					showCartNotice(root, error);
					return;
				}
				return refreshCartWidgets().then(function () {
					showCartNotice(root, null);
					openKadenceCart();
					revealAddedCartLine(previousKeys, addedText, originScroll);
				});
			}).catch(function () {
				form.removeAttribute('data-apd-stay');
				HTMLFormElement.prototype.submit.call(form);
			}).then(function () {
				if (button) {
					button.removeAttribute('data-apd-posting');
					button.disabled = scriptRejected(currentPlateText());
				}
			});
		});
	}

	function showCartNotice(root, node) {
		var host = root.parentElement || root;
		var existing = host.querySelector('.apd-cart-notice');
		if (!node) {
			if (existing) {
				existing.remove();
			}
			return;
		}
		if (!existing) {
			existing = document.createElement('div');
			host.insertBefore(existing, root);
		}
		var isError = node.className && node.className.indexOf('error') !== -1;
		existing.className = 'apd-cart-notice ' + (isError ? 'woocommerce-error' : 'woocommerce-message');
		existing.textContent = node.textContent.replace(/\s+/g, ' ').trim();
	}

	function refreshCartWidgets() {
		var url = new URL(window.location.href);
		url.search = '';
		url.searchParams.set('wc-ajax', 'get_refreshed_fragments');
		return fetch(url.toString(), {
			method: 'POST',
			credentials: 'same-origin'
		}).then(function (response) {
			return response.json();
		}).then(function (payload) {
			if (!payload || !payload.fragments) {
				return;
			}
			Object.keys(payload.fragments).forEach(function (selector) {
				document.querySelectorAll(selector).forEach(function (el) {
					var wrap = document.createElement('div');
					wrap.innerHTML = payload.fragments[selector];
					if (wrap.firstElementChild) {
						el.replaceWith(wrap.firstElementChild);
					}
				});
			});
			document.body.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart', { bubbles: true }));
		}).catch(function () {});
	}

	function filterFrenchField(input) {
		if (!input || !input.getAttribute) {
			return;
		}
		var kind = input.getAttribute('data-apd-fr') || input.getAttribute('data-apd-at');
		if (!kind) {
			return;
		}
		var next = String(input.value || '');
		if (kind === 'digits') {
			next = next.replace(/[^0-9]/g, '');
		} else if (kind === 'mixed') {
			next = next.toLocaleUpperCase('bg').replace(/[^A-Z0-9 ]/g, '');
		} else {
			next = next.toLocaleUpperCase('bg').replace(/[^A-Z]/g, '');
		}
		var cap = parseInt(input.getAttribute('maxlength'), 10);
		if (cap > 0 && next.length > cap) {
			next = next.slice(0, cap);
		}
		if (next === input.value) {
			return;
		}
		input.value = next;
	}

	function syncFrenchFields(root) {
		var nodes = root.querySelectorAll('[data-apd-fr-group]');
		if (!nodes.length) {
			return;
		}
		var hidden = root.querySelector('[name="apd_text"]');
		if (!hidden) {
			return;
		}
		var mark = cfg.format && cfg.format.fr_mark ? String(cfg.format.fr_mark) : '';
		var parts = [];
		var i;
		if (mark) {
			parts.push(mark);
		}
		for (i = 0; i < nodes.length; i += 1) {
			parts.push(nodes[i].value);
		}
		hidden.value = parts.join('-');
	}

	function syncAustriaFields(root) {
		var left = root.querySelector('[name="apd_at_left"]');
		var right = root.querySelector('[name="apd_at_right"]');
		var hidden = root.querySelector('[name="apd_text"]');
		if (!left || !right || !hidden) {
			return;
		}
		hidden.value = left.value + '\n' + right.value;
	}

	function bind(root, cache) {
		function resetFieldsAfterScript(textOnly) {
			if (!cfg.scripts || cfg.scripts.length < 2) {
				return;
			}
			scriptWarning = false;
			var sample = currentScript() === 'cyrillic' ? (cfg.default_text_cyrillic || '') : (currentScript() === 'combined' ? (cfg.default_text_combined || '') : (cfg.default_text || ''));
			sample = String(sample).replace(/\r\n/g, '\n').replace(/\r/g, '\n');
			var rows = root.querySelectorAll('[data-apd-plate-row]');
			if (rows.length >= 2) {
				var parts = sample.split('\n');
				rows[0].value = parts[0] || '';
				rows[1].value = parts[1] || '';
				rows.forEach(function (input) {
					upperPlateControl(input);
					stripWrongScript(input);
				});
			} else {
				var input = root.querySelector('[name="apd_text"]');
				if (input) {
					input.value = sample.replace(/\n/g, ' ');
					if (input.type !== 'hidden') {
						upperPlateControl(input);
						stripWrongScript(input);
					}
				}
			}
			syncSuvRows(root);
			syncSideFields(root);
			if (textOnly) {
				updateCount(root);
				return;
			}
			root.querySelectorAll('[data-apd-color]').forEach(restoreInitialValue);
			root.querySelectorAll('[data-apd-swatch]').forEach(function (btn) {
				var name = btn.getAttribute('data-apd-swatch');
				var hidden = root.querySelector('[data-apd-color="' + name + '"]');
				var pressed = hidden && String(btn.getAttribute('data-apd-hex') || '').toLowerCase() === String(hidden.value || '').toLowerCase();
				btn.setAttribute('aria-pressed', pressed ? 'true' : 'false');
			});
			var plain = root.querySelector('[data-apd-frame-plain]');
			if (plain) {
				plain.checked = plain.getAttribute('data-apd-initial-on') === '1';
			}
			updateCount(root);
		}
		rememberInitialValues(root);
		upperPlateFields(root);
		if (plateTextMismatchesScript(root)) {
			resetFieldsAfterScript(true);
		} else {
			root.querySelectorAll('[name="apd_text"], [data-apd-plate-row], [data-apd-plate-side]').forEach(function (input) {
				if (stripWrongScript(input)) {
					scriptWarning = true;
				}
			});
		}
		window.addEventListener('pageshow', function () {
			if (plateTextMismatchesScript(root)) {
				resetFieldsAfterScript(true);
				syncLetterChrome();
				scheduleDraw(root, cache);
			}
		});
		syncSuvRows(root);
		syncSideFields(root);
		syncFrenchFields(root);
		syncAustriaFields(root);
		root.addEventListener('input', function (event) {
			if (plateTextControl(event.target)) {
				upperPlateControl(event.target);
				filterFrenchField(event.target);
				if (event.target.name === 'apd_text' || event.target.hasAttribute('data-apd-plate-row') || event.target.hasAttribute('data-apd-plate-side')) {
					scriptWarning = stripWrongScript(event.target);
				}
			}
			syncSuvRows(root);
			syncSideFields(root);
			syncFrenchFields(root);
			syncAustriaFields(root);
			updateCount(root);
			syncLetterChrome();
			scheduleDraw(root, cache);
		});
		syncFrameColors();
		if (cfg.cart_item_key) {
			var cartForm = root.closest('form');
			var cartEditButton = cartForm ? cartForm.querySelector('.single_add_to_cart_button') : null;
			var cartEditSaved = cartEditSignature(root);
			if (cartEditButton) {
				paintCartEdit = function () {
					syncCartEditButton(cartEditButton, cartEditSaved, cartEditSignature(root));
				};
				paintCartEdit();
				cartEditButton.addEventListener('click', function (event) {
					if (cartEditButton.getAttribute('data-apd-cart-back') !== '1') {
						return;
					}
					event.preventDefault();
					if (cfg.cart_url) {
						window.location.href = cfg.cart_url;
					}
				});
				cartForm.addEventListener('submit', function (event) {
					if (cartEditButton.getAttribute('data-apd-cart-back') !== '1') {
						return;
					}
					event.preventDefault();
					event.stopPropagation();
					if (cfg.cart_url) {
						window.location.href = cfg.cart_url;
					}
				}, true);
			}
		}
		root.addEventListener('change', function (event) {
			if (keepsPreviewStage(event.target)) {
				holdPreviewStage();
			}
			if (event.target && event.target.hasAttribute('data-apd-plain')) {
				applyPlainHolder(root);
			}
			if (event.target && event.target.hasAttribute('data-apd-script')) {
				resetFieldsAfterScript();
				syncLetterChrome();
			}
			syncFrameColors();
			scheduleDraw(root, cache, keepsPreviewStage(event.target));
		});

		root.addEventListener('click', function (event) {
			var swatch = event.target.closest('[data-apd-swatch]');
			if (swatch) {
				event.preventDefault();
				holdPreviewStage();
				var name = swatch.getAttribute('data-apd-swatch');
				var hex = swatch.getAttribute('data-apd-hex');
				var hidden = root.querySelector('[data-apd-color="' + name + '"]');
				if (hidden) {
					hidden.value = hex;
				}
				root.querySelectorAll('[data-apd-swatch="' + name + '"]').forEach(function (btn) {
					btn.setAttribute('aria-pressed', btn === swatch ? 'true' : 'false');
				});
				scheduleDraw(root, cache, true);
				paintCartEdit();
				return;
			}

			if (event.target.closest('[data-apd-country-open]')) {
				event.preventDefault();
				openCountryDialog(root);
				return;
			}

			if (event.target.closest('[data-apd-country-dismiss]')) {
				event.preventDefault();
				closeCountryDialog(root);
				return;
			}

			var presetBtn = event.target.closest('[data-apd-preset-id]');
			if (presetBtn) {
				event.preventDefault();
				applyCountry(root, presetBtn);
				scheduleDraw(root, cache);
				closeCountryDialog(root);
				paintCartEdit();
			}
		});

		bindStayOnProduct(root, cache);

		document.addEventListener('keydown', function (event) {
			var dialog = root.querySelector('[data-apd-country-dialog]');
			if (!dialog || dialog.hidden) {
				return;
			}
			if (event.key === 'Escape') {
				event.preventDefault();
				closeCountryDialog(root);
				return;
			}
			if (event.key !== 'Tab') {
				return;
			}
			var focusable = dialog.querySelectorAll('button:not([disabled])');
			if (!focusable.length) {
				return;
			}
			var first = focusable[0];
			var last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});

		var canvas = getCanvas();
		if (canvas) {
			canvas.addEventListener('click', function (event) {
				if (!offersCountryPicker()) {
					return;
				}
				var packed = { w: cfg.format.width || 520, h: cfg.format.height || 110 };
				if (!pointerOnBand(canvas, event, packed.w, packed.h)) {
					return;
				}
				event.preventDefault();
				openCountryDialog(root);
			});
			canvas.addEventListener('mousemove', function (event) {
				var packed = { w: cfg.format.width || 520, h: cfg.format.height || 110 };
				canvas.style.cursor = offersCountryPicker() && pointerOnBand(canvas, event, packed.w, packed.h) ? 'pointer' : '';
			});
			canvas.addEventListener('mouseleave', function () {
				canvas.style.cursor = '';
			});
		}

		var form = root.closest('form');
		if (form) {
			form.addEventListener('submit', function (event) {
				syncSuvRows(root);
				syncSideFields(root);
				syncFrenchFields(root);
				syncAustriaFields(root);
				var sides = root.querySelectorAll('[data-apd-plate-side]');
				var rows = root.querySelectorAll('[data-apd-plate-row]');
				var sloganInput = root.querySelector('[name="apd_slogan"]');
				var sloganCap = cfg.format && cfg.format.slogan_max ? cfg.format.slogan_max : 40;
				var sloganOver = sloganInput ? countChars(sloganInput.value) > sloganCap : false;
				var over = sides.length >= 2 ? sideOverLimit(root) : (rows.length >= 2 ? suvRowsOverLimit(root) : plateCharCount(root) > (cfg.format.max_chars || 12));
				over = over || sloganOver;
				var allowEmpty = !!cfg.format.allow_empty;
				if ((!allowEmpty && plateTextEmpty(root)) || over) {
					event.preventDefault();
					window.alert((cfg.i18n && cfg.i18n.invalid) || 'Invalid text');
				}
			});
		}
		syncLetterChrome();
	}

	ready(function () {
		var root = document.querySelector('[data-apd-root]');
		if (!root) {
			return;
		}

		var cache = { images: {} };
		var urls = [];
		if (cfg.format.base_image_url) {
			urls.push(cfg.format.base_image_url);
		}
		(cfg.format.presets || []).forEach(function (preset) {
			if (preset.image_url) {
				urls.push(preset.image_url);
			}
		});
		(cfg.format.designs || []).forEach(function (design) {
			if (design.image_url) {
				urls.push(design.image_url);
			}
		});

		Promise.all(
			urls.map(function (url) {
				return loadImage(url, cache.images).then(function (img) {
					cache.images[url] = img;
				});
			})
		).then(function () {
			return ensureAllFontsLoaded();
		}).then(function () {
			if (document.fonts && document.fonts.ready) {
				return document.fonts.ready;
			}
			return null;
		}).then(function () {
			updateCount(root);
			draw(root, cache);
		});

		hoistPreview(root);
		bind(root, cache);
		updateCount(root);
		measurePreviewStage();
		scheduleDraw(root, cache);
		window.requestAnimationFrame(function () {
			measurePreviewStage();
			scheduleDraw(root, cache);
		});

		if (typeof ResizeObserver === 'function') {
			var frame = root.querySelector('.apd-canvas-frame') || getCanvas();
			if (frame) {
				new ResizeObserver(function () {
					scheduleDraw(root, cache);
				}).observe(frame);
			}
		}
		window.addEventListener('resize', function () {
			previewChromeTop = null;
			measurePreviewStage();
			scheduleDraw(root, cache);
		});
		window.addEventListener('scroll', schedulePreviewStage, { passive: true });
	});
})();
