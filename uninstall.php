<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-schema.php';

function poke_monitor_uninstall_site(): void {
	global $wpdb;

	$table_suffixes_children_first = array(
		'request_errors',
		'requests',
		'request_types',
		'user_agents',
		'error_types',
	);

	foreach ( $table_suffixes_children_first as $table_suffix ) {
		$table_name = \Poke_Monitor\Schema::get_table_name( $table_suffix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
	}

	$option_names = array(
		'poke_monitor_cf_status',
		'poke_monitor_cf_ip_status',
		'poke_monitor_cf_ip_ranges',
		'poke_monitor_probe_patterns',
		'poke_monitor_settings',
		'poke_monitor_allowlist',
		'poke_monitor_db_version',
	);

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}

	wp_clear_scheduled_hook( 'poke_monitor_purge' );

	wp_clear_scheduled_hook( 'poke_monitor_update_remote_data' );

	wp_cache_delete( 'cache_version', 'poke-monitor' );
}

function poke_monitor_uninstall_users(): void {
	delete_metadata( 'user', 0, 'poke_monitor_last_tab', '', true );
}

function poke_monitor_uninstall(): void {
	if ( is_multisite() ) {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );

			poke_monitor_uninstall_site();

			restore_current_blog();
		}
	} else {
		poke_monitor_uninstall_site();
	}

	poke_monitor_uninstall_users();
}

poke_monitor_uninstall();