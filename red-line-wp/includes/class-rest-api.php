<?php
/**
 * REST API endpoint definitions.
 * Namespace: /wp-json/redline/v1/
 */

defined( 'ABSPATH' ) || exit;

class RedLine_REST_API {

	private static string $namespace = 'redline/v1';

	public static function register_routes(): void {
		$ns = self::$namespace;

		// Alerts.
		register_rest_route( $ns, '/alerts', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_alerts' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );

		register_rest_route( $ns, '/alerts/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_alert' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );

		// Polls.
		register_rest_route( $ns, '/polls', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_polls' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );

		register_rest_route( $ns, '/polls/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_poll' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );

		register_rest_route( $ns, '/polls/(?P<id>\d+)/vote', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'submit_vote' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );

		// Desk.
		register_rest_route( $ns, '/desk', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_desk' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );

		register_rest_route( $ns, '/desk/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_desk_message' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );

		// Config.
		register_rest_route( $ns, '/config', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_config' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );

		// Analytics.
		register_rest_route( $ns, '/analytics', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'ingest_analytics' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );

		// Device registration.
		register_rest_route( $ns, '/register', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'register_device' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );

		// Heartbeat.
		register_rest_route( $ns, '/heartbeat', array(
			'methods'             => 'PUT',
			'callback'            => array( __CLASS__, 'heartbeat' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );
	}

	/**
	 * Verify API key from X-RedLine-Key header.
	 */
	public static function verify_api_key( WP_REST_Request $request ): bool {
		$key = $request->get_header( 'X-RedLine-Key' );
		$stored_key = get_option( 'redline_api_key', '' );

		// Allow requests without key during initial setup / demo.
		if ( empty( $stored_key ) ) {
			return true;
		}

		return hash_equals( $stored_key, $key ?? '' );
	}

	// -- Alerts --

	public static function get_alerts(): WP_REST_Response {
		$max = (int) get_option( 'redline_max_alerts', 20 );
		$alerts = RedLine_Alerts::get_published( $max );
		return new WP_REST_Response( $alerts, 200 );
	}

	public static function get_alert( WP_REST_Request $request ): WP_REST_Response {
		$alert = RedLine_Alerts::get( (int) $request['id'] );
		if ( ! $alert ) {
			return new WP_REST_Response( array( 'error' => 'Not found' ), 404 );
		}
		return new WP_REST_Response( $alert, 200 );
	}

	// -- Polls --

	public static function get_polls(): WP_REST_Response {
		$polls = RedLine_Polls::get_active();

		// Respect show_results — strip results from response if hidden.
		$polls = array_map( function ( $poll ) {
			if ( ! $poll['show_results'] ) {
				unset( $poll['results'] );
			}
			return $poll;
		}, $polls );

		return new WP_REST_Response( $polls, 200 );
	}

	public static function get_poll( WP_REST_Request $request ): WP_REST_Response {
		$poll = RedLine_Polls::get( (int) $request['id'] );
		if ( ! $poll ) {
			return new WP_REST_Response( array( 'error' => 'Not found' ), 404 );
		}

		if ( ! $poll['show_results'] ) {
			unset( $poll['results'] );
		}

		return new WP_REST_Response( $poll, 200 );
	}

	public static function submit_vote( WP_REST_Request $request ): WP_REST_Response {
		$poll_id = (int) $request['id'];
		$body    = $request->get_json_params();

		$body['device_hash'] = $request->get_header( 'X-Device-Hash' ) ?: ( $body['device_hash'] ?? '' );
		$body['poh_score']   = (int) ( $request->get_header( 'X-PoH-Score' ) ?: ( $body['poh_score'] ?? 0 ) );

		$result = RedLine_Polls::vote( $poll_id, $body );

		if ( isset( $result['error'] ) ) {
			return new WP_REST_Response( $result, 400 );
		}

		return new WP_REST_Response( $result, 201 );
	}

	// -- Desk --

	public static function get_desk(): WP_REST_Response {
		return new WP_REST_Response( RedLine_Desk::get_published(), 200 );
	}

	public static function get_desk_message( WP_REST_Request $request ): WP_REST_Response {
		$msg = RedLine_Desk::get( (int) $request['id'] );
		if ( ! $msg ) {
			return new WP_REST_Response( array( 'error' => 'Not found' ), 404 );
		}
		RedLine_Desk::increment_views( (int) $request['id'] );
		return new WP_REST_Response( $msg, 200 );
	}

	// -- Config --

	public static function get_config(): WP_REST_Response {
		return new WP_REST_Response( array(
			'poll_interval'    => (int) get_option( 'redline_poll_interval', 300 ),
			'min_poh_to_vote'  => (int) get_option( 'redline_min_poh_vote', 30 ),
			'min_poh_verified' => (int) get_option( 'redline_min_poh_verified', 80 ),
			'max_alerts_display' => (int) get_option( 'redline_max_alerts', 20 ),
			'auto_flag_threshold' => (int) get_option( 'redline_auto_flag', 20 ),
		), 200 );
	}

	// -- Analytics --

	public static function ingest_analytics( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		$result = RedLine_Analytics::ingest( $body );
		return new WP_REST_Response( $result, 200 );
	}

	// -- Devices --

	public static function register_device( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		$result = RedLine_Devices::register( $body );
		return new WP_REST_Response( $result, isset( $result['error'] ) ? 400 : 201 );
	}

	public static function heartbeat( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		$result = RedLine_Devices::heartbeat( $body );
		return new WP_REST_Response( $result, 200 );
	}
}
