<?php

namespace Poke_Monitor;

defined( 'ABSPATH' ) || exit;

class Probe_Patterns {

	const OPTION_NAME = 'poke_monitor_probe_patterns';

	const SOURCE_URL = 'https://raw.githubusercontent.com/agabor/poke-monitor/main/patterns/probing.txt';

	const MAX_PATTERNS = 500;

	const MAX_PATTERN_LENGTH = 200;

	public function get_stored_patterns(): array {
		$stored_data = $this->read_stored_data();

		if ( ! isset( $stored_data['patterns'] ) || ! is_array( $stored_data['patterns'] ) ) {
			return array();
		}

		return array_values( array_filter( $stored_data['patterns'], 'is_string' ) );
	}

	public function get_last_updated(): ?string {
		$stored_data = $this->read_stored_data();

		return isset( $stored_data['updated_at'] ) && is_string( $stored_data['updated_at'] ) ? $stored_data['updated_at'] : null;
	}

	public function update(): bool {
		$response = wp_safe_remote_get(
			self::SOURCE_URL,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = (string) wp_remote_retrieve_body( $response );

		if ( '' === trim( $body ) ) {
			return false;
		}

		$patterns = $this->parse( $body );

		if ( empty( $patterns ) ) {
			return false;
		}

		update_option(
			self::OPTION_NAME,
			array(
				'patterns'   => $patterns,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			false
		);

		return true;
	}

	public function parse( string $body ): array {
		$patterns = array();

		foreach ( preg_split( '/\R/', $body ) as $line ) {
			$pattern = trim( $line );

			if ( '' === $pattern || '#' === $pattern[0] ) {
				continue;
			}

			if ( in_array( $pattern, $patterns, true ) || ! $this->is_valid_pattern( $pattern ) ) {
				continue;
			}

			$patterns[] = $pattern;

			if ( count( $patterns ) >= self::MAX_PATTERNS ) {
				break;
			}
		}

		return $patterns;
	}

	private function is_valid_pattern( string $pattern ): bool {
		if ( strlen( $pattern ) > self::MAX_PATTERN_LENGTH || false !== strpos( $pattern, '#' ) ) {
			return false;
		}

		return false !== @preg_match( '#' . $pattern . '#i', '' );
	}

	private function read_stored_data(): array {
		$stored_data = get_option( self::OPTION_NAME, array() );

		return is_array( $stored_data ) ? $stored_data : array();
	}
}