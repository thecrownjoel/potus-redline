<?php
/**
 * Admin menu registration and page routing.
 */

defined( 'ABSPATH' ) || exit;

class RedLine_Admin {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
	}

	public static function register_menu(): void {
		// Top-level menu.
		add_menu_page(
			'Red Line',
			'Red Line',
			'manage_options',
			'red-line',
			array( __CLASS__, 'page_dashboard' ),
			'dashicons-phone',
			3
		);

		add_submenu_page( 'red-line', 'Dashboard', 'Dashboard', 'manage_options', 'red-line', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'red-line', 'Alerts', 'Alerts', 'manage_options', 'red-line-alerts', array( __CLASS__, 'page_alerts' ) );
		add_submenu_page( 'red-line', 'Polls', 'Polls', 'manage_options', 'red-line-polls', array( __CLASS__, 'page_polls' ) );
		add_submenu_page( 'red-line', 'From the Desk', 'From the Desk', 'manage_options', 'red-line-desk', array( __CLASS__, 'page_desk' ) );
		add_submenu_page( 'red-line', 'Analytics', 'Analytics', 'manage_options', 'red-line-analytics', array( __CLASS__, 'page_analytics' ) );
		add_submenu_page( 'red-line', 'Settings', 'Settings', 'manage_options', 'red-line-settings', array( __CLASS__, 'page_settings' ) );
	}

	public static function enqueue_assets( string $hook ): void {
		// Only load on our pages.
		if ( strpos( $hook, 'red-line' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'redline-admin',
			REDLINE_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			REDLINE_VERSION
		);

		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);

		wp_enqueue_script(
			'redline-charts',
			REDLINE_PLUGIN_URL . 'admin/js/charts.js',
			array( 'chart-js' ),
			REDLINE_VERSION,
			true
		);

		wp_enqueue_script(
			'redline-admin',
			REDLINE_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			REDLINE_VERSION,
			true
		);

		// Pass data to JS.
		wp_localize_script( 'redline-admin', 'RedLineAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'redline_admin' ),
		) );
	}

	public static function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle form submissions.
		if ( isset( $_POST['redline_action'] ) && check_admin_referer( 'redline_admin_action' ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['redline_action'] ) );

			switch ( $action ) {
				case 'save_alert':
					self::handle_save_alert();
					break;
				case 'delete_alert':
					self::handle_delete_alert();
					break;
				case 'save_poll':
					self::handle_save_poll();
					break;
				case 'delete_poll':
					self::handle_delete_poll();
					break;
				case 'save_desk':
					self::handle_save_desk();
					break;
				case 'delete_desk':
					self::handle_delete_desk();
					break;
				case 'save_settings':
					self::handle_save_settings();
					break;
				case 'seed_mock':
					RedLine_Mock_Data::seed();
					wp_safe_redirect( add_query_arg( 'msg', 'mock_seeded', wp_get_referer() ) );
					exit;
				case 'purge_data':
					$days = (int) get_option( 'redline_data_retention', 365 );
					RedLine_Analytics::purge_old_data( $days );
					wp_safe_redirect( add_query_arg( 'msg', 'purged', wp_get_referer() ) );
					exit;
			}
		}
	}

	private static function handle_save_alert(): void {
		$data = array(
			'headline'     => sanitize_text_field( wp_unslash( $_POST['headline'] ?? '' ) ),
			'body'         => sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ),
			'category'     => sanitize_text_field( wp_unslash( $_POST['category'] ?? 'Statement' ) ),
			'link_url'     => esc_url_raw( wp_unslash( $_POST['link_url'] ?? '' ) ),
			'priority'     => sanitize_text_field( wp_unslash( $_POST['priority'] ?? 'normal' ) ),
			'status'       => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'draft' ) ),
			'scheduled_at' => sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ?? '' ) ) ?: null,
		);

		$id = isset( $_POST['alert_id'] ) ? absint( $_POST['alert_id'] ) : 0;

		if ( $id ) {
			RedLine_Alerts::update( $id, $data );
		} else {
			RedLine_Alerts::create( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=red-line-alerts&msg=saved' ) );
		exit;
	}

	private static function handle_delete_alert(): void {
		$id = absint( $_POST['alert_id'] ?? 0 );
		if ( $id ) {
			RedLine_Alerts::delete( $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=red-line-alerts&msg=deleted' ) );
		exit;
	}

	private static function handle_save_poll(): void {
		$options = array();
		if ( isset( $_POST['options'] ) && is_array( $_POST['options'] ) ) {
			foreach ( $_POST['options'] as $opt ) {
				$clean = sanitize_text_field( wp_unslash( $opt ) );
				if ( $clean ) {
					$options[] = $clean;
				}
			}
		}

		$data = array(
			'question'       => sanitize_text_field( wp_unslash( $_POST['question'] ?? '' ) ),
			'options'        => $options,
			'show_results'   => isset( $_POST['show_results'] ) ? 1 : 0,
			'status'         => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'draft' ) ),
			'min_poh_to_vote' => absint( $_POST['min_poh_to_vote'] ?? 30 ),
			'opens_at'       => sanitize_text_field( wp_unslash( $_POST['opens_at'] ?? '' ) ) ?: null,
			'closes_at'      => sanitize_text_field( wp_unslash( $_POST['closes_at'] ?? '' ) ) ?: null,
		);

		$id = isset( $_POST['poll_id'] ) ? absint( $_POST['poll_id'] ) : 0;

		if ( $id ) {
			RedLine_Polls::update( $id, $data );
		} else {
			RedLine_Polls::create( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=red-line-polls&msg=saved' ) );
		exit;
	}

	private static function handle_delete_poll(): void {
		$id = absint( $_POST['poll_id'] ?? 0 );
		if ( $id ) {
			RedLine_Polls::delete( $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=red-line-polls&msg=deleted' ) );
		exit;
	}

	private static function handle_save_desk(): void {
		$data = array(
			'title'        => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'body'         => wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) ),
			'image_url'    => esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ) ),
			'status'       => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'draft' ) ),
			'scheduled_at' => sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ?? '' ) ) ?: null,
		);

		$id = isset( $_POST['desk_id'] ) ? absint( $_POST['desk_id'] ) : 0;

		if ( $id ) {
			RedLine_Desk::update( $id, $data );
		} else {
			RedLine_Desk::create( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=red-line-desk&msg=saved' ) );
		exit;
	}

	private static function handle_delete_desk(): void {
		$id = absint( $_POST['desk_id'] ?? 0 );
		if ( $id ) {
			RedLine_Desk::delete( $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=red-line-desk&msg=deleted' ) );
		exit;
	}

	private static function handle_save_settings(): void {
		$settings = array(
			'redline_api_key'              => sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ),
			'redline_poll_interval'        => absint( $_POST['poll_interval'] ?? 300 ),
			'redline_default_show_results' => isset( $_POST['default_show_results'] ) ? 1 : 0,
			'redline_max_alerts'           => absint( $_POST['max_alerts'] ?? 20 ),
			'redline_min_poh_vote'         => absint( $_POST['min_poh_vote'] ?? 30 ),
			'redline_min_poh_verified'     => absint( $_POST['min_poh_verified'] ?? 80 ),
			'redline_auto_flag'            => absint( $_POST['auto_flag'] ?? 20 ),
			'redline_data_retention'       => absint( $_POST['data_retention'] ?? 365 ),
			'redline_rate_limit'           => absint( $_POST['rate_limit'] ?? 60 ),
			'redline_cors_origins'         => sanitize_textarea_field( wp_unslash( $_POST['cors_origins'] ?? '' ) ),
		);

		foreach ( $settings as $key => $value ) {
			update_option( $key, $value );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=red-line-settings&msg=saved' ) );
		exit;
	}

	// Page renderers.
	public static function page_dashboard(): void {
		include REDLINE_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public static function page_alerts(): void {
		include REDLINE_PLUGIN_DIR . 'admin/views/alerts.php';
	}

	public static function page_polls(): void {
		include REDLINE_PLUGIN_DIR . 'admin/views/polls.php';
	}

	public static function page_desk(): void {
		include REDLINE_PLUGIN_DIR . 'admin/views/desk.php';
	}

	public static function page_analytics(): void {
		include REDLINE_PLUGIN_DIR . 'admin/views/analytics.php';
	}

	public static function page_settings(): void {
		include REDLINE_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
