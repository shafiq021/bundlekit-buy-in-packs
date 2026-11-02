<?php
/**
 * Plugin Name: BundleKit – Buy in Packs
 * Plugin URI: https://github.com/shafiq021/BundleKit-Buy-in-Packs
 * Description: Buy in Packs add-on for BundleKit. Sell any product in quantity packs — 1 pack, 2 packs, 3 packs, as many as you create — with per-pack discounts, plus an optional Custom pack where the customer picks their own quantity. The packs replace the quantity counter on the product page; simple packs show only the variant dropdown for variable products, while the custom pack shows a quantity counter and the variant dropdown. Requires WooCommerce.
 * Version: 1.6.0
 * Author: Shafiq Khan
 * Author URI: https://www.linkedin.com/in/shafiqkhan21/
 * Text Domain: bundlekit-buy-in-packs
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 10.0
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * NOTE — no separate framework plugin (WPClever model):
 * The BundleKit framework lives inside this plugin at
 * includes/framework/ and is loaded by the line below. It is NOT a
 * separate plugin, it never appears in the Plugins list, and the user
 * never installs it. Every BundleKit add-on ships the same framework
 * library; only the first loaded copy runs, the rest skip theirs.
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'BUNDLEKIT_BUY_IN_PACKS_VERSION' ) ) {
	return;
}

define( 'BUNDLEKIT_BUY_IN_PACKS_VERSION', '1.6.0' );
define( 'BUNDLEKIT_BUY_IN_PACKS_FILE', __FILE__ );
define( 'BUNDLEKIT_BUY_IN_PACKS_DIR', plugin_dir_path( __FILE__ ) );
define( 'BUNDLEKIT_BUY_IN_PACKS_URL', plugin_dir_url( __FILE__ ) );

// Load the embedded BundleKit framework (shared admin: About Developer /
// Recommended Plugins). If another BundleKit add-on already loaded its
// copy, this file returns immediately and that copy is used instead.
require_once BUNDLEKIT_BUY_IN_PACKS_DIR . 'includes/framework/bundlekit-framework.php';

/**
 * If the old separate "BundleKit" plugin (from Fixed Bundle v1.1.0) is
 * still installed and active, tell the admin it can now be safely
 * deleted — the framework is built into this add-on. This is a
 * one-time clean-up notice, not an error.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active( 'bundlekit/bundlekit.php' ) ) {
			return;
		}
		echo '<div class="notice notice-info is-dismissible"><p><strong>BundleKit – Buy in Packs:</strong> the BundleKit framework is now built into this plugin, so the separate <strong>BundleKit</strong> plugin is no longer needed. You can safely deactivate and delete it from Plugins — this add-on will keep working on its built-in copy.</p></div>';
	}
);

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BUNDLEKIT_BUY_IN_PACKS_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', BUNDLEKIT_BUY_IN_PACKS_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p><strong>BundleKit – Buy in Packs</strong> needs WooCommerce to be active.</p></div>';
				}
			);
			return;
		}

		require_once BUNDLEKIT_BUY_IN_PACKS_DIR . 'includes/class-bundlekit-buy-in-packs.php';
		require_once BUNDLEKIT_BUY_IN_PACKS_DIR . 'includes/class-bundlekit-buy-in-packs-styling.php';
		require_once BUNDLEKIT_BUY_IN_PACKS_DIR . 'includes/class-bundlekit-buy-in-packs-admin.php';
		require_once BUNDLEKIT_BUY_IN_PACKS_DIR . 'includes/class-bundlekit-buy-in-packs-pricing.php';
		require_once BUNDLEKIT_BUY_IN_PACKS_DIR . 'includes/class-bundlekit-buy-in-packs-frontend.php';

		BundleKit_Buy_In_Packs::init();
		BundleKit_Buy_In_Packs_Admin::init();
		BundleKit_Buy_In_Packs_Pricing::init();
		BundleKit_Buy_In_Packs_Frontend::init();
	},
	25
);

// Register with the BundleKit framework (shows on About tab as an installed add-on).
add_filter(
	'bundlekit_addons',
	function ( $addons ) {
		$addons[] = array(
			'slug'    => 'bundlekit-buy-in-packs',
			'name'    => 'BundleKit – Buy in Packs',
			'version' => BUNDLEKIT_BUY_IN_PACKS_VERSION,
			'file'    => BUNDLEKIT_BUY_IN_PACKS_FILE,
		);
		return $addons;
	}
);

// Register the Buy in Packs global page (BundleKit > Buy in Packs):
// Settings (global on/off), Styling Controls (global styling defaults)
// and Display (show/hide frontend parts). The framework only adds the
// submenu if this class is loaded, so the page never appears without
// the add-on itself.
add_filter(
	'bundlekit_addon_pages',
	function ( $pages ) {
		$pages[] = array(
			'slug'   => 'bundlekit-buy-in-packs',
			'title'  => __( 'Buy in Packs', 'bundlekit-buy-in-packs' ),
			'render' => array( 'BundleKit_Buy_In_Packs_Admin', 'global_page' ),
		);
		return $pages;
	}
);
