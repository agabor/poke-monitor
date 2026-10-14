<?php

namespace Poke_Monitor;

defined( 'ABSPATH' ) || exit;

class Deactivator {

	public static function deactivate() {
		wp_clear_scheduled_hook( 'poke_monitor_purge' );
		wp_clear_scheduled_hook( Cron::UPDATE_HOOK );
	}
}