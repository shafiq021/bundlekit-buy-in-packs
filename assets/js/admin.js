/* BundleKit – Buy in Packs — tiers repeater + pack-source toggle.
 *
 * The repeater is prefix-agnostic: any table with [data-tiers-table]
 * and [data-prefix] works, so the same script drives the product
 * editor table ("bundlekit_packs") and the global Settings page table
 * ("bundlekit_global_packs"). Row indexes are renumbered whenever a
 * row is removed (and on #post submit), and the Default radio always
 * points at the right row.
 *
 * The product editor also shows the per-product pack fields only when
 * "Custom packs for this product" is picked as the pack source.
 */
(function ($) {
	'use strict';

	function renumber($table) {
		var prefix = $table.data('prefix');
		$table.find('tbody tr').each(function (i) {
			var $row = $(this);
			$row.find('input[name^="' + prefix + '_tiers"]').each(function () {
				var name = $(this).attr('name');
				$(this).attr('name', name.replace(new RegExp(prefix + '_tiers\\[\\d+\\]'), prefix + '_tiers[' + i + ']'));
			});
			$row.find('input[type="radio"][name="' + prefix + '_default"]').val(i);
		});
	}

	function rowHtml(prefix, i, discount) {
		return '<tr>' +
			'<td><input type="number" min="1" step="1" name="' + prefix + '_tiers[' + i + '][qty]" value="1" class="small-text" /></td>' +
			'<td><input type="number" min="0" max="100" step="any" name="' + prefix + '_tiers[' + i + '][discount]" value="' + discount + '" class="small-text" /></td>' +
			'<td><input type="text" name="' + prefix + '_tiers[' + i + '][badge]" value="" placeholder="Most Popular" /></td>' +
			'<td><input type="text" name="' + prefix + '_tiers[' + i + '][label]" value="" placeholder="e.g. Single" /></td>' +
			'<td><input type="text" name="' + prefix + '_tiers[' + i + '][desc]" value="" placeholder="Perfect for everyday use." /></td>' +
			'<td><input type="radio" name="' + prefix + '_default" value="' + i + '"' + (i === 0 ? ' checked' : '') + ' /></td>' +
			'<td><button type="button" class="button" data-tiers-remove>&times;</button></td>' +
			'</tr>';
	}

	$(document).on('click', '[data-tiers-add]', function (e) {
		e.preventDefault();
		var $table = $('#' + $(this).data('tiers-add'));
		if (!$table.length) {
			return;
		}
		var prefix = $table.data('prefix');
		var i = $table.find('tbody tr').length;
		var discount = $table.data('default-discount');
		if (discount === undefined || discount === '') {
			discount = 10;
		}
		$table.find('tbody').append(rowHtml(prefix, i, discount));
	});

	$(document).on('click', '[data-tiers-remove]', function (e) {
		e.preventDefault();
		var $table = $(this).closest('table[data-tiers-table]');
		$(this).closest('tr').remove();
		if ($table.length) {
			renumber($table);
		}
	});

	// Keep indexes clean if rows were removed and the product is saved.
	$(document).on('submit', '#post', function () {
		$('table[data-tiers-table]').each(function () {
			renumber($(this));
		});
	});

	// Product editor: per-product pack fields are only relevant when
	// "Custom packs for this product" is the pack source.
	function syncMode() {
		var $sel = $('#bundlekit_packs_mode');
		if (!$sel.length) {
			return;
		}
		$('#bundlekit-packs-custom-fields').toggleClass('bkp-hidden', $sel.val() !== 'custom');
	}
	$(document).on('change', '#bundlekit_packs_mode', syncMode);
	$(syncMode);
})(jQuery);
