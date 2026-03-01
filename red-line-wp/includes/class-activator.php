<?php
/**
 * Plugin activation — creates custom database tables.
 */

defined( 'ABSPATH' ) || exit;

class RedLine_Activator {

	public static function activate(): void {
		self::create_tables();
		self::set_defaults();

		// Seed mock data if empty.
		if ( ! get_option( 'redline_mock_seeded' ) ) {
			RedLine_Mock_Data::seed();
			update_option( 'redline_mock_seeded', true );
		}
	}

	private static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$sql = array();

		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}redline_devices (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			device_hash VARCHAR(64) NOT NULL,
			poh_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
			poh_breakdown JSON DEFAULT NULL,
			ip_type VARCHAR(20) DEFAULT 'unknown',
			geo_country VARCHAR(5) DEFAULT '',
			geo_region VARCHAR(10) DEFAULT '',
			geo_city VARCHAR(100) DEFAULT '',
			first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			is_flagged TINYINT(1) NOT NULL DEFAULT 0,
			flag_reason VARCHAR(255) DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY device_hash (device_hash),
			KEY poh_score (poh_score),
			KEY is_flagged (is_flagged)
		) {$charset};";

		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}redline_alerts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			headline VARCHAR(120) NOT NULL,
			body TEXT NOT NULL,
			category VARCHAR(30) NOT NULL DEFAULT 'Statement',
			link_url VARCHAR(500) DEFAULT '',
			priority VARCHAR(10) NOT NULL DEFAULT 'normal',
			status VARCHAR(15) NOT NULL DEFAULT 'draft',
			scheduled_at DATETIME DEFAULT NULL,
			impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
			clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status),
			KEY scheduled_at (scheduled_at)
		) {$charset};";

		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}redline_polls (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			question VARCHAR(200) NOT NULL,
			options JSON NOT NULL,
			show_results TINYINT(1) NOT NULL DEFAULT 1,
			status VARCHAR(15) NOT NULL DEFAULT 'draft',
			min_poh_to_vote TINYINT UNSIGNED NOT NULL DEFAULT 30,
			opens_at DATETIME DEFAULT NULL,
			closes_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status)
		) {$charset};";

		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}redline_votes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			poll_id BIGINT UNSIGNED NOT NULL,
			device_hash VARCHAR(64) NOT NULL,
			choice_index TINYINT UNSIGNED NOT NULL,
			poh_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
			poh_breakdown JSON DEFAULT NULL,
			ip_type VARCHAR(20) DEFAULT 'unknown',
			geo_region VARCHAR(10) DEFAULT '',
			time_to_vote FLOAT DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY poll_device (poll_id, device_hash),
			KEY poll_id (poll_id),
			KEY poh_score (poh_score)
		) {$charset};";

		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}redline_desk (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(100) NOT NULL,
			body LONGTEXT NOT NULL,
			image_url VARCHAR(500) DEFAULT '',
			status VARCHAR(15) NOT NULL DEFAULT 'draft',
			scheduled_at DATETIME DEFAULT NULL,
			views BIGINT UNSIGNED NOT NULL DEFAULT 0,
			read_throughs BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status)
		) {$charset};";

		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}redline_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			device_hash VARCHAR(64) NOT NULL DEFAULT '',
			event_type VARCHAR(30) NOT NULL,
			event_data JSON DEFAULT NULL,
			poh_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
			geo_region VARCHAR(10) DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY event_type (event_type),
			KEY device_hash (device_hash),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	private static function set_defaults(): void {
		$defaults = array(
			'redline_api_key'            => wp_generate_password( 32, false ),
			'redline_poll_interval'      => 300,
			'redline_default_show_results' => 1,
			'redline_max_alerts'         => 20,
			'redline_min_poh_vote'       => 30,
			'redline_min_poh_verified'   => 80,
			'redline_auto_flag'          => 20,
			'redline_data_retention'     => 365,
			'redline_rate_limit'         => 60,
			'redline_cors_origins'       => '',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}
	}
}
