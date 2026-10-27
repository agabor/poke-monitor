<?php

namespace Poke_Monitor\Admin;

use Poke_Monitor\Cf_Detector;
use Poke_Monitor\Repository;
use Poke_Monitor\Schema;

defined( 'ABSPATH' ) || exit;

class Log_Controller {

	private $repository;

	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	public function register_hooks(): void {
		add_action( 'wp_ajax_poke_monitor_toggle_cf', array( $this, 'ajax_toggle_cf' ) );
		add_action( 'wp_ajax_poke_monitor_toggle_cf_ip', array( $this, 'ajax_toggle_cf_ip' ) );
		add_action( 'wp_ajax_poke_monitor_clear_impact', array( $this, 'ajax_clear_impact' ) );
		add_action( 'wp_ajax_poke_monitor_clear_type', array( $this, 'ajax_clear_type' ) );
		add_action( 'wp_ajax_poke_monitor_clear_all', array( $this, 'ajax_clear_all' ) );
	}

	public function verify_request(): void {
		check_ajax_referer( 'poke_monitor_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to do this.', 'poke-monitor' ) ),
				403
			);
		}
	}

	public function ajax_toggle_cf(): void {
		$this->verify_request();

		$enabled = $this->read_posted_enabled_flag();

		Cf_Detector::set_status( $enabled, 'user', get_current_user_id() );

		$admin_page = new Admin_Page( $this->repository );

		wp_send_json_success(
			array(
				'enabled'     => $enabled,
				'set_by_text' => $admin_page->describe_cf_set_by( Cf_Detector::get_status() ),
			)
		);
	}

	public function ajax_toggle_cf_ip(): void {
		$this->verify_request();

		$enabled = $this->read_posted_enabled_flag();

		Cf_Detector::set_ip_check_status( $enabled, 'user', get_current_user_id() );

		$admin_page = new Admin_Page( $this->repository );

		wp_send_json_success(
			array(
				'enabled'     => $enabled,
				'set_by_text' => $admin_page->describe_cf_set_by( Cf_Detector::get_ip_check_status() ),
			)
		);
	}

	public function ajax_clear_impact(): void {
		$this->verify_request();

		$error_type_id = $this->get_valid_error_type_id();

		wp_send_json_success( $this->repository->get_clear_type_impact( $error_type_id ) );
	}

	public function ajax_clear_type(): void {
		$this->verify_request();

		$error_type_id = $this->get_valid_error_type_id();

		wp_send_json_success(
			array( 'deleted' => $this->repository->delete_error_type( $error_type_id ) )
		);
	}

	public function ajax_clear_all(): void {
		$this->verify_request();

		$confirmation = isset( $_POST['confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmation'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

		if ( 'CLEAR' !== $confirmation ) {
			wp_send_json_error(
				array( 'message' => __( 'Confirmation text did not match.', 'poke-monitor' ) ),
				400
			);
		}

		$this->repository->delete_all();

		wp_send_json_success();
	}

	public function get_valid_error_type_id(): int {
		$error_type_id = isset( $_POST['error_type_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['error_type_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() by every caller.

		if ( ! in_array( $error_type_id, array_values( Schema::get_error_type_ids() ), true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid error type.', 'poke-monitor' ) ),
				400
			);
		}

		return $error_type_id;
	}

	private function read_posted_enabled_flag(): bool {
		$posted_value = isset( $_POST['enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['enabled'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

		return in_array( strtolower( $posted_value ), array( '1', 'true', 'on' ), true );
	}
}