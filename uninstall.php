<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function silvertrail_uninstall_site(): void {
	global $wpdb;
	delete_option( 'silvertrail' );
	delete_option( 'silvertrail_version' );
	wp_clear_scheduled_hook( 'silvertrail_prune_records' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removes the plugin's own table.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'silvertrail_records' ) );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $silvertrail_site ) {
		switch_to_blog( (int) $silvertrail_site );
		silvertrail_uninstall_site();
		restore_current_blog();
	}
} else {
	silvertrail_uninstall_site();
}
