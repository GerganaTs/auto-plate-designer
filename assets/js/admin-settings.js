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

	function isCompactPlate(type) {
		return type === 'mini' || type === 'mini_plain' || type === 'super_mini' || type === 'super_mini_plain';
	}

	function lettersFollowCount(type) {
		return type === 'eu' || type === 'eu_plain' || type === 'moto' || type === 'moto_240' || type === 'moto_plain' || type === 'moto_plain_240' || type === 'suv' || type === 'suv_340' || type === 'suv_eu' || type === 'suv_eu_340' || type === 'eu_300' || type === 'atyp_300' || isCompactPlate(type);
	}

	function typingDefaults(type) {
		if (type === 'moto' || type === 'moto_plain') {
			return [7, 5];
		}
		return [8, 6];
	}

	function formatFormIsFresh() {
		var form = document.querySelector('[data-apd-format-form]');
		return !!(form && form.getAttribute('data-apd-fresh') === '1');
	}

	function usesTwoRows(type) {
		if (type === 'atyp_300') {
			var picked = document.querySelector('[name="apd_format[text_rows]"]:checked');
			return !!(picked && picked.value === '2' && !picked.disabled);
		}
		return !!typeCaps(type).twoRow;
	}

	function toggleAtypicalRowChoice(type) {
		var on = type === 'atyp_300';
		document.querySelectorAll('[data-apd-row-choice]').forEach(function (row) {
			row.hidden = !on;
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = !on;
			});
		});
	}

	function isSuvEuro(type) {
		return type === 'suv_eu' || type === 'suv_eu_340';
	}

	function isSuvFamily(type) {
		return type === 'suv' || type === 'suv_340' || isSuvEuro(type);
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
		var raw = String(text || '');
		var mode = plateRowAlign(align);
		var hasSpace = mode === 'justify' && raw.indexOf(' ') !== -1;
		row.textContent = '';
		row.style.justifyContent = hasSpace ? 'flex-start' : alignToFlex(mode);
		row.setAttribute('data-apd-gap', hasSpace ? 'spaces' : '');
		Array.from(raw).forEach(function (ch) {
			var glyph = document.createElement('span');
			if (ch === ' ') {
				glyph.textContent = '\u00a0';
				glyph.className = 'apd-text-gap';
			} else {
				glyph.textContent = ch;
			}
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
		if (isSuvFamily(type)) {
			paintedSample = adminCfg().adminSampleSuv || 'CAAA 1234';
		}
		if (type === 'uk' || type === 'uk_plain') {
			paintedSample = adminCfg().adminSampleEnglish || 'LKOO FDN';
		} else if (type === 'mini' || type === 'mini_plain') {
			paintedSample = adminCfg().adminSampleMini || 'GUNTHER';
		} else if (type === 'super_mini' || type === 'super_mini_plain') {
			paintedSample = adminCfg().adminSampleSuper || 'FDN';
		}
		if (type === 'fr' || type === 'fr_ww' || type === 'fr_region' || type === 'austria') {
			var groupLabels = type === 'austria' ? ['WE', '000 CF'] : (type === 'fr_ww' ? ['WW', '000', 'AA'] : ['AA', '000', 'AA']);
			if (!sample.classList.contains('apd-text-box-sample--rows')) {
				sample.textContent = groupLabels[0];
			}
			document.querySelectorAll('[data-apd-extra-sample]').forEach(function (node) {
				var box = node.closest('[data-apd-extra-index]');
				var index = box ? parseInt(box.getAttribute('data-apd-extra-index'), 10) : -1;
				if (groupLabels[index]) {
					node.textContent = groupLabels[index];
				}
			});
			return;
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
		if (!isSuvEuro(currentFormatType()) || !letters) {
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
			if (type === 'atyp_300') {
				if (letterInput && !letterInput.value && box.letter_align) {
					letterInput.value = box.letter_align;
				}
				if (numberInput && !numberInput.value && box.number_align) {
					numberInput.value = box.number_align;
				}
			}
			syncRowStyleButtons();
		}
		renderPlateSample();
	}

	function syncDefaultPreset() {
		var select = document.querySelector('[data-apd-default-preset]');
		var list = document.querySelector('[data-apd-preset-list]');
		if (!select || !list) {
			return;
		}
		var checked = [];
		list.querySelectorAll('input[type="checkbox"]').forEach(function (box) {
			if (box.checked) {
				checked.push(box.value);
			}
		});
		Array.prototype.forEach.call(select.options, function (opt) {
			if (!opt.value) {
				opt.hidden = false;
				opt.disabled = false;
				return;
			}
			var on = checked.indexOf(opt.value) !== -1;
			opt.hidden = !on;
			opt.disabled = !on;
		});
		if (select.value && checked.indexOf(select.value) === -1) {
			select.value = checked[0] || '';
		}
	}

	function syncPresetChoice() {
		var selected = document.querySelector('[data-apd-preset-mode][value="selected"]');
		var list = document.querySelector('[data-apd-preset-list]');
		var presetSelect = document.querySelector('[data-apd-default-preset]');
		var open = !!(selected && selected.checked && !selected.disabled);
		if (list) {
			list.hidden = !open;
		}
		if (presetSelect && !presetSelect.closest('.apd-preset-choice[hidden]')) {
			presetSelect.disabled = !open;
		}
		syncDefaultPreset();
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
			var bandHeightMm = isSuvEuro(type) ? mm.height / 2 : mm.height;
			var ratio = (40 / 110) * (bandHeightMm / mm.width);
			var widthPct = Math.round(Math.max(4, Math.min(25, ratio * 100)) * 10) / 10;
			var sideInput = document.querySelector('[name="apd_format[band_side]"]');
			var side = sideInput && sideInput.value === 'right' ? 'right' : 'left';
			writeBandBox({
				x: side === 'right' ? Math.round((100 - widthPct) * 10) / 10 : 0,
				y: 0,
				width: widthPct,
				height: isSuvEuro(type) ? 50 : 100
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
					var shareNote = formatSharesTextFrame(type);
					note.textContent = shareNote ? (note.getAttribute('data-apd-error-share') || note.textContent) : (note.getAttribute('data-apd-error-slots') || note.textContent);
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
		toggleSeparateTextFrame(type);
		var slots = usesPaletteSlots(type);
		var photo = isPhotoHolder(type);
		var slotRoot = root.querySelector('[data-apd-palette-slots]');
		var checkRoot = root.querySelector('[data-apd-palette-checks]');
		var photoRoot = root.querySelector('[data-apd-photo-palettes]');
		var slotHelp = root.querySelector('[data-apd-palette-slot-help]');
		var checkHelp = root.querySelector('[data-apd-palette-check-help]');
		var photoHelp = root.querySelector('[data-apd-palette-photo-help]');
		var caps = (adminCfg().colorCaps && adminCfg().colorCaps[type]) || [];
		var canShare = !!typeCaps(type).sharesTextFrame;
		var split = formatSplitsTextFrame();
		var share = formatSharesTextFrame(type);
		root.hidden = !type;
		if (slotRoot) {
			slotRoot.hidden = !slots;
			var englishSlots = type === 'uk' || type === 'uk_plain';
			var slotKeys = type === 'us' ? ['text'] : (englishSlots ? ['text', 'background', 'border', 'slogan'] : ((canShare && !split) ? ['text', 'background'] : ['text', 'background', 'border']));
			slotRoot.querySelectorAll('[data-apd-palette-menu]').forEach(function (menu) {
				var slot = menu.getAttribute('data-apd-palette-slot') || '';
				var on = slots && slotKeys.indexOf(slot) !== -1;
				menu.hidden = !on;
				var heading = menu.querySelector('.apd-palette-menu__heading');
				if (heading) {
					var slotLabel = heading.getAttribute('data-apd-label') || heading.textContent;
					heading.textContent = (share && slot === 'text' && adminCfg().inkFrameColor) ? adminCfg().inkFrameColor : slotLabel;
				}
				menu.querySelectorAll('[data-apd-palette-slot-input]').forEach(function (input) {
					input.disabled = !on;
				});
			});
		}
		var englishSlots = type === 'uk' || type === 'uk_plain';
		if (slotHelp) {
			slotHelp.hidden = !slots || type === 'us' || englishSlots || (canShare && !split);
		}
		var shareHelp = root.querySelector('[data-apd-palette-share-help]');
		if (shareHelp) {
			shareHelp.hidden = !(slots && share);
		}
		var ukPaletteHelp = root.querySelector('[data-apd-palette-uk-help]');
		if (ukPaletteHelp) {
			ukPaletteHelp.hidden = !(slots && englishSlots);
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
			var hideBorder = canShare && !split && (field === 'border' || field === 'color_border');
			var allowed = !slots && !photo && caps.indexOf(field) !== -1 && !hideBorder;
			row.hidden = !allowed;
			var title = row.querySelector('strong');
			if (title) {
				var capLabel = title.getAttribute('data-apd-label') || title.textContent;
				title.textContent = (share && field === 'text' && adminCfg().inkFrameColor) ? adminCfg().inkFrameColor : capLabel;
			}
			row.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
				input.disabled = !allowed;
				if (!allowed && !hideBorder) {
					input.checked = false;
				}
			});
		});
		document.querySelectorAll('[data-apd-digital-palette]').forEach(function (row) {
			var on = lettersFollowCount(type) && !formatSharesTextFrame(type) && !formatSplitsTextFrame();
			row.hidden = !on;
			row.querySelectorAll('select').forEach(function (input) {
				input.disabled = !on;
			});
		});
	}

	function extraInput(key, field) {
		return document.querySelector('[data-apd-extra-input="' + key + '"][data-apd-extra-field="' + field + '"]');
	}

	function readExtraBox(key) {
		function num(field, fallback) {
			var input = extraInput(key, field);
			var value = input ? parseFloat(input.value) : fallback;
			return isNaN(value) ? fallback : value;
		}
		var align = extraInput(key, 'align');
		var valign = extraInput(key, 'valign');
		return {
			x: num('x', 0),
			y: num('y', 0),
			width: num('width', 10),
			height: num('height', 10),
			align: align && align.value ? align.value : 'center',
			valign: valign && valign.value ? valign.value : 'middle'
		};
	}

	function writeExtraBox(key, box) {
		var el = document.querySelector('[data-apd-extra-box="' + key + '"]');
		var next = key === 'at_region' ? clampRegionBox(box) : ((key === 'fr_month' || key === 'fr_year') ? clampDateBox(box) : clampBox(box));
		['x', 'y', 'width', 'height', 'align', 'valign'].forEach(function (field) {
			var input = extraInput(key, field);
			if (input && typeof next[field] !== 'undefined') {
				input.value = String(next[field]);
			}
		});
		if (el) {
			el.style.left = next.x + '%';
			el.style.top = next.y + '%';
			el.style.width = next.width + '%';
			el.style.height = next.height + '%';
			fitDateLabel(key);
			if (key === 'slogan') {
				fitSloganLabel();
			}
		}
	}

	function clampDateBox(box) {
		var next = {
			x: box.x,
			y: box.y,
			width: Math.max(3, box.width),
			height: Math.max(4, box.height),
			align: box.align || 'right',
			valign: box.valign || 'top'
		};
		if (next.x < 0) {
			next.x = 0;
		}
		if (next.y < 0) {
			next.y = 0;
		}
		if (next.x + next.width > 100) {
			next.width = 100 - next.x;
		}
		if (next.y + next.height > 100) {
			next.height = 100 - next.y;
		}
		next.x = Math.round(next.x * 100) / 100;
		next.y = Math.round(next.y * 100) / 100;
		next.width = Math.round(next.width * 100) / 100;
		next.height = Math.round(next.height * 100) / 100;
		return next;
	}

	function fitSloganLabel() {
		var el = document.querySelector('[data-apd-extra-box="slogan"]');
		var sample = el ? el.querySelector('[data-apd-extra-sample]') : null;
		var stage = el ? el.parentElement : null;
		if (!el || !sample || !stage || el.hidden) {
			return;
		}
		var height = stage.clientHeight * (parseFloat(el.style.height) || 0) / 100;
		sample.style.fontSize = Math.max(8, Math.round(height * 0.72)) + 'px';
	}

	function fitDateLabel(key) {
		if (key !== 'fr_month' && key !== 'fr_year') {
			return;
		}
		var el = document.querySelector('[data-apd-extra-box="' + key + '"]');
		var sample = el ? el.querySelector('[data-apd-extra-sample]') : null;
		var stage = el ? el.parentElement : null;
		if (!el || !sample || !stage) {
			return;
		}
		var height = stage.clientHeight * (parseFloat(el.style.height) || 0) / 100;
		sample.style.fontSize = Math.max(8, Math.round(height * 0.92)) + 'px';
		sample.style.alignItems = key === 'fr_year' ? 'flex-end' : 'flex-start';
		sample.style.justifyContent = 'flex-end';
	}

	function clampRegionBox(box) {
		var next = {
			x: box.x,
			y: box.y,
			width: Math.max(4, box.width),
			height: Math.max(20, box.height)
		};
		if (next.x < 0) {
			next.x = 0;
		}
		if (next.y < 0) {
			next.y = 0;
		}
		if (next.x + next.width > 100) {
			next.width = 100 - next.x;
		}
		if (next.y + next.height > 100) {
			next.height = 100 - next.y;
		}
		next.x = Math.round(next.x * 100) / 100;
		next.y = Math.round(next.y * 100) / 100;
		next.width = Math.round(next.width * 100) / 100;
		next.height = Math.round(next.height * 100) / 100;
		return next;
	}

	function applyGroupPreset(type) {
		var stage = document.querySelector('[data-apd-group-presets]');
		if (!stage) {
			return;
		}
		var presets = {};
		try {
			presets = JSON.parse(stage.getAttribute('data-apd-group-presets') || '{}');
		} catch (error) {
			presets = {};
		}
		var boxes = presets[type] || [];
		if (boxes[0]) {
			writeTextBox(boxes[0]);
		}
		if (boxes[1]) {
			writeExtraBox('text_box_2', boxes[1]);
		}
		if (boxes[2]) {
			writeExtraBox('text_box_3', boxes[2]);
		}
		if (type === 'austria') {
			var region = {};
			try {
				region = JSON.parse(stage.getAttribute('data-apd-region-preset') || '{}');
			} catch (regionError) {
				region = {};
			}
			if (region && region.width) {
				writeExtraBox('at_region', region);
			}
		}
	}

	function syncGroupBoxes(type, reset) {
		var french = type === 'fr' || type === 'fr_ww' || type === 'fr_region';
		var austria = type === 'austria';
		var visible = {
			text_box_2: french || austria,
			text_box_3: french,
			at_region: austria,
			fr_month: type === 'fr_ww',
			fr_year: type === 'fr_ww',
			fr_region_code: type === 'fr_region',
			slogan: type === 'uk' || type === 'uk_plain'
		};
		Object.keys(visible).forEach(function (key) {
			var on = visible[key];
			var box = document.querySelector('[data-apd-extra-box="' + key + '"]');
			if (box) {
				box.hidden = !on;
			}
			document.querySelectorAll('[data-apd-group-fields="' + key + '"]').forEach(function (fields) {
				fields.hidden = !on;
				fields.querySelectorAll('input').forEach(function (input) {
					input.disabled = !on;
				});
			});
		});
		if (reset && (french || austria)) {
			applyGroupPreset(type);
		}
		if (type === 'fr_ww') {
			fitDateLabel('fr_month');
			fitDateLabel('fr_year');
		}
		if (type === 'uk' || type === 'uk_plain') {
			fitSloganLabel();
		}
	}

	function bindExtraBoxes(stage) {
		var drag = null;

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
			writeExtraBox(drag.key, next);
		}

		function onUp() {
			document.removeEventListener('pointermove', onMove);
			document.removeEventListener('pointerup', onUp);
			drag = null;
		}

		document.querySelectorAll('[data-apd-extra-box]').forEach(function (el) {
			el.addEventListener('pointerdown', function (event) {
				if (el.hidden) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				var key = el.getAttribute('data-apd-extra-box');
				drag = {
					key: key,
					handle: event.target && event.target.getAttribute('data-apd-extra-handle') ? event.target.getAttribute('data-apd-extra-handle') : '',
					startX: event.clientX,
					startY: event.clientY,
					rect: stage.getBoundingClientRect(),
					box: readExtraBox(key)
				};
				document.addEventListener('pointermove', onMove);
				document.addEventListener('pointerup', onUp);
			});
		});
		document.addEventListener('input', function (event) {
			if (!event.target || !event.target.hasAttribute('data-apd-extra-input')) {
				return;
			}
			writeExtraBox(event.target.getAttribute('data-apd-extra-input'), readExtraBox(event.target.getAttribute('data-apd-extra-input')));
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
			setGroupEnabled('.apd-preset-choice', false);
			return;
		}
		applyDefaultSize(type);
		if (options && options.resetLayout) {
			resetLayoutForNewFormat(type);
		}
		setGroupEnabled('.apd-image-fields', usesBaseImage(type));
		setGroupEnabled('.apd-eu-fields', paintedPlate(type));
		setGroupEnabled('.apd-border-fields', !isHolderType(type) && type !== 'fr' && type !== 'fr_ww' && type !== 'fr_region' && type !== 'austria' && type !== 'uk' && type !== 'uk_plain');
		setGroupEnabled('.apd-band-fields, .apd-band-box-fields', usesCountryBand(type));
		setGroupEnabled('.apd-preset-choice', !!typeCaps(type).countryChoice);
		syncPresetChoice();
		syncGroupBoxes(type, !!(options && options.resetLayout));
		toggleAtypicalRowChoice(type);
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
			} else if (type === 'suv' || type === 'suv_340') {
				heading.textContent = cfg.suvImageLabel || 'Plate graphic';
			} else {
				heading.textContent = cfg.holderImageLabel || 'Holder photo';
			}
		}
		if (help) {
			if (type === 'us') {
				help.hidden = false;
				help.textContent = cfg.usImageHelp || '';
			} else if (type === 'suv' || type === 'suv_340') {
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
		var keepBoth = type === 'atyp_300';
		var french = type === 'fr' || type === 'fr_ww' || type === 'fr_region';
		var austria = type === 'austria';
		var english = type === 'uk' || type === 'uk_plain';
		var hideSingle = suv || !type || french || austria || (type === 'us' && splitActive());
		document.querySelectorAll('[data-apd-slogan-max]').forEach(function (row) {
			row.hidden = !english;
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = !english;
			});
		});
		var fillTypes = (window.apdAdmin && apdAdmin.initialFillTypes) || [];
		var fillOn = fillTypes.indexOf(type) !== -1;
		document.querySelectorAll('[data-apd-initial-fill-row]').forEach(function (row) {
			row.hidden = !fillOn;
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = !fillOn;
			});
		});
		var sideLimits = type === 'us' && splitActive();
		document.querySelectorAll('[data-apd-max-sides]').forEach(function (row) {
			row.hidden = !sideLimits;
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = !sideLimits;
			});
		});
		document.querySelectorAll('[data-apd-max-single]').forEach(function (row) {
			row.hidden = hideSingle;
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = keepBoth ? false : hideSingle;
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
		document.querySelectorAll('[data-apd-max-fr]').forEach(function (row) {
			var which = row.getAttribute('data-apd-fr-max');
			var on = french && !(type === 'fr_ww' && which === '1');
			row.hidden = !on;
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = !on;
				if (on && (!input.value || input.value === '0')) {
					input.value = which === '2' ? '3' : '2';
				}
			});
		});
		var wwNote = document.querySelector('[data-apd-fr-ww-note]');
		if (wwNote) {
			wwNote.hidden = type !== 'fr_ww';
		}
		document.querySelectorAll('[data-apd-max-at]').forEach(function (row) {
			var which = row.getAttribute('data-apd-at-max');
			row.hidden = !austria;
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = !austria;
				if (austria && (!input.value || input.value === '0')) {
					input.value = which === '2' ? '6' : '2';
				}
			});
		});
		document.querySelectorAll('[data-apd-max-rows]').forEach(function (row) {
			row.hidden = !suv;
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = keepBoth ? false : !suv;
				if (suv && formatFormIsFresh() && input.hasAttribute('data-apd-typing-default')) {
					var defs = typingDefaults(type);
					input.value = input.id === 'apd_format_row_2_max' ? String(defs[1]) : String(defs[0]);
				} else if (suv && (!input.value || input.value === '0')) {
					input.value = input.id === 'apd_format_row_2_max' ? '4' : '5';
				}
			});
		});
		var embossed = lettersFollowCount(type);
		document.querySelectorAll('[data-apd-embossed-line]').forEach(function (row) {
			var on = embossed && !suv;
			row.hidden = !on;
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = !on;
				if (on && (!input.value || input.value === '0')) {
					input.value = '9';
				}
			});
		});
		document.querySelectorAll('[data-apd-embossed-rows]').forEach(function (row) {
			row.hidden = !(embossed && suv);
			row.querySelectorAll('input').forEach(function (input) {
				input.disabled = !(embossed && suv);
				if (embossed && suv && (!input.value || input.value === '0')) {
					input.value = input.id === 'apd_format_embossed_row_2' ? '4' : '5';
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

	function formatHasAssignedFrame() {
		var box = document.querySelector('[data-apd-no-frame]');
		if (!box || box.disabled || box.checked) {
			return false;
		}
		var width = document.querySelector('[name="apd_format[border_width]"]');
		return !!width && !width.disabled && (parseInt(width.value, 10) || 0) > 0;
	}

	function formatSplitsTextFrame() {
		var box = document.querySelector('[data-apd-separate-text-frame]');
		return !!(box && !box.disabled && box.checked);
	}

	function toggleSeparateTextFrame(type) {
		var row = document.querySelector('[data-apd-separate-text-frame-row]');
		if (!row) {
			return;
		}
		var on = !!typeCaps(type).sharesTextFrame && formatHasAssignedFrame();
		row.hidden = !on;
		row.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
			if (!typeCaps(type).sharesTextFrame) {
				input.checked = false;
			}
			input.disabled = !on;
		});
	}

	function formatSharesTextFrame(type) {
		return !!typeCaps(type).sharesTextFrame && formatHasAssignedFrame() && !formatSplitsTextFrame();
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

	function englishFrameMm(type) {
		return (type === 'uk' || type === 'uk_plain') ? 1.4 : 0;
	}

	function englishFrameMarginMm(type) {
		return (type === 'uk' || type === 'uk_plain') ? 7 : 0;
	}

	function englishBandFrame(type) {
		if (type !== 'uk') {
			return null;
		}
		var band = readBandBox();
		if (!band || !(band.width > 0)) {
			return null;
		}
		if ((band.x + band.width / 2) >= 50) {
			return { side: 'right', edge: band.x };
		}
		return { side: 'left', edge: band.x + band.width };
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
		var fixed = englishFrameMm(type);
		if (isNaN(bw) || bw < 0) {
			bw = 0;
		}
		if (fixed > 0) {
			bw = fixed;
		}
		var hide = '' === type || isHolderType(type) || (fixed <= 0 && (wantsNoFrame() || bw <= 0));
		if (box) {
			box.hidden = hide || fixed > 0;
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
		var frameMargin = englishFrameMarginMm(type);
		if (fill) {
			if (frameMargin > 0 && mm.width > 0 && mm.height > 0) {
				var mx = (frameMargin / mm.width) * 100;
				var my = (frameMargin / mm.height) * 100;
				var leftPct = mx;
				var rightPct = mx;
				var bandFrame = englishBandFrame(type);
				if (bandFrame && bandFrame.side === 'left') {
					leftPct = bandFrame.edge + mx;
				} else if (bandFrame && bandFrame.side === 'right') {
					rightPct = Math.max(0, 100 - bandFrame.edge) + mx;
				}
				fill.style.left = leftPct + '%';
				fill.style.top = my + '%';
				fill.style.right = rightPct + '%';
				fill.style.bottom = my + '%';
				fill.style.borderTopLeftRadius = '';
				fill.style.borderBottomLeftRadius = '';
				fill.style.borderTopRightRadius = '';
				fill.style.borderBottomRightRadius = '';
			} else {
				fill.style.left = '';
				fill.style.top = '';
				fill.style.right = '';
				fill.style.bottom = '';
				fill.style.borderTopLeftRadius = '';
				fill.style.borderBottomLeftRadius = '';
				fill.style.borderTopRightRadius = '';
				fill.style.borderBottomRightRadius = '';
			}
		}
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
		if (isCompactPlate(type) && mm.width > 0 && mm.height > 0) {
			var radiusMm = Math.min(10, mm.height * 0.12);
			stage.style.borderRadius = (radiusMm / mm.width * 100).toFixed(3) + '% / ' + (radiusMm / mm.height * 100).toFixed(3) + '%';
		} else {
			stage.style.borderRadius = '';
		}
		var studio = stage.closest('[data-apd-plate-studio]');
		if (studio) {
			var previewMax = 500;
			var basis = 0;
			if (type === 'moto' || type === 'moto_240' || type === 'moto_plain' || type === 'moto_plain_240') {
				basis = 199;
			} else if (type === 'suv' || type === 'suv_340' || type === 'suv_eu' || type === 'suv_eu_340') {
				basis = 280;
			} else if (type === 'us') {
				basis = 305;
			} else if (type === 'eu_300' || type === 'atyp_300') {
				basis = 199;
			} else if (isCompactPlate(type)) {
				basis = 520;
			}
			if (basis && mm.width > 0) {
				previewMax = Math.max(80, Math.round(500 * (mm.width / basis)));
			} else if (isPhotoHolder(type) && mm.width > 0) {
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
			fitSloganLabel();
			return;
		}
		if (schematic) {
			schematic.hidden = false;
			schematic.classList.toggle('is-austria', type === 'austria');
			schematic.style.backgroundColor = type === 'fr_ww' ? '#F19DC4' : '';
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
		fitSloganLabel();
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

	function paintPresetPreview(img) {
		if (!img || img.dataset.apdClean === '1') {
			return;
		}
		function drawClean() {
			if (!img.naturalWidth || !img.parentNode) {
				return;
			}
			var cssW = img.clientWidth || Math.min(220, img.naturalWidth);
			var cssH = img.clientHeight || Math.max(1, Math.round(cssW * img.naturalHeight / img.naturalWidth));
			if (cssW < 2 || cssH < 2) {
				return;
			}
			var canvas = img.parentNode.querySelector('canvas.apd-preset-clean');
			if (!canvas) {
				canvas = document.createElement('canvas');
				canvas.className = 'apd-preset-clean';
				img.parentNode.insertBefore(canvas, img);
			}
			var dpr = window.devicePixelRatio || 1;
			canvas.width = Math.max(1, Math.round(cssW * dpr));
			canvas.height = Math.max(1, Math.round(cssH * dpr));
			canvas.style.width = cssW + 'px';
			canvas.style.height = cssH + 'px';
			var ctx = canvas.getContext('2d');
			ctx.setTransform(canvas.width / cssW, 0, 0, canvas.height / cssH, 0, 0);
			ctx.imageSmoothingEnabled = true;
			if ('imageSmoothingQuality' in ctx) {
				ctx.imageSmoothingQuality = 'high';
			}
			ctx.clearRect(0, 0, cssW, cssH);
			var source = euroBandImage(img);
			var sw = source.naturalWidth || source.width;
			var sh = source.naturalHeight || source.height;
			var scale = Math.min(cssW / sw, cssH / sh);
			var tw = sw * scale;
			var th = sh * scale;
			ctx.drawImage(source, (cssW - tw) / 2, (cssH - th) / 2, tw, th);
			img.dataset.apdClean = '1';
			img.hidden = true;
		}
		if (img.complete && img.naturalWidth) {
			drawClean();
		} else {
			img.addEventListener('load', drawClean);
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
				if (field.querySelector('[name="apd_preset[image_id]"]')) {
					paintPresetPreview(img);
				}
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
		if (event.target && event.target.getAttribute('name') === 'apd_format[text_rows]') {
			var rowType = currentFormatType();
			toggleSuvCharLimits(rowType);
			toggleRowStyles(rowType);
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
			toggleFormatPalettes(currentFormatType());
		}
		if (event.target && event.target.hasAttribute('data-apd-separate-text-frame')) {
			toggleFormatPalettes(currentFormatType());
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
		if (event.target && event.target.getAttribute('name') === 'apd_format[border_width]') {
			toggleFormatPalettes(currentFormatType());
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
		if ((type === 'fr' || type === 'fr_ww' || type === 'fr_region' || type === 'austria') && mm.width > 0 && mm.height > 0) {
			var gapX = (TEXT_FRAME_GAP_MM / mm.width) * 100;
			var gapY = (TEXT_FRAME_GAP_MM / mm.height) * 100;
			region = {
				x: gapX,
				y: gapY,
				width: 100 - gapX * 2,
				height: 100 - gapY * 2
			};
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
			var bandBtn = event.target && event.target.closest('[data-apd-center-band]');
			if (!bandBtn) {
				return;
			}
			event.preventDefault();
			var band = readBandBox();
			band.y = Math.round(((100 - band.height) / 2) * 10) / 10;
			if (band.y < 0) {
				band.y = 0;
			}
			writeBandBox(band);
		});

		document.addEventListener('click', function (event) {
			var btn = event.target && event.target.closest('[data-apd-center-text-box]');
			if (!btn) {
				return;
			}
			event.preventDefault();
			if (!splitActive()) {
				var extraKeys = [];
				document.querySelectorAll('[data-apd-extra-kind="text"]').forEach(function (el) {
					if (!el.hidden) {
						extraKeys.push(el.getAttribute('data-apd-extra-box'));
					}
				});
				if (!extraKeys.length) {
					writeTextBox(centerBoxInRegion(readTextBox(), computeTextAreaRegion()));
					return;
				}
				var groupRegion = computeTextAreaRegion();
				var groupBoxes = [readTextBox()];
				extraKeys.forEach(function (key) {
					groupBoxes.push(readExtraBox(key));
				});
				var unionLeft = groupBoxes[0].x;
				var unionRight = groupBoxes[0].x + groupBoxes[0].width;
				var unionTop = groupBoxes[0].y;
				var unionBottom = groupBoxes[0].y + groupBoxes[0].height;
				groupBoxes.forEach(function (item) {
					unionLeft = Math.min(unionLeft, item.x);
					unionRight = Math.max(unionRight, item.x + item.width);
					unionTop = Math.min(unionTop, item.y);
					unionBottom = Math.max(unionBottom, item.y + item.height);
				});
				var groupShiftX = (groupRegion.x + (groupRegion.width - (unionRight - unionLeft)) / 2) - unionLeft;
				var groupShiftY = (groupRegion.y + (groupRegion.height - (unionBottom - unionTop)) / 2) - unionTop;
				groupBoxes[0].x += groupShiftX;
				groupBoxes[0].y += groupShiftY;
				writeTextBox(groupBoxes[0]);
				extraKeys.forEach(function (key, index) {
					groupBoxes[index + 1].x += groupShiftX;
					groupBoxes[index + 1].y += groupShiftY;
					writeExtraBox(key, groupBoxes[index + 1]);
				});
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
			if (event.target && event.target.closest('[data-apd-extra-box]')) {
				return;
			}
			if (event.target && event.target.getAttribute('data-apd-handle')) {
				startDrag(event, event.target.getAttribute('data-apd-handle'), 'left');
				return;
			}
			startDrag(event, '', 'left');
		});
		bindExtraBoxes(stage);

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

	function fitBandCorners(el) {
		var stage = el.parentElement;
		var graphic = el.querySelector('img, .apd-band-box-fill');
		if (!stage || !graphic) {
			return;
		}
		var radius = window.getComputedStyle(stage).borderTopLeftRadius || '0px';
		var parts = String(radius).split(/\s+/);
		var width = stage.clientWidth || 0;
		var height = stage.clientHeight || 0;
		function radiusPx(token, size) {
			var n = parseFloat(token) || 0;
			if (String(token).indexOf('%') !== -1) {
				return size * n / 100;
			}
			return n;
		}
		var curve = radiusPx(parts[0], width) + 'px ' + radiusPx(parts[1] || parts[0], height) + 'px';
		var leftPct = parseFloat(el.style.left) || 0;
		var widthPct = parseFloat(el.style.width) || 0;
		var onRight = (leftPct + widthPct / 2) >= 50;
		var outer = curve;
		var flat = '0';
		graphic.style.borderTopLeftRadius = onRight ? flat : outer;
		graphic.style.borderBottomLeftRadius = onRight ? flat : outer;
		graphic.style.borderTopRightRadius = onRight ? outer : flat;
		graphic.style.borderBottomRightRadius = onRight ? outer : flat;
		el.style.borderTopLeftRadius = graphic.style.borderTopLeftRadius;
		el.style.borderBottomLeftRadius = graphic.style.borderBottomLeftRadius;
		el.style.borderTopRightRadius = graphic.style.borderTopRightRadius;
		el.style.borderBottomRightRadius = graphic.style.borderBottomRightRadius;
		paintAdminEuroBand(graphic);
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

	function paintAdminEuroBand(graphic) {
		if (!graphic || graphic.tagName !== 'IMG') {
			return;
		}
		var box = graphic.parentElement;
		if (!box) {
			return;
		}
		var canvas = box.querySelector('canvas.apd-band-euro');
		if (!graphic.naturalWidth) {
			if (canvas) {
				canvas.hidden = true;
			}
			graphic.style.visibility = '';
			if (!graphic.dataset.apdEuroLoad) {
				graphic.dataset.apdEuroLoad = '1';
				graphic.addEventListener('load', function () {
					fitBandCorners(box);
				});
			}
			return;
		}
		if (!canvas) {
			canvas = document.createElement('canvas');
			canvas.className = 'apd-band-euro';
			box.insertBefore(canvas, graphic);
		}
		var w = box.clientWidth;
		var h = box.clientHeight;
		if (!(w > 0) || !(h > 0)) {
			return;
		}
		var dpr = window.devicePixelRatio || 1;
		var bufW = Math.max(1, Math.round(w * dpr));
		var bufH = Math.max(1, Math.round(h * dpr));
		if (canvas.width !== bufW || canvas.height !== bufH) {
			canvas.width = bufW;
			canvas.height = bufH;
		}
		var ctx = canvas.getContext('2d');
		ctx.setTransform(bufW / w, 0, 0, bufH / h, 0, 0);
		ctx.clearRect(0, 0, w, h);
		ctx.fillStyle = '#003399';
		ctx.fillRect(0, 0, w, h);
		drawEuroBand(ctx, graphic, 0, 0, w, h);
		canvas.style.borderTopLeftRadius = graphic.style.borderTopLeftRadius;
		canvas.style.borderTopRightRadius = graphic.style.borderTopRightRadius;
		canvas.style.borderBottomRightRadius = graphic.style.borderBottomRightRadius;
		canvas.style.borderBottomLeftRadius = graphic.style.borderBottomLeftRadius;
		canvas.hidden = false;
		graphic.style.visibility = 'hidden';
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
		fitBandCorners(el);
		var mirror = document.querySelector('[data-apd-band-mirror]');
		var regionCode = document.querySelector('[data-apd-extra-box="fr_region_code"]');
		var mirrored = currentFormatType() === 'fr_region';
		if (mirror) {
			mirror.hidden = !mirrored || el.hidden;
			mirror.style.left = String(Math.max(0, 100 - box.x - box.width)) + '%';
			mirror.style.top = String(box.y) + '%';
			mirror.style.width = String(box.width) + '%';
			mirror.style.height = String(box.height) + '%';
			fitBandCorners(mirror);
		}
		if (regionCode && mirrored) {
			regionCode.style.left = String(Math.max(0, 100 - box.x - box.width)) + '%';
			regionCode.style.width = String(box.width) + '%';
		}
		if (isSuvEuro(currentFormatType())) {
			renderPlateSample();
		}
		if (currentFormatType() === 'uk') {
			applyFrameOverlay();
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
		window.requestAnimationFrame(function () {
			applyBandOverlay(readBandBox());
		});

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
		document.addEventListener('change', function (event) {
			if (event.target && event.target.hasAttribute('data-apd-preset-mode')) {
				syncPresetChoice();
			}
			if (event.target && event.target.closest && event.target.closest('[data-apd-preset-list]') && event.target.type === 'checkbox') {
				syncDefaultPreset();
			}
		});
		toggleFormatFields();
		toggleProductColorFields();
		toggleProductScripts();
		toggleProductTextRows();
		toggleProductDesign();
		toggleProductPreset();
		toggleProductOptions();
		toggleDesignFormats();
		toggleSwatchRadius();
		bindTextBoxEditor();
		bindSelectAllFonts();
		bindBandBoxEditor();
		bindFrameEditor();
		bindPresetOrder();
		bindFormatOrder();
		syncFrameFields();
		updateFormatStage();
		document.querySelectorAll('[name="apd_preset[image_id]"]').forEach(function (input) {
			var field = closest(input, '.apd-media-field');
			var previewImg = field ? field.querySelector('.apd-media-preview img') : null;
			if (previewImg) {
				paintPresetPreview(previewImg);
			}
		});
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

	function toggleProductPreset() {
		var formatSelect = document.getElementById('apd_format_id');
		var field = document.querySelector('[data-apd-product-preset]');
		var select = document.getElementById('apd_default_preset_id');
		var data = document.getElementById('apd_product_preset_choices');
		if (!formatSelect || !field || !select || !data) {
			return;
		}
		var map;
		try {
			map = JSON.parse(data.textContent || '{}');
		} catch (error) {
			return;
		}
		if (!map || typeof map !== 'object') {
			return;
		}
		var rows = map[formatSelect.value] || [];
		var current = select.value;
		var blank = select.getAttribute('data-apd-blank') || '';
		select.textContent = '';
		var empty = document.createElement('option');
		empty.value = '';
		empty.textContent = blank;
		select.appendChild(empty);
		rows.forEach(function (row) {
			if (!row || !row.id) {
				return;
			}
			var option = document.createElement('option');
			option.value = String(row.id);
			option.textContent = row.label || row.id;
			select.appendChild(option);
		});
		var known = rows.some(function (row) {
			return row && String(row.id) === current;
		});
		select.value = known ? current : '';
		field.hidden = rows.length < 1;
		select.disabled = rows.length < 1;
	}

	function bindPresetOrder() {
		bindRowOrder('[data-apd-preset-sortable]');
	}

	function bindFormatOrder() {
		bindRowOrder('[data-apd-format-sortable]');
	}

	function bindRowOrder(selector) {
		var body = document.querySelector(selector);
		if (!body || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.sortable) {
			return;
		}
		window.jQuery(body).sortable({
			items: '> tr',
			axis: 'y',
			handle: '.apd-preset-handle',
			helper: function (event, row) {
				row.children().each(function () {
					window.jQuery(this).width(window.jQuery(this).width());
				});
				return row;
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
		if (type === 'fr' || type === 'fr_ww' || type === 'fr_region') {
			return 'french';
		}
		if (type === 'austria') {
			return 'austria';
		}
		if (type === 'moto' || type === 'moto_240' || type === 'moto_plain' || type === 'moto_plain_240' || type === 'suv' || type === 'suv_340' || type === 'suv_eu' || type === 'suv_eu_340') {
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
		var french = document.querySelector('[data-apd-default-french]');
		var austria = document.querySelector('[data-apd-default-austria]');
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
		if (french) {
			french.hidden = mode !== 'french';
			var regionOption = select.options[select.selectedIndex];
			var regionRow = french.querySelector('[data-apd-fr-region]');
			var regionType = regionOption && regionOption.getAttribute('data-apd-type') === 'fr_region';
			if (regionRow) {
				regionRow.hidden = !(mode === 'french' && regionType);
				regionRow.querySelectorAll('input').forEach(function (input) {
					input.disabled = !(mode === 'french' && regionType);
				});
			}
		}
		if (austria) {
			austria.hidden = mode !== 'austria';
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
		if (french) {
			var option = select.options[select.selectedIndex];
			var temp = option && option.getAttribute('data-apd-type') === 'fr_ww';
			var first = french.querySelector('[data-apd-fr-field="1"]');
			var fixed = french.querySelector('[data-apd-fr-fixed]');
			if (first) {
				first.hidden = mode === 'french' && temp;
				first.querySelectorAll('input').forEach(function (input) {
					input.disabled = mode !== 'french' || temp;
					if (option && option.getAttribute('data-apd-g1')) {
						input.maxLength = parseInt(option.getAttribute('data-apd-g1'), 10) || input.maxLength;
					}
				});
			}
			if (fixed) {
				fixed.hidden = !(mode === 'french' && temp);
			}
			french.querySelectorAll('#apd_default_fr_2, #apd_default_fr_3').forEach(function (input) {
				input.disabled = mode !== 'french';
				var attr = input.id === 'apd_default_fr_2' ? 'data-apd-g2' : 'data-apd-g3';
				if (option && option.getAttribute(attr)) {
					input.maxLength = parseInt(option.getAttribute(attr), 10) || input.maxLength;
				}
			});
		}
		if (austria) {
			var atOption = select.options[select.selectedIndex];
			austria.querySelectorAll('input').forEach(function (input) {
				input.disabled = mode !== 'austria';
				var atAttr = input.id === 'apd_default_at_1' ? 'data-apd-g1' : 'data-apd-g2';
				if (atOption && atOption.getAttribute(atAttr)) {
					input.maxLength = parseInt(atOption.getAttribute(atAttr), 10) || input.maxLength;
				}
			});
		}
		if (mode === 'sides') {
			applySideLimits();
		}
		syncProductScriptFields();
		var slogan = document.querySelector('[data-apd-default-slogan]');
		if (slogan) {
			var sloganOption = select.options[select.selectedIndex];
			var sloganType = sloganOption ? sloganOption.getAttribute('data-apd-type') || '' : '';
			var english = sloganType === 'uk' || sloganType === 'uk_plain';
			slogan.hidden = !english;
			slogan.querySelectorAll('input').forEach(function (input) {
				input.disabled = !english;
				if (english && sloganOption && sloganOption.getAttribute('data-apd-slogan-max')) {
					input.maxLength = parseInt(sloganOption.getAttribute('data-apd-slogan-max'), 10) || input.maxLength;
				}
			});
		}
		var fillRow = document.querySelector('[data-apd-default-fill]');
		if (fillRow && select) {
			var fillOption = select.options[select.selectedIndex];
			var fillOpen = !!(fillOption && fillOption.getAttribute('data-apd-initial-fill') === '1');
			fillRow.hidden = !fillOpen;
			var fillSelect = fillRow.querySelector('select');
			if (fillSelect) {
				fillSelect.disabled = !fillOpen;
				var fillFormat = fillOption ? fillOption.value : '';
				if (fillOpen && fillRow.getAttribute('data-apd-fill-format') !== fillFormat) {
					var fillColors = [];
					try {
						fillColors = JSON.parse(fillOption.getAttribute('data-apd-fills') || '[]');
					} catch (fillError) {
						fillColors = [];
					}
					var fillCurrent = fillSelect.value;
					var fillBlank = fillSelect.querySelector('option[value=""]');
					var fillBlankLabel = fillBlank ? fillBlank.textContent : 'Default';
					fillSelect.innerHTML = '';
					var fillEmpty = document.createElement('option');
					fillEmpty.value = '';
					fillEmpty.textContent = fillBlankLabel;
					fillSelect.appendChild(fillEmpty);
					fillColors.forEach(function (color) {
						if (!color || !color.hex) {
							return;
						}
						var fillChoice = document.createElement('option');
						fillChoice.value = color.hex;
						fillChoice.textContent = color.label || color.hex;
						if (String(color.hex).toUpperCase() === String(fillCurrent).toUpperCase()) {
							fillChoice.selected = true;
						}
						fillSelect.appendChild(fillChoice);
					});
					fillRow.setAttribute('data-apd-fill-format', fillFormat);
				}
			}
		}
	}

	function toggleProductScripts() {
		var select = document.getElementById('apd_format_id');
		var panel = document.querySelector('[data-apd-script-panel]');
		if (!select || !panel) {
			return;
		}
		var option = select.options[select.selectedIndex];
		var open = !!(option && option.getAttribute('data-apd-scripts') === '1');
		panel.hidden = !open;
		panel.querySelectorAll('input').forEach(function (input) {
			input.disabled = !open;
		});
		syncProductScriptFields();
		if (!open || !option) {
			return;
		}
		var typeName = option.getAttribute('data-apd-type') || '';
		var holderType = typeName === 'holder' || typeName === 'holder_moto' || typeName === 'holder_d';
		var all = holderType || option.getAttribute('data-apd-fonts-all') === '1';
		var ids = (option.getAttribute('data-apd-font-ids') || '').split(',').filter(Boolean);
		panel.querySelectorAll('[data-apd-product-font]').forEach(function (label) {
			var id = label.getAttribute('data-apd-product-font') || '';
			var show = all || ids.indexOf(id) !== -1;
			label.hidden = !show;
			var input = label.querySelector('input');
			if (!input) {
				return;
			}
			input.disabled = !show;
			if (!show) {
				input.checked = false;
			}
		});
		syncProductScriptFields();
	}

	function syncProductScriptFields() {
		var panel = document.querySelector('[data-apd-script-panel]');
		var latin = document.querySelector('[name="apd_script_latin"]');
		var cyr = document.querySelector('[name="apd_script_cyrillic"]');
		var open = !!(panel && !panel.hidden && latin && cyr);
		var formatSelect = document.getElementById('apd_format_id');
		var formatOption = formatSelect && formatSelect.options[formatSelect.selectedIndex];
		var formatType = formatOption ? formatOption.getAttribute('data-apd-type') || '' : '';
		var holder = formatType === 'holder' || formatType === 'holder_moto' || formatType === 'holder_d';
		var holderChoice = 'latin';
		var holderPicked = document.querySelector('[name="apd_holder_script"]:checked');
		if (holderPicked && holderPicked.value) {
			holderChoice = holderPicked.value;
		}
		var both = open && !holder && latin.checked && cyr.checked;
		var onlyCyr = open && (holder ? holderChoice === 'cyrillic' : (cyr.checked && !latin.checked));
		document.querySelectorAll('[data-apd-latin-only]').forEach(function (el) {
			el.hidden = both || onlyCyr;
		});
		var rowHost = document.querySelector('[data-apd-default-rows]');
		var singleHost = document.querySelector('[data-apd-default-single]');
		document.querySelectorAll('[data-apd-latin-both]').forEach(function (el) {
			el.hidden = el.tagName === 'P' ? (!both || !rowHost || rowHost.hidden) : !both;
		});
		document.querySelectorAll('[data-apd-cyr-only]').forEach(function (el) {
			el.hidden = !onlyCyr;
		});
		document.querySelectorAll('[data-apd-cyrillic-defaults]').forEach(function (box) {
			var kind = box.getAttribute('data-apd-cyrillic-defaults');
			var host = kind === 'rows' ? rowHost : singleHost;
			var show = both && host && !host.hidden;
			box.hidden = !show;
			box.querySelectorAll('input').forEach(function (input) {
				input.disabled = !show;
			});
		});
		var help = document.querySelector('[data-apd-script-text-help]');
		if (help) {
			help.hidden = !both;
		}
		document.querySelectorAll('[data-apd-plate-scripts]').forEach(function (el) {
			el.hidden = holder;
			el.querySelectorAll('input').forEach(function (input) {
				input.disabled = holder || !open;
			});
		});
		var latinFonts = holder ? (holderChoice === 'latin' || holderChoice === 'combined') : !!(latin && latin.checked);
		var cyrFonts = holder ? (holderChoice === 'cyrillic' || holderChoice === 'combined') : !!(cyr && cyr.checked);
		document.querySelectorAll('[data-apd-script-font-block]').forEach(function (block) {
			var key = block.getAttribute('data-apd-script-font-block');
			var show = open && (key === 'latin' ? latinFonts : cyrFonts);
			block.hidden = !show;
			block.querySelectorAll('input').forEach(function (input) {
				var fontLabel = input.closest('[data-apd-product-font]');
				input.disabled = !show || !!(fontLabel && fontLabel.hidden);
			});
		});
		var latinNote = document.querySelector('[data-apd-latin-font-note]');
		if (latinNote) {
			latinNote.hidden = holder && holderChoice === 'combined';
		}
		var combinedNote = document.querySelector('[data-apd-combined-font-note]');
		if (combinedNote) {
			combinedNote.hidden = !(holder && open && holderChoice === 'combined');
		}
		document.querySelectorAll('[data-apd-holder-only]').forEach(function (el) {
			el.hidden = !holder;
			el.querySelectorAll('input').forEach(function (input) {
				if (!holder || !open) {
					input.disabled = true;
					return;
				}
				var fontLabel = input.closest('[data-apd-product-font]');
				if (fontLabel && fontLabel.hidden) {
					return;
				}
				input.disabled = false;
			});
		});
		document.querySelectorAll('[data-apd-plate-script-note]').forEach(function (el) {
			el.hidden = holder;
		});
		var combinedOn = !!(holder && open && holderChoice === 'combined');
		var singleLine = !!(singleHost && rowHost && rowHost.hidden);
		var onlyCombined = combinedOn;
		var namedLatin = false;
		document.querySelectorAll('[data-apd-default-single]').forEach(function (el) {
			if (!holder || !singleLine) {
				return;
			}
			el.hidden = onlyCombined;
			el.querySelectorAll('input').forEach(function (input) {
				input.disabled = onlyCombined;
			});
		});
		document.querySelectorAll('[data-apd-holder-latin]').forEach(function (el) {
			el.hidden = !namedLatin;
		});
		document.querySelectorAll('[data-apd-latin-only]').forEach(function (el) {
			if (namedLatin || onlyCombined) {
				el.hidden = true;
			}
		});
		document.querySelectorAll('[data-apd-combined-defaults]').forEach(function (box) {
			var show = combinedOn && singleLine;
			box.hidden = !show;
			box.querySelectorAll('input').forEach(function (input) {
				input.disabled = !show;
			});
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
			toggleProductPreset();
			toggleProductTextRows();
			toggleProductScripts();
		}
		if (event.target.id === 'apd_design_id') {
			toggleProductTextRows();
		}
		if (event.target.name === 'apd_enabled') {
			toggleProductOptions();
		}
		if (event.target.name === 'apd_script_latin' || event.target.name === 'apd_script_cyrillic' || event.target.name === 'apd_holder_script') {
			syncProductScriptFields();
		}
	});

	document.addEventListener('click', function (event) {
		var button = event.target && event.target.closest ? event.target.closest('[data-apd-color-move]') : null;
		if (!button) {
			return;
		}
		var row = button.closest('[data-apd-color-row]');
		var list = row ? row.parentNode : null;
		if (!row || !list) {
			return;
		}
		var direction = button.getAttribute('data-apd-color-move');
		var sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
		if (!sibling) {
			return;
		}
		if (direction === 'up') {
			list.insertBefore(row, sibling);
		} else {
			list.insertBefore(sibling, row);
		}
	});
})();
