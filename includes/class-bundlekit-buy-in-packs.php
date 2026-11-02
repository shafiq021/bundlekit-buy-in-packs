<?php
defined( 'ABSPATH' ) || exit;

/**
 * Buy in Packs config + pricing maths.
 *
 * v1 scope: simple and variable products. The product page shows one
 * pack card per created tier ("1 pack", "2 packs", ...), each with its
 * own quantity and % discount, plus an optional Custom pack where the
 * customer picks a quantity through a counter. The packs REPLACE the
 * product's quantity counter:
 *  - simple packs: the quantity is already defined by the merchant, so
 *    no quantity counter is shown — only the variant dropdown, for
 *    variable products;
 *  - custom pack: a quantity counter is shown inside the custom pack
 *    card, and the variant dropdown for variable products.
 * Every pack is added as ONE cart line: quantity = pack quantity, unit
 * price = effective price minus the pack discount.
 */
class BundleKit_Buy_In_Packs {

	public static function init() {
		// Nothing to hook here — config/totals are used by Admin / Pricing / Frontend.
	}

	/**
	 * Global on/off (BundleKit > Buy in Packs > Settings). Default
	 * on: turning it off hides every packs widget and stops pack
	 * pricing site-wide, even on products with packs switched on.
	 */
	public static function enabled() {
		return 'no' !== get_option( 'bundlekit_buy_in_packs_enabled', 'yes' );
	}

	/**
	 * Sanitized global styling values (BundleKit > Buy in Packs >
	 * Styling Controls). These are the values every packs widget
	 * uses; an empty value means the built-in look.
	 *
	 * @return array<string,string>
	 */
	public static function global_style_values() {
		return BundleKit_Buy_In_Packs_Styling::styling_values();
	}

	/**
	 * CSS classes for hidden frontend elements, e.g. "bkp-no-title".
	 * Added to the packs widget; frontend.css hides the matching part.
	 * The live preview uses the same classes. The show/hide state now
	 * lives in the Styling Controls schema (checkbox controls, no CSS
	 * variable) instead of the old Display tab options.
	 *
	 * @return array<int,string>
	 */
	public static function display_classes() {
		$values = BundleKit_Buy_In_Packs_Styling::styling_values();
		$map    = array(
			'show_title'         => 'title',
			'show_hint'          => 'hint',
			'show_save_badge'    => 'save-badge',
			'show_save_text'     => 'save-percent',
			'show_regular'       => 'regular',
			'show_popular'       => 'popular',
			'show_custom'        => 'custom',
			'show_desc'          => 'desc',
			'show_divider'       => 'divider',
			'show_stepper_icon'  => 'stepper-icon',
			'show_radio'         => 'radio',
			'show_popular_star'  => 'popular-star',
		);
		$classes = array();
		foreach ( $map as $key => $suffix ) {
			if ( empty( $values[ $key ] ) ) {
				$classes[] = 'bkp-no-' . $suffix;
			}
		}
		return $classes;
	}

	/**
	 * Sanitize one pack tier. Quantities are whole units; the discount
	 * is a percentage (0–100).
	 *
	 * @param array $tier Raw tier.
	 * @return array{qty:int,discount:float,badge:string,label:string,default:bool}
	 */
	public static function sanitize_tier( $tier ) {
		$tier = is_array( $tier ) ? $tier : array();
		return array(
			'qty'      => max( 1, absint( isset( $tier['qty'] ) ? $tier['qty'] : 1 ) ),
			'discount' => min( 100, max( 0, (float) ( isset( $tier['discount'] ) ? $tier['discount'] : 0 ) ) ),
			'badge'    => isset( $tier['badge'] ) ? sanitize_text_field( (string) $tier['badge'] ) : '',
			'label'    => isset( $tier['label'] ) ? sanitize_text_field( (string) $tier['label'] ) : '',
			'desc'     => isset( $tier['desc'] ) ? sanitize_text_field( (string) $tier['desc'] ) : '',
			'default'  => ! empty( $tier['default'] ),
		);
	}

	/**
	 * Sanitize the custom pack settings.
	 *
	 * @param array $custom Raw custom pack.
	 * @return array{on:bool,label:string,min:int,max:int,step:int,discount:float}
	 */
	public static function sanitize_custom( $custom ) {
		$custom = is_array( $custom ) ? $custom : array();
		$min    = max( 1, absint( isset( $custom['min'] ) ? $custom['min'] : 1 ) );
		// Max is optional: empty means NO upper limit — the customer
		// can buy as many as they want. Only the minimum is enforced.
		// A stored 0 means the same as empty (it is what the empty
		// form field saves as).
		$max_raw = isset( $custom['max'] ) ? trim( (string) $custom['max'] ) : '';
		$max     = ( '' === $max_raw || '0' === $max_raw ) ? 0 : max( $min, absint( $max_raw ) );
		return array(
			'on'       => ! empty( $custom['on'] ),
			'label'    => isset( $custom['label'] ) && '' !== (string) $custom['label'] ? sanitize_text_field( (string) $custom['label'] ) : __( 'Custom pack', 'bundlekit-buy-in-packs' ),
			'min'      => $min,
			'max'      => $max,
			'step'     => max( 1, absint( isset( $custom['step'] ) ? $custom['step'] : 1 ) ),
			'discount' => min( 100, max( 0, (float) ( isset( $custom['discount'] ) ? $custom['discount'] : 0 ) ) ),
		);
	}

	/**
	 * Normalize a raw tiers list: one card per quantity (drop
	 * duplicates), smallest pack first, exactly one default (the
	 * marked one, else the first tier).
	 *
	 * @param array $raw Raw tier rows.
	 * @return array<int,array>
	 */
	public static function normalize_tiers( $raw ) {
		$tiers = array();
		$seen  = array();
		foreach ( (array) $raw as $tier ) {
			$tier = self::sanitize_tier( $tier );
			// One card per quantity — drop duplicate quantities.
			if ( isset( $seen[ $tier['qty'] ] ) ) {
				continue;
			}
			$seen[ $tier['qty'] ] = true;
			$tiers[]              = $tier;
		}
		// Smallest pack first, like the reference design.
		usort(
			$tiers,
			function ( $a, $b ) {
				return $a['qty'] - $b['qty'];
			}
		);
		// Exactly one default: the marked one, else the first tier.
		$has_default = false;
		foreach ( $tiers as $i => $tier ) {
			if ( $tier['default'] && ! $has_default ) {
				$has_default = true;
			} else {
				$tiers[ $i ]['default'] = false;
			}
		}
		if ( ! $has_default && ! empty( $tiers ) ) {
			$tiers[0]['default'] = true;
		}
		return $tiers;
	}

	/**
	 * Sanitize submitted tiers rows (product editor or global
	 * settings): re-index in row order, apply the default radio,
	 * then normalize.
	 *
	 * @param mixed $raw           Raw $_POST rows.
	 * @param int   $default_index Submitted default radio value.
	 * @return array<int,array>
	 */
	public static function sanitize_tiers_input( $raw, $default_index = -1 ) {
		$tiers = array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		// Re-index by the row order the rows were submitted in.
		ksort( $raw );
		foreach ( array_values( $raw ) as $i => $row ) {
			$tier            = self::sanitize_tier( is_array( $row ) ? $row : array() );
			$tier['default'] = ( $i === (int) $default_index );
			$tiers[]         = $tier;
		}
		return self::normalize_tiers( $tiers );
	}

	/**
	 * All widget layouts: key => label. 'current' is the original
	 * vertical-cards widget; the rest are the six new designs.
	 *
	 * @return array<string,string>
	 */
	public static function packs_layouts() {
		return array(
			'current' => __( 'Classic cards', 'bundlekit-buy-in-packs' ),
			'columns' => __( 'Three column cards', 'bundlekit-buy-in-packs' ),
			'builder' => __( 'Step-by-step builder', 'bundlekit-buy-in-packs' ),
		);
	}

	/**
	 * The picked widget layout (BundleKit > Buy in Packs > Styling
	 * Controls). One layout applies to every packs widget.
	 */
	public static function packs_layout() {
		$layouts = self::packs_layouts();
		$values  = BundleKit_Buy_In_Packs_Styling::styling_values();
		$layout  = isset( $values['layout'] ) ? $values['layout'] : 'current';
		if ( ! isset( $layouts[ $layout ] ) ) {
			$layout = get_option( 'bundlekit_buy_in_packs_layout', 'current' );
		}
		return isset( $layouts[ $layout ] ) ? $layout : 'current';
	}

	/**
	 * The built-in global packs: a 1-pack (qty 1) and a 2-pack (qty 2).
	 * Used when the plugin is installed and active and no global packs
	 * have been saved yet — so packs show on every product by default.
	 *
	 * @return array<int,array>
	 */
	public static function default_global_tiers() {
		return self::normalize_tiers(
			array(
				array(
					'qty'      => 1,
					'discount' => 0,
					'badge'    => '',
					'label'    => '',
					'default'  => true,
				),
				array(
					'qty'      => 2,
					'discount' => 0,
					'badge'    => '',
					'label'    => '',
					'default'  => false,
				),
			)
		);
	}

	/**
	 * The global packs (BundleKit > Buy in Packs > Settings). These
	 * show on every product unless that product overrides them in its
	 * own BundleKit Packs tab.
	 *
	 * A missing option means "never saved" — fall back to the built-in
	 * defaults. An explicitly saved empty list means "no packs".
	 *
	 * @return array{tiers:array<int,array>,custom:array}
	 */
	public static function global_packs() {
		$tiers_raw = get_option( 'bundlekit_buy_in_packs_global_tiers', false );
		$tiers     = false === $tiers_raw ? self::default_global_tiers() : self::normalize_tiers( $tiers_raw );
		$custom    = self::sanitize_custom( get_option( 'bundlekit_buy_in_packs_global_custom', array() ) );
		return array(
			'tiers'  => $tiers,
			'custom' => $custom,
		);
	}

	/**
	 * Where one product's packs come from: 'global' (the site-wide
	 * defaults), 'custom' (this product's own packs), or 'disabled'
	 * (no packs on this product).
	 *
	 * Back-compat: products that had their own packs switched on in
	 * 1.2.0 and earlier keep them ('custom'); everything else follows
	 * the global packs.
	 */
	public static function mode( $product_id ) {
		$product_id = absint( $product_id );
		$mode       = get_post_meta( $product_id, '_bundlekit_packs_mode', true );
		if ( in_array( $mode, array( 'global', 'custom', 'disabled' ), true ) ) {
			return $mode;
		}
		return 'yes' === get_post_meta( $product_id, '_bundlekit_packs_on', true ) ? 'custom' : 'global';
	}

	/**
	 * The packs that actually apply to one product.
	 *
	 * Resolution order:
	 *   1. Global kill-switch off -> no packs anywhere.
	 *   2. Product mode 'disabled' -> no packs on this product.
	 *   3. Product mode 'custom' -> this product's own packs
	 *      (overrides the global packs).
	 *   4. Otherwise -> the global packs (show on all products).
	 *
	 * The Fixed Bundle conflict is checked separately by the
	 * frontend/pricing callers: a Fixed Bundle offer on the product
	 * still wins over packs.
	 *
	 * @return array{on:bool,title:string,hint:string,tiers:array<int,array>,custom:array}
	 */
	public static function effective_config( $product_id ) {
		$off = array(
			'on'     => false,
			'title'  => '',
			'hint'   => '',
			'tiers'  => array(),
			'custom' => self::sanitize_custom( array() ),
		);
		if ( ! self::enabled() ) {
			return $off;
		}
		$mode = self::mode( $product_id );
		if ( 'disabled' === $mode ) {
			return $off;
		}
		if ( 'custom' === $mode ) {
			return self::config( $product_id );
		}
		$packs = self::global_packs();
		$on    = ! empty( $packs['tiers'] ) || $packs['custom']['on'];
		return array(
			'on'     => $on,
			'title'  => '',
			'hint'   => '',
			'tiers'  => $packs['tiers'],
			'custom' => $packs['custom'],
		);
	}

	/**
	 * The packs config for one product — the product's OWN packs only
	 * (its BundleKit Packs tab). Frontend and pricing use
	 * effective_config() instead, which resolves global vs. custom.
	 *
	 * @return array{on:bool,title:string,hint:string,tiers:array<int,array>,custom:array}
	 */
	public static function config( $product_id ) {
		$product_id = absint( $product_id );
		$meta       = function ( $key, $default = '' ) use ( $product_id ) {
			$v = get_post_meta( $product_id, '_bundlekit_packs_' . $key, true );
			return ( '' === $v || null === $v ) ? $default : $v;
		};

		$tiers  = self::normalize_tiers( get_post_meta( $product_id, '_bundlekit_packs_tiers', true ) );
		$custom = self::sanitize_custom( get_post_meta( $product_id, '_bundlekit_packs_custom', true ) );

		return array(
			'on'     => ! empty( $tiers ) || $custom['on'],
			'title'  => (string) $meta( 'title', '' ),
			'hint'   => (string) $meta( 'hint', '' ),
			'tiers'  => $tiers,
			'custom' => $custom,
		);
	}

	/**
	 * The label shown on a pack card, e.g. "1 pack", "2 packs".
	 */
	public static function tier_label( $tier ) {
		if ( '' !== $tier['label'] ) {
			return $tier['label'];
		}
		/* translators: %d: pack quantity */
		return 1 === $tier['qty'] ? sprintf( __( '%d pack', 'bundlekit-buy-in-packs' ), $tier['qty'] ) : sprintf( __( '%d packs', 'bundlekit-buy-in-packs' ), $tier['qty'] );
	}

	/**
	 * The pack label for a cart / order item, guaranteed. The stored
	 * label is preferred; when it is missing (a line built by an
	 * older version, a third-party add-to-cart flow, or any path
	 * that attached the pack key without the label), it is rebuilt
	 * from the pack key and quantity — so a custom pack ALWAYS shows
	 * "Custom pack (N)".
	 *
	 * @param array $cart_item Cart item (or order item values).
	 * @return string
	 */
	public static function pack_label_for( $cart_item ) {
		if ( ! empty( $cart_item['bundlekit_pack_label'] ) ) {
			return (string) $cart_item['bundlekit_pack_label'];
		}
		$key = isset( $cart_item['bundlekit_pack_key'] ) ? (string) $cart_item['bundlekit_pack_key'] : '';
		if ( '' === $key ) {
			return '';
		}
		$qty = isset( $cart_item['bundlekit_pack_qty'] ) ? (int) $cart_item['bundlekit_pack_qty'] : 0;
		if ( $qty <= 0 ) {
			$qty = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;
		}
		$product_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
		// The effective config (global vs. this product's own packs) —
		// so a global custom pack label resolves here too.
		$cfg        = $product_id ? self::effective_config( $product_id ) : null;
		if ( 0 === strpos( $key, 'tier:' ) ) {
			$index = (int) substr( $key, 5 );
			if ( $cfg && isset( $cfg['tiers'][ $index ] ) ) {
				return self::tier_label( $cfg['tiers'][ $index ] );
			}
			/* translators: %d: pack quantity */
			return 1 === $qty ? sprintf( __( '%d pack', 'bundlekit-buy-in-packs' ), $qty ) : sprintf( __( '%d packs', 'bundlekit-buy-in-packs' ), $qty );
		}
		if ( 'custom' === $key ) {
			$label = ( $cfg && '' !== $cfg['custom']['label'] ) ? $cfg['custom']['label'] : __( 'Custom pack', 'bundlekit-buy-in-packs' );
			/* translators: 1: custom pack label, 2: quantity */
			return sprintf( __( '%1$s (%2$d)', 'bundlekit-buy-in-packs' ), $label, max( 1, $qty ) );
		}
		return '';
	}

	/**
	 * Totals for one pack: quantity × unit price, minus the pack discount.
	 *
	 * @return array{regular:float,total:float,save:float,unit:float,percent:float}
	 */
	public static function pack_totals( $unit_price, $qty, $discount_pct ) {
		$unit_price = max( 0, (float) $unit_price );
		$qty        = max( 1, (int) $qty );
		$discount   = min( 100, max( 0, (float) $discount_pct ) );
		$regular    = $unit_price * $qty;
		$total      = $regular * ( 100 - $discount ) / 100;
		$total      = max( 0, min( $total, $regular ) );
		$save       = $regular - $total;
		return array(
			'regular' => round( $regular, 2 ),
			'total'   => round( $total, 2 ),
			'save'    => round( $save, 2 ),
			'unit'    => $qty ? round( $total / $qty, 2 ) : 0,
			'percent' => $regular > 0 ? round( $save / $regular * 100, 2 ) : 0,
		);
	}

	/**
	 * Font stacks offered for the title / pack text. Keys are stored
	 * in options; the stack itself is only ever output from this
	 * allow-list, never from raw user input.
	 *
	 * @return array<string,string>
	 */
	public static function font_stacks() {
		return array(
			''          => '',
			'system'    => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
			'arial'     => 'Arial, Helvetica, sans-serif',
			'verdana'   => 'Verdana, Geneva, sans-serif',
			'trebuchet' => '"Trebuchet MS", Helvetica, sans-serif',
			'georgia'   => 'Georgia, serif',
			'times'     => '"Times New Roman", Times, serif',
			'mono'      => '"Courier New", Courier, monospace',
		);
	}

	/**
	 * Back-compat: the old per-field list. New code should use
	 * BundleKit_Buy_In_Packs_Styling::all_controls().
	 *
	 * @return array<int,array>
	 */
	public static function style_fields() {
		return BundleKit_Buy_In_Packs_Styling::all_controls();
	}

	/**
	 * Back-compat sanitizer. New code should use
	 * BundleKit_Buy_In_Packs_Styling::sanitize_value().
	 */
	public static function sanitize_style_value( $spec, $raw ) {
		return BundleKit_Buy_In_Packs_Styling::sanitize_value( $spec, $raw );
	}
}
