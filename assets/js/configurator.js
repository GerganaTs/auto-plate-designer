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

	function usesCountryBand(type) {
		type = type || formatType();
		return type === 'eu' || type === 'moto';
	}

	function usesPaintedPlate(type) {
		type = type || formatType();
		return type === 'eu' || type === 'moto' || type === 'custom' || type === 'color';
	}

	function usesBaseImage(type) {
		type = type || formatType();
		return type === 'holder' || type === 'suv';
	}

	function usesPlateDesigns(type) {
		type = type || formatType();
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
		for (i = 0; i < fonts.length; i += 1) {
			if (fonts[i].id === id) {
				return fonts[i];
			}
		}
		return fonts[0];
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
		return !!(cfg.format && cfg.format.no_frame);
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

	function countryThumbCanvas(root) {
		var wrap = root.querySelector('.apd-country-current');
		if (!wrap) {
			return null;
		}
		var canvas = wrap.querySelector('canvas.apd-country-thumb');
		if (canvas) {
			return canvas;
		}
		canvas = document.createElement('canvas');
		canvas.className = 'apd-country-thumb';
		canvas.setAttribute('aria-hidden', 'true');
		var img = wrap.querySelector('[data-apd-country-thumb]');
		if (img) {
			img.hidden = true;
			wrap.insertBefore(canvas, img);
		} else {
			wrap.insertBefore(canvas, wrap.firstChild);
		}
		return canvas;
	}

	function paintCountryThumb(root, cache, band) {
		var plate = getCanvas();
		var thumb = countryThumbCanvas(root);
		if (!plate || !thumb || !band) {
			return;
		}
		var cssH = plate.clientHeight;
		var cssW = Math.max(1, Math.round(plate.clientWidth * (band.width / Math.max(1, cfg.format.width || 520))));
		if (cssH < 8) {
			return;
		}
		var dpr = window.devicePixelRatio || 1;
		if (dpr < 2) {
			dpr = 2;
		}
		var bufW = Math.max(1, Math.round(cssW * dpr));
		var bufH = Math.max(1, Math.round(cssH * dpr));
		if (thumb.width !== bufW || thumb.height !== bufH) {
			thumb.width = bufW;
			thumb.height = bufH;
		}
		thumb.style.width = cssW + 'px';
		thumb.style.height = cssH + 'px';
		var wrap = root.querySelector('.apd-country-current');
		if (wrap) {
			wrap.style.setProperty('--apd-band-w', cssW + 'px');
			wrap.style.setProperty('--apd-band-h', cssH + 'px');
		}
		var ctx = thumb.getContext('2d');
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
		ctx.imageSmoothingEnabled = true;
		if ('imageSmoothingQuality' in ctx) {
			ctx.imageSmoothingQuality = 'high';
		}
		ctx.clearRect(0, 0, cssW, cssH);
		ctx.fillStyle = '#003399';
		ctx.fillRect(0, 0, cssW, cssH);
		var preset = currentPreset();
		var src = preset && preset.image_url ? cache.images[preset.image_url] : null;
		if (src && src.tagName === 'IMG') {
			drawBandImage(ctx, src, { x: 0, y: 0, width: cssW, height: cssH });
		} else if (preset && preset.country_code) {
			ctx.fillStyle = '#ffffff';
			ctx.textAlign = 'center';
			ctx.textBaseline = 'middle';
			ctx.font = '700 ' + Math.round(cssH * 0.22) + 'px sans-serif';
			ctx.fillText(preset.country_code, cssW / 2, cssH * 0.72);
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

	function fitText(ctx, text, family, weight, style, maxWidth, maxHeight, minSize) {
		var lines = String(text).split('\n');
		var size = Math.floor(maxHeight);
		var font;
		ctx.textBaseline = 'alphabetic';
		while (size >= minSize) {
			font = style + ' ' + weight + ' ' + size + 'px "' + family + '", sans-serif';
			ctx.font = font;
			var ink = linesInk(ctx, lines, size);
			var face = faceMetrics(ctx, size);
			var extra = Math.max(0, lines.length - 1) * size;
			var blockH = face.ascent + extra;
			if (lines.length > 1) {
				blockH += face.descent;
			}
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
		return {
			x: bx,
			y: by,
			width: bw,
			height: bh,
			align: box.align === 'left' || box.align === 'right' ? box.align : 'center',
			valign: box.valign === 'top' || box.valign === 'bottom' ? box.valign : 'middle'
		};
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
		var face = fitted.face || faceMetrics(ctx, size);
		var extra = (lines.length - 1) * size;
		var cap = face.ascent;
		var blockH = cap + extra;
		if (lines.length > 1) {
			blockH += face.descent;
		}
		var top = box.y + (box.height - blockH) / 2;
		if (box.valign === 'top') {
			top = box.y;
		} else if (box.valign === 'bottom') {
			top = box.y + box.height - blockH;
		}
		var baseline = top + cap;
		var i;
		var j;
		var line;
		var chars;
		var ch;
		var x;
		var lineW;
		var glyph;
		var sy;
		for (i = 0; i < lines.length; i += 1) {
			line = lines[i] || '';
			lineW = ctx.measureText(line).width;
			x = box.x + (box.width - lineW) / 2;
			if (box.align === 'left') {
				x = box.x;
			} else if (box.align === 'right') {
				x = box.x + box.width - lineW;
			}
			chars = Array.from(line);
			for (j = 0; j < chars.length; j += 1) {
				ch = chars[j];
				glyph = measureInk(ctx, ch, size);
				sy = (cap > 0 && glyph.ascent > cap) ? cap / glyph.ascent : 1;
				ctx.save();
				ctx.translate(x, baseline + i * size);
				if (sy !== 1) {
					ctx.scale(1, sy);
				}
				ctx.fillText(ch, 0, 0);
				ctx.restore();
				x += ctx.measureText(ch).width;
			}
		}
		ctx.restore();
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

		ctx.clearRect(0, 0, w, h);
		bandHit = null;

		if (usesPlateDesigns(type) || usesBaseImage(type)) {
			var plateUrl = format.base_image_url;
			var design = usesPlateDesigns(type) ? currentDesign() : null;
			if (design && design.image_url) {
				plateUrl = design.image_url;
			}
			var img = plateUrl ? cache.images[plateUrl] : null;
			ctx.fillStyle = '#d9d9d9';
			ctx.fillRect(0, 0, w, h);
			if (img && img.tagName === 'IMG') {
				drawImageContained(ctx, img, 0, 0, w, h);
			}

			if (type === 'holder') {
				var stripH = Math.round(h * 0.18);
				ctx.fillStyle = colorValue('apd_background_color');
				ctx.fillRect(0, h - stripH, w, stripH);
				if (text) {
					var holderBox = resolveTextBox(format, null, null, w, h);
					var fittedH = fitText(ctx, text, font.family, font.weight, font.style, holderBox.width, holderBox.height, minSize);
					drawTextInBox(ctx, fittedH, holderBox, colorValue('apd_text_color'));
				}
				return;
			}

			if (text) {
				var usBox = resolveTextBox(format, design, null, w, h);
				var fittedU = fitText(ctx, text, font.family, font.weight, font.style, usBox.width, usBox.height, minSize);
				drawTextInBox(ctx, fittedU, usBox, colorValue('apd_text_color'));
			}
			return;
		}

		var radius = Math.min(w, h) * 0.08;
		var border = wantsNoFrame() ? 0 : (format.border_width || 8);
		var preset = usesCountryBand(type) ? currentPreset() : null;
		var bandImg = preset && preset.image_url ? cache.images[preset.image_url] : null;
		var band = usesCountryBand(type) ? resolveBand(format, preset, w, h) : null;
		bandHit = band;

		ctx.save();
		roundRect(ctx, 1, 1, w - 2, h - 2, radius);
		ctx.clip();
		ctx.fillStyle = colorValue('apd_background_color');
		ctx.fillRect(0, 0, w, h);
		if (band) {
			ctx.fillStyle = '#003399';
			ctx.fillRect(band.x, band.y, band.width, band.height);
			if (bandImg && bandImg.tagName === 'IMG') {
				drawBandImage(ctx, bandImg, band);
			} else if (preset && preset.country_code) {
				ctx.fillStyle = '#ffffff';
				ctx.textAlign = 'center';
				ctx.textBaseline = 'middle';
				ctx.font = '700 ' + Math.round(h * 0.22) + 'px sans-serif';
				ctx.fillText(preset.country_code, band.x + band.width / 2, h * 0.72);
			}
		}

		var textX = border + 8;
		var textW = w - border - textX;
		if (band) {
			textX = band.side === 'right' ? border + 8 : band.x + band.width + 8;
			textW = band.side === 'right' ? band.x - textX : w - border - textX;
		}
		if (text) {
			var euBox = resolveTextBox(format, null, preset, w, h, band);
			if (euBox.width <= 0) {
				euBox = { x: textX, y: h * 0.19, width: Math.max(0, textW), height: h * 0.62, align: 'center', valign: 'middle' };
			}
			var fitted = fitText(ctx, text, font.family, font.weight, font.style, euBox.width, euBox.height, minSize);
			drawTextInBox(ctx, fitted, euBox, colorValue('apd_text_color'));
		}
		ctx.restore();

		if (border > 0) {
			ctx.lineWidth = border;
			ctx.strokeStyle = colorValue('apd_border_color');
			roundRect(ctx, border / 2, border / 2, w - border, h - border, radius);
			ctx.stroke();
		}
		paintCountryThumb(root, cache, band);
	}

	function updateCount(root) {
		var input = root.querySelector('[name="apd_text"]');
		var counter = $(root, '[data-apd-count]');
		if (!input || !counter) {
			return;
		}
		var max = cfg.format.max_chars || 12;
		var n = countChars(input.value);
		var template = (cfg.i18n && cfg.i18n.chars) || '%1$s / %2$s characters';
		counter.textContent = template.replace('%1$s', String(n)).replace('%2$s', String(max));
		counter.classList.toggle('is-over', n > max);
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

	function bind(root, cache) {
		root.addEventListener('input', function () {
			updateCount(root);
			draw(root, cache);
		});
		root.addEventListener('change', function () {
			draw(root, cache);
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
				draw(root, cache);
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
				draw(root, cache);
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
				draw(root, cache);
			}
		});

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
				var input = root.querySelector('[name="apd_text"]');
				var text = input ? input.value : '';
				var n = countChars(text);
				var max = cfg.format.max_chars || 12;
				var allowEmpty = !!cfg.format.allow_empty;
				if ((!allowEmpty && !text.trim()) || n > max) {
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
		draw(root, cache);
		window.requestAnimationFrame(function () {
			measurePreviewStage();
			draw(root, cache);
		});

		if (typeof ResizeObserver === 'function') {
			var frame = root.querySelector('.apd-canvas-frame') || getCanvas();
			if (frame) {
				new ResizeObserver(function () {
					draw(root, cache);
				}).observe(frame);
			}
		}
		window.addEventListener('resize', function () {
			previewChromeTop = null;
			measurePreviewStage();
			draw(root, cache);
		});
	});
})();
