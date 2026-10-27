<?php

namespace Poke_Monitor\Rules;

use Poke_Monitor\Request_Context;
use Poke_Monitor\Cf_Detector;

defined( 'ABSPATH' ) || exit;

class Cf_Bypass_Rule {

	public function matches( Request_Context $context ) {
		if ( ! Cf_Detector::is_bypass_detection_enabled() ) {
			return false;
		}

		if ( ! $context->has_all_cf_headers() ) {
			return true;
		}

		return Cf_Detector::is_ip_check_enabled() && ! Cf_Detector::is_cloudflare_ip( $context->get_remote_addr() );
	}

	public function get_detail( Request_Context $context ) {
		if ( ! $context->has_all_cf_headers() ) {
			return null;
		}

		return 'headers from non-Cloudflare IP';
	}
}