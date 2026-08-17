<?php
/**
 * Plugin Name: Woo Hierarchical Category Menu
 * Description: Adds movable, dynamic WooCommerce category items to WordPress menus and provides a hierarchical category shortcode.
 * Version: 2.0.0
 * Author: WBL
 * License: GPL-2.0-or-later
 * Text Domain: woo-hierarchical-category-menu
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

defined( 'ABSPATH' ) || exit;

final class WBL_Woo_Hierarchical_Category_Menu {
	private const MARKER_URL = '#wbl-woo-categories';
	private const SHORTCODE_META = '_wbl_whcm_shortcode';
	private const SHORTCODE = 'woo_category_menu';
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
		add_action( 'admin_head-nav-menus.php', array( $this, 'add_menu_meta_box' ) );
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'menu_item_fields' ), 10, 2 );
		add_action( 'wp_update_nav_menu_item', array( $this, 'save_menu_item' ), 10, 3 );
		add_filter( 'wp_nav_menu_objects', array( $this, 'expand_menu_items' ), 10, 2 );
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
	}

	public function woocommerce_notice(): void {
		if ( current_user_can( 'activate_plugins' ) && ! taxonomy_exists( 'product_cat' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Woo Hierarchical Category Menu requires WooCommerce.', 'woo-hierarchical-category-menu' ) . '</p></div>';
		}
	}

	public function add_menu_meta_box(): void {
		add_meta_box( 'wbl-whcm-menu-item', __( 'Woo Category Shortcode', 'woo-hierarchical-category-menu' ), array( $this, 'render_menu_meta_box' ), 'nav-menus', 'side', 'default' );
	}

	public function render_menu_meta_box(): void {
		$shortcode = '[' . self::SHORTCODE . ' depth="0" hide_empty="1"]';
		?>
		<div id="posttype-wbl-whcm" class="posttypediv">
			<div class="tabs-panel tabs-panel-active">
				<ul class="categorychecklist form-no-clear"><li>
					<input type="checkbox" class="menu-item-checkbox" name="menu-item[-9999][menu-item-object-id]" value="-9999" checked="checked" style="display:none">
					<label for="wbl-whcm-title"><?php esc_html_e( 'Title', 'woo-hierarchical-category-menu' ); ?></label>
					<input id="wbl-whcm-title" class="widefat" type="text" name="menu-item[-9999][menu-item-title]" value="<?php esc_attr_e( 'Product Categories', 'woo-hierarchical-category-menu' ); ?>">
					<label for="wbl-whcm-shortcode" style="display:block;margin-top:10px"><?php esc_html_e( 'Shortcode', 'woo-hierarchical-category-menu' ); ?></label>
					<textarea id="wbl-whcm-shortcode" class="widefat" rows="4" name="menu-item[-9999][menu-item-description]"><?php echo esc_textarea( $shortcode ); ?></textarea>
					<input type="hidden" name="menu-item[-9999][menu-item-type]" value="custom">
					<input type="hidden" name="menu-item[-9999][menu-item-url]" value="<?php echo esc_attr( self::MARKER_URL ); ?>">
					<input type="hidden" name="menu-item[-9999][menu-item-object]" value="custom">
				</li></ul>
				<p class="description"><?php esc_html_e( 'Attributes: depth, parent, hide_empty, orderby, order, include, exclude, show_count.', 'woo-hierarchical-category-menu' ); ?></p>
			</div>
			<p class="button-controls wp-clearfix"><span class="add-to-menu">
				<input type="submit" class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu', 'woo-hierarchical-category-menu' ); ?>" name="add-post-type-menu-item" id="submit-posttype-wbl-whcm"><span class="spinner"></span>
			</span></p>
		</div>
		<?php
	}

	public function menu_item_fields( int $item_id, $item ): void {
		if ( ! $this->is_placeholder( $item ) ) {
			return;
		}
		$value = get_post_meta( $item_id, self::SHORTCODE_META, true );
		$value = $value ?: ( $item->description ?: '[' . self::SHORTCODE . ']' );
		?>
		<p class="description description-wide">
			<label for="edit-menu-item-wbl-shortcode-<?php echo esc_attr( $item_id ); ?>"><?php esc_html_e( 'Category shortcode', 'woo-hierarchical-category-menu' ); ?><br>
			<input type="text" id="edit-menu-item-wbl-shortcode-<?php echo esc_attr( $item_id ); ?>" class="widefat code" name="menu-item-wbl-shortcode[<?php echo esc_attr( $item_id ); ?>]" value="<?php echo esc_attr( $value ); ?>"></label>
		</p>
		<?php
	}

	public function save_menu_item( int $menu_id, int $menu_item_id, array $args ): void {
		if ( isset( $_POST['menu-item-wbl-shortcode'][ $menu_item_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value = sanitize_text_field( wp_unslash( $_POST['menu-item-wbl-shortcode'][ $menu_item_id ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $menu_item_id, self::SHORTCODE_META, $value );
		} elseif ( self::MARKER_URL === ( $args['menu-item-url'] ?? '' ) ) {
			$value = sanitize_text_field( $args['menu-item-description'] ?? '' );
			update_post_meta( $menu_item_id, self::SHORTCODE_META, $value ?: '[' . self::SHORTCODE . ']' );
		}
	}

	public function expand_menu_items( array $items, $args ): array {
		$expanded = array();
		foreach ( $items as $item ) {
			$expanded[] = $item;
			if ( $this->is_placeholder( $item ) ) {
				$shortcode = get_post_meta( $item->ID, self::SHORTCODE_META, true );
				foreach ( $this->category_menu_items( $this->parse_shortcode_attributes( $shortcode ), (int) $item->ID ) as $category_item ) {
					$expanded[] = $category_item;
				}
			}
		}
		return $expanded;
	}

	private function is_placeholder( $item ): bool {
		return isset( $item->type, $item->url ) && 'custom' === $item->type && self::MARKER_URL === $item->url;
	}

	private function parse_shortcode_attributes( $shortcode ): array {
		if ( is_string( $shortcode ) && '' !== trim( $shortcode ) ) {
			$pattern = get_shortcode_regex( array( self::SHORTCODE ) );
			if ( preg_match( '/^' . $pattern . '$/s', trim( $shortcode ), $match ) && self::SHORTCODE === $match[2] ) {
				return $this->attributes( shortcode_parse_atts( $match[3] ) ?: array() );
			}
		}
		return $this->attributes( array() );
	}

	private function attributes( array $attributes ): array {
		$attributes = shortcode_atts( array(
			'depth' => 0, 'parent' => 0, 'hide_empty' => 1, 'orderby' => 'name',
			'order' => 'ASC', 'include' => '', 'exclude' => '', 'show_count' => 0,
		), $attributes, self::SHORTCODE );
		$allowed_orderby = array( 'name', 'slug', 'term_id', 'count', 'menu_order' );
		$attributes['depth'] = max( 0, absint( $attributes['depth'] ) );
		$attributes['parent'] = absint( $attributes['parent'] );
		$attributes['hide_empty'] = $this->boolean( $attributes['hide_empty'] );
		$attributes['show_count'] = $this->boolean( $attributes['show_count'] );
		$attributes['orderby'] = in_array( $attributes['orderby'], $allowed_orderby, true ) ? $attributes['orderby'] : 'name';
		$attributes['order'] = 'DESC' === strtoupper( $attributes['order'] ) ? 'DESC' : 'ASC';
		$attributes['include'] = implode( ',', wp_parse_id_list( $attributes['include'] ) );
		$attributes['exclude'] = implode( ',', wp_parse_id_list( $attributes['exclude'] ) );
		return $attributes;
	}

	private function boolean( $value ): bool {
		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}

	private function get_categories( array $attributes ): array {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$query = array( 'taxonomy' => 'product_cat', 'hide_empty' => $attributes['hide_empty'], 'orderby' => $attributes['orderby'], 'order' => $attributes['order'] );
		if ( $attributes['include'] ) {
			$query['include'] = wp_parse_id_list( $attributes['include'] );
		}
		if ( $attributes['exclude'] ) {
			$query['exclude'] = wp_parse_id_list( $attributes['exclude'] );
		}
		$terms = get_terms( $query );
		return is_wp_error( $terms ) ? array() : $this->ordered_terms( $terms, $attributes['parent'], $attributes['depth'] );
	}

	private function ordered_terms( array $terms, int $root, int $max_depth ): array {
		$children = array();
		foreach ( $terms as $term ) {
			$children[ (int) $term->parent ][] = $term;
		}
		$result = array();
		$walk = function ( int $parent, int $level ) use ( &$walk, &$result, $children, $max_depth ): void {
			if ( $max_depth && $level > $max_depth ) {
				return;
			}
			foreach ( $children[ $parent ] ?? array() as $term ) {
				$result[] = array( 'term' => $term, 'level' => $level );
				$walk( (int) $term->term_id, $level + 1 );
			}
		};
		$walk( $root, 1 );
		return $result;
	}

	private function category_menu_items( array $attributes, int $placeholder_id ): array {
		$items = array();
		$ids = array();
		foreach ( $this->get_categories( $attributes ) as $entry ) {
			$term = $entry['term'];
			$id = -1 * ( ( $placeholder_id * 1000000 ) + (int) $term->term_id );
			$ids[ (int) $term->term_id ] = $id;
			$parent_id = (int) $term->parent === (int) $attributes['parent'] ? $placeholder_id : ( $ids[ (int) $term->parent ] ?? $placeholder_id );
			$url = get_term_link( $term );
			$item = new stdClass();
			$item->ID = $id;
			$item->db_id = $id;
			$item->menu_item_parent = $parent_id;
			$item->object_id = (int) $term->term_id;
			$item->object = 'product_cat';
			$item->type = 'taxonomy';
			$item->type_label = __( 'Product category', 'woo-hierarchical-category-menu' );
			$item->title = $term->name . ( $attributes['show_count'] ? ' (' . (int) $term->count . ')' : '' );
			$item->url = is_wp_error( $url ) ? '#' : $url;
			$item->target = '';
			$item->attr_title = '';
			$item->description = '';
			$item->classes = array( 'menu-item', 'menu-item-type-taxonomy', 'menu-item-object-product_cat', 'wbl-dynamic-product-category' );
			$item->xfn = '';
			$item->status = 'publish';
			$items[] = $item;
		}
		return $items;
	}

	public function render_shortcode( $attributes ): string {
		$attributes = $this->attributes( is_array( $attributes ) ? $attributes : array() );
		$entries = $this->get_categories( $attributes );
		if ( ! $entries ) {
			return '';
		}
		$output = '<ul class="wbl-woo-category-menu">';
		$level = 1;
		foreach ( $entries as $index => $entry ) {
			$current = (int) $entry['level'];
			if ( $index && $current > $level ) {
				$output .= '<ul class="children">';
			} elseif ( $index && $current < $level ) {
				$output .= str_repeat( '</li></ul>', $level - $current ) . '</li>';
			} elseif ( $index ) {
				$output .= '</li>';
			}
			$term = $entry['term'];
			$url = get_term_link( $term );
			$title = esc_html( $term->name );
			$title .= $attributes['show_count'] ? ' <span class="count">(' . (int) $term->count . ')</span>' : '';
			$output .= '<li class="product-cat-item product-cat-item-' . (int) $term->term_id . '"><a href="' . esc_url( is_wp_error( $url ) ? '#' : $url ) . '">' . $title . '</a>';
			$level = $current;
		}
		$output .= str_repeat( '</li></ul>', $level );
		return $output;
	}
}

WBL_Woo_Hierarchical_Category_Menu::instance();
