<?php

namespace Poke_Monitor;

defined( 'ABSPATH' ) || exit;

class Cron {

	const HOOK = 'poke_monitor_purge';

	const UPDATE_HOOK = 'poke_monitor_update_remote_data';

	const KEEP_DAYS = 30;

	private $repository;

	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	public function register_hooks(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( self::UPDATE_HOOK, array( $this, 'run_remote_update' ) );
		add_action( 'plugins_loaded', array( $this, 'ensure_scheduled' ) );
	}

	public function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK );
		}

		if ( ! wp_next_scheduled( self::UPDATE_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::UPDATE_HOOK );
		}
	}

	public function run(): int {
		try {
			return $this->repository->delete_requests_older_than( $this->get_keep_days() );
		} catch ( \Throwable $caught_exception ) {
			return 0;
		}
	}

	public function run_remote_update(): void {
		try {
			( new Probe_Patterns() )->update();
		} catch ( \Throwable $caught_exception ) {
			unset( $caught_exception );
		}

		try {
			( new Cf_Ip_Ranges() )->refresh();
		} catch ( \Throwable $caught_exception ) {
			unset( $caught_exception );
		}
	}

	public function get_keep_days(): int {
		return self::KEEP_DAYS;
	}
}