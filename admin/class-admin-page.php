<?php

namespace Poke_Monitor\Admin;

use Poke_Monitor\Cf_Detector;
use Poke_Monitor\Cron;
use Poke_Monitor\Ip_Resolver;
use Poke_Monitor\Repository;
use Poke_Monitor\Request_Context;
use Poke_Monitor\Schema;
use Poke_Monitor\Allowlist;

defined( 'ABSPATH' ) || exit;

class Admin_Page {

	const PAGE_SLUG = 'poke-monitor';

	const NONCE_ACTION = 'poke_monitor_admin';

	const LAST_TAB_META_KEY = 'poke_monitor_last_tab';

	const PER_PAGE = 50;

	const SECTION_PARAM = 'section';

	const SECTION_LOG = 'log';

	const SECTION_ALLOWLIST = 'allowlist';

	private $repository;

	private $ip_resolver;

	private $allowlist;

	private $page_hook = '';

	public function __construct( Repository $repository ) {
		$this->repository  = $repository;
		$this->ip_resolver = new Ip_Resolver();
		$this->allowlist   = new Allowlist();
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu(): void {
		$page_hook = add_menu_page(
			__( 'Poke Monitor', 'poke-monitor' ),
			__( 'Poke Monitor', 'poke-monitor' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-shield-alt'
		);

		if ( ! is_string( $page_hook ) ) {
			return;
		}

		$this->page_hook = $page_hook;

		add_action( 'load-' . $page_hook, array( $this, 'on_load_page' ) );
	}

	public function on_load_page(): void {
		Cf_Detector::maybe_auto_set_on_first_admin_visit();
		Cf_Detector::maybe_auto_set_ip_check_on_first_admin_visit();
	}

	public function enqueue_assets( $hook_suffix ): void {
		if ( '' === $this->page_hook || $this->page_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'poke-monitor-admin',
			POKE_MONITOR_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			POKE_MONITOR_VERSION
		);

		wp_enqueue_script(
			'poke-monitor-admin',
			POKE_MONITOR_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			POKE_MONITOR_VERSION,
			true
		);

		wp_localize_script(
			'poke-monitor-admin',
			'pokeMonitorAdmin',
			array(
				'ajaxUrl'                   => admin_url( 'admin-ajax.php' ),
				'nonce'                     => wp_create_nonce( self::NONCE_ACTION ),
				'requestHasAllCfHeaders'    => Cf_Detector::request_has_all_cf_headers() ? '1' : '0',
				'requestIsFromCloudflareIp' => Cf_Detector::request_remote_addr_is_cloudflare() ? '1' : '0',
				'strings'                   => array(
					'confirmEnable'                  => __( 'Turn on Cloudflare bypass detection?', 'poke-monitor' ),
					'confirmEnableWithoutCloudflare' => __( 'This request did not come through Cloudflare. If the site is not really behind Cloudflare, every front-end request will be logged as a Cloudflare Bypass. Turn detection on anyway?', 'poke-monitor' ),
					'confirmDisable'                 => __( 'Requests that reach your server directly will no longer be logged as Cloudflare Bypass. Turn detection off?', 'poke-monitor' ),
					'confirmIpCheckEnable'           => __( 'Turn on the Cloudflare IP check? Requests from addresses that are not Cloudflare addresses will be logged as Cloudflare Bypass, even if they carry Cloudflare headers.', 'poke-monitor' ),
					'confirmIpCheckEnableWithoutCloudflareIp' => __( 'This request did not come from a Cloudflare IP address. With the IP check on, requests from non-Cloudflare addresses are logged as Cloudflare Bypass even if they carry Cloudflare headers, so this could flag every visitor. Turn the IP check on anyway?', 'poke-monitor' ),
					'confirmIpCheckDisable'          => __( 'Requests from non-Cloudflare addresses that carry Cloudflare headers will no longer be logged as Cloudflare Bypass. Turn the IP check off?', 'poke-monitor' ),
					/* translators: 1: error type name, 2: total number of requests, 3: number of requests that stay because they have other types, 4: number of requests that are deleted. */
					'confirmClearType'               => __( 'Remove %1$s from %2$s requests? %3$s of them also have other types and stay in those tabs; the other %4$s are deleted. This can\'t be undone.', 'poke-monitor' ),
					'clearAllPrompt'                 => __( 'This deletes the entire log, including entries in all other tabs. Type CLEAR to confirm.', 'poke-monitor' ),
					'errorGeneric'                   => __( 'Something went wrong. Please try again.', 'poke-monitor' ),
					'on'                             => __( 'On', 'poke-monitor' ),
					'off'                            => __( 'Off', 'poke-monitor' ),
					'confirmDeleteEntry'             => __( 'Remove this entry from the allowlist?', 'poke-monitor' ),
					'addEntryTitle'                  => __( 'Add allowlist entry', 'poke-monitor' ),
					'editEntryTitle'                 => __( 'Edit allowlist entry', 'poke-monitor' ),
					'noCurrentIp'                    => __( 'Your current IP address could not be determined.', 'poke-monitor' ),
					'ipPlaceholder'                  => __( '203.0.113.7', 'poke-monitor' ),
					'uaPlaceholder'                  => __( 'Mozilla/5.0 (compatible; MyMonitor/1.0)', 'poke-monitor' ),
				),
			)
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'poke-monitor' ) );
		}

		if ( self::SECTION_ALLOWLIST === $this->get_current_section() ) {
			View::render( 'allowlist', $this->get_allowlist_view_data() );

			return;
		}

		View::render( 'log', $this->get_log_view_data() );
	}

	public function get_current_section(): string {
		$requested_section = isset( $_GET[ self::SECTION_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::SECTION_PARAM ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.

		return self::SECTION_ALLOWLIST === $requested_section ? self::SECTION_ALLOWLIST : self::SECTION_LOG;
	}

	public function build_section_url( string $section ): string {
		if ( self::SECTION_LOG === $section ) {
			return $this->build_page_url( array() );
		}

		return $this->build_page_url( array( self::SECTION_PARAM => $section ) );
	}

	public function get_section_view_models( string $current_section ): array {
		$section_labels = array(
			self::SECTION_LOG       => __( 'Log', 'poke-monitor' ),
			self::SECTION_ALLOWLIST => __( 'Allowlist', 'poke-monitor' ),
		);

		$sections = array();

		foreach ( $section_labels as $section_slug => $section_label ) {
			$sections[] = array(
				'slug'      => $section_slug,
				'label'     => $section_label,
				'url'       => $this->build_section_url( $section_slug ),
				'is_active' => $current_section === $section_slug,
			);
		}

		return $sections;
	}

	public function get_tab_definitions(): array {
		return array(
			array(
				'slug'          => 'all',
				'label'         => __( 'All', 'poke-monitor' ),
				'error_type_id' => null,
				'badge_class'   => 'poke-monitor-badge--all',
				'empty_message' => __( 'No suspicious requests have been logged yet.', 'poke-monitor' ),
			),
			array(
				'slug'          => 'cloudflare-bypass',
				'label'         => __( 'Cloudflare Bypass', 'poke-monitor' ),
				'error_type_id' => Schema::ERROR_TYPE_CF_BYPASS,
				'badge_class'   => 'poke-monitor-badge--cloudflare-bypass',
				'empty_message' => __( 'Requests that reach your server without the headers Cloudflare always adds appear here.', 'poke-monitor' ),
			),
			array(
				'slug'          => 'probing',
				'label'         => __( 'Probing', 'poke-monitor' ),
				'error_type_id' => Schema::ERROR_TYPE_PROBING,
				'badge_class'   => 'poke-monitor-badge--probing',
				'empty_message' => __( 'Requests that end in a 404 and look for files scanners hunt for, such as .env or backup.sql, appear here.', 'poke-monitor' ),
			),
			array(
				'slug'          => 'direct-php',
				'label'         => __( 'Direct PHP Access', 'poke-monitor' ),
				'error_type_id' => Schema::ERROR_TYPE_DIRECT_PHP,
				'badge_class'   => 'poke-monitor-badge--direct-php',
				'empty_message' => __( 'Requests for PHP files inside wp-includes or the uploads folder appear here.', 'poke-monitor' ),
			),
			array(
				'slug'          => 'xmlrpc',
				'label'         => __( 'XML-RPC', 'poke-monitor' ),
				'error_type_id' => Schema::ERROR_TYPE_XMLRPC,
				'badge_class'   => 'poke-monitor-badge--xmlrpc',
				'empty_message' => __( 'Requests to xmlrpc.php appear here.', 'poke-monitor' ),
			),
			array(
				'slug'          => 'suspicious-ua',
				'label'         => __( 'Suspicious User Agent', 'poke-monitor' ),
				'error_type_id' => Schema::ERROR_TYPE_SUSPICIOUS_UA,
				'badge_class'   => 'poke-monitor-badge--suspicious-ua',
				'empty_message' => __( 'Requests with a missing, malformed, HTTP library or scanner User-Agent appear here.', 'poke-monitor' ),
			),
		);
	}

	public function get_error_type_label( $error_type_id ): string {
		foreach ( $this->get_tab_definitions() as $tab_definition ) {
			if ( null !== $tab_definition['error_type_id'] && (int) $error_type_id === $tab_definition['error_type_id'] ) {
				return $tab_definition['label'];
			}
		}

		return '';
	}

	public function get_current_tab(): string {
		$valid_slugs = wp_list_pluck( $this->get_tab_definitions(), 'slug' );
		$user_id     = get_current_user_id();
		$stored_slug = (string) get_user_meta( $user_id, self::LAST_TAB_META_KEY, true );

		$requested_slug = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter, validated against a fixed list of slugs.

		if ( in_array( $requested_slug, $valid_slugs, true ) ) {
			if ( $requested_slug !== $stored_slug ) {
				update_user_meta( $user_id, self::LAST_TAB_META_KEY, $requested_slug );
			}

			return $requested_slug;
		}

		if ( in_array( $stored_slug, $valid_slugs, true ) ) {
			return $stored_slug;
		}

		return 'all';
	}

	public function build_tab_url( $tab_slug, $page = 1 ): string {
		return $this->build_page_url(
			array(
				'type'  => $tab_slug,
				'paged' => max( 1, (int) $page ),
			)
		);
	}

	private function build_page_url( array $query_arguments ): string {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE_SLUG ), $query_arguments ),
			admin_url( 'admin.php' )
		);
	}

	public function get_log_view_data(): array {
		$current_slug             = $this->get_current_tab();
		$current_tab              = $this->find_tab_by_slug( $current_slug );
		$current_error_type_id    = $current_tab['error_type_id'];
		$bypass_detection_enabled = Cf_Detector::is_bypass_detection_enabled();
		$sort                     = $this->read_sort();

		$total_items  = $this->repository->count_requests( $current_error_type_id );
		$total_pages  = max( 1, (int) ceil( $total_items / self::PER_PAGE ) );
		$current_page = min( $this->read_requested_page(), $total_pages );

		$requests = $this->repository->get_requests(
			$current_error_type_id,
			$sort['orderby'],
			$sort['order'],
			$current_page,
			self::PER_PAGE
		);

		$error_types_by_request = $this->repository->get_error_types_for_requests( wp_list_pluck( $requests, 'id' ) );

		$tabs                    = $this->build_tab_view_models( $current_slug, $bypass_detection_enabled );
		$tabs_by_error_type_id   = $this->index_tabs_by_error_type_id( $tabs );
		$cloudflare_status       = Cf_Detector::get_status();
		$cloudflare_live_note    = Cf_Detector::get_live_note();
		$cloudflare_ip_live_note = Cf_Detector::get_ip_check_live_note();
		$allowlist_lookup        = $this->build_allowlist_lookup();

		$rows = array();

		foreach ( $requests as $request_row ) {
			$row_error_types = isset( $error_types_by_request[ (int) $request_row->id ] ) ? $error_types_by_request[ (int) $request_row->id ] : array();

			$rows[] = $this->build_row_view_model( $request_row, $row_error_types, $current_error_type_id, $tabs_by_error_type_id, $allowlist_lookup );
		}

		$pagination_base_url = $this->build_page_url(
			array(
				'type'    => $current_slug,
				'orderby' => $sort['orderby_param'],
				'order'   => $sort['order_param'],
			)
		);

		return array(
			'sections'           => $this->get_section_view_models( self::SECTION_LOG ),
			'tabs'               => $tabs,
			'current_tab'        => $current_tab,
			'is_all_tab'         => null === $current_error_type_id,
			'keep_days'          => Cron::KEEP_DAYS,
			'sort'               => $this->build_sort_view_model( $current_slug, $sort ),
			'page'               => $current_page,
			'total_items'        => $total_items,
			'total_pages'        => $total_pages,
			'pagination_base'    => add_query_arg( 'paged', '%#%', $pagination_base_url ),
			'badge_column_label' => null === $current_error_type_id ? __( 'Types', 'poke-monitor' ) : __( 'Also flagged', 'poke-monitor' ),
			'rows'               => $rows,
			'category_options'   => $this->build_category_options(),
			'ua_hint'            => $this->get_ua_hint(),
			'cloudflare'         => array(
				'enabled'        => $bypass_detection_enabled,
				'live_note'      => $cloudflare_live_note,
				'live_note_icon' => $this->get_live_note_icon( $cloudflare_live_note ),
				'hint'           => Cf_Detector::get_switch_hint(),
				'description'    => $this->describe_cf_set_by( $cloudflare_status ),
			),
			'cloudflare_ip'      => array(
				'enabled'        => Cf_Detector::is_ip_check_enabled(),
				'live_note'      => $cloudflare_ip_live_note,
				'live_note_icon' => $this->get_live_note_icon( $cloudflare_ip_live_note ),
				'hint'           => Cf_Detector::get_ip_check_switch_hint(),
				'description'    => $this->describe_cf_set_by( Cf_Detector::get_ip_check_status() ),
			),
		);
	}

	public function get_allowlist_view_data(): array {
		$entries = array();

		foreach ( $this->allowlist->get_entries() as $entry ) {
			$entries[] = $this->build_allowlist_entry_view_model( $entry );
		}

		return array(
			'sections'         => $this->get_section_view_models( self::SECTION_ALLOWLIST ),
			'entries'          => $entries,
			'category_options' => $this->build_category_options(),
			'current_ip'       => $this->allowlist->resolve_ip_for_matching( Request_Context::capture() ),
			'ua_hint'          => $this->get_ua_hint(),
		);
	}

	private function get_live_note_icon( array $live_note ): string {
		$live_note_icons = array(
			'full'    => '✓',
			'partial' => '⚠',
			'none'    => '○',
		);

		return isset( $live_note_icons[ $live_note['status'] ] ) ? $live_note_icons[ $live_note['status'] ] : '○';
	}

	private function get_ua_hint(): string {
		return __( 'Library and monitor User-Agents, such as uptime checkers, can be allowlisted for Suspicious User Agent only, so their requests are still logged if they probe for other things.', 'poke-monitor' );
	}

	public function describe_allowlist_categories( $categories ): string {
		if ( Allowlist::CATEGORY_ALL === $categories ) {
			return __( 'All', 'poke-monitor' );
		}

		$labels = array();

		foreach ( (array) $categories as $error_type_id ) {
			$label = $this->get_error_type_label( $error_type_id );

			if ( '' !== $label ) {
				$labels[] = $label;
			}
		}

		return implode( ', ', $labels );
	}

	public function describe_cf_set_by( array $status ): string {
		$set_by = isset( $status['set_by'] ) ? $status['set_by'] : null;

		if ( 'activation' === $set_by ) {
			return __( 'Set automatically at activation', 'poke-monitor' );
		}

		if ( 'first_admin_visit' === $set_by ) {
			return __( 'Set automatically on first visit', 'poke-monitor' );
		}

		if ( 'user' !== $set_by ) {
			return __( 'Not set yet', 'poke-monitor' );
		}

		$user         = ! empty( $status['user_id'] ) ? get_userdata( (int) $status['user_id'] ) : false;
		$display_name = $user ? $user->display_name : __( 'unknown user', 'poke-monitor' );
		$set_date     = ! empty( $status['set_at'] )
			? mysql2date( get_option( 'date_format' ), get_date_from_gmt( $status['set_at'] ) )
			: '';

		if ( ! empty( $status['enabled'] ) ) {
			return sprintf(
				/* translators: 1: user display name, 2: date. */
				__( 'Turned on by %1$s, %2$s', 'poke-monitor' ),
				$display_name,
				$set_date
			);
		}

		return sprintf(
			/* translators: 1: user display name, 2: date. */
			__( 'Turned off by %1$s, %2$s', 'poke-monitor' ),
			$display_name,
			$set_date
		);
	}

	private function build_allowlist_entry_view_model( array $entry ): array {
		$is_ip_entry  = Allowlist::TYPE_IP === $entry['type'];
		$creator      = ! empty( $entry['created_by'] ) ? get_userdata( (int) $entry['created_by'] ) : false;
		$created_date = ! empty( $entry['created_at'] ) ? get_date_from_gmt( $entry['created_at'], 'Y-m-d H:i:s' ) : '';

		return array(
			'type'       => (string) $entry['type'],
			'type_label' => $is_ip_entry ? __( 'IP', 'poke-monitor' ) : __( 'UA', 'poke-monitor' ),
			'value'      => (string) $entry['value'],
			'applies_to' => $this->describe_allowlist_categories( $entry['categories'] ),
			'note'       => (string) $entry['note'],
			'created_at' => $created_date,
			'created_by' => $creator ? $creator->display_name : '',
			'entry_json' => (string) wp_json_encode( $entry ),
		);
	}

	private function build_allowlist_lookup(): array {
		$allowlist_lookup = array();

		foreach ( $this->allowlist->get_entries() as $entry ) {
			$lookup_key                      = $this->build_allowlist_lookup_key( (string) $entry['type'], (string) $entry['value'] );
			$allowlist_lookup[ $lookup_key ] = $this->build_allowlist_entry_view_model( $entry );
		}

		return $allowlist_lookup;
	}

	private function build_allowlist_lookup_key( string $type, string $value ): string {
		return $type . "\n" . $value;
	}

	private function build_row_allowlist_view_model( $request_row, array $row_error_types, array $allowlist_lookup ): array {
		$error_type_ids = array_map( 'intval', array_keys( $row_error_types ) );

		sort( $error_type_ids );

		$prefill_categories = empty( $error_type_ids ) ? Allowlist::CATEGORY_ALL : $error_type_ids;
		$user_agent_value   = null === $request_row->user_agent ? '' : (string) $request_row->user_agent;

		return array(
			Allowlist::TYPE_IP => $this->build_allowlist_button_view_model(
				Allowlist::TYPE_IP,
				$this->get_allowlist_ip_for_row( $request_row ),
				$prefill_categories,
				$allowlist_lookup
			),
			Allowlist::TYPE_UA => $this->build_allowlist_button_view_model(
				Allowlist::TYPE_UA,
				$user_agent_value,
				$prefill_categories,
				$allowlist_lookup
			),
		);
	}

	private function build_allowlist_button_view_model( string $type, string $value, $prefill_categories, array $allowlist_lookup ): array {
		$is_disabled    = '' === $value;
		$lookup_key     = $this->build_allowlist_lookup_key( $type, $value );
		$existing_entry = ! $is_disabled && isset( $allowlist_lookup[ $lookup_key ] ) ? $allowlist_lookup[ $lookup_key ] : null;

		return array(
			'label'         => Allowlist::TYPE_UA === $type ? __( 'UA', 'poke-monitor' ) : __( 'IP', 'poke-monitor' ),
			'disabled'      => $is_disabled,
			'exists'        => null !== $existing_entry,
			'title'         => $this->get_allowlist_button_title( $type, null !== $existing_entry, $is_disabled ),
			'prefill_json'  => (string) wp_json_encode(
				array(
					'type'       => $type,
					'value'      => $value,
					'categories' => $prefill_categories,
				)
			),
			'existing_json' => null === $existing_entry ? '' : $existing_entry['entry_json'],
		);
	}

	private function get_allowlist_button_title( string $type, bool $exists, bool $is_disabled ): string {
		if ( $is_disabled ) {
			return Allowlist::TYPE_UA === $type
				? __( 'A missing User-Agent cannot be allowlisted.', 'poke-monitor' )
				: __( 'No IP address is available for this request.', 'poke-monitor' );
		}

		if ( $exists ) {
			return __( 'Already on the allowlist. Click to edit the entry.', 'poke-monitor' );
		}

		return Allowlist::TYPE_UA === $type
			? __( 'Add this User-Agent to the allowlist', 'poke-monitor' )
			: __( 'Add this IP address to the allowlist', 'poke-monitor' );
	}

	private function get_allowlist_ip_for_row( $request_row ): string {
		$has_all_cf_headers = ! empty( $request_row->cf_ray ) && ! empty( $request_row->cf_ipcountry ) && ! empty( $request_row->cf_visitor );

		return $has_all_cf_headers
			? $this->format_ip( $request_row->client_ip )
			: $this->format_ip( $request_row->remote_addr );
	}

	private function build_category_options(): array {
		$category_options = array();

		foreach ( array_values( Schema::get_error_type_ids() ) as $error_type_id ) {
			$category_options[] = array(
				'id'    => $error_type_id,
				'label' => $this->get_error_type_label( $error_type_id ),
			);
		}

		return $category_options;
	}

	private function find_tab_by_slug( $tab_slug ): array {
		$tab_definitions = $this->get_tab_definitions();

		foreach ( $tab_definitions as $tab_definition ) {
			if ( $tab_slug === $tab_definition['slug'] ) {
				return $tab_definition;
			}
		}

		return $tab_definitions[0];
	}

	private function read_requested_page(): int {
		return isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter.
	}

	private function read_sort(): array {
		$orderby_param = 'time';
		$order_param   = 'desc';

		if ( isset( $_GET['orderby'] ) && 'code' === sanitize_key( wp_unslash( $_GET['orderby'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sorting parameter, compared against a fixed value.
			$orderby_param = 'code';
		}

		if ( isset( $_GET['order'] ) && 'asc' === sanitize_key( wp_unslash( $_GET['order'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sorting parameter, compared against a fixed value.
			$order_param = 'asc';
		}

		return array(
			'orderby_param' => $orderby_param,
			'order_param'   => $order_param,
			'orderby'       => 'code' === $orderby_param ? 'return_code' : 'created_at',
			'order'         => 'asc' === $order_param ? 'ASC' : 'DESC',
		);
	}

	private function build_sort_view_model( $current_slug, array $sort ): array {
		$sort_view_model = array();

		foreach ( array( 'time', 'code' ) as $column_name ) {
			$is_active_column = $column_name === $sort['orderby_param'];
			$next_order       = $is_active_column && 'desc' === $sort['order_param'] ? 'asc' : 'desc';
			$indicator        = '';

			if ( $is_active_column ) {
				$indicator = 'desc' === $sort['order_param'] ? '↓' : '↑';
			}

			$sort_view_model[ $column_name . '_url' ]       = $this->build_page_url(
				array(
					'type'    => $current_slug,
					'orderby' => $column_name,
					'order'   => $next_order,
				)
			);
			$sort_view_model[ $column_name . '_indicator' ] = $indicator;
		}

		return $sort_view_model;
	}

	private function build_tab_view_models( $current_slug, $bypass_detection_enabled ): array {
		$tab_counts = $this->repository->get_tab_counts();
		$tabs       = array();

		foreach ( $this->get_tab_definitions() as $tab_definition ) {
			$count_key        = null === $tab_definition['error_type_id'] ? 'all' : $tab_definition['error_type_id'];
			$is_active        = $current_slug === $tab_definition['slug'];
			$is_detection_off = Schema::ERROR_TYPE_CF_BYPASS === $tab_definition['error_type_id'] && ! $bypass_detection_enabled;

			$tabs[] = array(
				'slug'             => $tab_definition['slug'],
				'label'            => $tab_definition['label'],
				'error_type_id'    => $tab_definition['error_type_id'],
				'badge_class'      => $tab_definition['badge_class'],
				'empty_message'    => $tab_definition['empty_message'],
				'count'            => isset( $tab_counts[ $count_key ] ) ? (int) $tab_counts[ $count_key ] : 0,
				'url'              => $this->build_tab_url( $tab_definition['slug'] ),
				'is_active'        => $is_active,
				'is_detection_off' => $is_detection_off,
				'css_classes'      => $this->build_tab_css_classes( $is_active, $is_detection_off ),
				'tooltip'          => null === $tab_definition['error_type_id']
					? __( 'Counts each request once, even if it was logged under several types, so it can be lower than the sum of the other tabs.', 'poke-monitor' )
					: '',
			);
		}

		return $tabs;
	}

	private function build_tab_css_classes( bool $is_active, bool $is_detection_off ): string {
		$css_classes = array( 'poke-monitor-tab' );

		if ( $is_active ) {
			$css_classes[] = 'is-active';
		}

		if ( $is_detection_off ) {
			$css_classes[] = 'is-muted';
		}

		return implode( ' ', $css_classes );
	}

	private function index_tabs_by_error_type_id( array $tabs ): array {
		$indexed_tabs = array();

		foreach ( $tabs as $tab ) {
			if ( null !== $tab['error_type_id'] ) {
				$indexed_tabs[ $tab['error_type_id'] ] = $tab;
			}
		}

		return $indexed_tabs;
	}

	private function build_row_view_model( $request_row, array $row_error_types, $current_error_type_id, array $tabs_by_error_type_id, array $allowlist_lookup ): array {
		$client_ip   = $this->format_ip( $request_row->client_ip );
		$remote_addr = $this->format_ip( $request_row->remote_addr );
		$status_code = (int) $request_row->return_code;

		return array(
			'time'             => get_date_from_gmt( $request_row->created_at, 'Y-m-d H:i:s' ),
			'method'           => (string) $request_row->http_method,
			'path'             => (string) $request_row->request_path,
			'status_code'      => $status_code,
			'status_class'     => $this->get_status_class( $status_code ),
			'client_ip'        => $client_ip,
			'remote_addr'      => $remote_addr,
			'addresses_differ' => '' !== $remote_addr && $client_ip !== $remote_addr,
			'second_line'      => $this->build_second_line( $request_row, $row_error_types, $current_error_type_id ),
			'badges'           => $this->build_row_badges( $row_error_types, $current_error_type_id, $tabs_by_error_type_id ),
			'allowlist'        => $this->build_row_allowlist_view_model( $request_row, $row_error_types, $allowlist_lookup ),
		);
	}

	private function build_row_badges( array $row_error_types, $current_error_type_id, array $tabs_by_error_type_id ): array {
		ksort( $row_error_types );

		$badges = array();

		foreach ( array_keys( $row_error_types ) as $error_type_id ) {
			if ( $error_type_id === $current_error_type_id || ! isset( $tabs_by_error_type_id[ $error_type_id ] ) ) {
				continue;
			}

			$badges[] = array(
				'label'       => $tabs_by_error_type_id[ $error_type_id ]['label'],
				'badge_class' => $tabs_by_error_type_id[ $error_type_id ]['badge_class'],
				'url'         => $tabs_by_error_type_id[ $error_type_id ]['url'],
			);
		}

		return $badges;
	}

	private function build_second_line( $request_row, array $row_error_types, $current_error_type_id ): string {
		if ( null !== $current_error_type_id && ! empty( $request_row->detail ) ) {
			return (string) $request_row->detail;
		}

		if ( null === $current_error_type_id ) {
			ksort( $row_error_types );

			foreach ( $row_error_types as $detail ) {
				if ( ! empty( $detail ) ) {
					return (string) $detail;
				}
			}
		}

		if ( null === $request_row->user_agent || '' === $request_row->user_agent ) {
			return __( '— missing —', 'poke-monitor' );
		}

		return (string) $request_row->user_agent;
	}

	private function get_status_class( $status_code ): string {
		if ( $status_code >= 200 && $status_code < 300 ) {
			return 'poke-monitor-code--2xx';
		}

		if ( $status_code >= 400 && $status_code < 500 ) {
			return 'poke-monitor-code--4xx';
		}

		if ( $status_code >= 500 ) {
			return 'poke-monitor-code--5xx';
		}

		return 'poke-monitor-code--neutral';
	}

	private function format_ip( $binary_ip ): string {
		if ( ! is_string( $binary_ip ) || ! in_array( strlen( $binary_ip ), array( 4, 16 ), true ) ) {
			return '';
		}

		$readable_ip = $this->ip_resolver->to_readable( $binary_ip );

		return null === $readable_ip ? '' : $readable_ip;
	}
}