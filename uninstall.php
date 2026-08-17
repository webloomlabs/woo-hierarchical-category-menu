<?php
/**
 * Uninstall cleanup for Woo Hierarchical Category Menu.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$menu_items = get_posts(
	array(
		'post_type'      => 'nav_menu_item',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			array(
				'key'   => '_wbl_whcm_managed',
				'value' => '1',
			),
			array(
				'key'     => '_wbl_whcm_shortcode',
				'compare' => 'EXISTS',
			),
		),
	)
);

foreach ( $menu_items as $menu_item_id ) {
	wp_delete_post( $menu_item_id, true );
}

delete_option( 'wbl_whcm_settings' );
wp_clear_scheduled_hook( 'wbl_whcm_sync_event' );
