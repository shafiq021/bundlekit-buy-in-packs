/* BundleKit – Buy in Packs — frontend.
 * The packs replace the product's quantity counter:
 *  - the selected pack keeps the form's own qty input in sync, so the
 *    normal Add to cart flow adds the right quantity;
 *  - the pack selection is also posted to the bundlekit_set_pack
 *    wc-ajax endpoint (session flag), covering themes whose Add to
 *    cart buttons submit via AJAX with only id + quantity;
 *  - on variable products the widget listens to WooCommerce's
 *    show_variation event and recomputes every pack price from the
 *    selected variation's price.
 * No jQuery needed; works in the admin live preview too (the preview
 * widget has data-preview="1" and no ajax URL, so the flag fetch is
 * skipped there while selection + counter keep working).
 */
(function () {
	'use strict';

	function fmt(amount) {
		var p = window.bundlekitPacks || {};
		var d = parseInt(p.decimals, 10);
		if (isNaN(d)) { d = 2; }
		var n = Number(amount);
		if (!isFinite(n)) { n = 0; }
		var parts = n.toFixed(d).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		return (p.symbol || '$') + parts.join('.');
	}

	function saveBadgeText(save) {
		var p = window.bundlekitPacks || {};
		return (p.saveBadge || 'Save %s').replace('%s', fmt(save));
	}

	function savePctText(pct) {
		var p = window.bundlekitPacks || {};
		// The % sign is appended here in JS: a literal %% in the
		// localized string would print as "10.00%%".
		return (p.savePct || 'You save %s').replace('%s', Number(pct).toFixed(2) + '%');
	}

	function totals(unit, qty, discount) {
		unit = Math.max(0, Number(unit) || 0);
		qty = Math.max(1, parseInt(qty, 10) || 1);
		discount = Math.min(100, Math.max(0, Number(discount) || 0));
		var regular = unit * qty;
		var total = regular * (100 - discount) / 100;
		total = Math.max(0, Math.min(total, regular));
		var save = regular - total;
		return {
			regular: regular,
			total: total,
			save: save,
			pct: regular > 0 ? (save / regular) * 100 : 0
		};
	}

	function paintCard(card, unit) {
		var qty, discount;
		if (card.hasAttribute('data-tier') && card.getAttribute('data-tier') === 'custom') {
			qty = clampCustom(card);
			discount = parseFloat(card.getAttribute('data-discount')) || 0;
		} else {
			qty = parseInt(card.getAttribute('data-qty'), 10) || 1;
			discount = parseFloat(card.getAttribute('data-discount')) || 0;
		}
		var t = totals(unit, qty, discount);
		var totalEl = card.querySelector('.bundlekit-pack-total');
		var regEl = card.querySelector('.bundlekit-pack-regular');
		var badgeEl = card.querySelector('.bundlekit-pack-save-badge');
		var pctEl = card.querySelector('.bundlekit-pack-savepct');
		if (totalEl) { totalEl.textContent = fmt(t.total); }
		if (regEl) {
			regEl.textContent = fmt(t.regular);
			regEl.style.display = t.save > 0 ? '' : 'none';
		}
		if (badgeEl) {
			badgeEl.textContent = saveBadgeText(t.save);
			badgeEl.style.display = t.save > 0 ? '' : 'none';
		}
		if (pctEl) {
			pctEl.textContent = savePctText(t.pct);
			pctEl.style.display = t.save > 0 ? '' : 'none';
		}
	}

	function paintAll(widget, unit) {
		var cards = widget.querySelectorAll('.bundlekit-pack-card');
		for (var i = 0; i < cards.length; i++) {
			paintCard(cards[i], unit);
		}
		widget.setAttribute('data-unit', unit);
	}

	function clampCustom(card) {
		var input = card.querySelector('.bundlekit-pack-qty');
		if (!input) { return 1; }
		var min = parseInt(card.getAttribute('data-min'), 10) || 1;
		// data-max 0 = no upper limit: the customer can buy as many
		// as they want; only the minimum is enforced.
		var max = parseInt(card.getAttribute('data-max'), 10) || 0;
		var step = parseInt(card.getAttribute('data-step'), 10) || 1;
		var v = parseInt(input.value, 10);
		if (isNaN(v)) { v = min; }
		v = Math.max(min, v);
		if (max > 0) { v = Math.min(max, v); }
		if (step > 1) {
			v = min + Math.floor((v - min) / step) * step;
		}
		if (String(v) !== String(input.value)) { input.value = v; }
		return v;
	}

	function qtyInput(widget) {
		var form = widget.closest('form.cart');
		return form ? form.querySelector('input.qty') : null;
	}

	function currentSelection(widget) {
		var checked = widget.querySelector('input.bundlekit-pack-radio:checked');
		if (!checked) { return { sel: '', qty: 0 }; }
		var sel = checked.value;
		var card = checked.closest('.bundlekit-pack-card');
		var qty = 0;
		if (card) {
			if (card.getAttribute('data-tier') === 'custom') {
				qty = clampCustom(card);
			} else {
				qty = parseInt(card.getAttribute('data-qty'), 10) || 1;
			}
		}
		return { sel: sel, qty: qty };
	}

	function syncQty(widget) {
		var s = currentSelection(widget);
		var q = qtyInput(widget);
		if (q && s.qty > 0) { q.value = s.qty; }
	}

	function postFlag(widget) {
		// The admin live preview has no ajax URL — selection and the
		// counter still work there, but there is no session to flag.
		var url = widget.getAttribute('data-ajax-url');
		if (!url) { return; }
		var s = currentSelection(widget);
		if (!s.sel) { return; }
		var body = new URLSearchParams();
		body.append('product_id', widget.getAttribute('data-product-id') || '0');
		body.append('sel', s.sel);
		body.append('custom_qty', s.sel === 'custom' ? s.qty : 0);
		body.append('nonce', widget.getAttribute('data-nonce') || '');
		fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).catch(function () { /* flag is best-effort; the form POST is the primary path */ });
	}

	function onSelectionChange(widget) {
		var cards = widget.querySelectorAll('.bundlekit-pack-card');
		for (var i = 0; i < cards.length; i++) {
			var radio = cards[i].querySelector('input.bundlekit-pack-radio');
			cards[i].classList.toggle('is-selected', !!(radio && radio.checked));
		}
		syncQty(widget);
		postFlag(widget);
		syncLayoutExtras(widget);
	}

	/**
	 * Sync the builder total bar (the layout extra that is not
	 * a pack card itself). Reads the selected card's data
	 * attributes and the live totals.
	 * attributes and the live totals.
	 */
	function syncLayoutExtras(widget) {
		var s = currentSelection(widget);
		if (!s.sel) { return; }
		var radio = widget.querySelector('input.bundlekit-pack-radio[value="' + s.sel + '"]');
		var card = radio ? radio.closest('.bundlekit-pack-card') : null;
		if (!card) { return; }
		var unit = parseFloat(widget.getAttribute('data-unit')) || 0;
		var isCustom = card.getAttribute('data-tier') === 'custom';
		var qty = isCustom ? clampCustom(card) : (parseInt(card.getAttribute('data-qty'), 10) || 1);
		var discount = parseFloat(card.getAttribute('data-discount')) || 0;
		var t = totals(unit, qty, discount);
		var name = card.getAttribute('data-name') || '';
		var desc = card.getAttribute('data-desc') || '';
		var badge = card.getAttribute('data-badge') || '';

		// Builder layout: total bar.
		var bTotal = widget.querySelector('.bkp-builder-total-price');
		if (bTotal) {
			bTotal.textContent = fmt(t.total);
			var sub = widget.querySelector('.bkp-builder-total-sub');
			if (sub) { sub.textContent = '(' + qty + ' \u00d7 ' + fmt(unit) + ')'; }
		}
	}

	function initWidget(widget) {
		var isPreview = widget.getAttribute('data-preview') === '1';

		// If a fallback hook printed the widget outside the form, move
		// it inside form.cart so the radios submit with Add to cart.
		var form = widget.closest('form.cart');
		if (!form) {
			var product = widget.closest('.product');
			form = product ? product.querySelector('form.cart') : document.querySelector('form.cart');
			if (form) {
				var qtyWrap = form.querySelector('.quantity');
				form.insertBefore(widget, qtyWrap ? qtyWrap : form.firstChild);
				form = widget.closest('form.cart');
			}
		}
		// Fallback for browsers without :has() — the CSS already hides
		// .quantity when the widget is inside the form.
		if (form) {
			// Grid-based form layout (1.1.0): a class the CSS can rely
			// on without :has(). The form becomes a single-column grid
			// — packs, then the Add to cart button — so theme row
			// rules can no longer squeeze the widget beside the
			// buttons, on simple AND variable products.
			form.classList.add('bkp-has-packs');
			var q = form.querySelector('.quantity');
			if (q) { q.style.display = 'none'; }
			// Variable products: move WooCommerce's native variant
			// selectors INSIDE the packs box, so the whole purchase
			// control (variants + packs + counter) is one
			// self-contained, responsive unit. The selects stay inside
			// the same form, so WooCommerce's variation script keeps
			// working unchanged. Preferred target: the custom pack's
			// variant slot (the reference row style); fallback: above
			// the packs list (products without a custom pack).
			if (widget.getAttribute('data-variable') === '1') {
				var variations = form.querySelector('.variations');
				if (variations && !variations.hasAttribute('data-bkp-moved')) {
					variations.setAttribute('data-bkp-moved', '1');
					variations.classList.add('bundlekit-packs-variations');
					var slot = widget.querySelector('.bundlekit-pack-variant-slot');
					if (slot) {
						slot.appendChild(variations);
					} else {
						var list = widget.querySelector('.bundlekit-packs-list');
						widget.insertBefore(variations, list);
					}
				}
			}
		}

		// Table layout: clicking a tier row selects its pack. (The
		// custom row has its own handler below, so it is excluded.)
		widget.addEventListener('click', function (e) {
			var row = e.target.closest ? e.target.closest('tr.bundlekit-pack-card:not(.bundlekit-pack-custom)') : null;
			if (!row) { return; }
			if (e.target.closest('input, select, button, textarea, a')) { return; }
			var radio = row.querySelector('input.bundlekit-pack-radio');
			if (radio && !radio.checked) {
				radio.checked = true;
				onSelectionChange(widget);
			}
		});

		// Pack selection.
		widget.addEventListener('change', function (e) {
			if (e.target && e.target.classList && e.target.classList.contains('bundlekit-pack-radio')) {
				onSelectionChange(widget);
			}
			if (e.target && e.target.classList && e.target.classList.contains('bundlekit-pack-qty')) {
				var card = e.target.closest('.bundlekit-pack-card');
				if (card) {
					clampCustom(card);
					paintCard(card, parseFloat(widget.getAttribute('data-unit')) || 0);
					syncQty(widget);
					postFlag(widget);
					syncLayoutExtras(widget);
				}
			}
		});

		// Clicking anywhere on the custom card selects the custom pack.
		var customCard = widget.querySelector('.bundlekit-pack-custom');
		if (customCard) {
			customCard.addEventListener('click', function () {
				var radio = customCard.querySelector('input.bundlekit-pack-radio');
				if (radio && !radio.checked) {
					radio.checked = true;
					onSelectionChange(widget);
				}
			});
			// Counter buttons.
			var minus = customCard.querySelector('.bundlekit-pack-minus');
			var plus = customCard.querySelector('.bundlekit-pack-plus');
			var input = customCard.querySelector('.bundlekit-pack-qty');
			var stepBy = function (dir) {
				var step = parseInt(customCard.getAttribute('data-step'), 10) || 1;
				var v = clampCustom(customCard);
				var min = parseInt(customCard.getAttribute('data-min'), 10) || 1;
				var max = parseInt(customCard.getAttribute('data-max'), 10) || 0;
				v = v + dir * step;
				v = Math.max(min, v);
				if (max > 0) { v = Math.min(max, v); }
				input.value = v;
				clampCustom(customCard);
				paintCard(customCard, parseFloat(widget.getAttribute('data-unit')) || 0);
				var radio2 = customCard.querySelector('input.bundlekit-pack-radio');
				if (radio2 && !radio2.checked) { radio2.checked = true; }
				onSelectionChange(widget);
			};
			if (minus) { minus.addEventListener('click', function (e) { e.preventDefault(); stepBy(-1); }); }
			if (plus) { plus.addEventListener('click', function (e) { e.preventDefault(); stepBy(1); }); }
		}

		// Variable products: recompute pack prices when the variation
		// changes. WooCommerce fires show_variation with the variation
		// object (display_price), and reset_data when cleared.
		if (widget.getAttribute('data-variable') === '1' && form && window.jQuery) {
			var $form = window.jQuery(form);
			$form.on('show_variation', function (e, variation) {
				if (variation && typeof variation.display_price !== 'undefined') {
					paintAll(widget, parseFloat(variation.display_price) || 0);
					syncQty(widget);
					syncLayoutExtras(widget);
				}
			});
			$form.on('hide_variation reset_data', function () {
				paintAll(widget, parseFloat(widget.getAttribute('data-unit')) || 0);
				syncLayoutExtras(widget);
			});
		}

		// Responsive: toggle .bk-narrow / .bk-tablet at fixed
		// widget widths (640px mobile, 900px tablet).
		syncResponsive(widget);
		// Initial state: paint, sync the hidden qty, flag the session.
		paintAll(widget, parseFloat(widget.getAttribute('data-unit')) || 0);
		onSelectionChange(widget);
		if (!isPreview) {
			postFlag(widget);
		}
	}

	function syncResponsive(widget) {
		var bp = 640;
		var bpt = 900;
		var w = widget.offsetWidth || widget.clientWidth || 0;
		widget.classList.toggle('bk-narrow', w > 0 && w < bp);
		widget.classList.toggle('bk-tablet', w >= bp && w < bpt);
	}

	function syncAllResponsive() {
		var widgets = document.querySelectorAll('[data-bundlekit-packs]');
		for (var i = 0; i < widgets.length; i++) {
			syncResponsive(widgets[i]);
		}
	}

	var resizeTimer = null;
	window.addEventListener('resize', function () {
		if (resizeTimer) { clearTimeout(resizeTimer); }
		resizeTimer = setTimeout(syncAllResponsive, 150);
	});

	function init() {
		var widgets = document.querySelectorAll('[data-bundlekit-packs]');
		for (var i = 0; i < widgets.length; i++) {
			if (!widgets[i].hasAttribute('data-bkp-init')) {
				widgets[i].setAttribute('data-bkp-init', '1');
				initWidget(widgets[i]);
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
