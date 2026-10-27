<?php

namespace Poke_Monitor;

defined( 'ABSPATH' ) || exit;

class Cf_Ip_Ranges {

	const OPTION_NAME = 'poke_monitor_cf_ip_ranges';

	const IPV4_URL = 'https://www.cloudflare.com/ips-v4';

	const IPV6_URL = 'https://www.cloudflare.com/ips-v6';

	private $ip_resolver;

	private static $loaded_ranges = null;

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
		if ( null === self::$loaded_ranges ) {
			self::$loaded_ranges = $this->load_stored_ranges();
		}

		return self::$loaded_ranges;
	}

	public function get_last_updated(): ?string {
		$stored_data = $this->read_stored_data();

		return isset( $stored_data['updated_at'] ) && is_string( $stored_data['updated_at'] ) ? $stored_data['updated_at'] : null;
	}

	public function refresh(): bool {
		$ipv4_ranges = $this->fetch_ranges( self::IPV4_URL );
		$ipv6_ranges = $this->fetch_ranges( self::IPV6_URL );

		if ( empty( $ipv4_ranges ) || empty( $ipv6_ranges ) ) {
			return false;
		}

		update_option(
			self::OPTION_NAME,
			array(
				'ranges'     => array_merge( $ipv4_ranges, $ipv6_ranges ),
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			true
		);

		$this->flush_cache();

		return true;
	}

	public function parse( string $body ): array {
		$cidrs = array();

		foreach ( preg_split( '/\R/', $body ) as $line ) {
			$cidr = trim( $line );

			if ( $this->is_valid_cidr( $cidr ) ) {
				$cidrs[] = $cidr;
			}
		}

		return $cidrs;
	}

	public function contains( string $ip ): bool {
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
		self::$loaded_ranges = null;
	}

	private function fetch_ranges( string $url ): array {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		return $this->parse( (string) wp_remote_retrieve_body( $response ) );
	}

	private function is_valid_cidr( string $cidr ): bool {
		if ( false === strpos( $cidr, '/' ) ) {
			return false;
		}

		list( $address, $prefix_length ) = explode( '/', $cidr, 2 );

		if ( ! $this->ip_resolver->is_valid_ip( $address ) || ! ctype_digit( $prefix_length ) ) {
			return false;
		}

		$max_prefix_length = $this->is_ipv4_address( $address ) ? 32 : 128;

		return (int) $prefix_length <= $max_prefix_length;
	}

	private function is_ipv4_address( string $address ): bool {
		return false !== filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
	}

	private function cidr_contains( string $binary_ip, string $cidr ): bool {
		list( $network_address, $prefix_length ) = explode( '/', $cidr, 2 );

		$binary_network = $this->ip_resolver->to_binary( $network_address );

		if ( null === $binary_network || strlen( $binary_network ) !== strlen( $binary_ip ) ) {
			return false;
		}

		$prefix_length  = (int) $prefix_length;
		$full_bytes     = intdiv( $prefix_length, 8 );
		$remaining_bits = $prefix_length % 8;

		if ( 0 !== strncmp( $binary_ip, $binary_network, $full_bytes ) ) {
			return false;
		}

		if ( 0 === $remaining_bits ) {
			return true;
		}

		$mask = ( 0xFF << ( 8 - $remaining_bits ) ) & 0xFF;

		return ( ord( $binary_ip[ $full_bytes ] ) & $mask ) === ( ord( $binary_network[ $full_bytes ] ) & $mask );
	}

	private function load_stored_ranges(): array {
		$stored_data = $this->read_stored_data();

		if ( isset( $stored_data['ranges'] ) && is_array( $stored_data['ranges'] ) && ! empty( $stored_data['ranges'] ) ) {
			return $stored_data['ranges'];
		}

		return $this->get_default_ranges();
	}

	private function read_stored_data(): array {
		$stored_data = get_option( self::OPTION_NAME, array() );

		return is_array( $stored_data ) ? $stored_data : array();
	}
}