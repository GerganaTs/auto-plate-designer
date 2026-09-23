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
	var previewChromeTop = null;

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
		return type === 'eu' || type === 'moto' || type === 'suv_eu';
	}

	function usesPaintedPlate(type) {
		type = type || formatType();
		var caps = formatCaps(type);
		if (caps) {
			return !!caps.painted;
		}
		return type === 'eu' || type === 'eu_plain' || type === 'moto' || type === 'moto_plain' || type === 'suv_eu' || type === 'custom' || type === 'color';
	}

	function usesBaseImage(type) {
		type = type || formatType();
		var caps = formatCaps(type);
		if (caps) {
			return !!caps.base_image;
		}
		return type === 'holder' || type === 'suv';
	}

	function usesTwoRows(type) {
		type = type || formatType();
		var caps = formatCaps(type);
		if (caps) {
			return !!caps.two_row;
		}
		return type === 'moto' || type === 'moto_plain' || type === 'suv' || type === 'suv_eu';
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
		if (value === 'left' || value === 'right' || value === 'center') {
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
		return matched || fonts[0];
	}

	function fontFaceSpec(font, px) {
		var size = px || 48;
		var weight = font && font.weight ? font.weight : 400;
		var style = font && font.style === 'italic' ? 'italic' : 'normal';
		var family = font && font.family ? font.family : 'sans-serif';
		return style + ' ' + weight + ' ' + size + 'px "' + family + '"';
	}

	var fontFacePromises = {};

	function ensureFontLoaded(font) {
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

		var weight = String(font.weight || 400);
		var style = font.style === 'italic' ? 'italic' : 'normal';
		var spec = fontFaceSpec(font, 48);

		if (window.FontFace && font.url) {
			var source = 'url(' + JSON.stringify(String(font.url)) + ')';
			var loadFace = function (faceWeight) {
				return new FontFace(String(font.family), source, {
					style: style,
					weight: faceWeight,
					display: 'swap'
				}).load();
			};
			fontFacePromises[key] = loadFace('100 900').catch(function () {
				return loadFace(weight);
			}).then(function (loaded) {
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

	function scheduleDraw(root, cache) {
		var font = currentFont(root);
		ensureFontLoaded(font).then(function () {
			draw(root, cache);
		});
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

	function wantsNoFrame() {
		if (!cfg.format || cfg.format.frame_choice === false || cfg.format.type === 'holder' || cfg.format.type === 'us') {
			return true;
		}
		var box = document.querySelector('[data-apd-frame]');
		if (box) {
			return !box.checked;
		}
		return !!(cfg.format && cfg.format.no_frame);
	}

	function syncFrameColors() {
		var box = document.querySelector('[data-apd-frame]');
		var colors = document.querySelector('[data-apd-frame-colors]');
		if (!colors) {
			return;
		}
		colors.hidden = !box || !box.checked;
	}

	function frameThickness() {
		if (wantsNoFrame()) {
			return 0;
		}
		var border = cfg.format && cfg.format.border_width ? cfg.format.border_width : 0;
		return border > 0 ? border : 0;
	}

	function paintAdminFrame(ctx, w, h) {
		var border = frameThickness();
		if (border <= 0) {
			return;
		}
		var radius = Math.min(w, h) * 0.08;
		ctx.lineWidth = border;
		ctx.strokeStyle = colorValue('apd_border_color');
		roundRect(ctx, border / 2, border / 2, w - border, h - border, radius);
		ctx.stroke();
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
		var thick = frameThickness();
		var radius = Math.max(0, Math.min(w, h) * 0.08 - thick);
		var frameEl = canvas.parentElement;
		var scale = frameEl && frameEl.clientHeight ? frameEl.clientHeight / h : 1;
		var rpx = radius * scale;
		var left = band.side !== 'right';
		var touchTop = band.y <= thick + 0.5;
		var touchBottom = (band.y + band.height) >= (h - thick - 0.5);
		img.style.borderTopLeftRadius = (left && touchTop) ? rpx + 'px' : '0';
		img.style.borderBottomLeftRadius = (left && touchBottom) ? rpx + 'px' : '0';
		img.style.borderTopRightRadius = (!left && touchTop) ? rpx + 'px' : '0';
		img.style.borderBottomRightRadius = (!left && touchBottom) ? rpx + 'px' : '0';
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
		return window.matchMedia('(min-width: 960px)').matches;
	}

	function measurePreviewStage() {
		var preview = document.querySelector('.apd-product-layout__preview');
		if (!preview) {
			return;
		}
		if (!desktopPreview()) {
			preview.style.removeProperty('--apd-stage-h');
			preview.style.removeProperty('--apd-chrome-top');
			previewChromeTop = null;
			return;
		}
		var top = Math.max(0, Math.round(preview.getBoundingClientRect().top));
		if (previewChromeTop == null || top > previewChromeTop) {
			previewChromeTop = top;
		}
		preview.style.setProperty('--apd-chrome-top', previewChromeTop + 'px');
		preview.style.setProperty('--apd-stage-h', Math.max(220, window.innerHeight - previewChromeTop - 24) + 'px');
	}

	function paintCountryThumb(root, band) {
		var plate = getCanvas();
		var thumb = root.querySelector('[data-apd-country-thumb]');
		var wrap = root.querySelector('.apd-country-current');
		if (wrap) {
			wrap.querySelectorAll('canvas.apd-country-thumb').forEach(function (node) {
				node.remove();
			});
		}
		if (!plate || !thumb || !band) {
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
		thumb.hidden = false;
		thumb.style.width = 'auto';
		thumb.style.height = cssH + 'px';
		if (wrap) {
			wrap.style.setProperty('--apd-band-w', cssW + 'px');
			wrap.style.setProperty('--apd-band-h', cssH + 'px');
		}
	}

	function roundRect(ctx, x, y, w, h, r) {
		var radius = Math.min(r, w / 2, h / 2);
		ctx.beginPath();
		ctx.moveTo(x + radius, y);
		ctx.arcTo(x + w, y, x + w, y + h, radius);
		ctx.arcTo(x + w, y + h, x, y + h, radius);
		ctx.arcTo(x, y + h, x, y, radius);
		ctx.arcTo(x, y, x + w, y, radius);
		ctx.closePath();
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
		return {
			ascent: ascent,
			descent: descent,
			width: width,
			height: ascent + descent + Math.max(0, lines.length - 1) * size
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

	function fitText(ctx, text, family, weight, style, maxWidth, maxHeight, minSize) {
		text = plateGlyphs(ctx, text, family, weight, style);
		var lines = String(text).split('\n');
		var size = Math.floor(lines.length === 1 ? maxHeight * 1.5 : maxHeight);
		var font;
		var face;
		ctx.textBaseline = 'alphabetic';
		while (size >= minSize) {
			font = style + ' ' + weight + ' ' + size + 'px "' + family + '", sans-serif';
			ctx.font = font;
			var ink = linesInk(ctx, lines, size);
			face = faceMetrics(ctx, size);
			var extra = Math.max(0, lines.length - 1) * size;
			var blockH = lines.length === 1 ? (ink.ascent + ink.descent) : (face.ascent + extra + face.descent);
			if (ink.width <= maxWidth && blockH <= maxHeight) {
				return { font: font, size: size, lines: lines, face: face, ink: ink };
			}
			size -= 1;
		}
		ctx.font = style + ' ' + weight + ' ' + minSize + 'px "' + family + '", sans-serif';
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
			number_align: usesTwoRows(format && format.type) ? rowAlignValue(box.number_align) : ''
		};
		return clampBoxInsideFrame(resolved, w, h);
	}

	function clampBoxInsideFrame(box, w, h) {
		var thick = frameThickness();
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

	function drawTextInBox(ctx, fitted, box, color) {
		if (!fitted || !box || box.width <= 0 || box.height <= 0) {
			return;
		}
		ctx.save();
		ctx.fillStyle = color;
		ctx.font = fitted.font;
		ctx.textAlign = 'left';
		ctx.textBaseline = 'alphabetic';
		var size = fitted.size;
		var lines = fitted.lines;
		var ink = linesInk(ctx, lines, size);
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
			lineW = ctx.measureText(line).width;
			chars = Array.from(line);
			var justify = box.align === 'justify' && chars.length > 1;
			var gap = 0;
			if (justify) {
				var sum = 0;
				for (j = 0; j < chars.length; j += 1) {
					sum += ctx.measureText(chars[j]).width;
				}
				gap = (box.width - sum) / (chars.length - 1);
				if (gap < 0) {
					gap = 0;
				}
				x = box.x;
			} else if (chars.length === 1 && box.align === 'justify') {
				x = box.x + (box.width - lineW) / 2;
			} else {
				x = box.x + (box.width - lineW) / 2;
				if (box.align === 'left') {
					x = box.x;
				} else if (box.align === 'right') {
					x = box.x + box.width - lineW;
				}
			}
			for (j = 0; j < chars.length; j += 1) {
				ch = chars[j];
				ctx.fillText(ch, x, baseline + i * size);
				x += ctx.measureText(ch).width;
				if (justify && j < chars.length - 1) {
					x += gap;
				}
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

	function drawConfiguredText(ctx, text, font, box, color, minSize, band) {
		if (!box || !box.letter_align) {
			var fitted = fitText(ctx, text, font.family, font.weight, font.style, box.width, box.height, minSize);
			drawTextInBox(ctx, fitted, box, color);
			return;
		}
		var parts = rowsForPlate(text);
		var half = box.height / 2;
		var letterBox = {
			x: box.x,
			y: box.y,
			width: box.width,
			height: half,
			align: plateRowAlign(box.letter_align),
			valign: 'middle'
		};
		var numberBox = {
			x: box.x,
			y: box.y + half,
			width: box.width,
			height: half,
			align: plateRowAlign(box.number_align || box.letter_align),
			valign: 'middle'
		};
		if (band && bandCoversMost(band, letterBox.y, letterBox.height) && !bandCoversMost(band, numberBox.y, numberBox.height)) {
			insetBesideBand(letterBox, band);
		}
		var fitL = fitText(ctx, parts.letters || ' ', font.family, font.weight, font.style, letterBox.width, letterBox.height, minSize);
		var fitN = fitText(ctx, parts.numbers || ' ', font.family, font.weight, font.style, numberBox.width, numberBox.height, minSize);
		var size = Math.min(fitL.size, fitN.size);
		if (parts.letters) {
			drawTextInBox(ctx, fittedAtSize(ctx, parts.letters, font.family, font.weight, font.style, size), letterBox, color);
		}
		if (parts.numbers) {
			drawTextInBox(ctx, fittedAtSize(ctx, parts.numbers, font.family, font.weight, font.style, size), numberBox, color);
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
		return { ctx: ctx, w: fw, h: fh };
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

	function draw(root, cache) {
		measurePreviewStage();
		var canvas = getCanvas();
		if (!canvas) {
			return;
		}
		var format = cfg.format;
		var packed = prepareCanvas(canvas, format);
		var ctx = packed.ctx;
		var w = packed.w;
		var h = packed.h;
		var textInput = root.querySelector('[name="apd_text"]');
		var text = textInput ? textInput.value : '';
		var font = currentFont(root);
		var minSize = cfg.minFont || 12;
		var type = format.type;

		ctx.fillStyle = '#ffffff';
		ctx.fillRect(0, 0, w, h);
		bandHit = null;

		if (usesPlateDesigns(type) || usesBaseImage(type)) {
			placeBandLayer(canvas, null, '', w, h);
			var plateUrl = format.base_image_url;
			var design = usesPlateDesigns(type) ? currentDesign() : null;
			if (design && design.image_url) {
				plateUrl = design.image_url;
			}
			var img = plateUrl ? cache.images[plateUrl] : null;
			if (img && img.tagName === 'IMG') {
				drawImageContained(ctx, img, 0, 0, w, h);
			}

			if (type === 'holder') {
				var strip = format.strip_box && format.strip_box.width ? format.strip_box : { x: 2.73, y: 77.3, width: 94.34, height: 6.26 };
				var stripX = w * strip.x / 100;
				var stripY = h * strip.y / 100;
				var stripW = w * strip.width / 100;
				var stripH = h * strip.height / 100;
				ctx.fillStyle = colorValue('apd_background_color');
				ctx.beginPath();
				roundRect(ctx, stripX, stripY, stripW, stripH, stripH * 0.25);
				ctx.fill();
				if (text) {
					var holderBox = resolveTextBox(format, null, null, w, h);
					var fittedH = fitText(ctx, text, font.family, font.weight, font.style, holderBox.width, holderBox.height, minSize);
					drawTextInBox(ctx, fittedH, holderBox, colorValue('apd_text_color'));
				}
				return;
			}

			if (text) {
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

		var radius = Math.min(w, h) * 0.08;
		var border = wantsNoFrame() ? 0 : ((cfg.format && cfg.format.border_width) || 8);
		var preset = usesCountryBand(type) ? currentPreset() : null;
		var rawBand = usesCountryBand(type) ? resolveBand(format, preset, w, h) : null;
		var band = rawBand ? placeBandInsideFrame(rawBand, w, h) : null;
		bandHit = band;
		var bandUrl = preset && preset.image_url ? preset.image_url : '';

		ctx.save();
		roundRect(ctx, 1, 1, w - 2, h - 2, radius);
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
				euBox = {
					x: textX,
					y: h * 0.19,
					width: Math.max(0, textW),
					height: h * 0.62,
					align: 'center',
					valign: 'middle',
					letter_align: usesTwoRows(type) ? 'justify' : '',
					number_align: usesTwoRows(type) ? 'justify' : ''
				};
			}
			drawConfiguredText(ctx, text, font, euBox, colorValue('apd_text_color'), minSize, band);
		}
		ctx.restore();

		paintAdminFrame(ctx, w, h);
		if (band && !bandUrl && preset && preset.country_code) {
			ctx.fillStyle = '#003399';
			ctx.fillRect(band.x, band.y, band.width, band.height);
			ctx.fillStyle = '#ffffff';
			ctx.textAlign = 'center';
			ctx.textBaseline = 'middle';
			ctx.font = '700 ' + Math.round(band.height * 0.22) + 'px sans-serif';
			ctx.fillText(preset.country_code, band.x + band.width / 2, band.y + band.height * 0.72);
		}
		placeBandLayer(canvas, band, bandUrl, w, h);
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

	function plateCharCount(root) {
		var rows = root.querySelectorAll('[data-apd-plate-row]');
		if (rows.length >= 2) {
			return countChars(rows[0].value) + countChars(rows[1].value);
		}
		var input = root.querySelector('[name="apd_text"]');
		return countChars(input ? input.value : '');
	}

	function plateTextEmpty(root) {
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

	function updateCount(root) {
		var inputs = root.querySelectorAll('[data-apd-plate-row]');
		var counters = root.querySelectorAll('[data-apd-count]');
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
				drawImageCover(ctx, band, dx, dy, dw, dh);
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

	function bindStayOnProduct(root) {
		var form = root.closest('form');
		if (!form || form.getAttribute('data-apd-stay')) {
			return;
		}
		form.setAttribute('data-apd-stay', '1');
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			syncSuvRows(root);
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
				var ok = doc.querySelector('.woocommerce-message, .wc-block-components-notice-banner.is-success');
				showCartNotice(root, error || ok);
				if (!error) {
					refreshCartWidgets();
				}
			}).catch(function () {
				form.removeAttribute('data-apd-stay');
				HTMLFormElement.prototype.submit.call(form);
			}).then(function () {
				if (button) {
					button.disabled = false;
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
		fetch(url.toString(), {
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

	function bind(root, cache) {
		root.addEventListener('input', function () {
			syncSuvRows(root);
			updateCount(root);
			scheduleDraw(root, cache);
		});
		syncFrameColors();
		root.addEventListener('change', function () {
			syncFrameColors();
			scheduleDraw(root, cache);
		});

		root.addEventListener('click', function (event) {
			var swatch = event.target.closest('[data-apd-swatch]');
			if (swatch) {
				event.preventDefault();
				var name = swatch.getAttribute('data-apd-swatch');
				var hex = swatch.getAttribute('data-apd-hex');
				var hidden = root.querySelector('[data-apd-color="' + name + '"]');
				if (hidden) {
					hidden.value = hex;
				}
				root.querySelectorAll('[data-apd-swatch="' + name + '"]').forEach(function (btn) {
					btn.setAttribute('aria-pressed', btn === swatch ? 'true' : 'false');
				});
				scheduleDraw(root, cache);
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
				return;
			}

			var designBtn = event.target.closest('[data-apd-design-id]');
			if (designBtn) {
				event.preventDefault();
				var hiddenDesign = root.querySelector('[data-apd-design]');
				if (hiddenDesign) {
					hiddenDesign.value = designBtn.getAttribute('data-apd-design-id');
				}
				root.querySelectorAll('[data-apd-design-id]').forEach(function (btn) {
					btn.setAttribute('aria-pressed', btn === designBtn ? 'true' : 'false');
				});
				scheduleDraw(root, cache);
			}
		});

		bindStayOnProduct(root);

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
				var packed = { w: cfg.format.width || 520, h: cfg.format.height || 110 };
				if (!pointerOnBand(canvas, event, packed.w, packed.h)) {
					return;
				}
				event.preventDefault();
				openCountryDialog(root);
			});
			canvas.addEventListener('mousemove', function (event) {
				var packed = { w: cfg.format.width || 520, h: cfg.format.height || 110 };
				canvas.style.cursor = pointerOnBand(canvas, event, packed.w, packed.h) ? 'pointer' : '';
			});
			canvas.addEventListener('mouseleave', function () {
				canvas.style.cursor = '';
			});
		}

		var form = root.closest('form');
		if (form) {
			form.addEventListener('submit', function (event) {
				syncSuvRows(root);
				var rows = root.querySelectorAll('[data-apd-plate-row]');
				var over = rows.length >= 2 ? suvRowsOverLimit(root) : plateCharCount(root) > (cfg.format.max_chars || 12);
				var allowEmpty = !!cfg.format.allow_empty;
				if ((!allowEmpty && plateTextEmpty(root)) || over) {
					event.preventDefault();
					window.alert((cfg.i18n && cfg.i18n.invalid) || 'Invalid text');
				}
			});
		}
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
	});
})();
