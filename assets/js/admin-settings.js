/**
 * Auto Plate Designer — admin settings UI.
 * Enqueued only on the plugin settings screen.
 */
(function () {
	'use strict';

	function closest(el, selector) {
		while (el && el.nodeType === 1) {
			if (el.matches(selector)) {
				return el;
			}
			el = el.parentElement;
		}
		return null;
	}

	function adminCfg() {
		return window.apdAdmin || {};
	}

	function usesCountryBand(type) {
		return type === 'eu' || type === 'moto';
	}

	function paintedPlate(type) {
		return type === 'eu' || type === 'moto' || type === 'custom' || type === 'color';
	}

	function usesBaseImage(type) {
		return type === 'holder' || type === 'suv';
	}

	function setGroupEnabled(selector, enabled) {
		document.querySelectorAll(selector).forEach(function (el) {
			el.hidden = !enabled;
			el.querySelectorAll('input, select, textarea').forEach(function (input) {
				if (input.hasAttribute('data-apd-media-input')) {
					return;
				}
				input.disabled = !enabled;
			});
		});
	}

	function applyDefaultSize(type) {
		var sizes = adminCfg().defaultSizes || {};
		var pair = sizes[type];
		var widthInput = document.querySelector('[name="apd_format[width]"]');
		var heightInput = document.querySelector('[name="apd_format[height]"]');
		if (!pair || !widthInput || !heightInput) {
			return;
		}
		var idInput = document.querySelector('[name="apd_format[id]"]');
		var currentW = parseInt(widthInput.value, 10);
		var currentH = parseInt(heightInput.value, 10);
		var matchesKnown = false;
		Object.keys(sizes).forEach(function (key) {
			if (sizes[key][0] === currentW && sizes[key][1] === currentH) {
				matchesKnown = true;
			}
		});
		if ((idInput && !idInput.value) || matchesKnown || isNaN(currentW) || isNaN(currentH)) {
			widthInput.value = String(pair[0]);
			heightInput.value = String(pair[1]);
		}
	}

	function toggleFormatFields() {
		var select = document.querySelector('[data-apd-format-type]');
		if (!select) {
			return;
		}
		var type = select.value;
		applyDefaultSize(type);
		setGroupEnabled('.apd-image-fields', usesBaseImage(type));
		setGroupEnabled('.apd-eu-fields, .apd-border-fields', paintedPlate(type));
		setGroupEnabled('.apd-band-fields, .apd-band-box-fields', usesCountryBand(type));
		document.querySelectorAll('.apd-us-only').forEach(function (el) {
			el.hidden = type !== 'us';
		});
		document.querySelectorAll('.apd-holder-only').forEach(function (el) {
			el.hidden = type !== 'holder';
		});
		var heading = document.querySelector('[data-apd-image-heading]');
		var help = document.querySelector('[data-apd-image-help]');
		var cfg = adminCfg();
		if (heading) {
			heading.textContent = type === 'suv'
				? (cfg.suvImageLabel || 'Plate graphic')
				: (cfg.holderImageLabel || 'Holder photo');
		}
		if (help) {
			help.textContent = type === 'suv'
				? (cfg.suvImageHelp || '')
				: (cfg.holderImageHelp || '');
		}
		syncFrameFields();
		updateFormatStage();
	}

	function formatMm() {
		var widthInput = document.querySelector('[name="apd_format[width]"]');
		var heightInput = document.querySelector('[name="apd_format[height]"]');
		return {
			width: widthInput ? parseInt(widthInput.value, 10) || 520 : 520,
			height: heightInput ? parseInt(heightInput.value, 10) || 110 : 110
		};
	}

	function currentFormatType() {
		var typeSelect = document.querySelector('[data-apd-format-type]');
		return typeSelect ? typeSelect.value : '';
	}

	function wantsNoFrame() {
		var cb = document.querySelector('[data-apd-no-frame]');
		return !!(cb && cb.checked);
	}

	function syncFrameFields() {
		var wrap = document.querySelector('[data-apd-frame-controls]');
		var off = wantsNoFrame();
		if (wrap) {
			wrap.classList.toggle('is-disabled', off);
			wrap.querySelectorAll('input').forEach(function (input) {
				input.readOnly = off;
				input.tabIndex = off ? -1 : 0;
			});
		}
		applyFrameOverlay();
	}

	function applyFrameOverlay() {
		var box = document.querySelector('[data-apd-frame-box]');
		var fill = document.querySelector('[data-apd-frame-fill]');
		var stage = document.querySelector('[data-apd-text-box-stage]');
		var type = currentFormatType();
		var mm = formatMm();
		var bwInput = document.querySelector('[name="apd_format[border_width]"]');
		var colorInput = document.querySelector('[name="apd_format[border_color]"]');
		var bw = bwInput ? parseInt(bwInput.value, 10) : 0;
		if (isNaN(bw) || bw < 0) {
			bw = 0;
		}
		var hide = !paintedPlate(type) || wantsNoFrame() || bw <= 0;
		if (box) {
			box.hidden = hide;
		}
		if (fill) {
			fill.hidden = hide;
		}
		if (hide || !stage) {
			return;
		}
		var ix = (bw / mm.width) * 100;
		var iy = (bw / mm.height) * 100;
		var px = (bw / mm.width) * stage.clientWidth;
		var color = colorInput && colorInput.value ? colorInput.value : '#000000';
		stage.style.setProperty('--apd-frame-px', String(Math.max(0, px)) + 'px');
		stage.style.setProperty('--apd-frame-color', color);
		if (box) {
			box.style.left = ix + '%';
			box.style.top = iy + '%';
			box.style.width = (100 - ix * 2) + '%';
			box.style.height = (100 - iy * 2) + '%';
			box.style.borderColor = color;
		}
	}

	function updateFormatStage() {
		var stage = document.querySelector('[data-apd-text-box-stage]');
		if (!stage) {
			return;
		}
		var type = currentFormatType();
		var mm = formatMm();
		if (mm.width > 0 && mm.height > 0) {
			stage.style.aspectRatio = String(mm.width) + ' / ' + String(mm.height);
		}
		var schematic = stage.querySelector('[data-apd-text-box-schematic]');
		var img = stage.querySelector('[data-apd-text-box-image]');
		var band = stage.querySelector('[data-apd-band-box]');
		var sample = stage.querySelector('[data-apd-text-box-sample]');
		var hasImage = !!(img && img.getAttribute('src'));
		if (usesBaseImage(type) && hasImage) {
			if (schematic) {
				schematic.hidden = true;
			}
			if (img) {
				img.hidden = false;
			}
			if (band) {
				band.hidden = true;
			}
			applyFrameOverlay();
			syncSampleSize();
			return;
		}
		if (schematic) {
			schematic.hidden = false;
		}
		if (img) {
			img.hidden = true;
		}
		if (band) {
			band.hidden = !usesCountryBand(type);
		}
		if (sample && document.querySelector('[data-apd-format-type]')) {
			sample.textContent = paintedPlate(type) ? 'CA 0909 BX' : (sample.getAttribute('data-fallback') || sample.textContent || 'TEXT');
		}
		applyFrameOverlay();
		applyBandOverlay(readBandBox());
		syncSampleSize();
	}

	function syncSampleSize() {
		var el = document.querySelector('[data-apd-text-box]');
		var sample = document.querySelector('[data-apd-text-box-sample]');
		if (!el || !sample) {
			return;
		}
		var height = el.clientHeight;
		if (height > 0) {
			sample.style.fontSize = String(Math.max(10, Math.floor(height * 0.72))) + 'px';
		}
	}

	function openMedia(button) {
		if (typeof wp === 'undefined' || !wp.media) {
			return;
		}
		var field = closest(button, '.apd-media-field');
		if (!field) {
			return;
		}
		var input = field.querySelector('[data-apd-media-input]');
		var preview = field.querySelector('.apd-media-preview');
		var kind = button.getAttribute('data-apd-media') || 'image';
		var frame = wp.media({
			title: kind === 'font'
				? (window.apdAdmin && window.apdAdmin.selectFont) || 'Select WOFF2 font'
				: (window.apdAdmin && window.apdAdmin.selectImage) || 'Select image',
			multiple: false,
			library: kind === 'font' ? { type: '' } : { type: 'image' }
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			if (input) {
				input.value = attachment.id;
			}
			if (!preview) {
				return;
			}
			preview.textContent = '';
			if (attachment.type === 'image' && attachment.url) {
				var img = document.createElement('img');
				var src = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) || attachment.url;
				img.src = src;
				img.alt = '';
				preview.appendChild(img);
			} else {
				preview.textContent = attachment.filename || '';
			}

			if (field.querySelector('[name="apd_design[image_id]"]') || field.querySelector('[name="apd_format[base_image_id]"]')) {
				var overlayImg = document.querySelector('[data-apd-text-box-image]');
				var wrap = document.querySelector('[data-apd-text-box-wrap]');
				if (overlayImg) {
					overlayImg.src = attachment.url;
					overlayImg.hidden = false;
				}
				if (wrap) {
					wrap.hidden = false;
				}
				updateFormatStage();
			}
		});

		frame.open();
	}

	function addZone() {
		var list = document.querySelector('[data-apd-zone-list]');
		var template = document.getElementById('apd-zone-template');
		if (!list || !template) {
			return;
		}
		var index = list.querySelectorAll('.apd-zone-row').length;
		var html = template.innerHTML.replace(/__i__/g, String(index));
		var wrap = document.createElement('div');
		wrap.innerHTML = html.trim();
		list.appendChild(wrap.firstElementChild);
	}

	function toggleSwatchRadius() {
		var checked = document.querySelector('[data-apd-swatch-shape]:checked');
		var input = document.querySelector('[data-apd-swatch-radius]');
		if (!input) {
			return;
		}
		input.disabled = !checked || checked.value !== 'rounded';
		updateSwatchPreview();
	}

	function updateSwatchPreview() {
		var preview = document.querySelector('[data-apd-swatch-preview]');
		if (!preview) {
			return;
		}
		var sizeInput = document.querySelector('[data-apd-swatch-size]');
		var radiusInput = document.querySelector('[data-apd-swatch-radius]');
		var borderInput = document.querySelector('[data-apd-swatch-border]');
		var checked = document.querySelector('[data-apd-swatch-shape]:checked');
		var size = sizeInput ? parseInt(sizeInput.value, 10) : 28;
		if (isNaN(size) || size < 10) {
			size = 10;
		}
		if (size > 100) {
			size = 100;
		}
		var shape = checked ? checked.value : 'circle';
		var radius = '50%';
		if (shape === 'square') {
			radius = '0';
		} else if (shape === 'rounded') {
			var px = radiusInput ? parseInt(radiusInput.value, 10) : 4;
			if (isNaN(px) || px < 0) {
				px = 0;
			}
			if (px > 50) {
				px = 50;
			}
			radius = String(px) + 'px';
		}
		preview.style.setProperty('--apd-swatch-size', String(size) + 'px');
		preview.style.setProperty('--apd-swatch-radius', radius);
		if (borderInput && borderInput.value) {
			preview.style.setProperty('--apd-swatch-border', borderInput.value);
		}
	}

	function toggleDesignFormats() {
		var all = document.querySelector('[data-apd-all-us]');
		if (!all) {
			return;
		}
		document.querySelectorAll('[data-apd-us-format]').forEach(function (el) {
			el.disabled = all.checked;
		});
	}

	document.addEventListener('change', function (event) {
		if (event.target && event.target.hasAttribute('data-apd-format-type')) {
			toggleFormatFields();
		}
		if (event.target && event.target.hasAttribute('data-apd-all-us')) {
			toggleDesignFormats();
		}
		if (event.target && (event.target.hasAttribute('data-apd-swatch-shape') || event.target.hasAttribute('data-apd-swatch-size') || event.target.hasAttribute('data-apd-swatch-radius') || event.target.hasAttribute('data-apd-swatch-border'))) {
			toggleSwatchRadius();
		}
		if (event.target && event.target.getAttribute('name') === 'apd_format[band_side]') {
			snapBandToSide(event.target.value);
			updateFormatStage();
		}
		if (event.target && (event.target.getAttribute('name') === 'apd_format[width]' || event.target.getAttribute('name') === 'apd_format[height]')) {
			updateFormatStage();
		}
		if (event.target && event.target.hasAttribute('data-apd-no-frame')) {
			syncFrameFields();
		}
		if (event.target && (event.target.getAttribute('name') === 'apd_format[border_width]' || event.target.getAttribute('name') === 'apd_format[border_color]')) {
			applyFrameOverlay();
		}
	});

	document.addEventListener('input', function (event) {
		if (event.target && (event.target.hasAttribute('data-apd-swatch-size') || event.target.hasAttribute('data-apd-swatch-radius') || event.target.hasAttribute('data-apd-swatch-border'))) {
			updateSwatchPreview();
		}
		if (event.target && (event.target.getAttribute('name') === 'apd_format[width]' || event.target.getAttribute('name') === 'apd_format[height]' || event.target.getAttribute('name') === 'apd_format[border_width]' || event.target.getAttribute('name') === 'apd_format[border_color]')) {
			updateFormatStage();
		}
	});

	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!target) {
			return;
		}

		if (closest(target, '[data-apd-media]')) {
			event.preventDefault();
			openMedia(closest(target, '[data-apd-media]'));
			return;
		}

		if (closest(target, '[data-apd-media-clear]')) {
			event.preventDefault();
			var field = closest(target, '.apd-media-field');
			if (!field) {
				return;
			}
			var input = field.querySelector('[data-apd-media-input]');
			var preview = field.querySelector('.apd-media-preview');
			if (input) {
				input.value = '';
			}
			if (preview) {
				preview.innerHTML = '';
			}
			if (field.querySelector('[name="apd_design[image_id]"]') || field.querySelector('[name="apd_format[base_image_id]"]')) {
				var wrap = document.querySelector('[data-apd-text-box-wrap]');
				var overlayImg = document.querySelector('[data-apd-text-box-image]');
				if (overlayImg) {
					overlayImg.removeAttribute('src');
					overlayImg.hidden = true;
				}
				if (wrap && field.querySelector('[name="apd_design[image_id]"]')) {
					wrap.hidden = true;
				}
				updateFormatStage();
			}
			return;
		}

		if (closest(target, '[data-apd-add-zone]')) {
			event.preventDefault();
			addZone();
			return;
		}

		if (closest(target, '.apd-remove-row')) {
			event.preventDefault();
			var row = closest(target, '.apd-zone-row');
			if (row && row.parentNode) {
				row.parentNode.removeChild(row);
			}
			return;
		}

		var del = closest(target, '.apd-js-confirm');
		if (del) {
			var message = (window.apdAdmin && window.apdAdmin.confirmDelete) || 'Delete this item?';
			if (!window.confirm(message)) {
				event.preventDefault();
			}
		}
	});

	function clampBox(box) {
		var next = {
			x: box.x,
			y: box.y,
			width: box.width,
			height: box.height,
			align: box.align || 'center',
			valign: box.valign || 'middle'
		};
		if (next.width < 5) {
			next.width = 5;
		}
		if (next.height < 5) {
			next.height = 5;
		}
		if (next.width > 100) {
			next.width = 100;
		}
		if (next.height > 100) {
			next.height = 100;
		}
		if (next.x < 0) {
			next.x = 0;
		}
		if (next.y < 0) {
			next.y = 0;
		}
		if (next.x + next.width > 100) {
			next.x = Math.max(0, 100 - next.width);
		}
		if (next.y + next.height > 100) {
			next.y = Math.max(0, 100 - next.height);
		}
		next.x = Math.round(next.x * 10) / 10;
		next.y = Math.round(next.y * 10) / 10;
		next.width = Math.round(next.width * 10) / 10;
		next.height = Math.round(next.height * 10) / 10;
		return next;
	}

	function readTextBox() {
		function num(name, fallback) {
			var el = document.querySelector('[data-apd-text-box-input="' + name + '"]');
			var value = el ? parseFloat(el.value) : fallback;
			return isNaN(value) ? fallback : value;
		}
		var alignEl = document.querySelector('[data-apd-text-box-input="align"]');
		var valignEl = document.querySelector('[data-apd-text-box-input="valign"]');
		return clampBox({
			x: num('x', 9),
			y: num('y', 34),
			width: num('width', 82),
			height: num('height', 42),
			align: alignEl ? alignEl.value : 'center',
			valign: valignEl ? valignEl.value : 'middle'
		});
	}

	function writeTextBox(box) {
		['x', 'y', 'width', 'height', 'align', 'valign'].forEach(function (name) {
			var el = document.querySelector('[data-apd-text-box-input="' + name + '"]');
			if (el) {
				el.value = String(box[name]);
			}
		});
		applyTextBoxOverlay(box);
	}

	function applyTextBoxOverlay(box) {
		var el = document.querySelector('[data-apd-text-box]');
		var sample = document.querySelector('[data-apd-text-box-sample]');
		if (!el) {
			return;
		}
		el.style.left = String(box.x) + '%';
		el.style.top = String(box.y) + '%';
		el.style.width = String(box.width) + '%';
		el.style.height = String(box.height) + '%';
		if (sample) {
			sample.style.justifyContent = box.align === 'left' ? 'flex-start' : (box.align === 'right' ? 'flex-end' : 'center');
			sample.style.alignItems = box.valign === 'top' ? 'flex-start' : (box.valign === 'bottom' ? 'flex-end' : 'center');
		}
		syncSampleSize();
	}

	function subtractBandFromRegion(region, band) {
		var mid = band.x + (band.width / 2);
		var right = region.x + region.width;
		if (mid < 50) {
			var x = Math.max(region.x, band.x + band.width);
			return {
				x: x,
				y: region.y,
				width: Math.max(5, right - x),
				height: region.height
			};
		}
		return {
			x: region.x,
			y: region.y,
			width: Math.max(5, band.x - region.x),
			height: region.height
		};
	}

	function computeTextAreaRegion() {
		var type = currentFormatType();
		var mm = formatMm();
		var region = { x: 0, y: 0, width: 100, height: 100 };
		if (type === 'holder') {
			return { x: 0, y: 82, width: 100, height: 18 };
		}
		if (paintedPlate(type) && !wantsNoFrame()) {
			var bwInput = document.querySelector('[name="apd_format[border_width]"]');
			var bw = bwInput ? parseInt(bwInput.value, 10) : 0;
			if (isNaN(bw) || bw < 0) {
				bw = 0;
			}
			if (bw > 0 && mm.width > 0 && mm.height > 0) {
				var ix = (bw / mm.width) * 100;
				var iy = (bw / mm.height) * 100;
				region = {
					x: ix,
					y: iy,
					width: 100 - ix * 2,
					height: 100 - iy * 2
				};
			}
		}
		if (usesCountryBand(type)) {
			region = subtractBandFromRegion(region, readBandBox());
		}
		if (region.width < 5) {
			region.width = 5;
		}
		if (region.height < 5) {
			region.height = 5;
		}
		return region;
	}

	function centerBoxInRegion(box, region) {
		var next = {
			x: box.x,
			y: box.y,
			width: box.width,
			height: box.height,
			align: box.align,
			valign: box.valign
		};
		var x = region.x + ((region.width - next.width) / 2);
		var y = region.y + ((region.height - next.height) / 2);
		var minX = region.x;
		var maxX = region.x + region.width - next.width;
		var minY = region.y;
		var maxY = region.y + region.height - next.height;
		if (maxX < minX) {
			x = region.x;
		} else {
			x = Math.max(minX, Math.min(maxX, x));
		}
		if (maxY < minY) {
			y = region.y;
		} else {
			y = Math.max(minY, Math.min(maxY, y));
		}
		next.x = Math.round(x * 10) / 10;
		next.y = Math.round(y * 10) / 10;
		return clampBox(next);
	}

	function bindTextBoxEditor() {
		var stage = document.querySelector('[data-apd-text-box-stage]');
		var boxEl = document.querySelector('[data-apd-text-box]');
		if (!stage || !boxEl) {
			return;
		}

		applyTextBoxOverlay(readTextBox());

		document.addEventListener('click', function (event) {
			var btn = event.target && event.target.closest('[data-apd-center-text-box]');
			if (!btn) {
				return;
			}
			event.preventDefault();
			writeTextBox(centerBoxInRegion(readTextBox(), computeTextAreaRegion()));
		});

		document.addEventListener('input', function (event) {
			if (event.target && event.target.hasAttribute('data-apd-text-box-input')) {
				writeTextBox(readTextBox());
			}
		});
		document.addEventListener('change', function (event) {
			if (event.target && event.target.hasAttribute('data-apd-text-box-input')) {
				writeTextBox(readTextBox());
			}
		});

		var drag = null;

		function startDrag(event, handle) {
			event.preventDefault();
			var rect = stage.getBoundingClientRect();
			var box = readTextBox();
			drag = {
				handle: handle,
				startX: event.clientX,
				startY: event.clientY,
				rect: rect,
				box: box
			};
			document.addEventListener('pointermove', onMove);
			document.addEventListener('pointerup', onUp);
		}

		function onMove(event) {
			if (!drag) {
				return;
			}
			var dx = ((event.clientX - drag.startX) / drag.rect.width) * 100;
			var dy = ((event.clientY - drag.startY) / drag.rect.height) * 100;
			var next = {
				x: drag.box.x,
				y: drag.box.y,
				width: drag.box.width,
				height: drag.box.height,
				align: drag.box.align,
				valign: drag.box.valign
			};
			if (!drag.handle) {
				next.x = drag.box.x + dx;
				next.y = drag.box.y + dy;
			} else {
				if (drag.handle.indexOf('w') !== -1) {
					next.x = drag.box.x + dx;
					next.width = drag.box.width - dx;
				}
				if (drag.handle.indexOf('e') !== -1) {
					next.width = drag.box.width + dx;
				}
				if (drag.handle.indexOf('n') !== -1) {
					next.y = drag.box.y + dy;
					next.height = drag.box.height - dy;
				}
				if (drag.handle.indexOf('s') !== -1) {
					next.height = drag.box.height + dy;
				}
			}
			writeTextBox(clampBox(next));
		}

		function onUp() {
			document.removeEventListener('pointermove', onMove);
			document.removeEventListener('pointerup', onUp);
			drag = null;
		}

		boxEl.addEventListener('pointerdown', function (event) {
			if (event.target && event.target.getAttribute('data-apd-handle')) {
				startDrag(event, event.target.getAttribute('data-apd-handle'));
				return;
			}
			startDrag(event, '');
		});
	}

	function clampBandBox(box) {
		var next = {
			x: box.x,
			y: box.y,
			width: box.width,
			height: box.height
		};
		if (next.width < 4) {
			next.width = 4;
		}
		if (next.width > 25) {
			next.width = 25;
		}
		if (next.height < 40) {
			next.height = 40;
		}
		if (next.height > 100) {
			next.height = 100;
		}
		if (next.x < 0) {
			next.x = 0;
		}
		if (next.y < 0) {
			next.y = 0;
		}
		if (next.x + next.width > 100) {
			next.x = Math.max(0, 100 - next.width);
		}
		if (next.y + next.height > 100) {
			next.y = Math.max(0, 100 - next.height);
		}
		next.x = Math.round(next.x * 10) / 10;
		next.y = Math.round(next.y * 10) / 10;
		next.width = Math.round(next.width * 10) / 10;
		next.height = Math.round(next.height * 10) / 10;
		return next;
	}

	function readBandBox() {
		function num(name, fallback) {
			var el = document.querySelector('[data-apd-band-box-input="' + name + '"]');
			var value = el ? parseFloat(el.value) : fallback;
			return isNaN(value) ? fallback : value;
		}
		return clampBandBox({
			x: num('x', 0),
			y: num('y', 0),
			width: num('width', 7.7),
			height: num('height', 100)
		});
	}

	function writeBandBox(box, skipSide) {
		['x', 'y', 'width', 'height'].forEach(function (name) {
			var el = document.querySelector('[data-apd-band-box-input="' + name + '"]');
			if (el) {
				el.value = String(box[name]);
			}
		});
		var ratioInput = document.querySelector('[data-apd-band-ratio], [name="apd_format[band_ratio]"]');
		if (ratioInput) {
			ratioInput.value = String(Math.round((box.width / 100) * 10000) / 10000);
		}
		if (!skipSide) {
			var sideInput = document.querySelector('[name="apd_format[band_side]"]');
			if (sideInput) {
				sideInput.value = (box.x + box.width / 2) >= 50 ? 'right' : 'left';
			}
		}
		applyBandOverlay(box);
	}

	function applyBandOverlay(box) {
		var el = document.querySelector('[data-apd-band-box]');
		if (!el || !box) {
			return;
		}
		el.style.left = String(box.x) + '%';
		el.style.top = String(box.y) + '%';
		el.style.width = String(box.width) + '%';
		el.style.height = String(box.height) + '%';
		el.hidden = !usesCountryBand(currentFormatType());
	}

	function snapBandToSide(side) {
		var box = readBandBox();
		if (side === 'right') {
			box.x = Math.max(0, 100 - box.width);
		} else {
			box.x = 0;
		}
		writeBandBox(box, true);
	}

	function bindBandBoxEditor() {
		var stage = document.querySelector('[data-apd-text-box-stage]');
		var boxEl = document.querySelector('[data-apd-band-box]');
		if (!stage || !boxEl) {
			return;
		}

		applyBandOverlay(readBandBox());

		document.addEventListener('input', function (event) {
			if (event.target && event.target.hasAttribute('data-apd-band-box-input')) {
				writeBandBox(readBandBox());
			}
		});
		document.addEventListener('change', function (event) {
			if (event.target && event.target.hasAttribute('data-apd-band-box-input')) {
				writeBandBox(readBandBox());
			}
		});

		var drag = null;

		function startDrag(event, handle) {
			event.preventDefault();
			event.stopPropagation();
			var rect = stage.getBoundingClientRect();
			drag = {
				handle: handle,
				startX: event.clientX,
				startY: event.clientY,
				rect: rect,
				box: readBandBox()
			};
			document.addEventListener('pointermove', onMove);
			document.addEventListener('pointerup', onUp);
		}

		function onMove(event) {
			if (!drag) {
				return;
			}
			var dx = ((event.clientX - drag.startX) / drag.rect.width) * 100;
			var dy = ((event.clientY - drag.startY) / drag.rect.height) * 100;
			var next = {
				x: drag.box.x,
				y: drag.box.y,
				width: drag.box.width,
				height: drag.box.height
			};
			if (!drag.handle) {
				next.x = drag.box.x + dx;
				next.y = drag.box.y + dy;
			} else {
				if (drag.handle.indexOf('w') !== -1) {
					next.x = drag.box.x + dx;
					next.width = drag.box.width - dx;
				}
				if (drag.handle.indexOf('e') !== -1) {
					next.width = drag.box.width + dx;
				}
				if (drag.handle.indexOf('n') !== -1) {
					next.y = drag.box.y + dy;
					next.height = drag.box.height - dy;
				}
				if (drag.handle.indexOf('s') !== -1) {
					next.height = drag.box.height + dy;
				}
			}
			writeBandBox(clampBandBox(next));
		}

		function onUp() {
			document.removeEventListener('pointermove', onMove);
			document.removeEventListener('pointerup', onUp);
			drag = null;
		}

		boxEl.addEventListener('pointerdown', function (event) {
			if (event.target && event.target.getAttribute('data-apd-band-handle')) {
				startDrag(event, event.target.getAttribute('data-apd-band-handle'));
				return;
			}
			startDrag(event, '');
		});
	}

	function bindFrameEditor() {
		var box = document.querySelector('[data-apd-frame-box]');
		var stage = document.querySelector('[data-apd-text-box-stage]');
		if (!box || !stage) {
			return;
		}
		var drag = null;

		box.addEventListener('pointerdown', function (event) {
			var handle = event.target && event.target.getAttribute('data-apd-frame-handle');
			if (!handle || wantsNoFrame()) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			var bwInput = document.querySelector('[name="apd_format[border_width]"]');
			drag = {
				handle: handle,
				startX: event.clientX,
				startY: event.clientY,
				rect: stage.getBoundingClientRect(),
				startBorder: bwInput ? parseInt(bwInput.value, 10) || 0 : 0,
				mm: formatMm(),
				input: bwInput
			};
			document.addEventListener('pointermove', onFrameMove);
			document.addEventListener('pointerup', onFrameUp);
		});

		function onFrameMove(event) {
			if (!drag || !drag.input) {
				return;
			}
			var dx = ((event.clientX - drag.startX) / drag.rect.width) * drag.mm.width;
			var dy = ((event.clientY - drag.startY) / drag.rect.height) * drag.mm.height;
			var delta = 0;
			var axes = 0;
			if (drag.handle.indexOf('e') !== -1) {
				delta -= dx;
				axes += 1;
			}
			if (drag.handle.indexOf('w') !== -1) {
				delta += dx;
				axes += 1;
			}
			if (drag.handle.indexOf('s') !== -1) {
				delta -= dy;
				axes += 1;
			}
			if (drag.handle.indexOf('n') !== -1) {
				delta += dy;
				axes += 1;
			}
			if (axes > 1) {
				delta = delta / axes;
			}
			var next = Math.round(drag.startBorder + delta);
			if (next < 0) {
				next = 0;
			}
			if (next > 40) {
				next = 40;
			}
			drag.input.value = String(next);
			applyFrameOverlay();
		}

		function onFrameUp() {
			document.removeEventListener('pointermove', onFrameMove);
			document.removeEventListener('pointerup', onFrameUp);
			drag = null;
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady);
	} else {
		onReady();
	}

	function onReady() {
		toggleFormatFields();
		toggleProductColorFields();
		toggleDesignFormats();
		toggleSwatchRadius();
		bindTextBoxEditor();
		bindBandBoxEditor();
		bindFrameEditor();
		syncFrameFields();
		updateFormatStage();
		document.querySelectorAll('form.apd-form [type="submit"]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				toggleFormatFields();
			});
		});
		window.addEventListener('resize', function () {
			applyFrameOverlay();
			syncSampleSize();
		});
	}

	function toggleProductColorFields() {
		var select = document.getElementById('apd_format_id');
		var box = document.querySelector('[data-apd-color-fields]');
		if (!select || !box) {
			return;
		}
		var option = select.options[select.selectedIndex];
		var type = option ? option.getAttribute('data-apd-type') || '' : '';
		var admin = window.apdAdmin || {};
		var caps = (admin.colorCaps && admin.colorCaps[type]) || [];
		var noFrame = option && option.getAttribute('data-apd-no-frame') === '1';
		box.hidden = !type;
		box.querySelectorAll('[data-apd-color-cap]').forEach(function (row) {
			var field = row.getAttribute('data-apd-color-cap');
			var allowed = caps.indexOf(field) !== -1 && !(noFrame && field === 'border');
			row.hidden = !allowed;
			row.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
				if (!allowed) {
					input.checked = false;
				}
			});
		});
		var bgLabel = box.querySelector('[data-apd-background-label]');
		if (bgLabel) {
			bgLabel.textContent = type === 'holder'
				? (admin.stripColorLabel || 'Text background')
				: (admin.plateColorLabel || 'Plate color');
		}
	}

	document.addEventListener('change', function (event) {
		if (event.target && event.target.id === 'apd_format_id') {
			toggleProductColorFields();
		}
	});
})();
