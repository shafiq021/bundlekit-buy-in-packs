<?php
defined( 'ABSPATH' ) || exit;

/**
 * Product editor tab: "BundleKit Packs" — pack tiers + custom pack for
 * one product (simple AND variable). Own file, own fields. No shared
 * settings file. Styling and show/hide controls are global only
 * (BundleKit > Buy in Packs).
 */
class BundleKit_Buy_In_Packs_Admin {

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		add_action( 'woocommerce_process_product_meta_simple', array( __CLASS__, 'save' ) );
		add_action( 'woocommerce_process_product_meta_variable', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'global_save' ) );
	}

	public static function tab( $tabs ) {
		// Packs work on any product the customer buys in quantity:
		// simple products, and variable products (each pack shows the
		// variant dropdown; the quantity is already defined, so no
		// quantity counter is shown on simple packs).
		$tabs['bundlekit_buy_in_packs'] = array(
			'label'    => __( 'BundleKit Packs', 'bundlekit-buy-in-packs' ),
			'target'   => 'bundlekit_buy_in_packs_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 60,
		);
		return $tabs;
	}

	public static function admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		// Product edit screen: the tiers repeater and the pack-source
		// toggle. The repeater is prefix-agnostic (data attributes),
		// so the same script drives the global Settings page table.
		if ( in_array( $screen->id, array( 'product', 'edit-product' ), true ) ) {
			wp_enqueue_style( 'bundlekit-buy-in-packs-admin', BUNDLEKIT_BUY_IN_PACKS_URL . 'assets/css/admin.css', array(), BUNDLEKIT_BUY_IN_PACKS_VERSION );
			wp_enqueue_script( 'bundlekit-buy-in-packs-admin', BUNDLEKIT_BUY_IN_PACKS_URL . 'assets/js/admin.js', array( 'jquery' ), BUNDLEKIT_BUY_IN_PACKS_VERSION, true );
			return;
		}
		// Global Buy in Packs page: the frontend CSS so the live
		// preview looks like the real widget, the WP color picker
		// for the color controls, and the preview script.
		if ( 'bundlekit_page_bundlekit-buy-in-packs' !== $screen->id ) {
			return;
		}
		// Shared BundleKit admin UI (framework 1.0.4): the Settings /
		// Styling Controls / Display design system. Each add-on loads
		// its own bundled copy — no cross-plugin dependency.
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'bundlekit-admin-ui', BUNDLEKIT_BUY_IN_PACKS_URL . 'includes/framework/assets/css/bundlekit-admin-ui.css', array(), BUNDLEKIT_FRAMEWORK_VERSION );
		wp_enqueue_script( 'bundlekit-admin-ui', BUNDLEKIT_BUY_IN_PACKS_URL . 'includes/framework/assets/js/bundlekit-admin-ui.js', array(), BUNDLEKIT_FRAMEWORK_VERSION, true );
		// The tiers repeater is shared with the product editor; the
		// global Default Packs table uses the same markup.
		wp_enqueue_script( 'bundlekit-buy-in-packs-admin', BUNDLEKIT_BUY_IN_PACKS_URL . 'assets/js/admin.js', array( 'jquery' ), BUNDLEKIT_BUY_IN_PACKS_VERSION, true );
		wp_enqueue_style( 'bundlekit-buy-in-packs-frontend', BUNDLEKIT_BUY_IN_PACKS_URL . 'assets/css/frontend.css', array(), BUNDLEKIT_BUY_IN_PACKS_VERSION );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'bundlekit-buy-in-packs-frontend', BUNDLEKIT_BUY_IN_PACKS_URL . 'assets/js/frontend.js', array(), BUNDLEKIT_BUY_IN_PACKS_VERSION, true );
		wp_enqueue_script( 'bundlekit-buy-in-packs-styling', BUNDLEKIT_BUY_IN_PACKS_URL . 'assets/js/packs-styling.js', array( 'jquery', 'wp-color-picker' ), BUNDLEKIT_BUY_IN_PACKS_VERSION, true );
		$styling_controls = BundleKit_Buy_In_Packs_Styling::all_controls();
		$var_map = array();
		$class_toggles = array(
			'show_title'        => 'bkp-no-title',
			'show_hint'         => 'bkp-no-hint',
			'show_save_badge'   => 'bkp-no-save-badge',
			'show_save_text'    => 'bkp-no-save-percent',
			'show_regular'      => 'bkp-no-regular',
			'show_popular'      => 'bkp-no-popular',
			'show_custom'       => 'bkp-no-custom',
			'show_desc'         => 'bkp-no-desc',
			'show_divider'      => 'bkp-no-divider',
			'show_stepper_icon' => 'bkp-no-stepper-icon',
			'show_radio'        => 'bkp-no-radio',
			'show_popular_star' => 'bkp-no-popular-star',
		);
		$defaults = array();
		foreach ( $styling_controls as $spec ) {
			$defaults[ $spec['key'] ] = (string) $spec['default'];
			if ( '' !== $spec['css_var'] ) {
				$var_map[ $spec['key'] ] = array(
					'var'  => $spec['css_var'],
					'type' => $spec['type'],
					'unit' => $spec['unit'],
				);
			}
		}
		$presets = array();
		foreach ( BundleKit_Buy_In_Packs::packs_layouts() as $lkey => $llabel ) {
			$presets[ $lkey ] = BundleKit_Buy_In_Packs_Styling::preset_values( $lkey );
		}
		wp_localize_script(
			'bundlekit-buy-in-packs-styling',
			'bundlekitPacksStyling',
			array(
				'varMap'       => $var_map,
				'classToggles' => $class_toggles,
				'defaults'     => $defaults,
				'presets'      => $presets,
				'fonts'        => BundleKit_Buy_In_Packs::font_stacks(),
				// Keys where 0 means "unset": mirrors the $skip_zero list
				// in BundleKit_Buy_In_Packs_Frontend::style_attr().
				'skipZero'     => array( 'widget_max_width' ),
			)
		);
	}

	public static function panel() {
		global $post;
		$cfg    = BundleKit_Buy_In_Packs::config( $post->ID );
		$mode   = BundleKit_Buy_In_Packs::mode( $post->ID );
		$title  = get_post_meta( $post->ID, '_bundlekit_packs_title', true );
		$hint   = get_post_meta( $post->ID, '_bundlekit_packs_hint', true );
		$custom = $cfg['custom'];
		?>
		<div id="bundlekit_buy_in_packs_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<p class="form-field">
					<label for="bundlekit_packs_mode"><?php esc_html_e( 'Pack source', 'bundlekit-buy-in-packs' ); ?></label>
					<select id="bundlekit_packs_mode" name="bundlekit_packs_mode">
						<option value="global" <?php selected( $mode, 'global' ); ?>><?php esc_html_e( 'Use global packs (default)', 'bundlekit-buy-in-packs' ); ?></option>
						<option value="custom" <?php selected( $mode, 'custom' ); ?>><?php esc_html_e( 'Custom packs for this product', 'bundlekit-buy-in-packs' ); ?></option>
						<option value="disabled" <?php selected( $mode, 'disabled' ); ?>><?php esc_html_e( 'No packs on this product', 'bundlekit-buy-in-packs' ); ?></option>
					</select>
					<span class="description"><?php esc_html_e( 'Global packs are set under BundleKit > Buy in Packs > Settings and show on every product. Pick "Custom packs" to override them for this product only.', 'bundlekit-buy-in-packs' ); ?></span>
				</p>
				<?php if ( class_exists( 'BundleKit_Fixed_Bundle' ) && BundleKit_Fixed_Bundle::config( $post->ID )['on'] ) : ?>
					<p class="form-field" style="color:#b45309;">
						<span class="description"><?php esc_html_e( 'Note: this product also has a Fixed Bundle offer switched on. Using both Fixed Bundle and Buy in Packs on the same product is not recommended — switch one of them off.', 'bundlekit-buy-in-packs' ); ?></span>
					</p>
				<?php endif; ?>
			</div>
			<div id="bundlekit-packs-custom-fields"<?php echo 'custom' === $mode ? '' : ' class="bkp-hidden"'; ?>>
				<div class="options_group">
					<p class="form-field">
						<label for="bundlekit_packs_title"><?php esc_html_e( 'Packs title', 'bundlekit-buy-in-packs' ); ?></label>
						<input type="text" id="bundlekit_packs_title" name="bundlekit_packs_title" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Save More When You Buy More', 'bundlekit-buy-in-packs' ); ?>" />
					</p>
					<p class="form-field">
						<label for="bundlekit_packs_hint"><?php esc_html_e( 'Hint text (optional)', 'bundlekit-buy-in-packs' ); ?></label>
						<input type="text" id="bundlekit_packs_hint" name="bundlekit_packs_hint" value="<?php echo esc_attr( $hint ); ?>" placeholder="<?php esc_attr_e( 'Select a pack, then click Add to cart.', 'bundlekit-buy-in-packs' ); ?>" />
					</p>
				</div>
				<div class="options_group">
					<h4 style="padding: 0 12px;"><?php esc_html_e( 'Packs', 'bundlekit-buy-in-packs' ); ?></h4>
					<p class="description" style="padding: 0 12px;"><?php esc_html_e( 'Create as many packs as you need. Each pack has a fixed quantity and its own discount — the customer picks a pack instead of a quantity. The marked pack is pre-selected on the product page.', 'bundlekit-buy-in-packs' ); ?></p>
					<?php self::render_tiers_table( $cfg['tiers'], 'bundlekit_packs', 10 ); ?>
				</div>
				<div class="options_group">
					<h4 style="padding: 0 12px;"><?php esc_html_e( 'Custom pack', 'bundlekit-buy-in-packs' ); ?></h4>
					<?php self::render_custom_pack_fields( $custom, 'bundlekit_packs' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * The tiers repeater table. $prefix namespaces the field names, so
	 * the same table works on the product tab (prefix "bundlekit_packs")
	 * and on the global Settings page (prefix "bundlekit_global_packs").
	 * $new_discount is the discount a freshly added row starts with.
	 * $no_inline renders without any inline style attribute (for the
	 * redesigned global pages — spacing comes from the stylesheet).
	 */
	public static function render_tiers_table( $tiers, $prefix, $new_discount = 10, $no_inline = false ) {
		$table_id    = 'bk-tiers-table-' . $prefix;
		$table_style = $no_inline ? '' : ' style="margin: 0 12px; width: calc(100% - 24px);"';
		$add_class   = $no_inline ? ' class="bk-tiers-add-wrap"' : '';
		$add_style   = $no_inline ? '' : ' style="padding: 0 12px;"';
		?>
		<table id="<?php echo esc_attr( $table_id ); ?>" class="widefat bk-tiers-table" data-tiers-table data-prefix="<?php echo esc_attr( $prefix ); ?>" data-default-discount="<?php echo esc_attr( $new_discount ); ?>"<?php echo $table_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<thead>
				<tr>
					<th><?php esc_html_e( 'Quantity', 'bundlekit-buy-in-packs' ); ?></th>
					<th><?php esc_html_e( 'Discount %', 'bundlekit-buy-in-packs' ); ?></th>
					<th><?php esc_html_e( 'Badge (optional)', 'bundlekit-buy-in-packs' ); ?></th>
					<th><?php esc_html_e( 'Label (optional)', 'bundlekit-buy-in-packs' ); ?></th>
					<th><?php esc_html_e( 'Description (optional)', 'bundlekit-buy-in-packs' ); ?></th>
					<th><?php esc_html_e( 'Default', 'bundlekit-buy-in-packs' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tiers as $i => $tier ) : ?>
					<tr>
						<td><input type="number" min="1" step="1" name="<?php echo esc_attr( $prefix ); ?>_tiers[<?php echo esc_attr( $i ); ?>][qty]" value="<?php echo esc_attr( $tier['qty'] ); ?>" class="small-text" /></td>
						<td><input type="number" min="0" max="100" step="any" name="<?php echo esc_attr( $prefix ); ?>_tiers[<?php echo esc_attr( $i ); ?>][discount]" value="<?php echo esc_attr( $tier['discount'] ); ?>" class="small-text" /></td>
						<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>_tiers[<?php echo esc_attr( $i ); ?>][badge]" value="<?php echo esc_attr( $tier['badge'] ); ?>" placeholder="<?php esc_attr_e( 'Most Popular', 'bundlekit-buy-in-packs' ); ?>" /></td>
						<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>_tiers[<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $tier['label'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Single', 'bundlekit-buy-in-packs' ); ?>" /></td>
						<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>_tiers[<?php echo esc_attr( $i ); ?>][desc]" value="<?php echo esc_attr( $tier['desc'] ); ?>" placeholder="<?php esc_attr_e( 'Perfect for everyday use.', 'bundlekit-buy-in-packs' ); ?>" /></td>
						<td><input type="radio" name="<?php echo esc_attr( $prefix ); ?>_default" value="<?php echo esc_attr( $i ); ?>" <?php checked( $tier['default'] ); ?> /></td>
						<td><button type="button" class="button" data-tiers-remove>&times;</button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p<?php echo $add_class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $add_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><button type="button" class="button" data-tiers-add="<?php echo esc_attr( $table_id ); ?>"><?php esc_html_e( 'Add pack', 'bundlekit-buy-in-packs' ); ?></button></p>
		<?php
	}

	/**
	 * The custom pack fields (on/off + label + min/max/step/discount),
	 * namespaced by $prefix like the tiers table.
	 */
	public static function render_custom_pack_fields( $custom, $prefix ) {
		?>
		<p class="form-field">
			<label><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>_custom[on]" value="yes" <?php checked( $custom['on'] ); ?> /> <?php esc_html_e( 'Let the customer build a custom pack', 'bundlekit-buy-in-packs' ); ?></label>
			<span class="description"><?php esc_html_e( 'The custom pack shows a quantity counter (and the variant dropdown for variable products) so the customer picks their own quantity.', 'bundlekit-buy-in-packs' ); ?></span>
		</p>
		<p class="form-field">
			<label for="<?php echo esc_attr( $prefix ); ?>_custom_label"><?php esc_html_e( 'Custom pack label', 'bundlekit-buy-in-packs' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $prefix ); ?>_custom_label" name="<?php echo esc_attr( $prefix ); ?>_custom[label]" value="<?php echo esc_attr( $custom['label'] ); ?>" placeholder="<?php esc_attr_e( 'Custom pack', 'bundlekit-buy-in-packs' ); ?>" />
		</p>
		<p class="form-field">
			<label for="<?php echo esc_attr( $prefix ); ?>_custom_min"><?php esc_html_e( 'Min quantity', 'bundlekit-buy-in-packs' ); ?></label>
			<input type="number" min="1" step="1" id="<?php echo esc_attr( $prefix ); ?>_custom_min" name="<?php echo esc_attr( $prefix ); ?>_custom[min]" value="<?php echo esc_attr( $custom['min'] ); ?>" class="small-text" />
		</p>
		<p class="form-field">
			<label for="<?php echo esc_attr( $prefix ); ?>_custom_max"><?php esc_html_e( 'Max quantity', 'bundlekit-buy-in-packs' ); ?></label>
			<input type="number" min="1" step="1" id="<?php echo esc_attr( $prefix ); ?>_custom_max" name="<?php echo esc_attr( $prefix ); ?>_custom[max]" value="<?php echo $custom['max'] > 0 ? esc_attr( $custom['max'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'No limit', 'bundlekit-buy-in-packs' ); ?>" class="small-text" />
			<span class="description"><?php esc_html_e( 'Leave empty for no limit — the customer can buy as many as they want. Only the minimum is enforced.', 'bundlekit-buy-in-packs' ); ?></span>
		</p>
		<p class="form-field">
			<label for="<?php echo esc_attr( $prefix ); ?>_custom_step"><?php esc_html_e( 'Step', 'bundlekit-buy-in-packs' ); ?></label>
			<input type="number" min="1" step="1" id="<?php echo esc_attr( $prefix ); ?>_custom_step" name="<?php echo esc_attr( $prefix ); ?>_custom[step]" value="<?php echo esc_attr( $custom['step'] ); ?>" class="small-text" />
		</p>
		<p class="form-field">
			<label for="<?php echo esc_attr( $prefix ); ?>_custom_discount"><?php esc_html_e( 'Custom pack discount %', 'bundlekit-buy-in-packs' ); ?></label>
			<input type="number" min="0" max="100" step="any" id="<?php echo esc_attr( $prefix ); ?>_custom_discount" name="<?php echo esc_attr( $prefix ); ?>_custom[discount]" value="<?php echo esc_attr( $custom['discount'] ); ?>" class="small-text" />
		</p>
		<?php
	}

	/**
	 * Global Buy in Packs page (BundleKit > Buy in Packs): Settings,
	 * Styling Controls and Display tabs, in the shared BundleKit admin
	 * design (framework 1.0.4).
	 */
	public static function global_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bundlekit-buy-in-packs' ) );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = in_array( $tab, array( 'settings', 'styling', 'css' ), true ) ? $tab : 'settings';
		$tabs = array(
			'settings' => array( 'label' => __( 'Settings', 'bundlekit-buy-in-packs' ), 'icon' => 'dashicons-admin-generic' ),
			'styling'  => array( 'label' => __( 'Styling Controls', 'bundlekit-buy-in-packs' ), 'icon' => 'dashicons-art' ),
			'css'      => array( 'label' => __( 'Custom CSS', 'bundlekit-buy-in-packs' ), 'icon' => 'dashicons-editor-code' ),
		);
		?>
		<div class="wrap">
			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'bundlekit-buy-in-packs' ); ?></p></div>
			<?php endif; ?>
			<div class="bk-ui" data-bk-ui>
				<?php
				bundlekit_ui_head(
					'dashicons-cart',
					__( 'Buy in Packs', 'bundlekit-buy-in-packs' ),
					__( 'Global Buy in Packs settings. Styling controls are picked here and apply to every packs widget on the site; the per-product BundleKit Packs tab only holds the packs themselves (title, tiers, custom pack). Turning Buy in Packs off here hides every packs widget and stops pack pricing on the whole site.', 'bundlekit-buy-in-packs' )
				);
				bundlekit_ui_tabs( 'bundlekit-buy-in-packs', $tabs, $tab );
				?>
			<?php if ( 'settings' === $tab ) : ?>
				<?php $global_packs = BundleKit_Buy_In_Packs::global_packs(); ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bundlekit-buy-in-packs&tab=settings' ) ); ?>">
					<?php wp_nonce_field( 'bundlekit_buy_in_packs_global', 'bundlekit_buy_in_packs_global_nonce' ); ?>
					<div class="bk-cards bk-cards--narrow">
						<?php bundlekit_ui_card_open( 'dashicons-admin-generic', __( 'General', 'bundlekit-buy-in-packs' ), __( 'Master switch for Buy in Packs.', 'bundlekit-buy-in-packs' ) ); ?>
							<?php bundlekit_ui_toggle( 'bundlekit_buy_in_packs_enabled', __( 'Show packs widgets and apply pack pricing', 'bundlekit-buy-in-packs' ), BundleKit_Buy_In_Packs::enabled() ); ?>
							<p class="bk-field-desc"><?php esc_html_e( 'Untick to turn Buy in Packs off for the whole site. No packs widget will show and no pack will be added, even on products with packs switched on. Product settings are kept, so ticking this again restores everything.', 'bundlekit-buy-in-packs' ); ?></p>
						<?php bundlekit_ui_card_close(); ?>
					</div>
					<div class="bk-cards">
						<?php bundlekit_ui_card_open( 'dashicons-grid-view', __( 'Default Packs', 'bundlekit-buy-in-packs' ), __( 'These packs show on every product by default. Edit a product\'s BundleKit Packs tab to override them for that product only.', 'bundlekit-buy-in-packs' ), 'bk-card--wide' ); ?>
							<?php self::render_tiers_table( $global_packs['tiers'], 'bundlekit_global_packs', 0, true ); ?>
							<p class="bk-field-desc"><?php esc_html_e( 'Create as many packs as you need. Each pack has a fixed quantity and its own discount — the customer picks a pack instead of a quantity. The marked pack is pre-selected on the product page.', 'bundlekit-buy-in-packs' ); ?></p>
						<?php bundlekit_ui_card_close(); ?>
						<?php bundlekit_ui_card_open( 'dashicons-plus', __( 'Custom Pack', 'bundlekit-buy-in-packs' ), __( 'Let the customer build their own pack on every product.', 'bundlekit-buy-in-packs' ) ); ?>
							<?php self::render_custom_pack_fields( $global_packs['custom'], 'bundlekit_global_packs' ); ?>
						<?php bundlekit_ui_card_close(); ?>
					</div>
					<?php bundlekit_ui_save_button( __( 'Save settings', 'bundlekit-buy-in-packs' ) ); ?>
				</form>
			<?php elseif ( 'css' === $tab ) : ?>
				<?php self::render_custom_css_tab(); ?>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bundlekit-buy-in-packs&tab=styling' ) ); ?>">
					<?php wp_nonce_field( 'bundlekit_buy_in_packs_global', 'bundlekit_buy_in_packs_global_nonce' ); ?>
					<div class="bk-bento">
						<div class="bk-bento-main">
							<p class="bk-field-desc"><?php esc_html_e( 'Style every packs widget. Colors use the color picker; clear a field to restore its default. Pick a layout to see only its controls. The live preview on the right updates instantly.', 'bundlekit-buy-in-packs' ); ?></p>
							<div class="bk-cards bundlekit-styling-controls">
								<?php self::render_styling_controls( BundleKit_Buy_In_Packs::global_style_values() ); ?>
							</div>
							<p class="bk-actions">
							<button type="submit" class="bk-btn bk-btn-primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save styling', 'bundlekit-buy-in-packs' ); ?></button>
							<button type="submit" name="bk_reset_all" value="1" class="bk-btn" onclick="return confirm('<?php echo esc_js( __( 'Reset ALL styling controls to their defaults? This cannot be undone.', 'bundlekit-buy-in-packs' ) ); ?>');"><span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e( 'Reset all', 'bundlekit-buy-in-packs' ); ?></button>
						</p>
						</div>
						<div class="bk-preview">
							<div class="bk-preview-frame">
								<div class="bk-preview-head">
									<span class="dashicons dashicons-visibility"></span>
									<div>
										<h3><?php esc_html_e( 'Live preview', 'bundlekit-buy-in-packs' ); ?></h3>
										<p><?php esc_html_e( 'This is how your packs widget will look with the current styling.', 'bundlekit-buy-in-packs' ); ?></p>
									</div>
								</div>
								<div class="bundlekit-preview-box">
									<?php self::render_preview(); ?>
								</div>
								<p class="bk-field-desc"><?php esc_html_e( 'A sample packs widget. Change any control on the left and this preview updates instantly. It reflects the saved Display settings.', 'bundlekit-buy-in-packs' ); ?></p>
							</div>
						</div>
					</div>
				</form>
			<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Schema-driven Styling Controls UI. Loops over
	 * BundleKit_Buy_In_Packs_Styling::all_controls(): one Essentials
	 * card, then one collapsible card per group in spec order.
	 * Every field wrapper carries data-layouts so the JS can show
	 * only the controls relevant to the picked layout. Advanced
	 * controls live in a nested "Advanced" toggle per group.
	 *
	 * Input names are bk_style[<key>]; the save handler merges only
	 * posted keys, so hidden controls keep their values.
	 *
	 * @param array<string,string> $values Sanitized values by key.
	 */
	public static function render_styling_controls( $values ) {
		$controls = BundleKit_Buy_In_Packs_Styling::all_controls();
		$groups   = BundleKit_Buy_In_Packs_Styling::all_groups();
		$fonts    = BundleKit_Buy_In_Packs::font_stacks();
		$icons    = array(
			'layout'         => 'dashicons-grid-view',
			'container'      => 'dashicons-admin-appearance',
			'heading'        => 'dashicons-text',
			'cards'          => 'dashicons-layout',
			'selected'       => 'dashicons-yes-alt',
			'typography'     => 'dashicons-editor-textcolor',
			'price_badges'   => 'dashicons-money-alt',
			'button'         => 'dashicons-button',
			'stepper'        => 'dashicons-plus',
			'radio'          => 'dashicons-marker',
			'footer'         => 'dashicons-editor-alignleft',
			'layout_options' => 'dashicons-admin-settings',
			'responsive'     => 'dashicons-smartphone',
			'custom_css'     => 'dashicons-editor-code',
		);

		// Essentials card: the ~10 most-used controls, always visible.
		?>
		<details class="bk-card bk-style-group" open>
			<summary class="bk-card-head bk-style-summary">
				<span class="bk-card-icon"><span class="dashicons dashicons-star-filled"></span></span>
				<div>
					<h3><?php esc_html_e( 'Essentials', 'bundlekit-buy-in-packs' ); ?></h3>
					<p><?php esc_html_e( 'The controls you will reach for most. Everything else lives in the groups below.', 'bundlekit-buy-in-packs' ); ?></p>
				</div>
			</summary>
			<div class="bk-style-fields">
				<?php
				foreach ( $controls as $spec ) {
					if ( ! empty( $spec['essential'] ) ) {
						self::render_styling_field( $spec, isset( $values[ $spec['key'] ] ) ? $values[ $spec['key'] ] : $spec['default'], $fonts );
					}
				}
				?>
			</div>
		</details>
		<?php

		foreach ( $groups as $slug => $label ) {
			if ( 'custom_css' === $slug ) {
				continue; // Custom CSS has its own tab.
			}
			$in_group = array();
			foreach ( $controls as $spec ) {
				if ( $spec['group'] === $slug && empty( $spec['essential'] ) ) {
					$in_group[] = $spec;
				}
			}
			if ( ! $in_group ) {
				continue;
			}
			$main = array();
			$adv  = array();
			foreach ( $in_group as $spec ) {
				if ( ! empty( $spec['advanced'] ) ) {
					$adv[] = $spec;
				} else {
					$main[] = $spec;
				}
			}
			$icon = isset( $icons[ $slug ] ) ? $icons[ $slug ] : 'dashicons-admin-generic';
			?>
			<details class="bk-card bk-style-group"<?php echo 'layout_options' === $slug ? ' open' : ''; ?>>
				<summary class="bk-card-head bk-style-summary">
					<span class="bk-card-icon"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span></span>
					<div>
						<h3><?php echo esc_html( $label ); ?></h3>
					</div>
				</summary>
				<div class="bk-style-fields">
					<?php
					foreach ( $main as $spec ) {
						self::render_styling_field( $spec, isset( $values[ $spec['key'] ] ) ? $values[ $spec['key'] ] : $spec['default'], $fonts );
					}
					if ( $adv || 'layout_options' === $slug ) :
						?>
						<details class="bk-style-advanced">
							<summary><?php esc_html_e( 'Advanced', 'bundlekit-buy-in-packs' ); ?></summary>
							<div class="bk-style-fields">
								<?php
								foreach ( $adv as $spec ) {
									self::render_styling_field( $spec, isset( $values[ $spec['key'] ] ) ? $values[ $spec['key'] ] : $spec['default'], $fonts );
								}
								if ( 'layout_options' === $slug ) :
									?>
									<p class="bk-actions">
										<button type="button" class="bk-btn" id="bk-reset-layout-defaults">
											<span class="dashicons dashicons-image-rotate"></span>
											<?php esc_html_e( 'Reset to layout defaults', 'bundlekit-buy-in-packs' ); ?>
										</button>
									</p>
									<p class="bk-field-desc"><?php esc_html_e( 'Fills every control with the picked layout\u2019s out-of-the-box look. Nothing is saved until you click Save styling.', 'bundlekit-buy-in-packs' ); ?></p>
									<?php
								endif;
								?>
							</div>
						</details>
						<?php
					endif;
					?>
				</div>
			</details>
			<?php
		}
	}

	/**
	 * One schema control → label + input. The wrapper carries
	 * data-layouts (the JS hides controls that do not apply to the
	 * picked layout) and data attributes the live preview needs.
	 *
	 * @param array  $spec  Control spec.
	 * @param string $value Current sanitized value.
	 * @param array  $fonts Font stacks.
	 */
	public static function render_styling_field( $spec, $value, $fonts ) {
		$key      = $spec['key'];
		$id       = 'bk_style_' . $key;
		$name     = 'bk_style[' . $key . ']';
		$layouts  = 'all' === $spec['layouts'] ? 'all' : implode( ',', (array) $spec['layouts'] );
		$suffix   = '';
		if ( 'number' === $spec['type'] && '' !== $spec['unit'] ) {
			$suffix = $spec['unit'];
		}
		?>
		<div class="bk-field bk-style-field" data-layouts="<?php echo esc_attr( $layouts ); ?>" data-key="<?php echo esc_attr( $key ); ?>">
			<label class="bk-field-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $spec['label'] ); ?></label>
			<?php if ( 'color' === $spec['type'] ) : ?>
				<div class="bk-color-wrap">
					<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="bk-input bundlekit-color-field" data-css-var="<?php echo esc_attr( $spec['css_var'] ); ?>" />
				</div>
			<?php elseif ( 'number' === $spec['type'] ) : ?>
				<div class="bk-input-group">
					<input type="number" <?php echo null !== $spec['min'] ? 'min="' . esc_attr( $spec['min'] ) . '"' : ''; ?> <?php echo null !== $spec['max'] ? 'max="' . esc_attr( $spec['max'] ) . '"' : ''; ?> step="any" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="bk-input" data-css-var="<?php echo esc_attr( $spec['css_var'] ); ?>" data-unit="<?php echo esc_attr( $spec['unit'] ); ?>" />
					<?php if ( $suffix ) : ?><span class="bk-suffix"><?php echo esc_html( $suffix ); ?></span><?php endif; ?>
				</div>
			<?php elseif ( 'font' === $spec['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="bk-select" data-css-var="<?php echo esc_attr( $spec['css_var'] ); ?>">
					<?php foreach ( (array) $fonts as $fkey => $stack ) : ?>
						<?php if ( '' === $fkey ) { continue; } ?>
						<option value="<?php echo esc_attr( $fkey ); ?>" <?php selected( $value, $fkey ); ?>><?php echo esc_html( ucfirst( $fkey ) ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'weight' === $spec['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="bk-select" data-css-var="<?php echo esc_attr( $spec['css_var'] ); ?>">
					<?php foreach ( array( '400' => 'Normal (400)', '500' => 'Medium (500)', '600' => 'Semi-bold (600)', '700' => 'Bold (700)', '800' => 'Extra bold (800)' ) as $w => $w_label ) : ?>
						<option value="<?php echo esc_attr( $w ); ?>" <?php selected( $value, (string) $w ); ?>><?php echo esc_html( $w_label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'select' === $spec['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="bk-select bk-layout-select" data-css-var="<?php echo esc_attr( $spec['css_var'] ); ?>">
					<?php foreach ( (array) $spec['options'] as $okey => $olabel ) : ?>
						<option value="<?php echo esc_attr( $okey ); ?>" <?php selected( $value, (string) $okey ); ?>><?php echo esc_html( $olabel ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'checkbox' === $spec['type'] ) : ?>
				<?php bundlekit_ui_toggle( $name, __( 'Enabled', 'bundlekit-buy-in-packs' ), 'yes' === $value ); ?>
			<?php elseif ( 'textarea' === $spec['type'] ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="bk-input bk-textarea" rows="8" placeholder="/* CSS */"><?php echo esc_textarea( $value ); ?></textarea>
			<?php else : ?>
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="bk-input" data-css-var="<?php echo esc_attr( $spec['css_var'] ); ?>" />
				<?php if ( 'custom_css' === $key ) : ?>
					<p class="bk-field-desc"><?php esc_html_e( 'Raw CSS printed only on pages where the packs widget renders.', 'bundlekit-buy-in-packs' ); ?></p>
					<?php elseif ( 'table_col_widths' === $key ) : ?>
					<p class="bk-field-desc"><?php esc_html_e( 'Optional. Example: 2fr 1fr 1fr auto. Leave empty for automatic widths.', 'bundlekit-buy-in-packs' ); ?></p>
					<?php elseif ( 'widget_shadow' === $key || 'card_shadow' === $key || 'selected_shadow' === $key ) : ?>
					<p class="bk-field-desc"><?php esc_html_e( 'Example: 0 2px 12px rgba(0,0,0,.08). Use "none" for no shadow.', 'bundlekit-buy-in-packs' ); ?></p>
					<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * The Custom CSS tab: one card with the raw-CSS textarea (the same
	 * schema control as on the old Styling page), saved on its own.
	 * Printed only on pages where the packs widget renders.
	 */
	public static function render_custom_css_tab() {
		$spec = null;
		foreach ( BundleKit_Buy_In_Packs_Styling::all_controls() as $c ) {
			if ( 'custom_css' === $c['key'] ) {
				$spec = $c;
				break;
			}
		}
		$values = BundleKit_Buy_In_Packs::global_style_values();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bundlekit-buy-in-packs&tab=css' ) ); ?>">
			<?php wp_nonce_field( 'bundlekit_buy_in_packs_global', 'bundlekit_buy_in_packs_global_nonce' ); ?>
			<?php bundlekit_ui_section_title( __( 'Custom CSS', 'bundlekit-buy-in-packs' ), __( 'Raw CSS printed only on pages where the packs widget renders. Use the --bk-* variables to restyle anything, e.g. .bundlekit-packs { --bk-card-radius: 0; }. Be careful: invalid CSS can break the widget look.', 'bundlekit-buy-in-packs' ) ); ?>
			<div class="bk-cards">
				<?php bundlekit_ui_card_open( 'dashicons-editor-code', __( 'Custom CSS', 'bundlekit-buy-in-packs' ), '', 'bk-card--wide' ); ?>
					<?php self::render_styling_field( $spec, isset( $values['custom_css'] ) ? $values['custom_css'] : '', BundleKit_Buy_In_Packs::font_stacks() ); ?>
				<?php bundlekit_ui_card_close(); ?>
			</div>
			<?php bundlekit_ui_save_button( __( 'Save custom CSS', 'bundlekit-buy-in-packs' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render the sample packs widget for the styling page preview:
	 * two tiers (1 pack, 2 packs "Most Popular") and a custom pack,
	 * unit price $100. The radios have no name, so they never submit
	 * with the form; no ajax URL is set, so the frontend script's
	 * session-flag fetch never runs here — but pack selection and the
	 * custom counter work live.
	 */
	public static function render_preview() {
		$unit   = 100;
		$tiers  = array(
			array( 'qty' => 1, 'discount' => 14.29, 'badge' => '', 'label' => '', 'desc' => 'Perfect for everyday use.', 'default' => true ),
			array( 'qty' => 2, 'discount' => 18.57, 'badge' => 'Most Popular', 'label' => '', 'desc' => 'Great value for more savings.', 'default' => false ),
		);
		$custom = array( 'on' => true, 'label' => 'Custom pack', 'min' => 3, 'max' => 12, 'step' => 1, 'discount' => 25 );
		$cfg    = array(
			'on'     => true,
			'title'  => '',
			'hint'   => __( 'Select a pack, then click Add to cart.', 'bundlekit-buy-in-packs' ),
			'tiers'  => $tiers,
			'custom' => $custom,
		);
		$picked = BundleKit_Buy_In_Packs::packs_layout();
		foreach ( BundleKit_Buy_In_Packs::packs_layouts() as $key => $label ) {
			$hidden = $key === $picked ? '' : ' is-hidden';
			?>
			<div class="bundlekit-preview-layout<?php echo esc_attr( $hidden ); ?>" data-preview-layout="<?php echo esc_attr( $key ); ?>">
				<?php echo BundleKit_Buy_In_Packs_Frontend::render_widget( $cfg, $unit, 0, false, true, $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php
		}
	}

	/**
	 * Save the global Buy in Packs page. Runs on admin_init, only for
	 * posts to this page with a valid nonce; then redirects back
	 * (Post/Redirect/Get) so a refresh never re-saves.
	 */
	public static function global_save() {
		if ( ! isset( $_GET['page'] ) || 'bundlekit-buy-in-packs' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! isset( $_POST['bundlekit_buy_in_packs_global_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['bundlekit_buy_in_packs_global_nonce'] ) ), 'bundlekit_buy_in_packs_global' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = in_array( $tab, array( 'settings', 'styling', 'css' ), true ) ? $tab : 'settings';
		if ( 'settings' === $tab ) {
			update_option( 'bundlekit_buy_in_packs_enabled', isset( $_POST['bundlekit_buy_in_packs_enabled'] ) ? 'yes' : 'no' );
			// Global default packs: the tiers + custom pack that show
			// on every product unless the product overrides them.
			$tiers = BundleKit_Buy_In_Packs::sanitize_tiers_input(
				isset( $_POST['bundlekit_global_packs_tiers'] ) ? wp_unslash( $_POST['bundlekit_global_packs_tiers'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				isset( $_POST['bundlekit_global_packs_default'] ) ? (int) $_POST['bundlekit_global_packs_default'] : -1
			);
			update_option( 'bundlekit_buy_in_packs_global_tiers', $tiers );
			$custom_raw = isset( $_POST['bundlekit_global_packs_custom'] ) && is_array( $_POST['bundlekit_global_packs_custom'] ) ? wp_unslash( $_POST['bundlekit_global_packs_custom'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			update_option( 'bundlekit_buy_in_packs_global_custom', BundleKit_Buy_In_Packs::sanitize_custom( $custom_raw ) );
		} elseif ( 'css' === $tab ) {
			// Custom CSS tab: only the custom_css key is updated.
			$spec   = null;
			foreach ( BundleKit_Buy_In_Packs_Styling::all_controls() as $c ) {
				if ( 'custom_css' === $c['key'] ) {
					$spec = $c;
					break;
				}
			}
			$posted = isset( $_POST['bk_style'] ) && is_array( $_POST['bk_style'] ) ? wp_unslash( $_POST['bk_style'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$stored = get_option( 'bundlekit_buy_in_packs_styling', array() );
			$stored = is_array( $stored ) ? $stored : array();
			if ( $spec && array_key_exists( 'custom_css', $posted ) ) {
				$raw = is_string( $posted['custom_css'] ) ? $posted['custom_css'] : '';
				$stored['custom_css'] = BundleKit_Buy_In_Packs_Styling::sanitize_value( $spec, $raw );
			}
			update_option( 'bundlekit_buy_in_packs_styling', $stored );
		} else {
			// "Reset all": wipe the styling array back to schema defaults.
			if ( isset( $_POST['bk_reset_all'] ) ) {
				update_option( 'bundlekit_buy_in_packs_styling', array() );
				wp_safe_redirect( admin_url( 'admin.php?page=bundlekit-buy-in-packs&tab=' . $tab . '&saved=1' ) );
				exit;
			}
			// Styling: one option array for every control. Only posted
			// keys are updated — hidden (layout-filtered) controls keep
			// their stored values, so switching layouts never deletes
			// anything. Checkbox controls post only when checked.
			$posted = isset( $_POST['bk_style'] ) && is_array( $_POST['bk_style'] ) ? wp_unslash( $_POST['bk_style'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$stored = get_option( 'bundlekit_buy_in_packs_styling', array() );
			$stored = is_array( $stored ) ? $stored : array();
			foreach ( BundleKit_Buy_In_Packs_Styling::all_controls() as $spec ) {
				$key = $spec['key'];
				if ( 'checkbox' === $spec['type'] ) {
					// Present in the form but unchecked => ''.
					$stored[ $key ] = isset( $posted[ $key ] ) ? 'yes' : '';
				} elseif ( array_key_exists( $key, $posted ) ) {
					$raw = is_string( $posted[ $key ] ) ? $posted[ $key ] : '';
					$stored[ $key ] = BundleKit_Buy_In_Packs_Styling::sanitize_value( $spec, $raw );
				}
				// Not posted at all (e.g. a control added in a newer
				// version): leave the stored value untouched.
			}
			update_option( 'bundlekit_buy_in_packs_styling', $stored );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=bundlekit-buy-in-packs&tab=' . $tab . '&saved=1' ) );
		exit;
	}

	public static function save( $post_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification — WooCommerce product save already verifies its nonce.
		$mode = isset( $_POST['bundlekit_packs_mode'] ) ? sanitize_key( wp_unslash( $_POST['bundlekit_packs_mode'] ) ) : 'global';
		$mode = in_array( $mode, array( 'global', 'custom', 'disabled' ), true ) ? $mode : 'global';
		update_post_meta( $post_id, '_bundlekit_packs_mode', $mode );
		update_post_meta( $post_id, '_bundlekit_packs_title', isset( $_POST['bundlekit_packs_title'] ) ? sanitize_text_field( wp_unslash( $_POST['bundlekit_packs_title'] ) ) : '' );
		update_post_meta( $post_id, '_bundlekit_packs_hint', isset( $_POST['bundlekit_packs_hint'] ) ? sanitize_text_field( wp_unslash( $_POST['bundlekit_packs_hint'] ) ) : '' );

		$tiers = BundleKit_Buy_In_Packs::sanitize_tiers_input(
			isset( $_POST['bundlekit_packs_tiers'] ) ? wp_unslash( $_POST['bundlekit_packs_tiers'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			isset( $_POST['bundlekit_packs_default'] ) ? (int) $_POST['bundlekit_packs_default'] : -1
		);
		update_post_meta( $post_id, '_bundlekit_packs_tiers', $tiers );

		$custom_raw = isset( $_POST['bundlekit_packs_custom'] ) && is_array( $_POST['bundlekit_packs_custom'] ) ? wp_unslash( $_POST['bundlekit_packs_custom'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_post_meta( $post_id, '_bundlekit_packs_custom', BundleKit_Buy_In_Packs::sanitize_custom( $custom_raw ) );
	}
}
