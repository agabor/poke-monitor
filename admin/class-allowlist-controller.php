<?php

namespace Poke_Monitor\Admin;

use Poke_Monitor\Ip_Resolver;
use Poke_Monitor\Repository;
use Poke_Monitor\Request_Context;
use Poke_Monitor\Allowlist;

defined( 'ABSPATH' ) || exit;

class Allowlist_Controller {

	private $repository;

	private $allowlist;

	private $ip_resolver;

	public function __construct( Repository $repository ) {
		$this->repository  = $repository;
		$this->allowlist   = new Allowlist();
		$this->ip_resolver = new Ip_Resolver();
	}

	public function register_hooks(): void {
		add_action( 'wp_ajax_poke_monitor_allowlist_add', array( $this, 'ajax_allowlist_add' ) );
		add_action( 'wp_ajax_poke_monitor_allowlist_update', array( $this, 'ajax_allowlist_update' ) );
		add_action( 'wp_ajax_poke_monitor_allowlist_delete', array( $this, 'ajax_allowlist_delete' ) );
	}

	public function verify_request(): void {
		check_ajax_referer( Admin_Page::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to do this.', 'poke-monitor' ) ),
				403
			);
		}
	}

	public function ajax_allowlist_add(): void {
		$this->verify_request();

		$posted_input = $this->read_input();
		$entry        = $this->allowlist->add_entry( $posted_input, get_current_user_id() );

		$this->send_entry_response( $entry, $posted_input['delete_existing'] );
	}

	public function ajax_allowlist_update(): void {
		$this->verify_request();

		$posted_input = $this->read_input();
		$entry        = $this->allowlist->update_entry( $this->read_entry_id(), $posted_input );

		$this->send_entry_response( $entry, $posted_input['delete_existing'] );
	}

	public function ajax_allowlist_delete(): void {
		$this->verify_request();

		if ( ! $this->allowlist->delete_entry( $this->read_entry_id() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Allowlist entry not found.', 'poke-monitor' ) ),
				404
			);
		}

		wp_send_json_success();
	}

	private function send_entry_response( $entry, bool $delete_existing ): void {
		if ( is_wp_error( $entry ) ) {
			wp_send_json_error(
				array( 'message' => $entry->get_error_message() ),
				400
			);
		}

		$deleted_requests = $delete_existing ? $this->purge_existing_rows( $entry ) : 0;

		wp_send_json_success(
			array(
				'entry'   => $entry,
				'deleted' => $deleted_requests,
			)
		);
	}

	private function read_entry_id(): string {
		return isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() by every caller.
	}

	private function read_input(): array {
		return array(
			'type'            => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() by every caller.
			'value'           => $this->read_value(),
			'categories'      => isset( $_POST['categories'] ) ? sanitize_text_field( wp_unslash( $_POST['categories'] ) ) : Allowlist::CATEGORY_ALL, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() by every caller.
			'note'            => isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() by every caller.
			'delete_existing' => isset( $_POST['delete_existing'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['delete_existing'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() by every caller.
		);
	}

	private function read_value(): string {
		if ( ! isset( $_POST['value'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request() by every caller.
			return '';
		}

		$sanitized_value = Request_Context::sanitize_user_agent( wp_unslash( $_POST['value'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in verify_request() by every caller; sanitized by Request_Context::sanitize_user_agent().

		return null === $sanitized_value ? '' : $sanitized_value;
	}

	private function purge_existing_rows( array $entry ): int {
		$error_type_ids = Allowlist::CATEGORY_ALL === $entry['categories']
			? array()
			: array_map( 'intval', (array) $entry['categories'] );

		if ( Allowlist::TYPE_IP === $entry['type'] ) {
			$binary_ip = $this->ip_resolver->to_binary( $entry['value'] );

			return null === $binary_ip ? 0 : $this->repository->delete_requests_by_ip( $binary_ip, $error_type_ids );
		}

		return $this->repository->delete_requests_by_user_agent( $entry['value'], $error_type_ids );
	}
}