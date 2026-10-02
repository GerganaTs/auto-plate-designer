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

	var TEXT_FRAME_GAP_MM = 8;

	function adminCfg() {
		return window.apdAdmin || {};
	}

	function typeCaps(type) {
		var map = adminCfg().typeCapabilities || {};
		if (type && map[type]) {
			return map[type];
		}
		return { painted: false, countryBand: false, baseImage: false, plateDesigns: false };
	}

	function usesCountryBand(type) {
		return !!typeCaps(type).countryBand;
	}

	function paintedPlate(type) {
		return !!typeCaps(type).painted;
	}

	function usesBaseImage(type) {
		return !!typeCaps(type).baseImage;
	}

	function usesTwoRows(type) {
		return !!typeCaps(type).twoRow;
	}

	function alignToFlex(align) {
		if (align === 'left') {
			return 'flex-start';
		}
		if (align === 'right') {
			return 'flex-end';
		}
		if (align === 'justify') {
			return 'space-between';
		}
		return 'center';
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

	function rowAlignValue(which) {
		var name = which === 'numbers' ? 'number_align' : 'letter_align';
		var input = document.querySelector('[data-apd-text-box-input="' + name + '"]');
		return input && input.value ? input.value : 'justify';
	}

	function fillGlyphRow(row, text, align) {
		row.textContent = '';
		row.style.justifyContent = alignToFlex(align);
		Array.from(String(text || '')).forEach(function (ch) {
			var glyph = document.createElement('span');
			glyph.textContent = ch === ' ' ? '\u00a0' : ch;
			row.appendChild(glyph);
		});
	}

	function renderPlateSample() {
		var sample = document.querySelector('[data-apd-text-box-sample]');
		if (!sample || !document.querySelector('[data-apd-format-type]')) {
			return;
		}
		var type = currentFormatType();
		var paintedSample = adminCfg().adminSamplePainted || 'CA 1234';
		if (type === 'suv' || type === 'suv_eu') {
			paintedSample = adminCfg().adminSampleSuv || 'CAAA 1234';
		}
		var text = (paintedPlate(type) || usesTwoRows(type)) ? paintedSample : (sample.getAttribute('data-fallback') || 'TEXT');
		var two = usesTwoRows(type);
		sample.classList.toggle('apd-text-box-sample--rows', two);
		if (!two) {
			if (splitActive()) {
				text = 'CA';
			}
			sample.textContent = text;
			sample.style.alignItems = '';
			sample.style.justifyContent = '';
			return;
		}
		sample.style.alignItems = 'stretch';
		sample.style.justifyContent = 'stretch';
		var rows = splitPlateRows(text);
		var letters = sample.querySelector('[data-apd-text-row="letters"]');
		var numbers = sample.querySelector('[data-apd-text-row="numbers"]');
		if (!letters || !numbers) {
			sample.textContent = '';
			letters = document.createElement('span');
			letters.className = 'apd-text-row';
			letters.setAttribute('data-apd-text-row', 'letters');
			numbers = document.createElement('span');
			numbers.className = 'apd-text-row';
			numbers.setAttribute('data-apd-text-row', 'numbers');
			sample.appendChild(letters);
			sample.appendChild(numbers);
		}
		fillGlyphRow(letters, rows.letters, plateRowAlign(rowAlignValue('letters')));
		fillGlyphRow(numbers, rows.numbers, plateRowAlign(rowAlignValue('numbers')));
		applySuvLetterInset(letters, numbers);
	}

	function applySuvLetterInset(letters, numbers) {
		letters.style.paddingLeft = '0';
		letters.style.paddingRight = '0';
		letters.style.boxSizing = 'border-box';
		numbers.style.paddingLeft = '0';
		numbers.style.paddingRight = '0';
		if (currentFormatType() !== 'suv_eu' || !letters) {
			return;
		}
		var band = readBandBox();
		var text = readTextBox();
		if (!text.width) {
			return;
		}
		var mid = band.x + (band.width / 2);
		if (mid < 50) {
			var overlap = (band.x + band.width) - text.x;
			if (overlap > 0) {
				letters.style.paddingLeft = String(Math.min(70, (overlap / text.width) * 100)) + '%';
			}
		} else {
			var overlapRight = (text.x + text.width) - band.x;
			if (overlapRight > 0) {
				letters.style.paddingRight = String(Math.min(70, (overlapRight / text.width) * 100)) + '%';
			}
		}
	}

	function syncRowStyleButtons() {
		['letters', 'numbers'].forEach(function (which) {
			var value = rowAlignValue(which);
			document.querySelectorAll('[data-apd-row-align="' + which + '"]').forEach(function (btn) {
				var on = btn.getAttribute('data-apd-align') === value;
				btn.classList.toggle('is-active', on);
				btn.setAttribute('aria-pressed', on ? 'true' : 'false');
			});
		});
	}

	function toggleRowStyles(type) {
		var panel = document.querySelector('[data-apd-row-styles]');
		var single = document.querySelector('[data-apd-single-align]');
		var on = usesTwoRows(type);
		if (panel) {
			panel.hidden = !on;
		}
		if (single) {
			single.hidden = on;
		}
		if (on) {
			var boxes = adminCfg().defaultTextBoxes || {};
			var box = boxes[type] || {};
			var letterInput = document.querySelector('[data-apd-text-box-input="letter_align"]');
			var numberInput = document.querySelector('[data-apd-text-box-input="number_align"]');
			var idInput = document.querySelector('[name="apd_format[id]"]');
			if (idInput && !idInput.value) {
				if (letterInput && box.letter_align) {
					letterInput.value = box.letter_align;
				}
				if (numberInput && box.number_align) {
					numberInput.value = box.number_align;
				}
			}
			syncRowStyleButtons();
		}
		renderPlateSample();
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
		var currentW = parseInt(widthInput.value, 10);
		var currentH = parseInt(heightInput.value, 10);
		if (type === 'custom') {
			widthInput.readOnly = false;
			heightInput.readOnly = false;
			if (isNaN(currentW) || isNaN(currentH) || currentW < 1 || currentH < 1) {
				widthInput.value = String(pair[0]);
				heightInput.value = String(pair[1]);
			}
			return;
		}
		widthInput.readOnly = true;
		heightInput.readOnly = true;
		widthInput.value = String(pair[0]);
		heightInput.value = String(pair[1]);
	}

	function resetLayoutForNewFormat(type) {
		var idInput = document.querySelector('[name="apd_format[id]"]');
		if (idInput && idInput.value) {
			return;
		}
		var mm = formatMm();
		if (usesCountryBand(type) && mm.width > 0 && mm.height > 0) {
			var bandHeightMm = type === 'suv_eu' ? mm.height / 2 : mm.height;
			var ratio = (40 / 110) * (bandHeightMm / mm.width);
			var widthPct = Math.round(Math.max(4, Math.min(25, ratio * 100)) * 10) / 10;
			var sideInput = document.querySelector('[name="apd_format[band_side]"]');
			var side = sideInput && sideInput.value === 'right' ? 'right' : 'left';
			writeBandBox({
				x: side === 'right' ? Math.round((100 - widthPct) * 10) / 10 : 0,
				y: 0,
				width: widthPct,
				height: type === 'suv_eu' ? 50 : 100
			}, true);
		}
		var boxes = adminCfg().defaultTextBoxes || {};
		if (boxes[type]) {
			writeTextBox(boxes[type]);
		}
	}

	function usesPaletteSlots(type) {
		var types = adminCfg().paletteSlotTypes || [];
		return types.indexOf(type) !== -1;
	}

	function isHolderType(type) {
		return type === 'holder' || type === 'holder_moto' || type === 'holder_d';
	}

	function isPhotoHolder(type) {
		return type === 'holder_moto' || type === 'holder_d';
	}

	function bundledHolderUrl(type) {
		var cfg = adminCfg();
		if (type === 'holder') {
			return cfg.holderImageUrl || '';
		}
		var photos = cfg.photoHolderImages || {};
		return photos[type] || '';
	}

	function closePaletteMenus(except) {
		document.querySelectorAll('[data-apd-palette-menu]').forEach(function (menu) {
			if (menu === except) {
				return;
			}
			var list = menu.querySelector('.apd-palette-menu__list');
			var button = menu.querySelector('.apd-palette-menu__button');
			if (list) {
				list.hidden = true;
			}
			if (button) {
				button.setAttribute('aria-expanded', 'false');
			}
		});
	}

	function bindPaletteMenus() {
		var root = document.querySelector('[data-apd-palette-slots]');
		if (!root || root.getAttribute('data-apd-menus-bound')) {
			return;
		}
		root.setAttribute('data-apd-menus-bound', '1');
		root.addEventListener('click', function (event) {
			var option = closest(event.target, '[data-apd-palette-option]');
			var toggle = closest(event.target, '.apd-palette-menu__button');
			var menu = closest(event.target, '[data-apd-palette-menu]');
			if (option && menu) {
				var input = menu.querySelector('[data-apd-palette-slot-input]');
				var current = menu.querySelector('.apd-palette-menu__current');
				var label = option.querySelector('.apd-palette-menu__label');
				if (input) {
					input.value = option.getAttribute('value') || '';
				}
				if (current && label) {
					current.textContent = '';
					current.appendChild(label.cloneNode(true));
				}
				menu.querySelectorAll('[data-apd-palette-option]').forEach(function (item) {
					item.setAttribute('aria-selected', item === option ? 'true' : 'false');
				});
				closePaletteMenus(null);
				var note = document.querySelector('[data-apd-palette-slot-error]');
				if (note) {
					note.hidden = true;
				}
				return;
			}
			if (toggle && menu) {
				var list = menu.querySelector('.apd-palette-menu__list');
				var open = list && list.hidden;
				closePaletteMenus(null);
				if (list && open) {
					list.hidden = false;
					toggle.setAttribute('aria-expanded', 'true');
				}
			}
		});
		document.addEventListener('click', function (event) {
			if (!closest(event.target, '[data-apd-palette-menu]')) {
				closePaletteMenus(null);
			}
		});
		var form = document.querySelector('[data-apd-format-form]');
		if (form) {
			form.addEventListener('submit', function (event) {
				var select = document.querySelector('[data-apd-format-type]');
				var type = select ? select.value : '';
				if (!usesPaletteSlots(type)) {
					return;
				}
				var missing = false;
				root.querySelectorAll('[data-apd-palette-slot-input]').forEach(function (input) {
					if (!input.disabled && '' === input.value) {
						missing = true;
					}
				});
				var note = document.querySelector('[data-apd-palette-slot-error]');
				if (!missing) {
					if (note) {
						note.hidden = true;
					}
					return;
				}
				event.preventDefault();
				if (note) {
					note.hidden = false;
				}
			});
		}
	}

	function toggleFormatPalettes(type) {
		var root = document.querySelector('[data-apd-format-palettes]');
		if (!root) {
			return;
		}
		var slots = usesPaletteSlots(type);
		var photo = isPhotoHolder(type);
		var slotRoot = root.querySelector('[data-apd-palette-slots]');
		var checkRoot = root.querySelector('[data-apd-palette-checks]');
		var photoRoot = root.querySelector('[data-apd-photo-palettes]');
		var slotHelp = root.querySelector('[data-apd-palette-slot-help]');
		var checkHelp = root.querySelector('[data-apd-palette-check-help]');
		var photoHelp = root.querySelector('[data-apd-palette-photo-help]');
		var caps = (adminCfg().colorCaps && adminCfg().colorCaps[type]) || [];
		root.hidden = !type;
		if (slotRoot) {
			slotRoot.hidden = !slots;
			var slotKeys = type === 'us' ? ['text'] : ['text', 'background', 'border'];
			slotRoot.querySelectorAll('[data-apd-palette-menu]').forEach(function (menu) {
				var slot = menu.getAttribute('data-apd-palette-slot') || '';
				var on = slots && slotKeys.indexOf(slot) !== -1;
				menu.hidden = !on;
				menu.querySelectorAll('[data-apd-palette-slot-input]').forEach(function (input) {
					input.disabled = !on;
				});
			});
		}
		if (slotHelp) {
			slotHelp.hidden = !slots || type === 'us';
		}
		var usPaletteHelp = root.querySelector('[data-apd-palette-us-help]');
		if (usPaletteHelp) {
			usPaletteHelp.hidden = !(slots && type === 'us');
		}
		if (checkHelp) {
			checkHelp.hidden = slots || photo || !type;
		}
		if (photoHelp) {
			photoHelp.hidden = !photo;
		}
		if (checkRoot) {
			checkRoot.hidden = slots || !type;
		}
		if (photoRoot) {
			photoRoot.hidden = !photo;
			photoRoot.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
				input.disabled = !photo;
			});
		}
		root.querySelectorAll('[data-apd-color-cap]').forEach(function (row) {
			var field = row.getAttribute('data-apd-color-cap');
			var allowed = !slots && !photo && caps.indexOf(field) !== -1;
			row.hidden = !allowed;
			row.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
				input.disabled = !allowed;
				if (!allowed) {
					input.checked = false;
				}
			});
		});
	}

	function toggleFormatFields(options) {
		var select = document.querySelector('[data-apd-format-type]');
		if (!select) {
			return;
		}
		var type = select.value;
		var plateNote = document.querySelector('[data-apd-holder-plate]');
		if (plateNote) {
			var plateLabels = adminCfg().holderPlateLabels || {};
			var holder = isHolderType(type);
			plateNote.hidden = !holder;
			plateNote.textContent = holder && plateLabels[type] ? plateLabels[type] : '';
		}
		toggleFormatPalettes(type);
		var hasType = type !== '';
		document.querySelectorAll('[data-apd-format-details]').forEach(function (el) {
			el.hidden = !hasType;
		});
		var submit = document.querySelector('[data-apd-format-submit]');
		if (submit) {
			submit.hidden = !hasType;
		}
		var studio = document.querySelector('[data-apd-plate-studio]');
		if (studio) {
			studio.hidden = !hasType;
		}
		var wrap = document.querySelector('[data-apd-text-box-wrap]');
		if (wrap) {
			wrap.hidden = !hasType;
		}
		if (!hasType) {
			setGroupEnabled('.apd-image-fields', false);
			setGroupEnabled('.apd-eu-fields, .apd-border-fields', false);
			setGroupEnabled('.apd-band-fields, .apd-band-box-fields', false);
			return;
		}
		applyDefaultSize(type);
		if (options && options.resetLayout) {
			resetLayoutForNewFormat(type);
		}
		setGroupEnabled('.apd-image-fields', usesBaseImage(type));
		setGroupEnabled('.apd-eu-fields', paintedPlate(type));
		setGroupEnabled('.apd-border-fields', !isHolderType(type));
		setGroupEnabled('.apd-band-fields, .apd-band-box-fields', usesCountryBand(type));
		toggleRowStyles(type);
		document.querySelectorAll('.apd-us-only').forEach(function (el) {
			el.hidden = type !== 'us';
		});
		document.querySelectorAll('.apd-holder-only').forEach(function (el) {
			el.hidden = !isHolderType(type);
		});
		document.querySelectorAll('.apd-canvas-fields').forEach(function (el) {
			el.hidden = isHolderType(type);
		});
		document.querySelectorAll('.apd-text-help-default').forEach(function (el) {
			el.hidden = isHolderType(type);
		});
		var heading = document.querySelector('[data-apd-image-heading]');
		var help = document.querySelector('[data-apd-image-help]');
		var cfg = adminCfg();
		if (heading) {
			if (type === 'us') {
				heading.textContent = cfg.usImageLabel || 'Plate graphic';
			} else if (type === 'suv') {
				heading.textContent = cfg.suvImageLabel || 'Plate graphic';
			} else {
				heading.textContent = cfg.holderImageLabel || 'Holder photo';
			}
		}
		if (help) {
			if (type === 'us') {
				help.hidden = false;
				help.textContent = cfg.usImageHelp || '';
			} else if (type === 'suv') {
				help.hidden = false;
				help.textContent = cfg.suvImageHelp || '';
			} else {
				help.hidden = true;
				help.textContent = cfg.holderImageHelp || '';
			}
		}
		toggleSuvCharLimits(type);
		document.querySelectorAll('[data-apd-wrap]').forEach(function (row) {
			var on = type === 'custom';
			row.hidden = !on;
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = !on;
			});
		});
		document.querySelectorAll('[data-apd-custom-size]').forEach(function (note) {
			note.hidden = type !== 'custom';
		});
		syncFrameFields();
		updateFormatStage();
		syncSplitPanels();
		syncHolderMetricMode();
	}

	function syncHolderMetricMode() {
		var type = currentFormatType();
		var holder = isHolderType(type);
		var stage = document.querySelector('[data-apd-text-box-stage]');
		var stored = stage ? (stage.getAttribute('data-apd-strip-metrics-type') || '') : '';
		if (stripMetricsActive() && stored && stored !== type) {
			var oldStrip = holderStrip(stored);
			var fields = readTextBoxFields();
			var canvas = oldStrip ? stripToCanvas(fields, oldStrip) : fields;
			setStripMetricFlag(false);
			['x', 'y', 'width', 'height'].forEach(function (name) {
				var input = document.querySelector('[data-apd-text-box-input="' + name + '"]');
				if (input && canvas[name] !== undefined && canvas[name] !== null) {
					input.value = String(canvas[name]);
				}
			});
		}
		if (holder && !stripMetricsActive()) {
			writeTextBox(readTextBox());
			return;
		}
		if (!holder && stripMetricsActive()) {
			var canvas = readTextBox();
			setStripMetricFlag(false);
			['x', 'y', 'width', 'height', 'align', 'valign'].forEach(function (name) {
				var el = document.querySelector('[data-apd-text-box-input="' + name + '"]');
				if (el && canvas[name] !== undefined && canvas[name] !== null) {
					el.value = String(canvas[name]);
				}
			});
			applyTextBoxOverlay(clampBox(canvas));
		}
	}

	function toggleSuvCharLimits(type) {
		var suv = usesTwoRows(type);
		var holder = isHolderType(type);
		var hideSingle = suv || !type || (type === 'us' && splitActive());
		document.querySelectorAll('[data-apd-max-single]').forEach(function (row) {
			row.hidden = hideSingle;
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = hideSingle;
				if (holder && input.getAttribute('data-apd-holder-max') !== '1') {
					if (!input.hasAttribute('data-apd-holder-cleared')) {
						if (!input.hasAttribute('data-apd-prev-max')) {
							input.setAttribute('data-apd-prev-max', input.value);
						}
						input.value = '';
						input.setAttribute('data-apd-holder-cleared', '1');
					}
				} else if (!holder) {
					input.removeAttribute('data-apd-holder-cleared');
					if (input.hasAttribute('data-apd-prev-max') && !input.value) {
						input.value = input.getAttribute('data-apd-prev-max');
					}
					input.removeAttribute('data-apd-prev-max');
				}
			});
		});
		document.querySelectorAll('[data-apd-max-rows]').forEach(function (row) {
			row.hidden = !suv;
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = !suv;
				if (suv && (!input.value || input.value === '0')) {
					input.value = input.id === 'apd_format_row_2_max' ? '4' : '5';
				}
			});
		});
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
		var hide = '' === type || isHolderType(type) || wantsNoFrame() || bw <= 0;
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

	function isDesignEditor() {
		return !!document.querySelector('[name="apd_design[image_id]"]');
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

	function resetDesignImage(img) {
		img._apdArt = null;
		img._apdArtSrc = '';
		img.style.left = '';
		img.style.top = '';
		img.style.width = '';
		img.style.height = '';
	}

	function fitDesignArtwork(img) {
		var stage = img.closest('[data-apd-design-stage]');
		if (!stage) {
			return;
		}
		function place() {
			if (img._apdArtSrc !== img.src) {
				img._apdArt = null;
				img._apdArtSrc = img.src;
			}
			var box = artworkSourceBox(img);
			var sw = stage.clientWidth;
			var sh = stage.clientHeight;
			if (!img.naturalWidth || !img.naturalHeight || !box.w || !box.h) {
				return;
			}
			if (!sw || !sh) {
				if (!img._apdFitWait) {
					img._apdFitWait = true;
					window.requestAnimationFrame(function () {
						img._apdFitWait = false;
						place();
					});
				}
				return;
			}
			var scale = Math.min(sw / box.w, sh / box.h);
			img.style.width = (img.naturalWidth * scale) + 'px';
			img.style.height = (img.naturalHeight * scale) + 'px';
			img.style.left = (((sw - box.w * scale) / 2) - (box.x * scale)) + 'px';
			img.style.top = (((sh - box.h * scale) / 2) - (box.y * scale)) + 'px';
		}
		if (img.complete && img.naturalWidth) {
			place();
			return;
		}
		img.addEventListener('load', place, { once: true });
	}

	function updateDesignStage(stage) {
		var schematic = stage.querySelector('[data-apd-text-box-schematic]');
		var img = stage.querySelector('[data-apd-text-box-image]');
		var band = stage.querySelector('[data-apd-band-box]');
		var wrap = stage.closest('[data-apd-text-box-wrap]');
		var hasImage = !!(img && img.getAttribute('src'));
		if (band) {
			band.hidden = true;
		}
		if (!hasImage) {
			if (schematic) {
				schematic.hidden = false;
			}
			if (img) {
				img.hidden = true;
				resetDesignImage(img);
			}
			if (wrap) {
				wrap.hidden = true;
			}
			return;
		}
		if (wrap) {
			wrap.hidden = false;
		}
		if (schematic) {
			schematic.hidden = true;
		}
		img.hidden = false;
		fitDesignArtwork(img);
		syncSampleSize();
	}

	function updateFormatStage() {
		var stage = document.querySelector('[data-apd-text-box-stage]');
		if (!stage) {
			return;
		}
		if (isDesignEditor()) {
			updateDesignStage(stage);
			return;
		}
		var type = currentFormatType();
		var mm = formatMm();
		if (mm.width > 0 && mm.height > 0) {
			stage.style.aspectRatio = String(mm.width) + ' / ' + String(mm.height);
		}
		var studio = stage.closest('[data-apd-plate-studio]');
		if (studio) {
			var previewMax = 500;
			if (isPhotoHolder(type) && mm.width > 0) {
				previewMax = Math.max(80, Math.round(500 * (mm.width / 520)));
			}
			studio.style.setProperty('--apd-canvas-max', previewMax + 'px');
		}
		var schematic = stage.querySelector('[data-apd-text-box-schematic]');
		var img = stage.querySelector('[data-apd-text-box-image]');
		var band = stage.querySelector('[data-apd-band-box]');
		var textBox = stage.querySelector('[data-apd-text-box]');
		var cfg = adminCfg();
		var holderPhoto = bundledHolderUrl(type);
		var uploaded = document.querySelector('[name="apd_format[base_image_id]"]');
		var hasUpload = !!(uploaded && uploaded.value && uploaded.value !== '0');
		if (isHolderType(type) && img && holderPhoto && !hasUpload) {
			var currentSrc = img.getAttribute('src') || '';
			if (!currentSrc || currentSrc.indexOf('gray-plate-holder-hole.png') !== -1 || currentSrc.indexOf('holder-moto.jpg') !== -1 || currentSrc.indexOf('holder-moto.png') !== -1 || currentSrc.indexOf('holder-type-d.png') !== -1) {
				img.src = holderPhoto;
			}
		}
		if (textBox) {
			var radii = cfg.holderStripRadii || {};
			var radiusFactor = radii[type] ? Number(radii[type]) : 0.25;
			textBox.classList.toggle('apd-text-box--strip', isHolderType(type));
			textBox.style.borderRadius = (isHolderType(type) && textBox.clientHeight > 0)
				? (textBox.clientHeight * radiusFactor) + 'px'
				: '';
		}
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
			renderPlateSample();
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
		writeTextBox(readTextBox());
		renderPlateSample();
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
		var width = el.clientWidth;
		var fill = parseFloat(adminCfg().sampleFontFill);
		if (isNaN(fill) || fill <= 0) {
			fill = 0.9;
		}
		if (height <= 0 || width <= 0) {
			return;
		}
		var rows = sample.querySelectorAll('.apd-text-row');
		var lineCount = rows.length > 1 ? rows.length : 1;
		var px = Math.max(10, Math.floor((height / lineCount) * fill));
		sample.style.fontSize = String(px) + 'px';
		var style = window.getComputedStyle(sample);
		var probeText = sample.textContent || '';
		if (rows.length > 1) {
			probeText = '';
			rows.forEach(function (row) {
				if (row.textContent && row.textContent.length > probeText.length) {
					probeText = row.textContent;
				}
			});
		}
		var probe = document.createElement('span');
		probe.textContent = probeText;
		probe.style.cssText = 'position:absolute;left:-9999px;top:0;white-space:nowrap;font-family:' + style.fontFamily + ';font-weight:' + style.fontWeight + ';font-style:' + style.fontStyle + ';letter-spacing:' + style.letterSpacing + ';font-size:' + px + 'px;';
		document.body.appendChild(probe);
		var textWidth = probe.getBoundingClientRect().width;
		document.body.removeChild(probe);
		if (textWidth > width) {
			px = Math.max(10, Math.floor(px * (width / textWidth) * 0.96));
			sample.style.fontSize = String(px) + 'px';
		}
		var rightEl = document.querySelector('[data-apd-text-box-right]');
		var rightSample = document.querySelector('[data-apd-text-box-sample-right]');
		if (!splitActive() || !rightEl || !rightSample || rightEl.hidden) {
			return;
		}
		var rightWidth = rightEl.clientWidth;
		var rightPx = px;
		if (rightWidth > 0) {
			var rightProbe = document.createElement('span');
			rightProbe.textContent = rightSample.textContent || '1234';
			rightProbe.style.cssText = probe.style.cssText;
			rightProbe.style.fontSize = String(px) + 'px';
			document.body.appendChild(rightProbe);
			var rightTextWidth = rightProbe.getBoundingClientRect().width;
			document.body.removeChild(rightProbe);
			if (rightTextWidth > rightWidth) {
				rightPx = Math.max(10, Math.floor(px * (rightWidth / rightTextWidth) * 0.96));
			}
		}
		var shared = Math.min(px, rightPx);
		sample.style.fontSize = String(shared) + 'px';
		rightSample.style.fontSize = String(shared) + 'px';
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
			toggleFormatFields({ resetLayout: true });
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
			var fileInput = field.querySelector('[data-apd-base-file]');
			if (fileInput) {
				fileInput.value = '';
			}
			if (preview) {
				preview.innerHTML = '';
			}
			if (field.querySelector('[name="apd_design[image_id]"]') || field.querySelector('[name="apd_format[base_image_id]"]')) {
				var wrap = document.querySelector('[data-apd-text-box-wrap]');
				var overlayImg = document.querySelector('[data-apd-text-box-image]');
				var holderFallback = bundledHolderUrl(currentFormatType());
				if (overlayImg && holderFallback) {
					overlayImg.src = holderFallback;
					overlayImg.hidden = false;
					if (preview) {
						var fallbackThumb = document.createElement('img');
						fallbackThumb.src = holderFallback;
						fallbackThumb.alt = '';
						preview.appendChild(fallbackThumb);
					}
				} else if (overlayImg) {
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

	function holderStrip(type) {
		type = type || currentFormatType();
		if (!isHolderType(type)) {
			return null;
		}
		var strips = adminCfg().holderStrips || {};
		var strip = strips[type] || (type === 'holder' ? adminCfg().holderStrip : null);
		if (strip && strip.width && strip.height) {
			return {
				x: Number(strip.x),
				y: Number(strip.y),
				width: Number(strip.width),
				height: Number(strip.height)
			};
		}
		if (type === 'holder') {
			return { x: 2.73, y: 77.3, width: 94.34, height: 6.26 };
		}
		return null;
	}

	function stripMetricsActive() {
		var stage = document.querySelector('[data-apd-text-box-stage]');
		return !!(stage && stage.getAttribute('data-apd-strip-metrics') === '1');
	}

	function setStripMetricFlag(on) {
		var stage = document.querySelector('[data-apd-text-box-stage]');
		var flag = document.querySelector('[data-apd-text-box-metric]');
		if (stage) {
			if (on) {
				stage.setAttribute('data-apd-strip-metrics', '1');
				stage.setAttribute('data-apd-strip-metrics-type', currentFormatType());
			} else {
				stage.removeAttribute('data-apd-strip-metrics');
				stage.removeAttribute('data-apd-strip-metrics-type');
			}
		}
		if (flag) {
			flag.value = on ? 'strip' : '';
		}
	}

	function stripToCanvas(box, strip) {
		return {
			x: strip.x + (strip.width * box.x / 100),
			y: strip.y + (strip.height * box.y / 100),
			width: strip.width * box.width / 100,
			height: strip.height * box.height / 100,
			align: box.align,
			valign: box.valign,
			letter_align: box.letter_align,
			number_align: box.number_align
		};
	}

	function canvasToStrip(box, strip) {
		var width = (box.width / strip.width) * 100;
		var height = (box.height / strip.height) * 100;
		var x = ((box.x - strip.x) / strip.width) * 100;
		var y = ((box.y - strip.y) / strip.height) * 100;
		width = Math.max(5, Math.min(100, width));
		height = Math.max(5, Math.min(100, height));
		if (x + width > 100) {
			x = 100 - width;
		}
		if (y + height > 100) {
			y = 100 - height;
		}
		return {
			x: round1(Math.max(0, x)),
			y: round1(Math.max(0, y)),
			width: round1(width),
			height: round1(height),
			align: box.align,
			valign: box.valign,
			letter_align: box.letter_align,
			number_align: box.number_align
		};
	}

	function clampBox(box) {
		var next = {
			x: box.x,
			y: box.y,
			width: box.width,
			height: box.height,
			align: box.align || 'center',
			valign: box.valign || 'middle'
		};
		var strip = holderStrip();
		if (strip) {
			var minW = Math.max(0.1, round1(strip.width * 0.05));
			var minH = Math.max(0.1, round1(strip.height * 0.05));
			if (next.width < minW) {
				next.width = minW;
			}
			if (next.height < minH) {
				next.height = minH;
			}
			if (next.width > strip.width) {
				next.width = strip.width;
			}
			if (next.height > strip.height) {
				next.height = strip.height;
			}
			var maxX = strip.x + strip.width - next.width;
			var maxY = strip.y + strip.height - next.height;
			next.x = Math.max(strip.x, Math.min(maxX, next.x));
			next.y = Math.max(strip.y, Math.min(maxY, next.y));
		} else {
			var region = computeTextAreaRegion();
			if (next.width < 5) {
				next.width = 5;
			}
			if (next.height < 5) {
				next.height = 5;
			}
			if (next.width > region.width) {
				next.width = region.width;
			}
			if (next.height > region.height) {
				next.height = region.height;
			}
			var maxX = region.x + region.width - next.width;
			var maxY = region.y + region.height - next.height;
			next.x = Math.max(region.x, Math.min(maxX, next.x));
			next.y = Math.max(region.y, Math.min(maxY, next.y));
		}
		next.x = round1(next.x);
		next.y = round1(next.y);
		next.width = round1(next.width);
		next.height = round1(next.height);
		if (box.letter_align) {
			next.letter_align = box.letter_align;
		}
		if (box.number_align) {
			next.number_align = box.number_align;
		}
		return next;
	}

	function readTextBoxFields() {
		function num(name, fallback) {
			var el = document.querySelector('[data-apd-text-box-input="' + name + '"]');
			var value = el ? parseFloat(el.value) : fallback;
			return isNaN(value) ? fallback : value;
		}
		var alignEl = document.querySelector('[data-apd-text-box-input="align"]');
		var valignEl = document.querySelector('[data-apd-text-box-input="valign"]');
		var letterEl = document.querySelector('[data-apd-text-box-input="letter_align"]');
		var numberEl = document.querySelector('[data-apd-text-box-input="number_align"]');
		var box = {
			x: num('x', 9),
			y: num('y', 34),
			width: num('width', 82),
			height: num('height', 42),
			align: alignEl ? alignEl.value : 'center',
			valign: valignEl ? valignEl.value : 'middle'
		};
		if (letterEl) {
			box.letter_align = letterEl.value || 'justify';
		}
		if (numberEl) {
			box.number_align = numberEl.value || 'justify';
		}
		return box;
	}

	function readTextBox() {
		var box = readTextBoxFields();
		if (stripMetricsActive()) {
			var strip = holderStrip();
			if (strip) {
				box = stripToCanvas(box, strip);
			}
		}
		return clampBox(box);
	}

	function writeTextBox(box) {
		var canvas = clampBox(box);
		var shown = canvas;
		var strip = holderStrip();
		if (strip) {
			shown = canvasToStrip(canvas, strip);
			setStripMetricFlag(true);
		} else {
			setStripMetricFlag(false);
		}
		['x', 'y', 'width', 'height', 'align', 'valign', 'letter_align', 'number_align'].forEach(function (name) {
			var el = document.querySelector('[data-apd-text-box-input="' + name + '"]');
			if (el && shown[name] !== undefined && shown[name] !== null) {
				el.value = String(shown[name]);
			}
		});
		syncRowStyleButtons();
		applyTextBoxOverlay(canvas);
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
		var stage = el.parentElement;
		var stageH = stage ? stage.clientHeight : 0;
		var stripFactor = 0.25;
		var stripRadii = adminCfg().holderStripRadii || {};
		var stripType = currentFormatType();
		if (stripRadii[stripType]) {
			stripFactor = Number(stripRadii[stripType]);
		}
		el.style.borderRadius = (el.classList.contains('apd-text-box--strip') && stageH > 0)
			? (stageH * (box.height / 100) * stripFactor) + 'px'
			: '';
		if (sample) {
			if (sample.classList.contains('apd-text-box-sample--rows')) {
				renderPlateSample();
				sample.style.alignItems = 'stretch';
				sample.style.justifyContent = 'stretch';
			} else {
				sample.style.justifyContent = alignToFlex(box.align);
				sample.style.alignItems = box.valign === 'top' ? 'flex-start' : (box.valign === 'bottom' ? 'flex-end' : 'center');
			}
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
		if (isHolderType(type)) {
			var strips = adminCfg().holderStrips || {};
			var strip = strips[type] || (type === 'holder' ? adminCfg().holderStrip : null);
			if (strip && strip.width) {
				return {
					x: Number(strip.x),
					y: Number(strip.y),
					width: Number(strip.width),
					height: Number(strip.height)
				};
			}
			if (type === 'holder') {
				return { x: 2.73, y: 77.3, width: 94.34, height: 6.26 };
			}
		}
		if (paintedPlate(type) && !wantsNoFrame()) {
			var bwInput = document.querySelector('[name="apd_format[border_width]"]');
			var bw = bwInput ? parseInt(bwInput.value, 10) : 0;
			if (isNaN(bw) || bw < 0) {
				bw = 0;
			}
			if (bw > 0 && mm.width > 0 && mm.height > 0) {
				var ix = ((bw + TEXT_FRAME_GAP_MM) / mm.width) * 100;
				var iy = ((bw + TEXT_FRAME_GAP_MM) / mm.height) * 100;
				region = {
					x: ix,
					y: iy,
					width: 100 - ix * 2,
					height: 100 - iy * 2
				};
			}
		}
		if (usesCountryBand(type)) {
			var band = readBandBox();
			if (band.height >= 80 && mm.width > 0) {
				region = subtractBandFromRegion(region, band);
				var gapX = (TEXT_FRAME_GAP_MM / mm.width) * 100;
				if (band.x + (band.width / 2) < 50) {
					region.x += gapX;
					region.width = Math.max(5, region.width - gapX);
				} else {
					region.width = Math.max(5, region.width - gapX);
				}
			}
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

	function bindSelectAllFonts() {
		var master = document.querySelector('[data-apd-fonts-all]');
		if (!master) {
			return;
		}
		var boxes = document.querySelectorAll('[name="apd_format[font_ids][]"]');
		master.addEventListener('change', function () {
			boxes.forEach(function (box) {
				box.checked = master.checked;
			});
		});
		boxes.forEach(function (box) {
			box.addEventListener('change', function () {
				if (!box.checked) {
					master.checked = false;
				}
			});
		});
	}

	function splitActive() {
		var cb = document.querySelector('[data-apd-split-text]');
		if (!cb || !cb.checked) {
			return false;
		}
		if (document.querySelector('[data-apd-design-stage]')) {
			return true;
		}
		return currentFormatType() === 'us';
	}

	function round1(value) {
		return Math.round(value * 10) / 10;
	}

	function readRightBox() {
		function num(name, fallback) {
			var el = document.querySelector('[data-apd-text-box-right-input="' + name + '"]');
			var value = el ? parseFloat(el.value) : fallback;
			return isNaN(value) ? fallback : value;
		}
		var alignEl = document.querySelector('[data-apd-text-box-right-input="align"]');
		var left = readTextBox();
		return clampBox({
			x: num('x', left.x + left.width + 8),
			y: left.y,
			width: num('width', 30),
			height: left.height,
			align: alignEl ? alignEl.value : 'center',
			valign: left.valign
		});
	}

	function applyRightOverlay(box) {
		var el = document.querySelector('[data-apd-text-box-right]');
		var sample = document.querySelector('[data-apd-text-box-sample-right]');
		if (!el || !box) {
			return;
		}
		el.style.left = String(box.x) + '%';
		el.style.top = String(box.y) + '%';
		el.style.width = String(box.width) + '%';
		el.style.height = String(box.height) + '%';
		if (sample) {
			sample.style.justifyContent = alignToFlex(box.align);
			sample.style.alignItems = box.valign === 'top' ? 'flex-start' : (box.valign === 'bottom' ? 'flex-end' : 'center');
		}
	}

	function writeRightBox(box) {
		['x', 'y', 'width', 'height', 'align', 'valign'].forEach(function (name) {
			var el = document.querySelector('[data-apd-text-box-right-input="' + name + '"]');
			if (el && box[name] !== undefined && box[name] !== null) {
				el.value = String(box[name]);
			}
		});
		applyRightOverlay(box);
	}

	function separateSides(left, right) {
		left = clampBox(left);
		right = clampBox({
			x: right.x,
			y: left.y,
			width: right.width,
			height: left.height,
			align: right.align || 'center',
			valign: left.valign
		});
		var leftEnd = round1(left.x + left.width);
		if (right.x < leftEnd) {
			var fit = round1(right.x - left.x);
			if (fit >= 5) {
				left.width = fit;
			} else {
				right.x = round1(left.x + left.width);
			}
		}
		if (right.x + right.width > 100) {
			right.width = round1(100 - right.x);
		}
		if (right.width < 5) {
			right.width = 5;
			if (right.x > 95) {
				right.x = 95;
			}
		}
		right.y = left.y;
		right.height = left.height;
		right.valign = left.valign;
		return { left: clampBox(left), right: right };
	}

	function seedPair(left) {
		var gap = 8;
		var usable = Math.max(10, left.width - gap);
		var leftW = round1(Math.max(5, usable * 0.45));
		var rightW = round1(Math.max(5, usable - leftW));
		var rightX = round1(left.x + leftW + gap);
		return separateSides(
			Object.assign({}, left, { width: leftW }),
			Object.assign({}, left, { x: rightX, width: rightW, align: 'center' })
		);
	}

	function movePair(left, right) {
		var minX = Math.min(left.x, right.x);
		var maxR = Math.max(left.x + left.width, right.x + right.width);
		if (minX < 0) {
			left.x -= minX;
			right.x -= minX;
		}
		maxR = Math.max(left.x + left.width, right.x + right.width);
		if (maxR > 100) {
			var over = maxR - 100;
			left.x -= over;
			right.x -= over;
		}
		if (left.y < 0) {
			left.y = 0;
		}
		if (left.y + left.height > 100) {
			left.y = round1(100 - left.height);
		}
		left.x = round1(left.x);
		left.y = round1(left.y);
		right.x = round1(right.x);
		right.y = left.y;
		right.height = left.height;
		right.valign = left.valign;
		return { left: left, right: right };
	}

	function writePair(left, right) {
		writeTextBox(left);
		writeRightBox(right);
		syncSampleSize();
	}

	function syncSplitPanels() {
		var design = !!document.querySelector('[data-apd-design-stage]');
		var allowed = design || currentFormatType() === 'us';
		var toggle = document.querySelector('.apd-split-toggle');
		if (toggle && !design) {
			toggle.hidden = currentFormatType() !== 'us';
		}
		var on = allowed && splitActive();
		document.querySelectorAll('[data-apd-split-panel]').forEach(function (el) {
			el.hidden = !on;
		});
		document.querySelectorAll('[data-apd-split-on]').forEach(function (el) {
			el.hidden = !on;
		});
		document.querySelectorAll('[data-apd-split-off]').forEach(function (el) {
			el.hidden = on;
		});
		var right = document.querySelector('[data-apd-text-box-right]');
		if (right) {
			right.hidden = !on;
		}
		if (document.querySelector('[data-apd-format-type]')) {
			renderPlateSample();
		} else {
			var sample = document.querySelector('[data-apd-text-box-sample]');
			if (sample && !sample.classList.contains('apd-text-box-sample--rows')) {
				sample.textContent = on ? 'CA' : (sample.getAttribute('data-fallback') || 'TEXT');
			}
		}
		var rightSample = document.querySelector('[data-apd-text-box-sample-right]');
		if (rightSample) {
			rightSample.textContent = '1234';
		}
	}

	function bindTextBoxEditor() {
		var stage = document.querySelector('[data-apd-text-box-stage]');
		var boxEl = document.querySelector('[data-apd-text-box]');
		if (!stage || !boxEl) {
			return;
		}

		if (isHolderType(currentFormatType())) {
			writeTextBox(readTextBox());
		} else {
			applyTextBoxOverlay(readTextBox());
		}
		syncSplitPanels();
		if (splitActive()) {
			writePair(readTextBox(), readRightBox());
		}

		var splitToggle = document.querySelector('[data-apd-split-text]');
		if (splitToggle) {
			splitToggle.addEventListener('change', function () {
				if (splitToggle.checked) {
					var leftNow = readTextBox();
					var rightNow = readRightBox();
					if (rightNow.x < leftNow.x + leftNow.width - 0.05) {
						var seeded = seedPair(leftNow);
						writePair(seeded.left, seeded.right);
					} else {
						writePair(leftNow, Object.assign({}, rightNow, { y: leftNow.y, height: leftNow.height, valign: leftNow.valign }));
					}
				}
				syncSplitPanels();
				toggleSuvCharLimits(currentFormatType());
				syncSampleSize();
			});
		}

		document.addEventListener('click', function (event) {
			var alignBtn = event.target && event.target.closest('[data-apd-row-align]');
			if (!alignBtn) {
				return;
			}
			event.preventDefault();
			var which = alignBtn.getAttribute('data-apd-row-align');
			var value = alignBtn.getAttribute('data-apd-align') || 'justify';
			var field = which === 'numbers' ? 'number_align' : 'letter_align';
			var input = document.querySelector('[data-apd-text-box-input="' + field + '"]');
			if (input) {
				input.value = value;
			}
			syncRowStyleButtons();
			renderPlateSample();
			syncSampleSize();
		});

		document.addEventListener('click', function (event) {
			var btn = event.target && event.target.closest('[data-apd-center-text-box]');
			if (!btn) {
				return;
			}
			event.preventDefault();
			if (!splitActive()) {
				writeTextBox(centerBoxInRegion(readTextBox(), computeTextAreaRegion()));
				return;
			}
			var region = computeTextAreaRegion();
			var leftBox = readTextBox();
			var rightBox = readRightBox();
			var unionLeft = Math.min(leftBox.x, rightBox.x);
			var unionRight = Math.max(leftBox.x + leftBox.width, rightBox.x + rightBox.width);
			var unionW = unionRight - unionLeft;
			var shiftX = (region.x + (region.width - unionW) / 2) - unionLeft;
			var shiftY = (region.y + (region.height - leftBox.height) / 2) - leftBox.y;
			leftBox.x += shiftX;
			leftBox.y += shiftY;
			rightBox.x += shiftX;
			rightBox.y = leftBox.y;
			rightBox.height = leftBox.height;
			var moved = movePair(leftBox, rightBox);
			writePair(moved.left, moved.right);
		});

		document.addEventListener('input', function (event) {
			if (!event.target) {
				return;
			}
			if (event.target.hasAttribute('data-apd-text-box-input') || event.target.hasAttribute('data-apd-text-box-right-input')) {
				if (splitActive()) {
					var linked = separateSides(readTextBox(), readRightBox());
					writePair(linked.left, linked.right);
				} else {
					writeTextBox(readTextBox());
				}
			}
		});
		document.addEventListener('change', function (event) {
			if (!event.target) {
				return;
			}
			if (event.target.hasAttribute('data-apd-text-box-input') || event.target.hasAttribute('data-apd-text-box-right-input')) {
				if (splitActive()) {
					var linkedChange = separateSides(readTextBox(), readRightBox());
					writePair(linkedChange.left, linkedChange.right);
				} else {
					writeTextBox(readTextBox());
				}
			}
		});

		var drag = null;

		function startDrag(event, handle, which) {
			event.preventDefault();
			var rect = stage.getBoundingClientRect();
			drag = {
				handle: handle,
				which: which || 'left',
				startX: event.clientX,
				startY: event.clientY,
				rect: rect,
				left: readTextBox(),
				right: readRightBox()
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
			if (!splitActive()) {
				var next = {
					x: drag.left.x,
					y: drag.left.y,
					width: drag.left.width,
					height: drag.left.height,
					align: drag.left.align,
					valign: drag.left.valign
				};
				if (!drag.handle) {
					next.x = drag.left.x + dx;
					next.y = drag.left.y + dy;
				} else {
					if (drag.handle.indexOf('w') !== -1) {
						next.x = drag.left.x + dx;
						next.width = drag.left.width - dx;
					}
					if (drag.handle.indexOf('e') !== -1) {
						next.width = drag.left.width + dx;
					}
					if (drag.handle.indexOf('n') !== -1) {
						next.y = drag.left.y + dy;
						next.height = drag.left.height - dy;
					}
					if (drag.handle.indexOf('s') !== -1) {
						next.height = drag.left.height + dy;
					}
				}
				writeTextBox(clampBox(next));
				return;
			}
			var left = Object.assign({}, drag.left);
			var right = Object.assign({}, drag.right);
			var source = drag.which === 'right' ? drag.right : drag.left;
			var target = drag.which === 'right' ? right : left;
			if (!drag.handle) {
				left.x = drag.left.x + dx;
				left.y = drag.left.y + dy;
				right.x = drag.right.x + dx;
				right.y = left.y;
				right.height = left.height;
				writePair(movePair(left, right).left, movePair(left, right).right);
				return;
			}
			if (drag.handle.indexOf('w') !== -1) {
				target.x = source.x + dx;
				target.width = source.width - dx;
			}
			if (drag.handle.indexOf('e') !== -1) {
				target.width = source.width + dx;
			}
			if (drag.handle.indexOf('n') !== -1) {
				left.y = drag.left.y + dy;
				left.height = drag.left.height - dy;
			}
			if (drag.handle.indexOf('s') !== -1) {
				left.height = drag.left.height + dy;
			}
			right.y = left.y;
			right.height = left.height;
			right.valign = left.valign;
			var separated = separateSides(left, right);
			writePair(separated.left, separated.right);
		}

		function onUp() {
			document.removeEventListener('pointermove', onMove);
			document.removeEventListener('pointerup', onUp);
			drag = null;
		}

		boxEl.addEventListener('pointerdown', function (event) {
			if (event.target && event.target.getAttribute('data-apd-handle')) {
				startDrag(event, event.target.getAttribute('data-apd-handle'), 'left');
				return;
			}
			startDrag(event, '', 'left');
		});

		var rightEl = document.querySelector('[data-apd-text-box-right]');
		if (rightEl) {
			rightEl.addEventListener('pointerdown', function (event) {
				if (!splitActive()) {
					return;
				}
				event.stopPropagation();
				if (event.target && event.target.getAttribute('data-apd-handle')) {
					startDrag(event, event.target.getAttribute('data-apd-handle'), 'right');
					return;
				}
				startDrag(event, '', 'right');
			});
		}
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
		if (currentFormatType() === 'suv_eu') {
			renderPlateSample();
		}
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

		stage.addEventListener('pointerdown', function (event) {
			if (!usesCountryBand(currentFormatType())) {
				return;
			}
			var handle = '';
			boxEl.querySelectorAll('[data-apd-band-handle]').forEach(function (el) {
				var rect = el.getBoundingClientRect();
				if (event.clientX >= rect.left && event.clientX <= rect.right && event.clientY >= rect.top && event.clientY <= rect.bottom) {
					handle = el.getAttribute('data-apd-band-handle') || '';
				}
			});
			if (handle) {
				startDrag(event, handle);
			}
		}, true);
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
		bindPaletteMenus();
		toggleFormatFields();
		toggleProductColorFields();
		toggleProductTextRows();
		toggleProductDesign();
		toggleProductOptions();
		toggleDesignFormats();
		toggleSwatchRadius();
		bindTextBoxEditor();
		bindSelectAllFonts();
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
			if (isDesignEditor()) {
				var designImg = document.querySelector('[data-apd-design-stage] [data-apd-text-box-image]');
				if (designImg && designImg.getAttribute('src')) {
					fitDesignArtwork(designImg);
				}
			}
		});
	}

	function toggleProductOptions() {
		var box = document.querySelector('.apd-product-metabox');
		if (!box) {
			return;
		}
		var enabled = box.querySelector('input[name="apd_enabled"]');
		var extras = box.querySelector('[data-apd-product-options]');
		if (!enabled || !extras) {
			return;
		}
		extras.hidden = !enabled.checked;
	}

	function toggleProductDesign() {
		var select = document.getElementById('apd_format_id');
		var designField = document.getElementById('apd_design_id');
		if (!select || !designField) {
			return;
		}
		var option = select.options[select.selectedIndex];
		var type = option ? option.getAttribute('data-apd-type') || '' : '';
		var formatId = select.value;
		var us = type === 'us';
		designField.disabled = !us;
		if (!us || !designField.value) {
			return;
		}
		var raw = designField.getAttribute('data-apd-formats') || '';
		var ids = raw ? raw.split(',') : [];
		if (ids.length && ids.indexOf(formatId) === -1) {
			designField.value = '';
		}
	}

	function productTextMode() {
		var select = document.getElementById('apd_format_id');
		if (!select) {
			return 'single';
		}
		var option = select.options[select.selectedIndex];
		var type = option ? option.getAttribute('data-apd-type') || '' : '';
		if (option && option.getAttribute('data-apd-rows') === '1') {
			return 'rows';
		}
		if (type === 'moto' || type === 'moto_240' || type === 'moto_plain' || type === 'moto_plain_240' || type === 'suv' || type === 'suv_eu') {
			return 'rows';
		}
		if (type !== 'us' || !option) {
			return 'single';
		}
		var photo = option.getAttribute('data-apd-photo') === '1';
		var formatSplit = option.getAttribute('data-apd-split') === '1';
		if (photo) {
			return formatSplit ? 'sides' : 'single';
		}
		var designField = document.getElementById('apd_design_id');
		if (designField && designField.value && designField.getAttribute('data-apd-split') === '1') {
			return 'sides';
		}
		if ((!designField || !designField.value) && formatSplit) {
			return 'sides';
		}
		return 'single';
	}

	function applySideLimits() {
		var select = document.getElementById('apd_format_id');
		var option = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
		if (!option) {
			return;
		}
		var left = option.getAttribute('data-apd-left') || '3';
		var right = option.getAttribute('data-apd-right') || '4';
		if (option.getAttribute('data-apd-photo') !== '1') {
			var designField = document.getElementById('apd_design_id');
			if (designField && designField.value && designField.getAttribute('data-apd-split') === '1') {
				left = designField.getAttribute('data-apd-left') || left;
				right = designField.getAttribute('data-apd-right') || right;
			}
		}
		var leftInput = document.getElementById('apd_default_text_left');
		var rightInput = document.getElementById('apd_default_text_right');
		if (leftInput) {
			leftInput.maxLength = parseInt(left, 10) || 3;
		}
		if (rightInput) {
			rightInput.maxLength = parseInt(right, 10) || 4;
		}
	}

	function toggleProductTextRows() {
		var select = document.getElementById('apd_format_id');
		var single = document.querySelector('[data-apd-default-single]');
		var rows = document.querySelector('[data-apd-default-rows]');
		var sides = document.querySelector('[data-apd-default-sides]');
		if (!select || !single || !rows) {
			return;
		}
		var mode = productTextMode();
		var suv = mode === 'rows';
		var singleInput = single.querySelector('input');
		var rowInputs = rows.querySelectorAll('input');
		var sideInputs = sides ? sides.querySelectorAll('input') : [];
		if (suv && singleInput && rowInputs.length === 2 && !rowInputs[0].value && !rowInputs[1].value && singleInput.value) {
			rowInputs[0].value = singleInput.value;
		}
		if (mode === 'sides' && singleInput && sideInputs.length === 2 && !sideInputs[0].value && !sideInputs[1].value && singleInput.value) {
			sideInputs[0].value = singleInput.value;
		}
		if (mode === 'single' && singleInput && rowInputs.length === 2 && !singleInput.value) {
			singleInput.value = [rowInputs[0].value, rowInputs[1].value].filter(Boolean).join(' ');
		}
		single.hidden = mode !== 'single';
		rows.hidden = mode !== 'rows';
		if (sides) {
			sides.hidden = mode !== 'sides';
		}
		if (singleInput) {
			singleInput.disabled = mode !== 'single';
		}
		rowInputs.forEach(function (input) {
			input.disabled = mode !== 'rows';
		});
		Array.prototype.forEach.call(sideInputs, function (input) {
			input.disabled = mode !== 'sides';
		});
		if (mode === 'sides') {
			applySideLimits();
		}
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
		box.hidden = !type;
		box.querySelectorAll('[data-apd-color-cap]').forEach(function (row) {
			var field = row.getAttribute('data-apd-color-cap');
			var allowed = caps.indexOf(field) !== -1;
			row.hidden = !allowed;
			row.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
				if (!allowed) {
					input.checked = false;
				}
			});
		});
	}

	function previewBaseFile(input) {
		var file = input.files && input.files[0];
		if (!file || !window.FileReader) {
			return;
		}
		var reader = new FileReader();
		reader.onload = function () {
			if (typeof reader.result !== 'string') {
				return;
			}
			var overlayImg = document.querySelector('[data-apd-text-box-image]');
			var field = closest(input, '.apd-media-field');
			var preview = field ? field.querySelector('.apd-media-preview') : null;
			if (overlayImg) {
				overlayImg.src = reader.result;
				overlayImg.hidden = false;
			}
			if (preview) {
				preview.textContent = '';
				var thumb = document.createElement('img');
				thumb.src = reader.result;
				thumb.alt = '';
				preview.appendChild(thumb);
			}
			updateFormatStage();
		};
		reader.readAsDataURL(file);
	}

	document.addEventListener('change', function (event) {
		if (!event.target) {
			return;
		}
		if (event.target.hasAttribute('data-apd-base-file')) {
			previewBaseFile(event.target);
		}
		if (event.target.id === 'apd_format_id') {
			toggleProductColorFields();
			toggleProductDesign();
			toggleProductTextRows();
		}
		if (event.target.id === 'apd_design_id') {
			toggleProductTextRows();
		}
		if (event.target.name === 'apd_enabled') {
			toggleProductOptions();
		}
	});
})();
