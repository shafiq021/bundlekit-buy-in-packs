/* BundleKit – Buy in Packs — Styling Controls live preview (1.5.1).
 * Schema-driven: every control updates the matching CSS variable on
 * ALL preview widgets instantly (one per layout), using the localized
 * bundlekitPacksStyling.varMap (key -> {var, type, unit}).
 * Clearing a control restores its schema default (localized as
 * bundlekitPacksStyling.defaults) — exactly like the real widget.
 * Show/hide checkboxes toggle the same bkp-no-* classes the frontend
 * uses (localized as bundlekitPacksStyling.classToggles).
 * The layout picker switches the visible preview layout AND shows
 * only the controls relevant to that layout (data-layouts).
 * "Reset to layout defaults" fills the form from the localized
 * per-layout presets.
 */
(function ($) {
	'use strict';

	function data() {
		return window.bundlekitPacksStyling || {};
	}

	function cssValue(key, spec, raw) {
		var d = data();
		raw = (raw === undefined || raw === null) ? '' : raw.toString().trim();
		if ('' === raw) {
			raw = (d.defaults && d.defaults[key] !== undefined) ? d.defaults[key].toString() : '';
		}
		if ('' === raw) { return { remove: true }; }
		// 0 means "unset" for these keys (mirrors PHP style_attr()).
		// Without this the preview collapses: --bk-widget-max-width:0px
		// squeezes every widget to zero width on page load.
		var skipZero = d.skipZero || [];
		if (skipZero.indexOf(key) !== -1) {
			var zn = parseFloat(raw);
			if (!isNaN(zn) && zn === 0) { return { remove: true }; }
		}
		if ('number' === spec.type) {
			var n = parseFloat(raw);
			if (isNaN(n)) { return { remove: true }; }
			return { value: (n === Math.floor(n) ? n : n) + (spec.unit || '') };
		}
		if ('font' === spec.type) {
			var fonts = d.fonts || {};
			return fonts[raw] ? { value: fonts[raw] } : { remove: true };
		}
		return { value: raw };
	}

	// Checkbox toggles render without an id; fall back to the name.
	function fieldInput(key) {
		var $input = $('#bk_style_' + key);
		if (!$input.length) {
			$input = $('[name="bk_style[' + key + ']"]');
		}
		return $input;
	}

	function refresh() {
		var d = data();
		var map = d.varMap || {};
		var toggles = d.classToggles || {};
		var $previews = $('.bundlekit-preview-box [data-bundlekit-packs]');
		if (!$previews.length) { return; }
		$.each(map, function (key, spec) {
			var $input = fieldInput(key);
			var raw = $input.val();
			// Column widths become per-column vars, like PHP style_attr().
			var out = cssValue(key, spec, raw);
			$previews.each(function () {
				if (out.remove) {
					this.style.removeProperty(spec.var);
				} else {
					this.style.setProperty(spec.var, out.value);
				}
			});
		});
		// Show/hide checkboxes -> bkp-no-* classes on the previews.
		$.each(toggles, function (key, cls) {
			var $input = fieldInput(key);
			var on = $input.length ? $input.is(':checked') : true;
			$previews.each(function () {
				$(this).toggleClass(cls, !on);
			});
		});
		syncBehavioral();
	}

	// Controls with no CSS variable that still change the preview's
	// markup: OR text, footer text.
	function syncBehavioral() {
		var $box = $('.bundlekit-preview-box');
		if (!$box.length) { return; }
		function val(key) {
			var $i = fieldInput(key);
			return $i.length ? $i.val() : null;
		}
		var orText = val('or_text');
		if (orText) { $box.find('.bkp-or span').text(orText); }
		var hint = val('hint_text');
		if (hint !== null) { $box.find('.bundlekit-packs-hint').text(hint); }
		// Behavioral classes (no CSS var): alignment, badge position
		// — mirror widget_extra_attrs() in PHP.
		var align = val('widget_align') || 'left';
		$box.find('[data-bundlekit-packs]').each(function () {
			var $w = $(this);
			$w.toggleClass('bk-align-center', align === 'center');
			$w.toggleClass('bk-align-right', align === 'right');
			$w.toggleClass('bk-popular-inline', val('popular_position') === 'inline');
		});
	}

	function currentLayout() {
		return $('#bk_style_layout').val() || 'current';
	}

	function syncLayoutPreview() {
		var layout = currentLayout();
		$('[data-preview-layout]').each(function () {
			$(this).toggleClass('is-hidden', $(this).data('preview-layout') !== layout);
		});
	}

	// Show only the controls relevant to the picked layout.
	function syncControlVisibility() {
		var layout = currentLayout();
		$('.bk-style-field').each(function () {
			var layouts = ($(this).data('layouts') || 'all').toString().split(',');
			var show = 'all' === layouts[0] || layouts.indexOf(layout) !== -1;
			$(this).css('display', show ? '' : 'none');
		});
	}

	function setInput(key, value) {
		var $input = fieldInput(key);
		if (!$input.length) { return; }
		if ($input.attr('type') === 'checkbox') {
			$input.prop('checked', 'yes' === value).trigger('change');
			return;
		}
		$input.val(value);
		if ($input.hasClass('bundlekit-color-field') && $input.wpColorPicker) {
			try { $input.wpColorPicker('color', value); } catch (e) { /* noop */ }
		}
		$input.trigger('change');
	}

	$(document).on('input change', '.bundlekit-styling-controls input, .bundlekit-styling-controls select, .bundlekit-styling-controls textarea', function () {
		refresh();
	});

	$(document).on('change', '#bk_style_layout', function () {
		syncLayoutPreview();
		syncControlVisibility();
	});

	$(document).on('click', '#bk-reset-layout-defaults', function () {
		var d = data();
		var layout = currentLayout();
		var preset = (d.presets && d.presets[layout]) || {};
		$.each(preset, function (key, value) {
			setInput(key, value);
		});
		refresh();
	});

	$(function () {
		// WP color picker on the color controls; refresh on pick/clear.
		if ($.fn.wpColorPicker) {
			$('.bundlekit-color-field').wpColorPicker({
				change: function () { setTimeout(refresh, 10); },
				clear: function () { setTimeout(refresh, 10); }
			});
		}
		syncLayoutPreview();
		syncControlVisibility();
		refresh();
	});
})(jQuery);
