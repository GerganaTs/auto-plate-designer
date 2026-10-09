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
		return type === 'eu' || type === 'moto' || type === 'moto_240' || type === 'suv_eu' || type === 'suv_eu_340';
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
		var box = document.querySelector('[data-apd-frame]');
		var colors = document.querySelector('[data-apd-frame-colors]');
		if (!colors) {
			return;
		}
		colors.hidden = !box || !box.checked;
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
		var cap = measureInk(ctx, 'H', size).ascent;
		var laidDescent = 0;
		var normalized = false;
		width = 0;
		for (i = 0; i < lines.length; i += 1) {
			var laid = lineSpan(ctx, lines[i] || 'H', cap);
			width = Math.max(width, laid.width);
			laidDescent = Math.max(laidDescent, laid.descent);
			if (laid.normalized) {
				normalized = true;
			}
		}
		if (cap > 0 && (normalized || ascent > cap * 1.04)) {
			ascent = cap;
		}
		if (laidDescent > 0) {
			descent = laidDescent;
		}
		return {
			ascent: ascent,
			descent: descent,
			width: width,
			height: ascent + descent + Math.max(0, lines.length - 1) * size
		};
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
		var normalized = false;
		var i;
		var measured;
		var scale;
		var down;
		for (i = 0; i < chars.length; i += 1) {
			measured = ctx.measureText(chars[i]);
			scale = glyphInkScale(measured.actualBoundingBoxAscent, cap);
			down = measured.actualBoundingBoxDescent;
			if (!isFinite(down) || down < 0) {
				down = 0;
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
			normalized: normalized
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

	function fitText(ctx, text, family, weight, style, maxWidth, maxHeight, minSize) {
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
			if (ink.width <= maxWidth && blockH <= maxHeight) {
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

	function lineBlockHeight(fitL, fitN) {
		var ink = rowInkHeight(fitL) + rowInkHeight(fitN);
		if (fitL && fitN) {
			return ink + TWO_ROW_GAP_MM;
		}
		return ink;
	}

	function drawConfiguredText(ctx, text, font, box, color, minSize, band) {
		resetCanvasSpacing(ctx);
		if (!box || !box.letter_align) {
			var fitted = fitText(ctx, text, font.family, font.weight, font.style, box.width, box.height, minSize);
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
			if (widthOk && lineBlockHeight(fitL, fitN) <= box.height + 0.5) {
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
				cursor += TWO_ROW_GAP_MM;
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
		return placeBandInsideFrame(resolveBand(format, preset, w, h), w, h);
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
			ctx.fillStyle = '#003399';
			ctx.fillRect(band.x, band.y, band.width, band.height);
			if (img) {
				drawImageCover(ctx, img, band.x, band.y, band.width, band.height);
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
		ctx.save();
		roundRect(ctx, 0, 0, w, h, radius);
		ctx.clip();
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
		ctx.save();
		ctx.strokeStyle = '#111111';
		ctx.lineWidth = 1.4;
		roundRect(ctx, 0.7, 0.7, Math.max(1, w - 1.4), Math.max(1, h - 1.4), radius);
		ctx.stroke();
		ctx.restore();
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
		ctx.fillStyle = '#003399';
		ctx.fillRect(band.x, band.y, band.width, band.height);
		if (img) {
			drawImageCover(ctx, img, band.x, band.y, band.width, band.height);
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

	function legalPlateGlyph(ch) {
		return /[0-9A-Za-z\u0400-\u04FF]/.test(ch);
	}

	function fontSizeForInk(ctx, font, target) {
		var family = font && font.family ? font.family : 'sans-serif';
		var weight = font && font.weight ? font.weight : 400;
		var style = font && font.style ? font.style : 'normal';
		var size = Math.max(8, Math.round(target));
		var step;
		for (step = 0; step < 6; step += 1) {
			ctx.font = frenchFont(style, weight, size, family);
			var ink = measureInk(ctx, 'H', size);
			var height = ink.ascent + ink.descent;
			if (!(height > 0)) {
				return size;
			}
			var next = Math.max(8, Math.round(size * target / height));
			if (Math.abs(next - size) <= 1) {
				ctx.font = frenchFont(style, weight, next, family);
				var check = measureInk(ctx, 'H', next);
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
		var size = fontSizeForInk(ctx, font, target);
		ctx.font = frenchFont(style, weight, size, family);
		var face = measureInk(ctx, 'H', size);
		var inkH = face.ascent + face.descent;
		var ratio = law.height > 0 ? inkH / law.height : 1;
		var full = law.width * ratio;
		var narrow = law.narrow * ratio;
		var gap = law.gap * ratio;
		var group = law.group * ratio;
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
			var measured = ctx.measureText(ch);
			var left = measured.actualBoundingBoxLeft;
			var right = measured.actualBoundingBoxRight;
			var ink = (isFinite(left) ? left : 0) + (isFinite(right) ? right : 0);
			if (!(ink > 0.5)) {
				ink = measured.width > 0 ? measured.width : 1;
				left = 0;
				right = ink;
			}
			var cell = ink;
			if (legalPlateGlyph(ch)) {
				cell = legalNarrowGlyph(ch) ? narrow : full;
			}
			var center = ((isFinite(right) ? right : ink) - (isFinite(left) ? left : 0)) / 2;
			glyphs.push({
				ch: ch,
				sx: ink > 0 ? cell / ink : 1,
				adv: cell,
				center: center
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
		var target = Math.min(law.height, box.height);
		if (!(target > 1)) {
			target = Math.max(minSize || 8, 8);
		}
		var laid = layoutLegalLine(ctx, chars, font, law, target);
		var guard = 0;
		while (laid.total > box.width + 0.5 && laid.total > 0 && guard < 3) {
			target = Math.max(1, target * (box.width / laid.total));
			laid = layoutLegalLine(ctx, chars, font, law, target);
			guard += 1;
		}
		ctx.save();
		ctx.fillStyle = color;
		ctx.font = laid.font;
		ctx.textAlign = 'left';
		ctx.textBaseline = 'alphabetic';
		var inkH = laid.face.ascent + laid.face.descent;
		var baseline = box.y + (box.height - inkH) / 2 + laid.face.ascent;
		var x = box.x + Math.max(0, (box.width - laid.total) / 2);
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
		var rawBand = type === 'uk' ? resolveBand(cfg.format, preset, w, h) : null;
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
		var left = bandOnLeft ? (rawBand.x + rawBand.width + line / 2) : inset;
		var right = bandOnRight ? (rawBand.x - line / 2) : (w - inset);
		var top = inset;
		var innerW = Math.max(8, right - left);
		var sloganSize = 11;
		var sloganWidth = 0;
		var textX = left;
		if (slogan) {
			var span = placed && placed.width > 8 ? Math.max(20, placed.width - 8) : innerW - 16;
			ctx.font = '400 ' + sloganSize + 'px Arial, "Helvetica Neue", sans-serif';
			sloganWidth = ctx.measureText(slogan).width;
			while (sloganSize > 6 && sloganWidth > span) {
				sloganSize -= 0.5;
				ctx.font = '400 ' + sloganSize + 'px Arial, "Helvetica Neue", sans-serif';
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
			bandOnLeft ? 'left' : (bandOnRight ? 'right' : '')
		);
		var textPad = 2.5;
		var textLeft = left + line / 2 + textPad;
		var textRight = right - line / 2 - textPad;
		var textTop = top + line / 2 + textPad;
		var sloganClear = slogan ? Math.max(7, sloganSize * 0.8) : textPad;
		var textBottom = lineY - line / 2 - sloganClear;
		var textInput = root.querySelector('[name="apd_text"]');
		var text = textInput ? textInput.value : '';
		if (text) {
			drawLegalRegistration(ctx, text, font, {
				x: textLeft,
				y: textTop,
				width: Math.max(8, textRight - textLeft),
				height: Math.max(8, textBottom - textTop)
			}, colorValue('apd_text_color'), LEGAL_CAR, minSize);
		}
		if (slogan) {
			ctx.save();
			ctx.fillStyle = colorValue('apd_slogan_color');
			ctx.font = '400 ' + sloganSize + 'px Arial, "Helvetica Neue", sans-serif';
			ctx.textAlign = 'left';
			ctx.textBaseline = 'alphabetic';
			ctx.fillText(slogan, textX, lineY + sloganSize * 0.12);
			ctx.restore();
		}
	}

	function drawMiniPlate(ctx, canvas, root, w, h, font, minSize) {
		var radius = Math.min(10, h * 0.12);
		var type = cfg.format.type;
		var preset = type === 'mini' ? currentPreset() : null;
		var rawBand = type === 'mini' ? resolveBand(cfg.format, preset, w, h) : null;
		bandHit = rawBand;
		ctx.save();
		roundRect(ctx, 0, 0, w, h, radius);
		ctx.clip();
		ctx.fillStyle = '#ffffff';
		ctx.fillRect(0, 0, w, h);
		ctx.restore();
		paintOpenBand(ctx, canvas, rawBand, w, h);
		bandHit = rawBand;
		ctx.save();
		ctx.strokeStyle = '#111111';
		ctx.lineWidth = 1.4;
		roundRect(ctx, 0.7, 0.7, Math.max(1, w - 1.4), Math.max(1, h - 1.4), radius);
		ctx.stroke();
		ctx.restore();
		var textInput = root.querySelector('[name="apd_text"]');
		var text = textInput ? textInput.value : '';
		if (!text) {
			return;
		}
		var left = rawBand ? rawBand.x + rawBand.width + 8 : 8;
		var right = w - 8;
		drawLegalRegistration(ctx, text, font, {
			x: left,
			y: 8,
			width: Math.max(8, right - left),
			height: Math.max(8, h - 16)
		}, '#000000', LEGAL_MOTO, minSize);
	}

	function draw(root, cache) {
		if (cache && cache.images) {
			imageCache = cache.images;
		}
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
		applyPlainHolder(root);
		var textInput = root.querySelector('[name="apd_text"]');
		var text = textInput ? textInput.value : '';
		if (plainHolderOn(root)) {
			text = '';
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

		if (type === 'mini' || type === 'mini_plain') {
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
					var fittedH = fitText(ctx, text, font.family, font.weight, font.style, holderBox.width, holderBox.height, minSize);
					drawTextInBox(ctx, fittedH, holderBox, colorValue('apd_text_color'));
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
				euBox = clampBoxInsideFrame({
					x: textX,
					y: h * 0.19,
					width: Math.max(0, textW),
					height: h * 0.62,
					align: 'center',
					valign: 'middle',
					letter_align: usesTwoRows(type) ? 'justify' : '',
					number_align: usesTwoRows(type) ? 'justify' : ''
				}, w, h);
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
	}

	function restoreInitialValue(input) {
		if (!input || !input.hasAttribute('data-apd-initial')) {
			return;
		}
		var initial = input.getAttribute('data-apd-initial');
		input.defaultValue = initial;
		input.value = initial;
	}

	function resetConfigurator(root, cache) {
		var form = root.closest('form');
		if (form) {
			form.reset();
		}
		root.querySelectorAll('[data-apd-color], [data-apd-preset], [data-apd-design]').forEach(restoreInitialValue);
		root.querySelectorAll('[data-apd-swatch]').forEach(function (btn) {
			var name = btn.getAttribute('data-apd-swatch');
			var hidden = root.querySelector('[data-apd-color="' + name + '"]');
			var pressed = hidden && String(btn.getAttribute('data-apd-hex') || '').toLowerCase() === String(hidden.value || '').toLowerCase();
			btn.setAttribute('aria-pressed', pressed ? 'true' : 'false');
		});
		var presetInput = root.querySelector('[data-apd-preset]');
		var presetButton = null;
		root.querySelectorAll('[data-apd-preset-id]').forEach(function (btn) {
			var on = presetInput && btn.getAttribute('data-apd-preset-id') === presetInput.value;
			btn.setAttribute('aria-pressed', on ? 'true' : 'false');
			if (on) {
				presetButton = btn;
			}
		});
		if (presetButton) {
			applyCountry(root, presetButton);
		}
		closeCountryDialog(root);
		syncFrameColors();
		upperPlateFields(root);
		syncSuvRows(root);
		syncSideFields(root);
		applyPlainHolder(root);
		updateCount(root);
		showCartNotice(root, null);
		scheduleDraw(root, cache);
	}

	function openKadenceCart() {
		if (document.body.classList.contains('showing-popup-drawer-from-cart')) {
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

	function bindStayOnProduct(root, cache) {
		var form = root.closest('form');
		if (!form || form.getAttribute('data-apd-stay')) {
			return;
		}
		form.setAttribute('data-apd-stay', '1');
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			syncSuvRows(root);
			syncSideFields(root);
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
				if (error) {
					showCartNotice(root, error);
					return;
				}
				return refreshCartWidgets().then(function () {
					resetConfigurator(root, cache);
					openKadenceCart();
				});
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
		rememberInitialValues(root);
		upperPlateFields(root);
		syncSuvRows(root);
		syncSideFields(root);
		syncFrenchFields(root);
		syncAustriaFields(root);
		root.addEventListener('input', function (event) {
			if (plateTextControl(event.target)) {
				upperPlateControl(event.target);
				filterFrenchField(event.target);
			}
			syncSuvRows(root);
			syncSideFields(root);
			syncFrenchFields(root);
			syncAustriaFields(root);
			updateCount(root);
			scheduleDraw(root, cache);
		});
		syncFrameColors();
		root.addEventListener('change', function (event) {
			if (event.target && event.target.hasAttribute('data-apd-plain')) {
				applyPlainHolder(root);
			}
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
				if (cfg.format && cfg.format.capabilities && cfg.format.capabilities.country_locked) {
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
