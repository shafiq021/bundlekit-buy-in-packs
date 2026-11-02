<?php
defined( 'ABSPATH' ) || exit;

/**
 * BundleKit – Buy in Packs — schema-driven styling system (1.5.0).
 *
 * ONE schema array defines every styling control. The admin page, the
 * save handler, the live preview and the frontend CSS-variable output
 * all loop over this same array, so a control cannot exist in one
 * place and be missing in another.
 *
 * Adding a control = adding one $add() line below.
 * Adding a layout = one presets entry + one layouts slug used by
 * controls + CSS rules for the new .bundlekit-packs--<slug> class.
 *
 * Spec per control:
 *   key      Option array key (also the old option suffix, so the
 *            1.4.x → 1.5.0 migration is a straight copy).
 *   label    Admin label.
 *   type     color|number|font|weight|select|checkbox|text|textarea.
 *   default  Default value. '' for checkbox means unchecked.
 *            Clearing any other control restores its default.
 *   css_var  CSS custom property, e.g. --bk-card-bg. '' = no CSS
 *            output (behavioral control: layout, labels, behavior).
 *   layouts  'all' or an array of layout slugs the control applies
 *            to. Drives the admin show/hide (data-layouts).
 *   group    Group slug; groups render as collapsible cards in the
 *            order of styling_groups().
 *   unit     Appended on output for number types ('px', 'ms', '').
 *   min/max  Clamp for number types.
 *   advanced If true, the control lives in the group's "Advanced"
 *            toggle instead of the main list.
 *   essential If true, the control also appears in the "Essentials"
 *            card at the top of the Styling tab.
 *   options  value => label pairs for select types.
 */
class BundleKit_Buy_In_Packs_Styling {

	/**
	 * Collapsible admin groups, in display order.
	 *
	 * @return array<string,string> slug => label.
	 */
	public static function styling_groups() {
		return array(
			'layout_options' => __( 'Layout Options', 'bundlekit-buy-in-packs' ),
			'container'      => __( 'Container', 'bundlekit-buy-in-packs' ),
			'heading'        => __( 'Heading', 'bundlekit-buy-in-packs' ),
			'cards'          => __( 'Cards', 'bundlekit-buy-in-packs' ),
			'selected'       => __( 'Selected State', 'bundlekit-buy-in-packs' ),
			'typography'     => __( 'Typography', 'bundlekit-buy-in-packs' ),
			'price_badges'   => __( 'Price & Badges', 'bundlekit-buy-in-packs' ),


		);
	}

	/**
	 * The full controls schema.
	 *
	 * @return array<int,array>
	 */
	public static function styling_schema() {
		$s = array();
		$add = function ( $key, $label, $type, $group, $args = array() ) use ( &$s ) {
			$s[] = array_merge(
				array(
					'key'       => $key,
					'label'     => $label,
					'type'      => $type,
					'default'   => '',
					'css_var'   => '',
					'layouts'   => 'all',
					'group'     => $group,
					'unit'      => '',
					'min'       => null,
					'max'       => null,
					'advanced'  => false,
					'essential' => false,
					'options'   => array(),
				),
				$args
			);
		};
		$px = array( 'unit' => 'px' );

		/* ============ Layout ============ */
		$add( 'layout', __( 'Packs layout', 'bundlekit-buy-in-packs' ), 'select', 'layout_options', array(
			'default' => 'current',
			'options' => BundleKit_Buy_In_Packs::packs_layouts(),
		) );

		/* ============ Container ============ */
		$add( 'widget_bg', __( 'Widget background', 'bundlekit-buy-in-packs' ), 'color', 'container', array( 'css_var' => '--bk-widget-bg', 'default' => '#ffffff' ) );
		$add( 'widget_border_color', __( 'Border color', 'bundlekit-buy-in-packs' ), 'color', 'container', array( 'css_var' => '--bk-widget-border-color', 'default' => '#e5e7eb', 'advanced' => true ) );
		$add( 'widget_border_width', __( 'Border width', 'bundlekit-buy-in-packs' ), 'number', 'container', array( 'css_var' => '--bk-widget-border-width', 'default' => '0', 'min' => 0, 'max' => 20, 'advanced' => true ) + $px );
		$add( 'widget_border_style', __( 'Border style', 'bundlekit-buy-in-packs' ), 'select', 'container', array( 'css_var' => '--bk-widget-border-style', 'default' => 'solid', 'advanced' => true, 'options' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ) ) );
		$add( 'widget_radius', __( 'Corner radius', 'bundlekit-buy-in-packs' ), 'number', 'container', array( 'css_var' => '--bk-widget-radius', 'default' => '0', 'min' => 0, 'max' => 60 ) + $px );
		$add( 'widget_shadow', __( 'Box shadow', 'bundlekit-buy-in-packs' ), 'text', 'container', array( 'css_var' => '--bk-widget-shadow', 'default' => 'none', 'advanced' => true ) );
		$add( 'widget_pad_top', __( 'Padding top', 'bundlekit-buy-in-packs' ), 'number', 'container', array( 'css_var' => '--bk-widget-pad-top', 'default' => '0', 'min' => 0, 'max' => 120, 'advanced' => true ) + $px );
		$add( 'widget_pad_right', __( 'Padding right', 'bundlekit-buy-in-packs' ), 'number', 'container', array( 'css_var' => '--bk-widget-pad-right', 'default' => '0', 'min' => 0, 'max' => 120, 'advanced' => true ) + $px );
		$add( 'widget_pad_bottom', __( 'Padding bottom', 'bundlekit-buy-in-packs' ), 'number', 'container', array( 'css_var' => '--bk-widget-pad-bottom', 'default' => '0', 'min' => 0, 'max' => 120, 'advanced' => true ) + $px );
		$add( 'widget_pad_left', __( 'Padding left', 'bundlekit-buy-in-packs' ), 'number', 'container', array( 'css_var' => '--bk-widget-pad-left', 'default' => '0', 'min' => 0, 'max' => 120, 'advanced' => true ) + $px );
		$add( 'margin_top', __( 'Margin top', 'bundlekit-buy-in-packs' ), 'number', 'container', array( 'css_var' => '--bk-margin-top', 'default' => '0', 'min' => 0, 'max' => 120 ) + $px );
		$add( 'margin_bottom', __( 'Margin bottom', 'bundlekit-buy-in-packs' ), 'number', 'container', array( 'css_var' => '--bk-margin-bottom', 'default' => '4', 'min' => 0, 'max' => 120 ) + $px );
		$add( 'widget_max_width', __( 'Max width', 'bundlekit-buy-in-packs' ), 'number', 'container', array( 'css_var' => '--bk-widget-max-width', 'default' => '0', 'min' => 0, 'max' => 2000, 'advanced' => true ) + $px );
		$add( 'widget_align', __( 'Alignment', 'bundlekit-buy-in-packs' ), 'select', 'container', array( 'default' => 'left', 'advanced' => true, 'options' => array( 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ) ) );
		$add( 'gap', __( 'Gap between options', 'bundlekit-buy-in-packs' ), 'number', 'container', array( 'css_var' => '--bk-gap', 'default' => '10', 'min' => 0, 'max' => 60, 'essential' => true ) + $px );
		$add( 'base_font', __( 'Base font family', 'bundlekit-buy-in-packs' ), 'font', 'container', array( 'css_var' => '--bk-base-font', 'default' => '' ) );
		$add( 'transition_speed', __( 'Animation speed', 'bundlekit-buy-in-packs' ), 'number', 'container', array( 'css_var' => '--bk-transition-speed', 'default' => '150', 'min' => 0, 'max' => 2000, 'advanced' => true, 'unit' => 'ms' ) );

		/* ============ Heading ============ */
		$add( 'show_title', __( 'Show heading', 'bundlekit-buy-in-packs' ), 'checkbox', 'heading', array( 'default' => 'yes' ) );
		$add( 'title_font', __( 'Heading font', 'bundlekit-buy-in-packs' ), 'font', 'heading', array( 'css_var' => '--bk-title-font', 'default' => '' ) );
		$add( 'title_size', __( 'Heading size', 'bundlekit-buy-in-packs' ), 'number', 'heading', array( 'css_var' => '--bk-title-size', 'default' => '15', 'min' => 8, 'max' => 60, 'essential' => true ) + $px );
		$add( 'title_weight', __( 'Heading weight', 'bundlekit-buy-in-packs' ), 'weight', 'heading', array( 'css_var' => '--bk-title-weight', 'default' => '700' ) );
		$add( 'title_color', __( 'Heading color', 'bundlekit-buy-in-packs' ), 'color', 'heading', array( 'css_var' => '--bk-title-color', 'default' => '#111827', 'essential' => true ) );
		$add( 'title_line_height', __( 'Line height', 'bundlekit-buy-in-packs' ), 'number', 'heading', array( 'css_var' => '--bk-title-line-height', 'default' => '1.4', 'min' => 0.5, 'max' => 3, 'advanced' => true ) );
		$add( 'title_letter_spacing', __( 'Letter spacing', 'bundlekit-buy-in-packs' ), 'text', 'heading', array( 'css_var' => '--bk-title-letter-spacing', 'default' => '0', 'advanced' => true ) );
		$add( 'title_transform', __( 'Text transform', 'bundlekit-buy-in-packs' ), 'select', 'heading', array( 'css_var' => '--bk-title-transform', 'default' => 'none', 'advanced' => true, 'options' => array( 'none' => 'None', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize' ) ) );
		$add( 'title_align', __( 'Alignment', 'bundlekit-buy-in-packs' ), 'select', 'heading', array( 'css_var' => '--bk-title-align', 'default' => 'center', 'options' => array( 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ) ) );
		$add( 'show_divider', __( 'Show divider lines', 'bundlekit-buy-in-packs' ), 'checkbox', 'heading', array( 'default' => 'yes' ) );
		$add( 'divider_color', __( 'Divider color', 'bundlekit-buy-in-packs' ), 'color', 'heading', array( 'css_var' => '--bk-divider-color', 'default' => '#d1d5db', 'advanced' => true ) );
		$add( 'divider_thickness', __( 'Divider thickness', 'bundlekit-buy-in-packs' ), 'number', 'heading', array( 'css_var' => '--bk-divider-thickness', 'default' => '1', 'min' => 0, 'max' => 10, 'advanced' => true ) + $px );
		$add( 'divider_style', __( 'Divider style', 'bundlekit-buy-in-packs' ), 'select', 'heading', array( 'css_var' => '--bk-divider-style', 'default' => 'solid', 'advanced' => true, 'options' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted' ) ) );
		$add( 'divider_gap', __( 'Gap between text and lines', 'bundlekit-buy-in-packs' ), 'number', 'heading', array( 'css_var' => '--bk-divider-gap', 'default' => '12', 'min' => 0, 'max' => 60, 'advanced' => true ) + $px );
		$add( 'title_margin_bottom', __( 'Margin bottom', 'bundlekit-buy-in-packs' ), 'number', 'heading', array( 'css_var' => '--bk-title-margin-bottom', 'default' => '14', 'min' => 0, 'max' => 80, 'advanced' => true ) + $px );

		/* ============ Cards ============ */
		$add( 'card_bg', __( 'Card background', 'bundlekit-buy-in-packs' ), 'color', 'cards', array( 'css_var' => '--bk-card-bg', 'default' => '#ffffff', 'essential' => true ) );
		$add( 'card_bg_hover', __( 'Card background (hover)', 'bundlekit-buy-in-packs' ), 'color', 'cards', array( 'css_var' => '--bk-card-bg-hover', 'default' => '#ffffff', 'advanced' => true ) );
		$add( 'card_border', __( 'Card border color', 'bundlekit-buy-in-packs' ), 'color', 'cards', array( 'css_var' => '--bk-card-border', 'default' => '#e5e7eb' ) );
		$add( 'card_border_hover', __( 'Card border (hover)', 'bundlekit-buy-in-packs' ), 'color', 'cards', array( 'css_var' => '--bk-card-border-hover', 'default' => '#d1d5db', 'advanced' => true ) );
		$add( 'card_border_width', __( 'Border width', 'bundlekit-buy-in-packs' ), 'number', 'cards', array( 'css_var' => '--bk-card-border-width', 'default' => '1', 'min' => 0, 'max' => 10 ) + $px );
		$add( 'card_border_style', __( 'Border style', 'bundlekit-buy-in-packs' ), 'select', 'cards', array( 'css_var' => '--bk-card-border-style', 'default' => 'solid', 'advanced' => true, 'options' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted' ) ) );
		$add( 'card_radius', __( 'Corner radius', 'bundlekit-buy-in-packs' ), 'number', 'cards', array( 'css_var' => '--bk-card-radius', 'default' => '12', 'min' => 0, 'max' => 60, 'essential' => true ) + $px );
		$add( 'card_padding', __( 'Card padding', 'bundlekit-buy-in-packs' ), 'number', 'cards', array( 'css_var' => '--bk-card-padding', 'default' => '14', 'min' => 0, 'max' => 60 ) + $px );
		$add( 'card_pad_top', __( 'Padding top', 'bundlekit-buy-in-packs' ), 'number', 'cards', array( 'css_var' => '--bk-card-pad-top', 'default' => '', 'min' => 0, 'max' => 60, 'advanced' => true ) + $px );
		$add( 'card_pad_right', __( 'Padding right', 'bundlekit-buy-in-packs' ), 'number', 'cards', array( 'css_var' => '--bk-card-pad-right', 'default' => '', 'min' => 0, 'max' => 60, 'advanced' => true ) + $px );
		$add( 'card_pad_bottom', __( 'Padding bottom', 'bundlekit-buy-in-packs' ), 'number', 'cards', array( 'css_var' => '--bk-card-pad-bottom', 'default' => '', 'min' => 0, 'max' => 60, 'advanced' => true ) + $px );
		$add( 'card_pad_left', __( 'Padding left', 'bundlekit-buy-in-packs' ), 'number', 'cards', array( 'css_var' => '--bk-card-pad-left', 'default' => '', 'min' => 0, 'max' => 60, 'advanced' => true ) + $px );
		$add( 'card_shadow', __( 'Box shadow', 'bundlekit-buy-in-packs' ), 'text', 'cards', array( 'css_var' => '--bk-card-shadow', 'default' => 'none', 'advanced' => true ) );
		$add( 'card_min_height', __( 'Card min-height', 'bundlekit-buy-in-packs' ), 'number', 'cards', array( 'css_var' => '--bk-card-min-height', 'default' => '0', 'min' => 0, 'max' => 600, 'layouts' => array( 'columns' ) ) + $px );

		/* ============ Selected State ============ */
		$add( 'selected_bg', __( 'Selected background', 'bundlekit-buy-in-packs' ), 'color', 'selected', array( 'css_var' => '--bk-selected-bg', 'default' => '#f0fdf4' ) );
		$add( 'selected_border', __( 'Selected border color', 'bundlekit-buy-in-packs' ), 'color', 'selected', array( 'css_var' => '--bk-selected-border', 'default' => '#16a34a', 'essential' => true ) );
		$add( 'selected_border_width', __( 'Selected border width', 'bundlekit-buy-in-packs' ), 'number', 'selected', array( 'css_var' => '--bk-selected-border-width', 'default' => '', 'min' => 0, 'max' => 10, 'advanced' => true ) + $px );
		$add( 'selected_color', __( 'Selected text color', 'bundlekit-buy-in-packs' ), 'color', 'selected', array( 'css_var' => '--bk-selected-color', 'default' => '', 'advanced' => true ) );
		$add( 'selected_shadow', __( 'Selected glow / shadow', 'bundlekit-buy-in-packs' ), 'text', 'selected', array( 'css_var' => '--bk-selected-shadow', 'default' => 'none', 'advanced' => true ) );

		/* ============ Typography ============ */
		$add( 'label_size', __( 'Pack title size', 'bundlekit-buy-in-packs' ), 'number', 'typography', array( 'css_var' => '--bk-label-size', 'default' => '17', 'min' => 8, 'max' => 60 ) + $px );
		$add( 'label_weight', __( 'Pack title weight', 'bundlekit-buy-in-packs' ), 'weight', 'typography', array( 'css_var' => '--bk-label-weight', 'default' => '700' ) );
		$add( 'label_color', __( 'Pack title color', 'bundlekit-buy-in-packs' ), 'color', 'typography', array( 'css_var' => '--bk-label-color', 'default' => '#111827' ) );
		$add( 'label_line_height', __( 'Title line height', 'bundlekit-buy-in-packs' ), 'number', 'typography', array( 'css_var' => '--bk-label-line-height', 'default' => '1.3', 'min' => 0.5, 'max' => 3, 'advanced' => true ) );
		$add( 'label_letter_spacing', __( 'Title letter spacing', 'bundlekit-buy-in-packs' ), 'text', 'typography', array( 'css_var' => '--bk-label-letter-spacing', 'default' => '0', 'advanced' => true ) );
		$add( 'label_color_selected', __( 'Title color (selected)', 'bundlekit-buy-in-packs' ), 'color', 'typography', array( 'css_var' => '--bk-label-color-selected', 'default' => '', 'advanced' => true ) );
		$add( 'show_desc', __( 'Show description', 'bundlekit-buy-in-packs' ), 'checkbox', 'typography', array( 'default' => 'yes' ) );
		$add( 'desc_size', __( 'Description size', 'bundlekit-buy-in-packs' ), 'number', 'typography', array( 'css_var' => '--bk-desc-size', 'default' => '13', 'min' => 8, 'max' => 40 ) + $px );
		$add( 'desc_weight', __( 'Description weight', 'bundlekit-buy-in-packs' ), 'weight', 'typography', array( 'css_var' => '--bk-desc-weight', 'default' => '400', 'advanced' => true ) );
		$add( 'desc_line_height', __( 'Description line height', 'bundlekit-buy-in-packs' ), 'number', 'typography', array( 'css_var' => '--bk-desc-line-height', 'default' => '1.5', 'min' => 0.5, 'max' => 3, 'advanced' => true ) );
		$add( 'desc_color', __( 'Description color', 'bundlekit-buy-in-packs' ), 'color', 'typography', array( 'css_var' => '--bk-desc-color', 'default' => '#6b7280' ) );
		$add( 'desc_color_selected', __( 'Description color (selected)', 'bundlekit-buy-in-packs' ), 'color', 'typography', array( 'css_var' => '--bk-desc-color-selected', 'default' => '', 'advanced' => true ) );

		/* ============ Price & Badges ============ */
		$add( 'price_size', __( 'Price size', 'bundlekit-buy-in-packs' ), 'number', 'price_badges', array( 'css_var' => '--bk-price-size', 'default' => '20', 'min' => 8, 'max' => 60, 'essential' => true ) + $px );
		$add( 'price_weight', __( 'Price weight', 'bundlekit-buy-in-packs' ), 'weight', 'price_badges', array( 'css_var' => '--bk-price-weight', 'default' => '800' ) );
		$add( 'price_color', __( 'Price color', 'bundlekit-buy-in-packs' ), 'color', 'price_badges', array( 'css_var' => '--bk-price-color', 'default' => '#111827', 'essential' => true ) );
		$add( 'price_align', __( 'Price alignment', 'bundlekit-buy-in-packs' ), 'select', 'price_badges', array( 'css_var' => '--bk-price-align', 'default' => 'right', 'advanced' => true, 'options' => array( 'right' => 'Right', 'left' => 'Left', 'center' => 'Center' ) ) );
		$add( 'regular_size', __( 'Compare price size', 'bundlekit-buy-in-packs' ), 'number', 'price_badges', array( 'css_var' => '--bk-regular-size', 'default' => '14', 'min' => 8, 'max' => 40 ) + $px );
		$add( 'regular_color', __( 'Compare price color', 'bundlekit-buy-in-packs' ), 'color', 'price_badges', array( 'css_var' => '--bk-regular-color', 'default' => '#9ca3af' ) );
		$add( 'regular_weight', __( 'Compare price weight', 'bundlekit-buy-in-packs' ), 'weight', 'price_badges', array( 'css_var' => '--bk-regular-weight', 'default' => '400', 'advanced' => true ) );
		$add( 'strike_color', __( 'Strikethrough color', 'bundlekit-buy-in-packs' ), 'color', 'price_badges', array( 'css_var' => '--bk-strike-color', 'default' => '', 'advanced' => true ) );
		$add( 'show_regular', __( 'Show struck regular price', 'bundlekit-buy-in-packs' ), 'checkbox', 'price_badges', array( 'default' => 'yes' ) );
		$add( 'price_spacing', __( 'Spacing between prices', 'bundlekit-buy-in-packs' ), 'number', 'price_badges', array( 'css_var' => '--bk-price-spacing', 'default' => '2', 'min' => 0, 'max' => 30, 'advanced' => true ) + $px );
		$add( 'show_save_text', __( 'Show "You save" text', 'bundlekit-buy-in-packs' ), 'checkbox', 'price_badges', array( 'default' => 'yes' ) );
		$add( 'show_save_badge', __( 'Show "Save" pill', 'bundlekit-buy-in-packs' ), 'checkbox', 'price_badges', array( 'default' => 'yes' ) );
		$add( 'save_size', __( '"You save" size', 'bundlekit-buy-in-packs' ), 'number', 'price_badges', array( 'css_var' => '--bk-save-size', 'default' => '13', 'min' => 8, 'max' => 40 ) + $px );
		$add( 'save_color', __( '"You save" color', 'bundlekit-buy-in-packs' ), 'color', 'price_badges', array( 'css_var' => '--bk-save-color', 'default' => '#6b7280' ) );
		$add( 'badge_bg', __( 'Save pill background', 'bundlekit-buy-in-packs' ), 'color', 'price_badges', array( 'css_var' => '--bk-save-pill-bg', 'default' => '#d1fae5' ) );
		$add( 'badge_color', __( 'Save pill text', 'bundlekit-buy-in-packs' ), 'color', 'price_badges', array( 'css_var' => '--bk-save-pill-color', 'default' => '#065f46' ) );
		$add( 'pill_size', __( 'Pill font size', 'bundlekit-buy-in-packs' ), 'number', 'price_badges', array( 'css_var' => '--bk-save-pill-size', 'default' => '12', 'min' => 8, 'max' => 30, 'advanced' => true ) + $px );
		$add( 'pill_weight', __( 'Pill font weight', 'bundlekit-buy-in-packs' ), 'weight', 'price_badges', array( 'css_var' => '--bk-save-pill-weight', 'default' => '600', 'advanced' => true ) );
		$add( 'pill_padding', __( 'Pill padding', 'bundlekit-buy-in-packs' ), 'text', 'price_badges', array( 'css_var' => '--bk-save-pill-padding', 'default' => '3px 10px', 'advanced' => true ) );
		$add( 'pill_radius', __( 'Pill radius', 'bundlekit-buy-in-packs' ), 'number', 'price_badges', array( 'css_var' => '--bk-save-pill-radius', 'default' => '999', 'min' => 0, 'max' => 60, 'advanced' => true ) + $px );
		$add( 'show_popular', __( 'Show "Most Popular" badge', 'bundlekit-buy-in-packs' ), 'checkbox', 'price_badges', array( 'default' => 'yes' ) );
		$add( 'popular_bg', __( '"Most Popular" background', 'bundlekit-buy-in-packs' ), 'color', 'price_badges', array( 'css_var' => '--bk-popular-bg', 'default' => '#111827', 'essential' => true ) );
		$add( 'popular_color', __( '"Most Popular" text', 'bundlekit-buy-in-packs' ), 'color', 'price_badges', array( 'css_var' => '--bk-popular-color', 'default' => '#ffffff' ) );
		$add( 'popular_size', __( 'Badge font size', 'bundlekit-buy-in-packs' ), 'number', 'price_badges', array( 'css_var' => '--bk-popular-size', 'default' => '12', 'min' => 8, 'max' => 30, 'advanced' => true ) + $px );
		$add( 'popular_weight', __( 'Badge font weight', 'bundlekit-buy-in-packs' ), 'weight', 'price_badges', array( 'css_var' => '--bk-popular-weight', 'default' => '700', 'advanced' => true ) );
		$add( 'popular_transform', __( 'Badge text transform', 'bundlekit-buy-in-packs' ), 'select', 'price_badges', array( 'css_var' => '--bk-popular-transform', 'default' => 'none', 'advanced' => true, 'options' => array( 'none' => 'None', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize' ) ) );
		$add( 'popular_padding', __( 'Badge padding', 'bundlekit-buy-in-packs' ), 'text', 'price_badges', array( 'css_var' => '--bk-popular-padding', 'default' => '4px 12px', 'advanced' => true ) );
		$add( 'popular_radius', __( 'Badge radius', 'bundlekit-buy-in-packs' ), 'number', 'price_badges', array( 'css_var' => '--bk-popular-radius', 'default' => '6', 'min' => 0, 'max' => 60, 'advanced' => true ) + $px );
		$add( 'popular_position', __( 'Badge position', 'bundlekit-buy-in-packs' ), 'select', 'price_badges', array( 'default' => 'overlap', 'advanced' => true, 'options' => array( 'overlap' => 'Overlap top-right', 'inline' => 'Inline with title' ) ) );
		$add( 'popular_offset_top', __( 'Badge offset top', 'bundlekit-buy-in-packs' ), 'number', 'price_badges', array( 'css_var' => '--bk-popular-offset-top', 'default' => '-13', 'min' => -60, 'max' => 60, 'advanced' => true ) + $px );
		$add( 'popular_offset_right', __( 'Badge offset right', 'bundlekit-buy-in-packs' ), 'number', 'price_badges', array( 'css_var' => '--bk-popular-offset-right', 'default' => '12', 'min' => -60, 'max' => 120, 'advanced' => true ) + $px );
		$add( 'show_popular_star', __( 'Show star icon', 'bundlekit-buy-in-packs' ), 'checkbox', 'price_badges', array( 'default' => 'yes', 'advanced' => true ) );
		$add( 'popular_star_color', __( 'Star color', 'bundlekit-buy-in-packs' ), 'color', 'price_badges', array( 'css_var' => '--bk-popular-star-color', 'default' => '', 'advanced' => true ) );

		/* ============ Button ============ */

		/* ============ Layout Options (Tier 2) ============ */
		$add( 'columns_desktop', __( 'Columns (desktop)', 'bundlekit-buy-in-packs' ), 'number', 'layout_options', array( 'css_var' => '--bk-columns-desktop', 'default' => '3', 'min' => 1, 'max' => 6, 'layouts' => array( 'columns' ) ) );
		$add( 'columns_tablet', __( 'Columns (tablet)', 'bundlekit-buy-in-packs' ), 'number', 'layout_options', array( 'css_var' => '--bk-columns-tablet', 'default' => '2', 'min' => 1, 'max' => 6, 'layouts' => array( 'columns' ) ) );
		$add( 'columns_mobile', __( 'Columns (mobile)', 'bundlekit-buy-in-packs' ), 'number', 'layout_options', array( 'css_var' => '--bk-columns-mobile', 'default' => '1', 'min' => 1, 'max' => 6, 'layouts' => array( 'columns' ) ) );
		$add( 'or_text', __( '"OR" divider text', 'bundlekit-buy-in-packs' ), 'text', 'layout_options', array( 'default' => 'OR', 'layouts' => array( 'builder' ) ) );
		$add( 'or_color', __( '"OR" text color', 'bundlekit-buy-in-packs' ), 'color', 'layout_options', array( 'css_var' => '--bk-or-color', 'default' => '#6b7280', 'layouts' => array( 'builder' ) ) );
		$add( 'or_line_color', __( '"OR" line color', 'bundlekit-buy-in-packs' ), 'color', 'layout_options', array( 'css_var' => '--bk-or-line-color', 'default' => '#e5e7eb', 'layouts' => array( 'builder' ) ) );
		$add( 'total_bg', __( 'Total box background', 'bundlekit-buy-in-packs' ), 'color', 'layout_options', array( 'css_var' => '--bk-total-bg', 'default' => '#f0fdf4', 'layouts' => array( 'builder' ) ) );
		$add( 'total_border_color', __( 'Total box border', 'bundlekit-buy-in-packs' ), 'color', 'layout_options', array( 'css_var' => '--bk-total-border', 'default' => '#bbf7d0', 'layouts' => array( 'builder' ) ) );
		$add( 'total_radius', __( 'Total box radius', 'bundlekit-buy-in-packs' ), 'number', 'layout_options', array( 'css_var' => '--bk-total-radius', 'default' => '12', 'min' => 0, 'max' => 60, 'layouts' => array( 'builder' ) ) + $px );
		$add( 'total_padding', __( 'Total box padding', 'bundlekit-buy-in-packs' ), 'number', 'layout_options', array( 'css_var' => '--bk-total-padding', 'default' => '16', 'min' => 0, 'max' => 60, 'layouts' => array( 'builder' ) ) + $px );
		$add( 'total_label_size', __( 'Total label size', 'bundlekit-buy-in-packs' ), 'number', 'layout_options', array( 'css_var' => '--bk-total-label-size', 'default' => '15', 'min' => 8, 'max' => 40, 'advanced' => true, 'layouts' => array( 'builder' ) ) + $px );
		$add( 'total_label_color', __( 'Total label color', 'bundlekit-buy-in-packs' ), 'color', 'layout_options', array( 'css_var' => '--bk-total-label-color', 'default' => '#111827', 'advanced' => true, 'layouts' => array( 'builder' ) ) );
		$add( 'total_price_size', __( 'Total price size', 'bundlekit-buy-in-packs' ), 'number', 'layout_options', array( 'css_var' => '--bk-total-price-size', 'default' => '22', 'min' => 8, 'max' => 60, 'layouts' => array( 'builder' ) ) + $px );
		$add( 'total_price_color', __( 'Total price color', 'bundlekit-buy-in-packs' ), 'color', 'layout_options', array( 'css_var' => '--bk-total-price-color', 'default' => '#16a34a', 'layouts' => array( 'builder' ) ) );
		$add( 'total_breakdown_size', __( 'Breakdown text size', 'bundlekit-buy-in-packs' ), 'number', 'layout_options', array( 'css_var' => '--bk-total-sub-size', 'default' => '12', 'min' => 8, 'max' => 30, 'advanced' => true, 'layouts' => array( 'builder' ) ) + $px );
		$add( 'total_breakdown_color', __( 'Breakdown text color', 'bundlekit-buy-in-packs' ), 'color', 'layout_options', array( 'css_var' => '--bk-total-sub-color', 'default' => '#6b7280', 'advanced' => true, 'layouts' => array( 'builder' ) ) );
		$add( 'builder_tier_min', __( 'Tier card min width', 'bundlekit-buy-in-packs' ), 'number', 'layout_options', array( 'css_var' => '--bk-builder-tier-min', 'default' => '150', 'min' => 100, 'max' => 400, 'advanced' => true, 'layouts' => array( 'builder' ) ) + $px );

		/* ============ Responsive ============ */

		/* ============ Custom CSS ============ */
		$add( 'custom_css', __( 'Custom CSS', 'bundlekit-buy-in-packs' ), 'textarea', 'custom_css', array( 'default' => '', 'advanced' => true ) );

		return $s;
	}

	/**
	 * Custom pack + stepper controls. Kept as a separate method so the
	 * schema stays readable; merged into styling_schema() output.
	 * Group: 'cards' would be wrong — they get group 'stepper'.
	 *
	 * @return array<int,array>
	 */
	public static function stepper_schema() {
		$s = array();
		$add = function ( $key, $label, $type, $args = array() ) use ( &$s ) {
			$s[] = array_merge(
				array(
					'key'       => $key,
					'label'     => $label,
					'type'      => $type,
					'default'   => '',
					'css_var'   => '',
					'layouts'   => 'all',
					'group'     => 'stepper',
					'unit'      => '',
					'min'       => null,
					'max'       => null,
					'advanced'  => false,
					'essential' => false,
					'options'   => array(),
				),
				$args
			);
		};
		$px = array( 'unit' => 'px' );
		$add( 'show_stepper_icon', __( 'Show icon box', 'bundlekit-buy-in-packs' ), 'checkbox', array( 'default' => 'yes' ) );
		$add( 'stepper_icon_size', __( 'Icon box size', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-stepper-icon-size', 'default' => '38', 'min' => 20, 'max' => 80, 'advanced' => true ) + $px );
		$add( 'stepper_icon_bg', __( 'Icon box background', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-stepper-icon-bg', 'default' => '#ffffff', 'advanced' => true ) );
		$add( 'stepper_icon_border', __( 'Icon box border', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-stepper-icon-border', 'default' => '#e5e7eb', 'advanced' => true ) );
		$add( 'stepper_icon_radius', __( 'Icon box radius', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-stepper-icon-radius', 'default' => '10', 'min' => 0, 'max' => 40, 'advanced' => true ) + $px );
		$add( 'stepper_icon_color', __( 'Icon color', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-stepper-icon-color', 'default' => '#6b7280', 'advanced' => true ) );
		$add( 'stepper_title_size', __( 'Custom title size', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-stepper-title-size', 'default' => '', 'min' => 8, 'max' => 40, 'advanced' => true ) + $px );
		$add( 'stepper_title_color', __( 'Custom title color', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-stepper-title-color', 'default' => '', 'advanced' => true ) );
		$add( 'stepper_sub_size', __( 'Subtitle size', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-stepper-sub-size', 'default' => '12', 'min' => 8, 'max' => 30, 'advanced' => true ) + $px );
		$add( 'stepper_sub_color', __( 'Subtitle color', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-stepper-sub-color', 'default' => '#6b7280', 'advanced' => true ) );
		$add( 'counter_bg', __( 'Stepper background', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-counter-bg', 'default' => '#f3f4f6' ) );
		$add( 'counter_border', __( 'Stepper border color', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-counter-border', 'default' => '#d1d5db', 'advanced' => true ) );
		$add( 'counter_radius', __( 'Stepper radius', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-counter-radius', 'default' => '8', 'min' => 0, 'max' => 40, 'advanced' => true ) + $px );
		$add( 'counter_btn_bg', __( '+/- button background', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-counter-btn-bg', 'default' => '', 'advanced' => true ) );
		$add( 'counter_btn_color', __( '+/- icon color', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-counter-btn-color', 'default' => '#111827' ) );
		$add( 'counter_btn_size', __( '+/- button size', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-counter-btn-size', 'default' => '34', 'min' => 24, 'max' => 60, 'advanced' => true ) + $px );
		$add( 'counter_color', __( 'Quantity text color', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-counter-color', 'default' => '#111827', 'advanced' => true ) );
		$add( 'counter_qty_size', __( 'Quantity font size', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-counter-qty-size', 'default' => '15', 'min' => 8, 'max' => 40, 'advanced' => true ) + $px );
		$add( 'counter_qty_width', __( 'Quantity input width', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-counter-qty-width', 'default' => '52', 'min' => 30, 'max' => 120, 'advanced' => true ) + $px );
		$add( 'counter_height', __( 'Stepper height', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-counter-height', 'default' => '34', 'min' => 24, 'max' => 80, 'advanced' => true ) + $px );
		$add( 'show_custom', __( 'Show the Custom pack row', 'bundlekit-buy-in-packs' ), 'checkbox', array( 'default' => 'yes' ) );
		return $s;
	}

	/**
	 * Radio controls, kept separate like the stepper for readability.
	 *
	 * @return array<int,array>
	 */
	public static function radio_schema() {
		$s = array();
		$add = function ( $key, $label, $type, $args = array() ) use ( &$s ) {
			$s[] = array_merge(
				array(
					'key'       => $key,
					'label'     => $label,
					'type'      => $type,
					'default'   => '',
					'css_var'   => '',
					'layouts'   => 'all',
					'group'     => 'radio',
					'unit'      => '',
					'min'       => null,
					'max'       => null,
					'advanced'  => false,
					'essential' => false,
					'options'   => array(),
				),
				$args
			);
		};
		$px = array( 'unit' => 'px' );
		$add( 'show_radio', __( 'Show radio', 'bundlekit-buy-in-packs' ), 'checkbox', array( 'default' => 'yes' ) );
		$add( 'radio_size', __( 'Radio size', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-radio-size', 'default' => '20', 'min' => 10, 'max' => 40 ) + $px );
		$add( 'radio_border', __( 'Radio border color', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-radio-border', 'default' => '#d1d5db' ) );
		$add( 'radio_color', __( 'Active radio color', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-radio-color', 'default' => '#16a34a' ) );
		$add( 'radio_dot_size', __( 'Dot size', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-radio-dot', 'default' => '', 'min' => 4, 'max' => 30, 'advanced' => true ) + $px );
		$add( 'radio_spacing', __( 'Spacing from title', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-radio-spacing', 'default' => '0', 'min' => 0, 'max' => 40, 'advanced' => true ) + $px );
		return $s;
	}

	/**
	 * Footer note controls.
	 *
	 * @return array<int,array>
	 */
	public static function footer_schema() {
		$s = array();
		$add = function ( $key, $label, $type, $args = array() ) use ( &$s ) {
			$s[] = array_merge(
				array(
					'key'       => $key,
					'label'     => $label,
					'type'      => $type,
					'default'   => '',
					'css_var'   => '',
					'layouts'   => 'all',
					'group'     => 'footer',
					'unit'      => '',
					'min'       => null,
					'max'       => null,
					'advanced'  => false,
					'essential' => false,
					'options'   => array(),
				),
				$args
			);
		};
		$px = array( 'unit' => 'px' );
		$add( 'show_hint', __( 'Show footer note', 'bundlekit-buy-in-packs' ), 'checkbox', array( 'default' => 'yes' ) );
		$add( 'hint_text', __( 'Footer note text', 'bundlekit-buy-in-packs' ), 'text', array( 'default' => 'Select a pack, then click Add to cart.' ) );
		$add( 'hint_size', __( 'Footer note size', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-hint-size', 'default' => '13', 'min' => 8, 'max' => 30 ) + $px );
		$add( 'hint_color', __( 'Footer note color', 'bundlekit-buy-in-packs' ), 'color', array( 'css_var' => '--bk-hint-color', 'default' => '#6b7280' ) );
		$add( 'hint_align', __( 'Footer note alignment', 'bundlekit-buy-in-packs' ), 'select', array( 'css_var' => '--bk-hint-align', 'default' => 'left', 'advanced' => true, 'options' => array( 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ) ) );
		$add( 'hint_margin_top', __( 'Footer note margin top', 'bundlekit-buy-in-packs' ), 'number', array( 'css_var' => '--bk-hint-margin-top', 'default' => '10', 'min' => 0, 'max' => 60, 'advanced' => true ) + $px );
		return $s;
	}

	/**
	 * All controls merged, in admin order. Groups render in
	 * styling_groups() order; 'stepper', 'radio' and 'footer' groups
	 * are appended after 'price_badges' for a sensible flow.
	 *
	 * @return array<int,array>
	 */
	public static function all_controls() {
		return array_merge(
			self::styling_schema(),
			self::stepper_schema(),
			self::radio_schema(),
			self::footer_schema()
		);
	}

	/**
	 * Group order for the merged schema: spec order, then stepper,
	 * radio and footer after the Button group.
	 *
	 * @return array<string,string>
	 */
	public static function all_groups() {
		$groups = self::styling_groups();
		$ordered = array();
		foreach ( $groups as $slug => $label ) {
			$ordered[ $slug ] = $label;
			if ( 'price_badges' === $slug ) {
				$ordered['stepper'] = __( 'Custom Pack & Stepper', 'bundlekit-buy-in-packs' );
				$ordered['radio']   = __( 'Radio Button', 'bundlekit-buy-in-packs' );
				$ordered['footer']  = __( 'Footer Note', 'bundlekit-buy-in-packs' );
			}
		}
		// Custom CSS lives on its own tab, not in the Styling tab groups.
		$ordered['custom_css'] = __( 'Custom CSS', 'bundlekit-buy-in-packs' );
		return $ordered;
	}

	/**
	 * Sanitize one control value. '' is only preserved for checkbox
	 * (unchecked); every other type falls back to its default.
	 *
	 * @param array $spec Control spec.
	 * @param mixed $raw  Raw input.
	 * @return string
	 */
	public static function sanitize_value( $spec, $raw ) {
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		if ( 'checkbox' === $spec['type'] ) {
			return 'yes' === $raw ? 'yes' : '';
		}
		if ( '' === $raw ) {
			$raw = (string) $spec['default'];
		}
		switch ( $spec['type'] ) {
			case 'color':
				$v = sanitize_hex_color( $raw );
				return $v ? $v : (string) $spec['default'];
			case 'number':
				$v = is_numeric( $raw ) ? (float) $raw : (float) $spec['default'];
				if ( null !== $spec['min'] ) {
					$v = max( (float) $spec['min'], $v );
				}
				if ( null !== $spec['max'] ) {
					$v = min( (float) $spec['max'], $v );
				}
				return (string) ( (float) $v === (float) (int) $v ? (int) $v : round( $v, 2 ) );
			case 'font':
				$stacks = BundleKit_Buy_In_Packs::font_stacks();
				return isset( $stacks[ $raw ] ) ? $raw : (string) $spec['default'];
			case 'weight':
				return in_array( $raw, array( '400', '500', '600', '700', '800' ), true ) ? $raw : (string) $spec['default'];
			case 'select':
				return isset( $spec['options'][ $raw ] ) ? $raw : (string) $spec['default'];
			case 'textarea':
				return wp_strip_all_tags( $raw );
			default: // text.
				$v = sanitize_text_field( $raw );
				return '' !== $v ? $v : (string) $spec['default'];
		}
	}

	/**
	 * Format a sanitized value for CSS variable output.
	 *
	 * @param array  $spec  Control spec.
	 * @param string $value Sanitized value.
	 * @return string Empty when nothing should be output.
	 */
	public static function css_var_value( $spec, $value ) {
		if ( '' === $spec['css_var'] ) {
			return '';
		}
		if ( 'checkbox' === $spec['type'] ) {
			// Checked = default look (no variable); unchecked hides.
			return 'yes' === $value ? '' : 'none';
		}
		if ( 'number' === $spec['type'] ) {
			return '' === $value ? '' : $value . $spec['unit'];
		}
		if ( 'font' === $spec['type'] ) {
			$stacks = BundleKit_Buy_In_Packs::font_stacks();
			return isset( $stacks[ $value ] ) ? $stacks[ $value ] : '';
		}
		return $value;
	}

	/**
	 * Migrate 1.4.x individual options into the single 1.5.0 array.
	 * Runs once; old options are left in place, harmless.
	 */
	public static function migrate_legacy() {
		if ( false !== get_option( 'bundlekit_buy_in_packs_styling', false ) ) {
			return;
		}
		$migrated = array();
		// 1.5.0 renames for the class-based show/hide controls, plus the
		// old Display tab options (bundlekit_buy_in_packs_show_*).
		$show_map = array(
			'show_title'         => array( 'show_title', 'title' ),
			'show_divider'       => array( 'divider_show', null ),
			'show_desc'          => array( 'desc_show', null ),
			'show_save_text'     => array( 'save_text_show', 'save_percent' ),
			'show_save_badge'    => array( 'save_pill_show', 'save_badge' ),
			'show_regular'       => array( null, 'regular' ),
			'show_popular'       => array( 'popular_show', 'popular' ),
			'show_popular_star'  => array( 'popular_star_show', null ),
			'show_custom'        => array( null, 'custom' ),
			'show_stepper_icon'  => array( 'stepper_icon_show', null ),
			'show_radio'         => array( 'radio_show', null ),
			'show_hint'          => array( 'hint_show', 'hint' ),
		);
		foreach ( self::all_controls() as $spec ) {
			$key = $spec['key'];
			$old = get_option( 'bundlekit_buy_in_packs_style_' . $key, null );
			if ( ( null === $old || '' === $old ) && isset( $show_map[ $key ] ) ) {
				list( $style_old, $display_old ) = $show_map[ $key ];
				if ( null !== $style_old ) {
					$old = get_option( 'bundlekit_buy_in_packs_style_' . $style_old, null );
				}
				if ( ( null === $old || '' === $old ) && null !== $display_old ) {
					// Display tab: 'yes' = show, 'no' = hide.
					$disp = get_option( 'bundlekit_buy_in_packs_show_' . $display_old, null );
					$old  = 'no' === $disp ? '' : $disp;
				}
			}
			if ( null !== $old && '' !== $old ) {
				$migrated[ $key ] = $old;
			}
		}
		$old_layout = get_option( 'bundlekit_buy_in_packs_layout', null );
		if ( null !== $old_layout && '' !== $old_layout ) {
			$migrated['layout'] = $old_layout;
		}
		update_option( 'bundlekit_buy_in_packs_styling', $migrated );
	}

	/**
	 * The effective styling values: stored array → sanitize →
	 * defaults. One loop, the single source of truth for admin,
	 * preview and frontend.
	 *
	 * @return array<string,string>
	 */
	public static function styling_values() {
		self::migrate_legacy();
		$stored = get_option( 'bundlekit_buy_in_packs_styling', array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = array();
		foreach ( self::all_controls() as $spec ) {
			$key = $spec['key'];
			if ( 'checkbox' === $spec['type'] ) {
				$raw = array_key_exists( $key, $stored ) ? $stored[ $key ] : $spec['default'];
			} else {
				$raw = isset( $stored[ $key ] ) && '' !== $stored[ $key ] ? $stored[ $key ] : $spec['default'];
			}
			$values[ $key ] = self::sanitize_value( $spec, $raw );
		}
		return $values;
	}

	/**
	 * Per-layout presets: deviations from the schema defaults that
	 * make each layout look right out of the box. Used by the
	 * "Reset to layout defaults" button.
	 *
	 * @return array<string,array<string,string>> layout => key => value.
	 */
	public static function styling_presets() {
		return array(
			'current'   => array(),
			'columns'   => array(),
			'builder'   => array(),
		);
	}

	/**
	 * A preset merged over the schema defaults: the full value set
	 * "Reset to layout defaults" writes into the form.
	 *
	 * @param string $layout Layout slug.
	 * @return array<string,string>
	 */
	public static function preset_values( $layout ) {
		$presets = self::styling_presets();
		$values  = array();
		foreach ( self::all_controls() as $spec ) {
			$values[ $spec['key'] ] = (string) $spec['default'];
		}
		if ( isset( $presets[ $layout ] ) ) {
			foreach ( $presets[ $layout ] as $key => $value ) {
				$values[ $key ] = $value;
			}
		}
		$values['layout'] = $layout;
		return $values;
	}
}
