<?php

namespace Poke_Monitor;

defined( 'ABSPATH' ) || exit;

class Cf_Ip_Ranges {

	const OPTION_NAME = 'poke_monitor_cf_ip_ranges';

	const IPV4_URL = 'https://www.cloudflare.com/ips-v4';

	const IPV6_URL = 'https://www.cloudflare.com/ips-v6';

	private $ip_resolver;

	private static $cached_ranges = null;

	public function __construct() {
		$this->ip_resolver = new Ip_Resolver();
	}

	public function get_default_ranges(): array {
		return array(
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/13',
			'104.24.0.0/14',
			'172.64.0.0/13',
			'131.0.72.0/22',
			'2400:cb00::/32',
			'2606:4700::/32',
			'2803:f800::/32',
			'2405:b500::/32',
			'2405:8100::/32',
			'2a06:98c0::/29',
			'2c0f:f248::/32',
		);
	}

	public function get_ranges(): array {
		if ( null !== self::$cached_ranges ) {
			return self::$cached_ranges;
		}

		$stored_ranges = $this->read_stored_ranges();

		self::$cached_ranges = ! empty( $stored_ranges ) ? $stored_ranges : $this->get_default_ranges();

		return self::$cached_ranges;
	}

	public function get_last_updated(): ?string {
		$stored_option = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored_option ) || empty( $stored_option['updated_at'] ) || ! is_string( $stored_option['updated_at'] ) ) {
			return null;
		}

		return $stored_option['updated_at'];
	}

	public function refresh(): bool {
		$ipv4_ranges = $this->fetch_ranges( self::IPV4_URL );
		$ipv6_ranges = $this->fetch_ranges( self::IPV6_URL );

		if ( empty( $ipv4_ranges ) || empty( $ipv6_ranges ) ) {
			return false;
		}

		$merged_ranges = array_merge( $ipv4_ranges, $ipv6_ranges );

		update_option(
			self::OPTION_NAME,
			array(
				'ranges'     => $merged_ranges,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			true
		);

		$this->flush_cache();

		return true;
	}

	public function parse( string $body ): array {
		$lines = preg_split( '/\R/', $body );

		if ( false === $lines ) {
			return array();
		}

		$trimmed_lines = array_map( 'trim', $lines );

		return array_values(
			array_filter(
				$trimmed_lines,
				array( $this, 'is_valid_cidr' )
			)
		);
	}

	public function contains( string $ip ): bool {
		if ( '' === $ip ) {
			return false;
		}

		$binary_ip = $this->ip_resolver->to_binary( $ip );

		if ( null === $binary_ip ) {
			return false;
		}

		foreach ( $this->get_ranges() as $cidr ) {
			if ( $this->cidr_contains( $binary_ip, $cidr ) ) {
				return true;
			}
		}

		return false;
	}

	public function flush_cache(): void {
		self::$cached_ranges = null;
	}

	private function read_stored_ranges(): array {
		$stored_option = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored_option ) || empty( $stored_option['ranges'] ) || ! is_array( $stored_option['ranges'] ) ) {
			return array();
		}

		$string_ranges = array_filter( $stored_option['ranges'], 'is_string' );

		return array_values( array_filter( $string_ranges, array( $this, 'is_valid_cidr' ) ) );
	}

	private function fetch_ranges( string $url ): array {
		$response = wp_safe_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		return $this->parse( (string) wp_remote_retrieve_body( $response ) );
	}

	private function is_valid_cidr( string $cidr ): bool {
		$parts = explode( '/', $cidr );

		if ( 2 !== count( $parts ) ) {
			return false;
		}

		list( $address, $prefix ) = $parts;

		if ( ! $this->ip_resolver->is_valid_ip( $address ) || ! ctype_digit( $prefix ) ) {
			return false;
		}

		$is_ipv4        = false !== filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		$maximum_prefix = $is_ipv4 ? 32 : 128;

		return (int) $prefix <= $maximum_prefix;
	}

	private function cidr_contains( string $binary_ip, string $cidr ): bool {
		$parts = explode( '/', $cidr );

		if ( 2 !== count( $parts ) ) {
			return false;
		}

		$binary_network = $this->ip_resolver->to_binary( $parts[0] );

		if ( null === $binary_network || strlen( $binary_network ) !== strlen( $binary_ip ) ) {
			return false;
		}

		$prefix_length   = (int) $parts[1];
		$whole_bytes     = intdiv( $prefix_length, 8 );
		$remaining_bits  = $prefix_length % 8;

		if ( substr( $binary_ip, 0, $whole_bytes ) !== substr( $binary_network, 0, $whole_bytes ) ) {
			return false;
		}

		if ( 0 === $remaining_bits ) {
			return true;
		}

		$bit_mask      = ( 0xFF << ( 8 - $remaining_bits ) ) & 0xFF;
		$ip_byte       = ord( $binary_ip[ $whole_bytes ] );
		$network_byte  = ord( $binary_network[ $whole_bytes ] );

		return ( $ip_byte & $bit_mask ) === ( $network_byte & $bit_mask );
	}
}