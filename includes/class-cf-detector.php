<?php

namespace Poke_Monitor;

defined( 'ABSPATH' ) || exit;

class Cf_Detector {

	const OPTION_NAME = 'poke_monitor_cf_status';

	const IP_OPTION_NAME = 'poke_monitor_cf_ip_status';

	public static function request_has_all_cf_headers() {
		return ! empty( $_SERVER['HTTP_CF_RAY'] )
			&& ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] )
			&& ! empty( $_SERVER['HTTP_CF_VISITOR'] );
	}

	public static function request_remote_addr_is_cloudflare(): bool {
		$address = ( new Ip_Resolver() )->resolve_remote_addr();

		return self::is_cloudflare_ip( $address );
	}

	public static function is_cloudflare_ip( string $ip ): bool {
		return ( new Cf_Ip_Ranges() )->contains( $ip );
	}

	public static function count_present_cf_headers() {
		$present_header_count = 0;

		if ( ! empty( $_SERVER['HTTP_CF_RAY'] ) ) {
			++$present_header_count;
		}

		if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			++$present_header_count;
		}

		if ( ! empty( $_SERVER['HTTP_CF_VISITOR'] ) ) {
			++$present_header_count;
		}

		return $present_header_count;
	}

	private static function read_status_option( $option_name ) {
		$default_status = array(
			'enabled' => false,
			'state'   => 'unknown',
			'set_by'  => null,
			'set_at'  => null,
			'user_id' => null,
		);

		$stored_status = get_option( $option_name, array() );

		if ( ! is_array( $stored_status ) ) {
			$stored_status = array();
		}

		return wp_parse_args( $stored_status, $default_status );
	}

	private static function write_status_option( $option_name, $enabled, $set_by, $user_id ) {
		$new_status = array(
			'enabled' => (bool) $enabled,
			'state'   => 'set',
			'set_by'  => $set_by,
			'set_at'  => gmdate( 'Y-m-d H:i:s' ),
			'user_id' => $user_id,
		);

		update_option( $option_name, $new_status );
	}

	public static function get_status() {
		return self::read_status_option( self::OPTION_NAME );
	}

	public static function get_ip_check_status() {
		return self::read_status_option( self::IP_OPTION_NAME );
	}

	public static function is_bypass_detection_enabled() {
		$current_status = self::get_status();

		return (bool) $current_status['enabled'];
	}

	public static function is_ip_check_enabled() {
		$current_status = self::get_ip_check_status();

		return (bool) $current_status['enabled'];
	}

	public static function is_cloudflare_trusted_request() {
		return self::is_bypass_detection_enabled()
			&& self::request_has_all_cf_headers()
			&& ( ! self::is_ip_check_enabled() || self::request_remote_addr_is_cloudflare() );
	}

	public static function maybe_auto_set_on_activation() {
		$current_status = self::get_status();

		if ( 'unknown' !== $current_status['state'] ) {
			return;
		}

		if ( empty( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		self::set_status( self::request_has_all_cf_headers(), 'activation' );
	}

	public static function maybe_auto_set_ip_check_on_activation() {
		$current_status = self::get_ip_check_status();

		if ( 'unknown' !== $current_status['state'] ) {
			return;
		}

		if ( empty( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		self::set_ip_check_status( self::request_remote_addr_is_cloudflare(), 'activation' );
	}

	public static function maybe_auto_set_on_first_admin_visit() {
		$current_status = self::get_status();

		if ( 'unknown' !== $current_status['state'] ) {
			return;
		}

		self::set_status( self::request_has_all_cf_headers(), 'first_admin_visit' );
	}

	public static function maybe_auto_set_ip_check_on_first_admin_visit() {
		$current_status = self::get_ip_check_status();

		if ( 'unknown' !== $current_status['state'] ) {
			return;
		}

		self::set_ip_check_status( self::request_remote_addr_is_cloudflare(), 'first_admin_visit' );
	}

	public static function set_status( $enabled, $set_by, $user_id = null ) {
		self::write_status_option( self::OPTION_NAME, $enabled, $set_by, $user_id );
	}

	public static function set_ip_check_status( $enabled, $set_by, $user_id = null ) {
		self::write_status_option( self::IP_OPTION_NAME, $enabled, $set_by, $user_id );
	}

	public static function get_live_note() {
		$present_header_count = self::count_present_cf_headers();
		$missing_headers      = array();

		if ( empty( $_SERVER['HTTP_CF_RAY'] ) ) {
			$missing_headers[] = 'CF-Ray';
		}

		if ( empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			$missing_headers[] = 'CF-IPCountry';
		}

		if ( empty( $_SERVER['HTTP_CF_VISITOR'] ) ) {
			$missing_headers[] = 'CF-Visitor';
		}

		$ray_id = ! empty( $_SERVER['HTTP_CF_RAY'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_RAY'] ) )
			: null;

		$country = ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) )
			: null;

		if ( 3 === $present_header_count ) {
			$status  = 'full';
			$message = sprintf(
				/* translators: 1: Cloudflare Ray ID, 2: two-letter country code. */
				__( 'This request came through Cloudflare (Ray %1$s, %2$s)', 'poke-monitor' ),
				$ray_id,
				$country
			);
		} elseif ( $present_header_count > 0 ) {
			$status  = 'partial';
			$message = sprintf(
				/* translators: %s: comma-separated list of missing header names. */
				__( 'This request has only some Cloudflare headers (missing: %s)', 'poke-monitor' ),
				implode( ', ', $missing_headers )
			);
		} else {
			$status  = 'none';
			$message = __( 'This request did not come through Cloudflare', 'poke-monitor' );
		}

		return array(
			'status'  => $status,
			'message' => $message,
		);
	}

	public static function get_ip_check_live_note() {
		$remote_address = ( new Ip_Resolver() )->resolve_remote_addr();

		if ( self::is_cloudflare_ip( $remote_address ) ) {
			return array(
				'status'  => 'full',
				'message' => sprintf(
					/* translators: %s: connecting IP address. */
					__( 'This request was sent from a Cloudflare IP address (%s)', 'poke-monitor' ),
					$remote_address
				),
			);
		}

		if ( '' === $remote_address ) {
			return array(
				'status'  => 'none',
				'message' => __( 'This request was not sent from a Cloudflare IP address (address unknown)', 'poke-monitor' ),
			);
		}

		return array(
			'status'  => 'none',
			'message' => sprintf(
				/* translators: %s: connecting IP address. */
				__( 'This request was not sent from a Cloudflare IP address (%s)', 'poke-monitor' ),
				$remote_address
			),
		);
	}

	public static function get_switch_hint() {
		$bypass_detection_enabled = self::is_bypass_detection_enabled();
		$request_has_all_headers  = self::request_has_all_cf_headers();
		$present_header_count     = self::count_present_cf_headers();

		if ( $bypass_detection_enabled && 0 === $present_header_count ) {
			return __( "You reached the site without Cloudflare. That's expected if you use a VPN, a hosts-file entry or a staging domain. Otherwise Cloudflare proxying (orange cloud) may be switched off — and every visitor would be logged as a bypass.", 'poke-monitor' );
		}

		if ( ! $bypass_detection_enabled && $request_has_all_headers ) {
			return __( 'This site seems to be behind Cloudflare. Turn on bypass detection?', 'poke-monitor' );
		}

		return null;
	}

	public static function get_ip_check_switch_hint(): ?string {
		$ip_check_enabled      = self::is_ip_check_enabled();
		$address_is_cloudflare = self::request_remote_addr_is_cloudflare();

		if ( $ip_check_enabled && ! $address_is_cloudflare ) {
			return __( "Your connection did not come from a Cloudflare IP address. With the Cloudflare IP check on, every request like this will be logged as a bypass. Some hosts show their own proxy address instead of Cloudflare's.", 'poke-monitor' );
		}

		if ( ! $ip_check_enabled && $address_is_cloudflare ) {
			return __( 'Your connection came from a Cloudflare IP address. Turn on the Cloudflare IP check?', 'poke-monitor' );
		}

		if ( $ip_check_enabled && ! self::is_bypass_detection_enabled() ) {
			return __( 'The Cloudflare IP check has no effect while bypass detection is off.', 'poke-monitor' );
		}

		return null;
	}
}