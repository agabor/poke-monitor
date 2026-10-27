<?php

namespace Poke_Monitor;

defined( 'ABSPATH' ) || exit;

class Activator {

	public static function activate() {
		Schema::create_or_upgrade();

		Cf_Detector::maybe_auto_set_on_activation();

		Cf_Detector::maybe_auto_set_ip_check_on_activation();

		if ( ! wp_next_scheduled( 'poke_monitor_purge' ) ) {
			wp_schedule_event( time(), 'daily', 'poke_monitor_purge' );
		}

		if ( ! wp_next_scheduled( Cron::UPDATE_HOOK ) ) {
			wp_schedule_event( time(), 'daily', Cron::UPDATE_HOOK );
		}
	}
}