<?php

namespace Poke_Monitor;

use Poke_Monitor\Admin\Admin_Page;
use Poke_Monitor\Admin\Log_Controller;
use Poke_Monitor\Admin\Allowlist_Controller;
use Poke_Monitor\Rules\Cf_Bypass_Rule;
use Poke_Monitor\Rules\Probe_Rule;
use Poke_Monitor\Rules\Direct_Php_Rule;
use Poke_Monitor\Rules\Xmlrpc_Rule;
use Poke_Monitor\Rules\Suspicious_Ua_Rule;

defined( 'ABSPATH' ) || exit;

class Plugin {

	private static $instance = null;

	private $request_context;

	private $repository;

	private $logger;

	private $cron;

	private function __construct() {}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot() {
		$this->request_context = Request_Context::capture();

		$cf_bypass_rule     = new Cf_Bypass_Rule();
		$probe_rule         = new Probe_Rule();
		$direct_php_rule    = new Direct_Php_Rule();
		$suspicious_ua_rule = new Suspicious_Ua_Rule();
		$allowlist          = new Allowlist();
		$xmlrpc_rule        = new Xmlrpc_Rule();

		$classifier = new Classifier(
			$cf_bypass_rule,
			$probe_rule,
			$direct_php_rule,
			$xmlrpc_rule,
			$suspicious_ua_rule,
			$allowlist
		);

		$this->repository = new Repository();
		$this->logger      = new Logger( $classifier, $this->repository );

		$this->cron = new Cron( $this->repository );
		$this->cron->register_hooks();

		if ( $this->request_context->is_xmlrpc_request() ) {
			$xmlrpc_rule->register_listeners();
		}

		$this->register_runtime_hooks();

		if ( is_admin() ) {
			$this->boot_admin();
		}
	}

	private function boot_admin(): void {
		$admin_page           = new Admin_Page( $this->repository );
		$log_controller       = new Log_Controller( $this->repository );
		$allowlist_controller = new Allowlist_Controller( $this->repository );

		$admin_page->register_hooks();
		$log_controller->register_hooks();
		$allowlist_controller->register_hooks();
	}

	public function register_runtime_hooks() {
		add_filter( 'status_header', array( $this, 'handle_status_header' ), 10, 2 );
		add_action( 'shutdown', array( $this, 'handle_shutdown' ) );
	}

	public function handle_status_header( $status_header, $code ) {
		$this->request_context->set_status_code( $code );

		return $status_header;
	}

	public function handle_shutdown() {
		if ( 0 === $this->request_context->get_status_code() ) {
			$fallback_status_code = http_response_code();

			if ( false !== $fallback_status_code ) {
				$this->request_context->set_status_code( $fallback_status_code );
			}
		}

		$this->logger->handle( $this->request_context );
	}
}