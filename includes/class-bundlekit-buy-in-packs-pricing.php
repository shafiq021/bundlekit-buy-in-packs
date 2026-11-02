<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cart behaviour for Buy in Packs.
 * Every pack is added as ONE cart line: quantity = pack quantity,
 * unit price = effective price minus the pack discount. Pack meta
 * ("Pack: 2 packs") is shown on the line and saved to the order.
 *
 * How the pack selection reaches the server — two paths, because
 * themes do not all submit Add to cart the same way:
 *  1. Normal form POST: the pack radios sit inside form.cart, so the
 *     browser sends `bundlekit_pack=tier:N` (or `custom`) with the
 *     form. (Classic themes, and the block Add to Cart Form.)
 *  2. Session flag: on every pack change, frontend.js posts the
 *     selection to the `bundlekit_set_pack` wc-ajax endpoint, which
 *     stores it in the WooCommerce session for that product. The
 *     handler below reads it as a fallback. This covers themes whose
 *     Add to cart buttons submit via AJAX and only send product id +
 *     quantity (so the radio field never arrives) — the same reason
 *     Fixed Bundle needed its session flag. The flag is consumed
 *     (cleared) when the pack is added, and the JS re-sends the
 *     current selection on page load, so a stale flag from an earlier
 *     visit can never pack a later, different add.
 *
 * One cart line per pack selection: the pack key
 * (`bundlekit_pack_key`, e.g. "tier:1" or "custom") is added to the
 * cart item data BEFORE the line is created
 * (`woocommerce_add_cart_item_data`), so "1 pack" and "2 packs" of
 * the same product become two separate lines instead of merging.
 * When the key is missing on the added line (session-flag path), the
 * line is removed and re-added with the key.
 */
class BundleKit_Buy_In_Packs_Pricing {

	public static function init() {
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'cart_item_data' ), 10, 4 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'add_pack' ), 21, 6 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_prices' ), 9999 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 3 );
		// Session-flag endpoint for the pack selection (guests included).
		add_action( 'wc_ajax_bundlekit_set_pack', array( __CLASS__, 'set_pack_flag' ) );
	}

	/**
	 * Remember the pack selection in the WooCommerce session, for the
	 * product the widget belongs to. Called by frontend.js on every
	 * pack change, and once on page load (to clear a stale flag).
	 * Only ever stores the selection for the user's own cart.
	 */
	public static function set_pack_flag() {
		// Global kill-switch: when Buy in Packs is off, no flag is
		// stored (the widget does not render, so this is only defense).
		if ( ! BundleKit_Buy_In_Packs::enabled() ) {
			wp_send_json_success();
		}
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce      = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $product_id && wp_verify_nonce( $nonce, 'bundlekit_pack' ) && WC()->session ) {
			$sel = isset( $_POST['sel'] ) ? sanitize_text_field( wp_unslash( $_POST['sel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( self::valid_selection( $product_id, $sel ) ) {
				$custom_qty = isset( $_POST['custom_qty'] ) ? absint( wp_unslash( $_POST['custom_qty'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				WC()->session->set(
					self::flag_key( $product_id ),
					array(
						'sel'        => $sel,
						'custom_qty' => $custom_qty,
					)
				);
				if ( method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
					/*
					 * Make the session real. A guest who has only viewed
					 * the product page has NO session cookie yet, and
					 * WooCommerce refuses to save session data without
					 * one (has_session() is false), so the flag would be
					 * lost with the request and the later Add to cart
					 * would see nothing. Setting the cookie here makes
					 * the flag persist to the next request.
					 */
					WC()->session->set_customer_session_cookie( true );
				}
				WC()->session->save_data();
			}
		}
		wp_send_json_success();
	}

	/** Session key for the pack selection of one product. */
	private static function flag_key( $product_id ) {
		return 'bundlekit_pack_' . (int) $product_id;
	}

	/**
	 * Is this a real pack selection for the product? "tier:N" must
	 * point at an existing tier, "custom" needs the custom pack on.
	 */
	private static function valid_selection( $product_id, $sel ) {
		$cfg = BundleKit_Buy_In_Packs::effective_config( $product_id );
		if ( ! $cfg['on'] ) {
			return false;
		}
		if ( 0 === strpos( $sel, 'tier:' ) ) {
			$index = (int) substr( $sel, 5 );
			return isset( $cfg['tiers'][ $index ] );
		}
		return 'custom' === $sel && $cfg['custom']['on'];
	}

	/**
	 * Resolve a selection into a concrete pack: quantity, discount %
	 * and the cart label. Returns null for an invalid selection.
	 *
	 * @return array{key:string,qty:int,discount:float,label:string}|null
	 */
	private static function resolve_pack( $product_id, $sel, $custom_qty = 0 ) {
		$cfg = BundleKit_Buy_In_Packs::effective_config( $product_id );
		if ( ! $cfg['on'] ) {
			return null;
		}
		if ( 0 === strpos( $sel, 'tier:' ) ) {
			$index = (int) substr( $sel, 5 );
			if ( ! isset( $cfg['tiers'][ $index ] ) ) {
				return null;
			}
			$tier = $cfg['tiers'][ $index ];
			return array(
				'key'      => 'tier:' . $index,
				'qty'      => $tier['qty'],
				'discount' => $tier['discount'],
				'label'    => BundleKit_Buy_In_Packs::tier_label( $tier ),
			);
		}
		if ( 'custom' === $sel && $cfg['custom']['on'] ) {
			$custom = $cfg['custom'];
			// Max 0 = no upper limit: only the minimum is enforced.
			$qty    = max( $custom['min'], (int) $custom_qty );
			if ( $custom['max'] > 0 ) {
				$qty = min( $custom['max'], $qty );
			}
			// Snap down to the step grid.
			if ( $custom['step'] > 1 ) {
				$qty = $custom['min'] + (int) floor( ( $qty - $custom['min'] ) / $custom['step'] ) * $custom['step'];
			}
			$qty = max( $custom['min'], $qty );
			return array(
				'key'      => 'custom',
				'qty'      => $qty,
				'discount' => $custom['discount'],
				/* translators: 1: custom pack label, 2: quantity */
				'label'    => sprintf( __( '%1$s (%2$d)', 'bundlekit-buy-in-packs' ), $custom['label'], $qty ),
			);
		}
		return null;
	}

	/**
	 * Attach the pack key to the cart item data BEFORE the line is
	 * created, so different pack selections of the same product get
	 * different cart IDs (separate lines) instead of merging.
	 */
	public static function cart_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
		if ( ! BundleKit_Buy_In_Packs::enabled() ) {
			return $cart_item_data;
		}
		$sel = isset( $_REQUEST['bundlekit_pack'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bundlekit_pack'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $sel && self::valid_selection( $product_id, $sel ) ) {
			$cart_item_data['bundlekit_pack_key'] = 0 === strpos( $sel, 'tier:' ) ? 'tier:' . (int) substr( $sel, 5 ) : 'custom';
		}
		return $cart_item_data;
	}

	public static function add_pack( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		// Global kill-switch (BundleKit > Buy in Packs > Settings):
		// when Buy in Packs is off site-wide, never pack — and clear
		// any session flag for this product, so it cannot pack a
		// later add after the feature is turned back on.
		if ( ! BundleKit_Buy_In_Packs::enabled() ) {
			if ( WC()->session ) {
				WC()->session->set( self::flag_key( $product_id ), null );
			}
			return;
		}

		// Path 1: the pack key arrived with the cart item data (the
		// radio field was in the form POST, or the line was re-added
		// below with the key).
		$sel = isset( $cart_item_data['bundlekit_pack_key'] ) ? $cart_item_data['bundlekit_pack_key'] : '';
		// Path 2: the raw radio field arrived with the form POST but
		// the filter above did not run for it (defense only).
		if ( '' === $sel && ! empty( $_REQUEST['bundlekit_pack'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = sanitize_text_field( wp_unslash( $_REQUEST['bundlekit_pack'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$sel = 0 === strpos( $raw, 'tier:' ) ? 'tier:' . (int) substr( $raw, 5 ) : ( 'custom' === $raw ? 'custom' : '' );
		}
		// Path 3: the session flag, set by frontend.js on pack change.
		$flag       = null;
		$custom_qty = 0;
		if ( '' === $sel && WC()->session ) {
			$flag = WC()->session->get( self::flag_key( $product_id ) );
			if ( is_array( $flag ) && ! empty( $flag['sel'] ) ) {
				$sel        = $flag['sel'];
				$custom_qty = isset( $flag['custom_qty'] ) ? (int) $flag['custom_qty'] : 0;
			}
		}
		// The custom quantity can also arrive with the form POST.
		if ( 'custom' === $sel && ! empty( $_REQUEST['bundlekit_custom_qty'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$custom_qty = absint( wp_unslash( $_REQUEST['bundlekit_custom_qty'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( '' === $sel || ! self::valid_selection( $product_id, $sel ) ) {
			return;
		}
		/*
		 * Resolve the pack. On the re-add path below, the resolved pack
		 * rides along in the cart item data ('bundlekit_pack'), so the
		 * second run never re-resolves with a missing custom quantity
		 * (which would silently reset a custom pack to its minimum).
		 */
		$pack = null;
		if ( isset( $cart_item_data['bundlekit_pack'] ) && is_array( $cart_item_data['bundlekit_pack'] ) ) {
			$pack = $cart_item_data['bundlekit_pack'];
		} else {
			$pack = self::resolve_pack( $product_id, $sel, $custom_qty );
		}
		if ( ! $pack ) {
			return;
		}

		/*
		 * The line was created WITHOUT the pack key (session-flag
		 * path: the filter saw no radio field). Remove it and re-add
		 * it with the key, so the cart ID is unique per pack
		 * selection and different packs never merge into one line.
		 * The re-add fires this same hook again; that time the key
		 * (and the resolved pack) is present and the pack is attached
		 * below.
		 */
		if ( ! isset( $cart_item_data['bundlekit_pack_key'] ) ) {
			$cart = WC()->cart;
			$cart->remove_cart_item( $cart_item_key );
			$cart->add_to_cart(
				$product_id,
				$pack['qty'],
				$variation_id,
				$variation,
				array(
					'bundlekit_pack_key' => $pack['key'],
					'bundlekit_pack'     => $pack,
				)
			);
			// The flag is consumed by the re-added line's own run.
			return;
		}

		// The effective unit price: the variation's price when a
		// variation was chosen, else the product's price.
		$item_product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
		if ( ! $item_product ) {
			return;
		}
		$calc = BundleKit_Buy_In_Packs::pack_totals( (float) $item_product->get_price(), $pack['qty'], $pack['discount'] );

		if ( isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
			WC()->cart->cart_contents[ $cart_item_key ]['bundlekit_pack_key']      = $pack['key'];
			WC()->cart->cart_contents[ $cart_item_key ]['bundlekit_pack_price']    = $calc['unit'];
			WC()->cart->cart_contents[ $cart_item_key ]['bundlekit_pack_label']    = $pack['label'];
			WC()->cart->cart_contents[ $cart_item_key ]['bundlekit_pack_qty']      = $pack['qty'];
			WC()->cart->cart_contents[ $cart_item_key ]['bundlekit_pack_discount'] = $pack['discount'];
			// The quantity must be the pack quantity, whatever the
			// theme sent (the hidden qty input normally covers this;
			// this is the safety net for AJAX add-to-carts that send
			// their own quantity).
			if ( (int) $quantity !== $pack['qty'] ) {
				WC()->cart->set_quantity( $cart_item_key, $pack['qty'], false );
			}
		}
		// Consume the session flag, so a later add of this product is
		// not packed by mistake.
		if ( WC()->session ) {
			WC()->session->set( self::flag_key( $product_id ), null );
		}
		/*
		 * Save the session again now. WooCommerce saves the cart
		 * session at the end of calculate_totals(), which runs on
		 * woocommerce_add_to_cart at priority 20 — BEFORE this hook
		 * (priority 21) runs, and before the pack meta above is set.
		 * Without this second save, the pack price/meta never reaches
		 * the session and the next request loads the line at full
		 * price. (Same timing as Fixed Bundle's bundle meta.)
		 */
		WC()->cart->calculate_totals();
		unset( $_REQUEST['bundlekit_pack'] );
	}

	public static function apply_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( isset( $item['bundlekit_pack_price'] ) ) {
				$item['data']->set_price( (float) $item['bundlekit_pack_price'] );
			}
		}
	}

	public static function get_cart_item_from_session( $cart_item, $values ) {
		foreach ( array( 'bundlekit_pack_key', 'bundlekit_pack', 'bundlekit_pack_price', 'bundlekit_pack_label', 'bundlekit_pack_qty', 'bundlekit_pack_discount' ) as $k ) {
			if ( isset( $values[ $k ] ) ) {
				$cart_item[ $k ] = $values[ $k ];
			}
		}
		return $cart_item;
	}

	public static function item_data( $item_data, $cart_item ) {
		// pack_label_for() guarantees the label: the stored one, or a
		// rebuilt one ("Custom pack (N)") when the key exists without it.
		$label = BundleKit_Buy_In_Packs::pack_label_for( $cart_item );
		if ( '' === $label ) {
			return $item_data;
		}
		$item_data[] = array(
			'key'   => __( 'Pack', 'bundlekit-buy-in-packs' ),
			'value' => $label,
		);
		return $item_data;
	}

	public static function order_item_meta( $item, $cart_item_key, $values ) {
		$label = BundleKit_Buy_In_Packs::pack_label_for( $values );
		if ( '' !== $label ) {
			$item->add_meta_data( __( 'Pack', 'bundlekit-buy-in-packs' ), $label, true );
		}
	}
}
