<?php
/**
 * Storms WooCommerce Calculo ST Uninstall
 *
 * Uninstalling Storms WooCommerce Calculo ST deletes the control tables that keep Calculo ST parameters
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function storms_wc_calculost_uninstall() {
	global $wpdb;

	$calculost_table = $wpdb->prefix . 'calculost';
	$wpdb->query("DROP TABLE IF EXISTS $calculost_table");
	delete_option("calculost_db_version");

	// Clear any cached data that has been removed.
	wp_cache_flush();
}

storms_wc_calculost_uninstall();
