<?php
defined( 'ABSPATH' ) || exit;

/**
 * Frontend: show the Buy in Packs widget on a simple or variable
 * product page. Own CSS + JS files, enqueued ONLY when a widget is
 * actually shown.
 *
 * The widget REPLACES the product's quantity counter:
 *  - the default quantity input is hidden (CSS + JS, scoped to forms
 *    containing the widget), and frontend.js keeps its value in sync
 *    with the selected pack, so the normal Add to cart flow (classic
 *    POST, core AJAX, block Store API) adds the right quantity;
 *  - simple packs have their quantity already defined by the
 *    merchant, so no quantity counter is shown — only the variant
 *    dropdown (variable products keep WooCommerce's native one);
 *  - the custom pack shows its own quantity counter inside the card,
 *    plus the variant dropdown for variable products.
 *
 * Placement — the pack choice MUST be inside the main add-to-cart
 * <form>, otherwise the browser will not send it when Add to cart is
 * clicked:
 *  - Primary hook: `woocommerce_before_add_to_cart_quantity` — fires
 *    INSIDE <form class="cart"> (simple) and inside the variation
 *    add-to-cart wrapper (variable). The widget lands inside the
 *    form, just before the quantity / Add to cart button.
 *  - Fallbacks: `woocommerce_after_add_to_cart_form` (after </form>)
 *    and `woocommerce_after_single_product_summary` (classic, after
 *    the tabs). If the widget lands outside the form, frontend.js
 *    moves it inside form.cart on load.
 *  - Deliberately NOT hooked to `woocommerce_single_product_summary`:
 *    on block themes WooCommerce's template-compatibility layer runs
 *    that hook BEFORE the excerpt block — before the add-to-cart form
 *    even exists in the page — so the widget would land outside the
 *    form and the once-only guard would then block the in-form hook.
 * A static flag guarantees the widget is printed exactly once per page
 * load. Deliberately NOT using `the_content`: on block themes that
 * filter runs while the product summary/excerpt block is generated,
 * and the widget HTML gets stripped down to plain text inside the
 * excerpt.
 */
class BundleKit_Buy_In_Packs_Frontend {

	/** Product IDs the widget has already been printed for, this request. */
	private static $rendered = array();

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		// Primary: inside the main add-to-cart form, before quantity/button.
		add_action( 'woocommerce_before_add_to_cart_quantity', array( __CLASS__, 'packs_widget' ) );
		// Fallbacks: after the form closes, and after the summary tabs.
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'packs_widget' ) );
		add_action( 'woocommerce_after_single_product_summary', array( __CLASS__, 'packs_widget' ), 5 );
	}

	public static function register_assets() {
		wp_register_style( 'bundlekit-buy-in-packs', BUNDLEKIT_BUY_IN_PACKS_URL . 'assets/css/frontend.css', array(), BUNDLEKIT_BUY_IN_PACKS_VERSION );
		wp_register_script( 'bundlekit-buy-in-packs', BUNDLEKIT_BUY_IN_PACKS_URL . 'assets/js/frontend.js', array(), BUNDLEKIT_BUY_IN_PACKS_VERSION, true );
		wp_localize_script(
			'bundlekit-buy-in-packs',
			'bundlekitPacks',
			array(
				'symbol'    => get_woocommerce_currency_symbol(),
				'decimals'  => wc_get_price_decimals(),
				'saveBadge' => __( 'Save %s', 'bundlekit-buy-in-packs' ),
				// NOTE: the JS appends the % sign itself (a literal %%
				// in the localized string would print as "10.00%%").
				'savePct'   => __( 'You save %s', 'bundlekit-buy-in-packs' ),
			)
		);
	}

	/**
	 * Prints the widget once, if it applies. Called from the hooks above.
	 */
	public static function packs_widget() {
		global $product;
		echo self::widget_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build the packs widget HTML for a product, or '' if it must not
	 * show. Returns '' (and prints nothing) when:
	 *  - not a simple or variable, purchasable product,
	 *  - packs are off for the product (or no tiers and no custom pack),
	 *  - the global kill-switch is off,
	 *  - the product also has a Fixed Bundle offer switched on (the two
	 *    add-ons should not fight over one product page),
	 *  - the widget was already printed for this product on this page.
	 *
	 * @param WC_Product|null $product Product to render for.
	 * @return string
	 */
	public static function widget_html( $product ) {
		if ( ! $product || ! $product->is_purchasable() ) {
			return '';
		}
		if ( ! $product->is_type( 'simple' ) && ! $product->is_type( 'variable' ) ) {
			return '';
		}
		// Global kill-switch (BundleKit > Buy in Packs > Settings):
		// when Buy in Packs is off site-wide, no widget renders anywhere.
		if ( ! BundleKit_Buy_In_Packs::enabled() ) {
			return '';
		}
		// One add-on per product page: if the Fixed Bundle add-on is
		// installed and has an offer on this product, its box wins and
		// the packs widget stays out of the way.
		if ( class_exists( 'BundleKit_Fixed_Bundle' ) ) {
			$bundle_cfg = BundleKit_Fixed_Bundle::config( $product->get_id() );
			if ( ! empty( $bundle_cfg['on'] ) ) {
				return '';
			}
		}
		$product_id = $product->get_id();
		if ( isset( self::$rendered[ $product_id ] ) ) {
			return '';
		}
		$cfg = BundleKit_Buy_In_Packs::effective_config( $product_id );
		if ( ! $cfg['on'] ) {
			return '';
		}
		self::$rendered[ $product_id ] = true;
		wp_enqueue_style( 'bundlekit-buy-in-packs' );
		wp_enqueue_script( 'bundlekit-buy-in-packs' );

		$unit        = (float) $product->get_price();
		$is_variable = $product->is_type( 'variable' );
		return self::render_widget( $cfg, $unit, $product_id, $is_variable, false );
	}

	/**
	 * Build the full widget HTML for a packs config. One method serves
	 * the product page and the admin live preview (which forces each
	 * layout in turn).
	 *
	 * @param array  $cfg         Effective packs config.
	 * @param float  $unit        Unit price used for the first paint.
	 * @param int    $product_id  Product ID (0 in the admin preview).
	 * @param bool   $is_variable Variable product.
	 * @param bool   $preview     Admin preview: radios get no name, no ajax URL.
	 * @param string $force_layout Layout key to render (preview), or null for the picked layout.
	 * @return string
	 */
	public static function render_widget( $cfg, $unit, $product_id, $is_variable, $preview = false, $force_layout = null ) {
		$layout          = null !== $force_layout ? $force_layout : BundleKit_Buy_In_Packs::packs_layout();
		$style           = BundleKit_Buy_In_Packs::global_style_values();
		$style_attr      = self::style_attr( $style );
		$display_classes = BundleKit_Buy_In_Packs::display_classes();
		$display_attr    = $display_classes ? ' ' . implode( ' ', $display_classes ) : '';
		$extra_attr      = self::widget_extra_attrs( $style );
		$title           = '' !== $cfg['title'] ? $cfg['title'] : __( 'Save More When You Buy More', 'bundlekit-buy-in-packs' );

		if ( $preview ) {
			$open = '<div class="bundlekit-packs bundlekit-packs--' . esc_attr( $layout ) . esc_attr( $display_attr ) . '"' . $style_attr . $extra_attr . ' data-bundlekit-packs data-preview="1" data-unit="' . esc_attr( $unit ) . '" data-variable="' . ( $is_variable ? '1' : '0' ) . '">';
		} else {
			$open = '<div class="bundlekit-packs bundlekit-packs--' . esc_attr( $layout ) . esc_attr( $display_attr ) . '"' . $style_attr . $extra_attr . ' data-bundlekit-packs data-product-id="' . esc_attr( $product_id ) . '" data-unit="' . esc_attr( $unit ) . '" data-variable="' . ( $is_variable ? '1' : '0' ) . '" data-ajax-url="' . esc_url( WC_AJAX::get_endpoint( 'bundlekit_set_pack' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'bundlekit_pack' ) ) . '">';
		}

		ob_start();
		echo $open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
			<p class="bundlekit-packs-title"><span><?php echo esc_html( $title ); ?></span></p>
			<?php echo self::layout_body( $layout, $cfg, $unit, $preview, $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			$hint_text = '' !== $cfg['hint'] ? $cfg['hint'] : ( isset( $style['hint_text'] ) ? $style['hint_text'] : '' );
			?>
			<?php if ( '' !== $hint_text ) : ?>
				<p class="bundlekit-packs-hint"><?php echo esc_html( $hint_text ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		echo self::custom_css_tag( $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return ob_get_clean();
	}

	/**
	 * Dispatch to the picked layout's body renderer.
	 */
	private static function layout_body( $layout, $cfg, $unit, $preview, $style = array() ) {
		switch ( $layout ) {
			case 'columns':
				return self::layout_columns( $cfg, $unit, $preview, $style );
			case 'builder':
				return self::layout_builder( $cfg, $unit, $preview, $style );
			default:
				return self::layout_current( $cfg, $unit, $preview, $style );
		}
	}

	/**
	 * Shared data attributes for a tier pack unit. The frontend JS
	 * reads these (data-tier / data-qty / data-discount); data-name /
	 * data-desc / data-badge feed the tabs detail panel, promo hero
	 * and builder total.
	 */
	private static function tier_attrs( $tier, $index ) {
		return 'data-tier="' . esc_attr( $index ) . '" data-qty="' . esc_attr( $tier['qty'] ) . '" data-discount="' . esc_attr( $tier['discount'] ) . '" data-name="' . esc_attr( BundleKit_Buy_In_Packs::tier_label( $tier ) ) . '" data-desc="' . esc_attr( $tier['desc'] ) . '" data-badge="' . esc_attr( $tier['badge'] ) . '"';
	}

	/**
	 * The pack radio. $preview radios get no name so they never submit.
	 */
	private static function pack_radio( $value, $checked, $preview, $sr_only = false ) {
		$name  = $preview ? '' : ' name="bundlekit_pack"';
		$class = 'bundlekit-pack-radio' . ( $sr_only ? ' bkp-sr-only' : '' );
		return '<input type="radio"' . $name . ' value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '"' . ( $checked ? ' checked' : '' ) . ' />';
	}

	/**
	 * First-paint price spans (total / struck regular / save badge /
	 * save percent). All four are always rendered — the JS repaints
	 * them live (variation change, counter), so they must exist even
	 * when the first paint has no savings.
	 */
	private static function price_spans( $calc ) {
		$hide = $calc['save'] > 0 ? '' : ' style="display:none;"';
		ob_start();
		?>
		<span class="bundlekit-pack-total"><?php echo wp_kses_post( wc_price( $calc['total'] ) ); ?></span>
		<del class="bundlekit-pack-regular"<?php echo $hide; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo wp_kses_post( wc_price( $calc['regular'] ) ); ?></del>
		<span class="bundlekit-pack-save-badge"<?php echo $hide; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php printf( esc_html__( 'Save %s', 'bundlekit-buy-in-packs' ), wp_kses_post( wc_price( $calc['save'] ) ) ); ?></span>
		<span class="bundlekit-pack-savepct"<?php echo $hide; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php printf( esc_html__( 'You save %s%%', 'bundlekit-buy-in-packs' ), esc_html( number_format_i18n( $calc['percent'], 2 ) ) ); ?></span>
		<?php
		return ob_get_clean();
	}

	/**
	 * The merchant badge pill ("Best Pick" / "Most Popular"), or ''.
	 */
	private static function badge_pill( $badge, $extra_class = '' ) {
		if ( '' === $badge ) {
			return '';
		}
		$class = 'bundlekit-pack-popular' . ( '' !== $extra_class ? ' ' . $extra_class : '' );
		return '<span class="' . esc_attr( $class ) . '"><span class="bundlekit-pack-popular-star" aria-hidden="true">&#9733;</span> ' . esc_html( $badge ) . '</span>';
	}

	/**
	 * Layout: classic — the original vertical cards.
	 */
	private static function layout_current( $cfg, $unit, $preview, $style = array() ) {
		ob_start();
		?>
		<div class="bundlekit-packs-list">
			<?php foreach ( $cfg['tiers'] as $i => $tier ) : ?>
				<?php echo self::tier_card( $tier, $i, $unit, $preview ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>
			<?php if ( $cfg['custom']['on'] ) : ?>
				<?php echo self::custom_card( $cfg['custom'], $unit, $preview, 'row', $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Layout: three column cards. Tiers sit side by side; the custom
	 * pack is a column card too (counter inside, like the reference).
	 */
	private static function layout_columns( $cfg, $unit, $preview, $style = array() ) {
		ob_start();
		?>
		<div class="bundlekit-packs-list bkp-cols">
			<?php foreach ( $cfg['tiers'] as $i => $tier ) : ?>
				<?php
				$calc     = BundleKit_Buy_In_Packs::pack_totals( $unit, $tier['qty'], $tier['discount'] );
				$selected = ! empty( $tier['default'] ) ? ' is-selected' : '';
				$checked  = ! empty( $tier['default'] );
				?>
				<label class="bundlekit-pack-card bkp-col<?php echo esc_attr( $selected ); ?>" <?php echo self::tier_attrs( $tier, $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<span class="bkp-col-top">
						<?php echo self::badge_pill( $tier['badge'], 'bkp-col-badge' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo self::pack_radio( 'tier:' . $i, $checked, $preview ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
					<span class="bundlekit-pack-name"><?php echo esc_html( BundleKit_Buy_In_Packs::tier_label( $tier ) ); ?></span>
					<?php if ( '' !== $tier['desc'] ) : ?>
						<span class="bkp-col-desc"><?php echo esc_html( $tier['desc'] ); ?></span>
					<?php endif; ?>
					<span class="bkp-col-prices"><?php echo self::price_spans( $calc ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</label>
			<?php endforeach; ?>
			<?php if ( $cfg['custom']['on'] ) : ?>
				<?php echo self::custom_card( $cfg['custom'], $unit, $preview, 'col', $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Layout: step-by-step builder. Custom pack hero with counter on
	 * top, OR divider, tier cards, live total bar at the bottom.
	 */
	private static function layout_builder( $cfg, $unit, $preview, $style = array() ) {
		ob_start();
		?>
		<div class="bkp-builder">
			<?php if ( $cfg['custom']['on'] ) : ?>
				<?php echo self::custom_card( $cfg['custom'], $unit, $preview, 'hero', $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( ! empty( $cfg['tiers'] ) ) : ?>
					<div class="bkp-or" aria-hidden="true"><span><?php echo esc_html( isset( $style['or_text'] ) ? $style['or_text'] : __( 'OR', 'bundlekit-buy-in-packs' ) ); ?></span></div>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( ! empty( $cfg['tiers'] ) ) : ?>
				<div class="bundlekit-packs-list bkp-builder-tiers">
					<?php foreach ( $cfg['tiers'] as $i => $tier ) : ?>
						<?php
						$calc     = BundleKit_Buy_In_Packs::pack_totals( $unit, $tier['qty'], $tier['discount'] );
						$selected = ! empty( $tier['default'] ) ? ' is-selected' : '';
						$checked  = ! empty( $tier['default'] );
						?>
						<label class="bundlekit-pack-card bkp-builder-tier<?php echo esc_attr( $selected ); ?>" <?php echo self::tier_attrs( $tier, $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php echo self::pack_radio( 'tier:' . $i, $checked, $preview ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="bkp-builder-tier-text">
								<span class="bundlekit-pack-name"><?php echo esc_html( BundleKit_Buy_In_Packs::tier_label( $tier ) ); ?></span>
								<?php echo self::badge_pill( $tier['badge'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
							<span class="bundlekit-pack-total"><?php echo wp_kses_post( wc_price( $calc['total'] ) ); ?></span>
							<span class="bundlekit-pack-regular" style="display:none;"><?php echo wp_kses_post( wc_price( $calc['regular'] ) ); ?></span>
							<span class="bundlekit-pack-save-badge" style="display:none;"></span>
							<span class="bundlekit-pack-savepct" style="display:none;"></span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="bkp-builder-total" aria-live="polite">
				<span class="bkp-builder-total-label"><?php esc_html_e( 'Total', 'bundlekit-buy-in-packs' ); ?></span>
				<span class="bkp-builder-total-right">
					<span class="bundlekit-pack-total bkp-builder-total-price"></span>
					<span class="bkp-builder-total-sub"></span>
				</span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
	/**
	 * One simple-pack card. The quantity is already defined by the
	 * merchant (data-qty), so the card shows NO quantity counter —
	 * for variable products the customer picks the variant in
	 * WooCommerce's native dropdown above the widget.
	 *
	 * @param array $tier   Sanitized tier.
	 * @param int   $index  Tier index (radio value "tier:N").
	 * @param float $unit   Unit price used for the first paint.
	 * @param bool  $preview Admin preview: radios get no name.
	 */
	public static function tier_card( $tier, $index, $unit, $preview = false ) {
		$calc     = BundleKit_Buy_In_Packs::pack_totals( $unit, $tier['qty'], $tier['discount'] );
		$label    = BundleKit_Buy_In_Packs::tier_label( $tier );
		$selected = ! empty( $tier['default'] ) ? ' is-selected' : '';
		$checked  = ! empty( $tier['default'] ) ? ' checked' : '';
		$name     = $preview ? '' : ' name="bundlekit_pack"';
		ob_start();
		?>
		<label class="bundlekit-pack-card<?php echo esc_attr( $selected ); ?>" data-tier="<?php echo esc_attr( $index ); ?>" data-qty="<?php echo esc_attr( $tier['qty'] ); ?>" data-discount="<?php echo esc_attr( $tier['discount'] ); ?>">
			<input type="radio"<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> value="tier:<?php echo esc_attr( $index ); ?>" class="bundlekit-pack-radio"<?php echo $checked; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
			<span class="bundlekit-pack-main">
				<span class="bundlekit-pack-name"><?php echo esc_html( $label ); ?></span>
				<span class="bundlekit-pack-prices">
					<span class="bundlekit-pack-total"><?php echo wp_kses_post( wc_price( $calc['total'] ) ); ?></span>
					<?php if ( $calc['save'] > 0 ) : ?>
						<del class="bundlekit-pack-regular"><?php echo wp_kses_post( wc_price( $calc['regular'] ) ); ?></del>
					<?php endif; ?>
				</span>
				<?php if ( $calc['save'] > 0 ) : ?>
					<span class="bundlekit-pack-save-badge"><?php printf( esc_html__( 'Save %s', 'bundlekit-buy-in-packs' ), wp_kses_post( wc_price( $calc['save'] ) ) ); ?></span>
					<span class="bundlekit-pack-savepct"><?php printf( esc_html__( 'You save %s%%', 'bundlekit-buy-in-packs' ), esc_html( number_format_i18n( $calc['percent'], 2 ) ) ); ?></span>
				<?php endif; ?>
			</span>
			<?php if ( '' !== $tier['badge'] ) : ?>
				<span class="bundlekit-pack-popular"><span class="bundlekit-pack-popular-star" aria-hidden="true">&#10022;</span> <?php echo esc_html( $tier['badge'] ); ?> <span class="bundlekit-pack-popular-star" aria-hidden="true">&#10022;</span></span>
			<?php endif; ?>
		</label>
		<?php
		return ob_get_clean();
	}

	/**
	 * The custom pack card, rebuilt in the reference row style: icon +
	 * name/subtitle, variant slot (variable products get WooCommerce's
	 * native selectors moved here by JS), hardened quantity counter,
	 * live price. $variant adapts it per layout: 'row' (default),
	 * 'row', 'col', 'hero'.
	 *
	 * The counter buttons carry !important sizing: page builders
	 * (Elementor) were restyling them and breaking the layout.
	 *
	 * @param array  $custom  Sanitized custom pack.
	 * @param float  $unit    Unit price used for the first paint.
	 * @param bool   $preview Admin preview: radios get no name.
	 * @param string $variant Layout variant: 'row', 'col', 'hero'.
	 */
	public static function custom_card( $custom, $unit, $preview = false, $variant = 'row', $style = array() ) {
		$calc = BundleKit_Buy_In_Packs::pack_totals( $unit, $custom['min'], $custom['discount'] );
		// Max 0 = no upper limit: no max attribute, the counter and
		// the server clamp only enforce the minimum.
		$max_attr = $custom['max'] > 0 ? ' max="' . esc_attr( $custom['max'] ) . '"' : '';
		$counter  = '<span class="bundlekit-pack-counter">'
			. '<button type="button" class="bundlekit-pack-minus" aria-label="' . esc_attr__( 'Decrease quantity', 'bundlekit-buy-in-packs' ) . '">&minus;</button>'
			. '<input type="number" class="bundlekit-pack-qty" name="bundlekit_custom_qty" value="' . esc_attr( $custom['min'] ) . '" min="' . esc_attr( $custom['min'] ) . '"' . $max_attr . ' step="' . esc_attr( $custom['step'] ) . '" aria-label="' . esc_attr__( 'Custom pack quantity', 'bundlekit-buy-in-packs' ) . '" />'
			. '<button type="button" class="bundlekit-pack-plus" aria-label="' . esc_attr__( 'Increase quantity', 'bundlekit-buy-in-packs' ) . '">+</button>'
			. '</span>';
		$slot     = '<div class="bundlekit-pack-variant-slot"></div>';
		$radio    = self::pack_radio( 'custom', false, $preview, true );
		$attrs    = 'data-tier="custom" data-discount="' . esc_attr( $custom['discount'] ) . '" data-min="' . esc_attr( $custom['min'] ) . '" data-max="' . esc_attr( $custom['max'] ) . '" data-step="' . esc_attr( $custom['step'] ) . '" data-name="' . esc_attr( $custom['label'] ) . '" data-desc="' . esc_attr__( 'Select variant and quantity', 'bundlekit-buy-in-packs' ) . '" data-badge=""';
		$icon     = '<span class="bkp-custom-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/></svg></span>';
		$text     = '<span class="bkp-custom-text"><span class="bundlekit-pack-name">' . esc_html( $custom['label'] ) . '</span><span class="bkp-custom-sub">' . esc_html__( 'Select variant and quantity', 'bundlekit-buy-in-packs' ) . '</span></span>';
		$prices   = '<span class="bundlekit-pack-prices"><span class="bundlekit-pack-total">' . wp_kses_post( wc_price( $calc['total'] ) ) . '</span>'
			. ( $calc['save'] > 0 ? '<del class="bundlekit-pack-regular">' . wp_kses_post( wc_price( $calc['regular'] ) ) . '</del>' : '<del class="bundlekit-pack-regular" style="display:none;">' . wp_kses_post( wc_price( $calc['regular'] ) ) . '</del>' )
			. '<span class="bundlekit-pack-save-badge" style="display:none;"></span><span class="bundlekit-pack-savepct" style="display:none;"></span></span>';

		ob_start();
		if ( 'col' === $variant ) {
			// Custom pack as a column card (columns layout): icon,
			// name, variant slot, counter, price stacked.
			?>
			<div class="bundlekit-pack-card bundlekit-pack-custom bkp-col" <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php echo $radio; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="bkp-col-top">
					<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="bundlekit-pack-name"><?php echo esc_html( $custom['label'] ); ?></span>
				<span class="bkp-col-desc"><?php esc_html_e( 'Choose your quantity and save more.', 'bundlekit-buy-in-packs' ); ?></span>
				<?php echo $slot; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $counter; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="bkp-col-prices">
					<span class="bundlekit-pack-total"><?php echo wp_kses_post( wc_price( $calc['total'] ) ); ?></span>
					<span class="bundlekit-pack-regular" style="display:none;"><?php echo wp_kses_post( wc_price( $calc['regular'] ) ); ?></span>
					<span class="bundlekit-pack-save-badge" style="display:none;"></span>
					<span class="bundlekit-pack-savepct" style="display:none;"></span>
				</span>
			</div>
			<?php
		} elseif ( 'hero' === $variant ) {
			?>
			<div class="bundlekit-pack-card bundlekit-pack-custom bkp-builder-custom" <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php echo $radio; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="bkp-builder-custom-top">
					<span class="bundlekit-pack-name"><?php echo esc_html( $custom['label'] ); ?></span>
					<span class="bundlekit-pack-total"><?php echo wp_kses_post( wc_price( $calc['total'] ) ); ?></span>
				</span>
				<span class="bkp-builder-custom-bottom">
					<?php echo $counter; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $slot; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="bundlekit-pack-regular" style="display:none;"><?php echo wp_kses_post( wc_price( $calc['regular'] ) ); ?></span>
				<span class="bundlekit-pack-save-badge" style="display:none;"></span>
				<span class="bundlekit-pack-savepct" style="display:none;"></span>
			</div>
			<?php
		} else {
			// 'row' — the reference custom-pack row.
			?>
			<div class="bundlekit-pack-card bundlekit-pack-custom" <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php echo $radio; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $slot; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $counter; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $prices; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php
		}
		return ob_get_clean();
	}

	/**
	 * Build the widget's style attribute from the saved styling: one
	 * CSS variable per schema control, e.g.
	 * --bk-card-radius:12px;--bk-title-color:#111827.
	 * Every control has a schema default, so every variable is always
	 * output and the layout CSS reads plain var(--bk-*) with no
	 * hardcoded fallbacks. Values come from
	 * BundleKit_Buy_In_Packs_Styling::styling_values() (sanitized);
	 * font stacks only from the font_stacks() allow-list.
	 *
	 * @param array<string,string> $style Sanitized styling values.
	 */
	public static function style_attr( $style ) {
		$parts     = array();
		$skip_zero = array( 'widget_max_width' );
		foreach ( BundleKit_Buy_In_Packs_Styling::all_controls() as $spec ) {
			if ( '' === $spec['css_var'] ) {
				continue;
			}
			$v = isset( $style[ $spec['key'] ] ) ? $style[ $spec['key'] ] : $spec['default'];
			if ( in_array( $spec['key'], $skip_zero, true ) && '0' === (string) $v ) {
				continue;
			}
			$v = BundleKit_Buy_In_Packs_Styling::css_var_value( $spec, $v );
			if ( '' === $v ) {
				continue;
			}
			$parts[] = $spec['css_var'] . ':' . $v;
		}
		return $parts ? ' style="' . esc_attr( implode( ';', $parts ) ) . '"' : '';
	}

	/**
	 * Back-compat: the old field → variable map. New code reads the
	 * schema (BundleKit_Buy_In_Packs_Styling::all_controls()).
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function var_map() {
		$map = array();
		foreach ( BundleKit_Buy_In_Packs_Styling::all_controls() as $spec ) {
			if ( '' !== $spec['css_var'] ) {
				$map[ $spec['css_var'] ] = array( $spec['key'], $spec['type'] );
			}
		}
		return $map;
	}

	/**
	 * Extra classes + data attributes for behavioral controls (no CSS
	 * variable): widget alignment, popular badge position, button
	 * width.
	 *
	 * @param array<string,string> $style Sanitized styling values.
	 * @return string Already-escaped attribute string (leading space).
	 */
	public static function widget_extra_attrs( $style ) {
		$classes = array();
		$align   = isset( $style['widget_align'] ) ? $style['widget_align'] : 'left';
		if ( in_array( $align, array( 'center', 'right' ), true ) ) {
			$classes[] = 'bk-align-' . $align;
		}
		if ( isset( $style['popular_position'] ) && 'inline' === $style['popular_position'] ) {
			$classes[] = 'bk-popular-inline';
		}
		return $classes ? ' ' . esc_attr( implode( ' ', $classes ) ) : '';
	}

	/**
	 * Print the merchant's Custom CSS once per page, only where a
	 * widget renders.
	 *
	 * @param array<string,string> $style Sanitized styling values.
	 * @return string
	 */
	public static function custom_css_tag( $style ) {
		static $printed = false;
		$css = isset( $style['custom_css'] ) ? trim( $style['custom_css'] ) : '';
		if ( '' === $css || $printed ) {
			return '';
		}
		$printed = true;
		return '<style class="bkp-custom-css">' . $css . '</style>';
	}
}
