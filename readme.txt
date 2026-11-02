=== BundleKit – Buy in Packs ===
Contributors: shafiq021
Tags: woocommerce, bundle, packs, quantity packs, buy in bulk, volume discount
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 8.0
WC tested up to: 10.0
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell any product in quantity packs — 1 pack, 2 packs, 3 packs, as many as you create — each with its own discount, plus an optional Custom pack where the customer picks their own quantity.

== Description ==

BundleKit – Buy in Packs is an add-on for the BundleKit framework (built in — there is no separate BundleKit plugin to install).

On any simple or variable product, switch on "Sell this product in packs" in the **BundleKit Packs** tab and create your packs:

* **Pack tiers** — 1 pack, 2 packs, 3 packs… as many as you need. Each pack has its own quantity, its own % discount, an optional badge (e.g. "Most Popular") and an optional custom label. The marked pack is pre-selected on the product page.
* **Custom pack** — optional. The customer picks their own quantity through a counter inside the custom pack card, within your min / max / step and discount.

On the product page the packs **replace the quantity counter**:

* Simple packs have their quantity already defined, so no quantity counter is shown — only the variant dropdown, for variable products.
* The custom pack shows a quantity counter inside its card, plus the variant dropdown for variable products.

Each pack card shows the pack name, a "Save Rs.X" badge, "You save X%", the pack total and the struck regular total. Every pack is added as ONE cart line (quantity = pack quantity, unit price = price minus the pack discount), with the pack name shown on the line and saved to the order.

Global controls live under **BundleKit > Buy in Packs**: Settings (global on/off kill-switch), Styling Controls (colors, sizes, radius, fonts — controls left, live preview right) and Display (show/hide every frontend part).

== Installation ==

1. Install and activate WooCommerce.
2. Upload `bundlekit-buy-in-packs.zip` via Plugins > Add New > Upload Plugin, then Activate. Only this plugin appears in the Plugins list — the BundleKit framework is built in.
3. Edit a simple or variable product > **BundleKit Packs** tab > tick "Sell this product in packs", create your packs, Update.

== Frequently Asked Questions ==

= The packs widget does not show on the product page =
Check: (1) "Sell this product in packs" is ticked in the product's BundleKit Packs tab and at least one pack tier exists (or the custom pack is on); (2) BundleKit > Buy in Packs > Settings has "Enable Buy in Packs" ticked; (3) the product is simple or variable, published and purchasable.

= I use Fixed Bundle on the same product =
One add-on per product page: if the product also has a Fixed Bundle offer switched on, the Fixed Bundle box wins and the packs widget does not render. Switch one of them off in the product editor.

= Does the pack discount combine with sale prices? =
The pack discount applies to the product's effective price (the sale price when the product is on sale, otherwise the regular price).

= Can different packs of the same product be in the cart together? =
Yes — "1 pack" and "2 packs" become separate cart lines, each with its own quantity and discounted price.

== Changelog ==

= 1.6.0 =
* Change: trimmed to 3 pack layouts — Classic cards, Three column cards, Step-by-step builder. The Horizontal tabs, Comparison table, Dark promo panel and Accordion list layouts are removed, along with all of their layout-specific styling controls.
* Change: the entire Button style group is removed (it only styled the removed layouts' buttons).
* Change: the Responsive controls group is removed (mobile breakpoint, tablet breakpoint, mobile heading size, mobile card padding). The widget stays responsive at fixed 640px / 900px widget widths.
* Tidy: smaller stylesheet and scripts with the removed layouts' CSS/JS gone.

= 1.5.2 =
* Fix: the live preview collapsed to zero width on page load. The preview JS set --bk-widget-max-width:0px / --bk-button-height:0px, but the PHP renderer treats 0 as "unset" for those two controls — the JS now mirrors that (localized skipZero list), so widgets render at full width again. This was also why Save seemed not to work: values were saved, but the collapsed preview never showed them.
* Fix: the "Column widths" control now updates the table preview live (it sets --bk-table-col-1..4 like the PHP renderer instead of an unused variable).
* Change: the "Reset to layout defaults" button moved into the Layout Options > Advanced section.
* The "Reset all" button wipes every styling control back to defaults, including the layout picker (back to Classic cards).

= 1.5.1 =
* Fix: live preview and frontend layout were broken by show/hide controls outputting display:var(--bk-*-display) — an undefined variable makes the declaration invalid, so the title collapsed to inline, radios to block, etc. All show/hide is now class-based (bkp-no-*), the same mechanism as the old Display tab, and the Display tab itself is removed — its 7 toggles now live in Styling Controls.
* New: the layout picker moved into Layout Options, and Layout Options is now the first group on the Styling tab.
* New: separate Custom CSS tab (moved out of Styling Controls).
* New: "Reset all" button on the Styling tab — wipes every styling control back to defaults at any point (with confirmation). "Reset to layout defaults" still fills the picked layout's preset into the form.
* Change: classic card rows restructured — pack name and price share the top row (price right), "Save" pill and "You save" text stack underneath, matching the reference design.
* Fix: the 1.5.0 migration now also maps renamed show/hide keys and the old Display tab options into the new controls.

= 1.5.0 =
* New: schema-driven Styling Controls system — one control schema drives the admin UI, the save handler, the live preview and the frontend CSS output. 200+ controls across 11 collapsible groups (Layout, Container, Heading, Cards, Selected State, Typography, Price & Badges, Button, Custom Pack & Stepper, Radio, Footer Note, Layout Options, Responsive, Custom CSS).
* New: the layout picker now shows only the controls relevant to the picked layout; Layout Options holds per-layout extras (columns count, tab pointer, table header/rows, dark panel, OR divider + total box, accordion chevron/behavior).
* New: "Reset to layout defaults" button — every layout has an out-of-the-box preset (dark promo panel defaults to #0f1b2d).
* New: all styling is CSS custom properties (--bk-*) printed on the widget wrapper; layout CSS reads only variables. Saved 1.4.x styling is migrated automatically on first load.
* New: responsive breakpoint controls (widget gets .bk-narrow/.bk-tablet classes from your breakpoints), accordion "allow multiple open" and "default open item", editable table column labels, editable button/footer/OR text.
* Change: the footer note now shows by default ("Select a pack, then click Add to cart.") and is editable under Footer Note; per-product hint text still overrides it.

= 1.4.2 =
* Fix: comparison table layout was broken — the table rows inherited the card flex display and the columns collapsed. Rows now render as proper table rows.

= 1.4.1 =
* Fix: fatal "Call to undefined method BundleKit_Buy_In_Packs_Frontend::tier_label()" on the Styling Controls preview — the tier data-attributes now call the core class correctly.

= 1.4.0 =
* New: 7 packs widget layouts — pick one on BundleKit > Buy in Packs > Styling Controls (Layout card): Classic cards, Three column cards, Horizontal tabs + detail panel, Comparison table, Dark promo panel, Step-by-step builder, Accordion list. The styling live preview switches to the picked layout instantly. All layouts are responsive.
* New: custom pack rebuilt in the reference row style — icon, name + "Select variant and quantity", variant dropdown slot (variable products), hardened quantity counter, live price. The - / + counter buttons are now theme-proof (Elementor was breaking their layout).
* New: optional per-pack description field (tiers repeater) — shown in the columns, tabs detail, promo and accordion layouts.

= 1.3.0 =
* New: global default packs. When the plugin is installed and active, every product shows a 1-pack (qty 1) and a 2-pack (qty 2) by default — no per-product setup needed. BundleKit > Buy in Packs > Settings now has a Default Packs repeater and Custom Pack on/off controls (label, min/max/step, discount). A product's BundleKit Packs tab can override the global packs ("Custom packs for this product") or turn packs off for that product ("No packs on this product"). The master switch still hides packs site-wide. Products that had their own packs in 1.2.0 keep them.

= 1.2.0 =
* Admin redesign: Settings, Styling Controls and Display now share the new BundleKit admin design (framework 1.0.4) — section cards with icons, toggle switches, sticky live preview; all inline CSS removed into a shared stylesheet. No functional changes.

= 1.1.0 =
* Custom pack max quantity is now optional: leave it empty for no upper limit — the customer can buy as many as they want. Only the minimum is enforced.
* Variable products: the variant selectors now move inside the packs box, so the whole purchase control is one self-contained unit; the add-to-cart form uses a grid layout that themes can no longer squeeze beside the buttons.
* New styling controls: widget margin top and margin bottom.
* Fixed: the "You save" text briefly showed a double percent sign (10.00%%) after the page loaded.
* Fixed: a custom pack added through an AJAX add-to-cart could lose its quantity on one path and, on some flows, its "Custom pack (N)" label — the label is now guaranteed on the cart line and the order.
* Admin: the Styling Controls page is redesigned in a bento grid — section cards on the left, sticky live preview on the right; Display and Settings use the same card style.

= 1.0.0 =
* First release: pack tiers with per-pack quantity and % discount, optional Custom pack with quantity counter, variable-product support with variant dropdown, packs replace the quantity counter, one cart line per pack, global Settings / Styling Controls / Display pages under BundleKit > Buy in Packs.
