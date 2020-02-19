<?php
/**
 * Storms WooCommerce Calculo ST Install
 *
 * Installing Storms WooCommerce Calculo ST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function storms_wc_calculost_create_database_table() {
	global $wpdb;

	$calculost_db_version = '1.0.0';

	$calculost_table = $wpdb->prefix . 'calculost';
	$charset_collate = $wpdb->get_charset_collate();

	// Check to see if the table exists already, if not, then create it
	if( $wpdb->get_var( "SHOW TABLES LIKE '{$calculost_table}'" ) != $calculost_table ) {

		$sql = "CREATE TABLE $calculost_table (
				  Id INT NOT NULL AUTO_INCREMENT,
				  UfOrigem varchar(3),
				  UfDestino varchar(255),
				  AliqInterestadual decimal(10,4),
				  AliqInterna decimal(10,4),
				  Mva decimal(10,5),
				  MvaImportado decimal(10,5),
				  AliqImportado decimal(10,4),
				  Formula TINYINT,
				  Fcp decimal(10,4),				  
				  PRIMARY KEY ( Id )
    			)    $charset_collate;";

		// Modifies the database based on specified SQL statements
		require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		dbDelta($sql);

		// Keep the version of our current database table
		add_option( 'calculost_db_version', $calculost_db_version );
	}
}

storms_wc_calculost_create_database_table();
