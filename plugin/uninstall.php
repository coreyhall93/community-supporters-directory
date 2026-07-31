<?php
/**
 * Uninstall routine — removes plugin options and cached records.
 *
 * @package CommunitySupportersDirectory
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'comsup_settings' );

// Remove cached Airtable record transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_comsup_records_%' OR option_name LIKE '_transient_timeout_comsup_records_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
