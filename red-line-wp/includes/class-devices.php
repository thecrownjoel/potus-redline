<?php
/**
 * Device registry & PoH tracking.
 */

defined( 'ABSPATH' ) || exit;

class RedLine_Devices {

	public static function register( array $data ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'redline_devices';

		$device_hash = sanitize_text_field( $data['device_hash'] ?? '' );
		if ( empty( $device_hash ) ) {
			return array( 'error' => 'device_hash required' );
		}

		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE device_hash = %s", $device_hash )
		);

		$row = array(
			'device_hash'   => $device_hash,
			'poh_score'     => absint( $data['poh_score'] ?? 0 ),
			'poh_breakdown' => wp_json_encode( $data['poh_breakdown'] ?? new stdClass() ),
			'ip_type'       => sanitize_text_field( $data['ip_type'] ?? 'unknown' ),
			'geo_country'   => sanitize_text_field( $data['geo']['country'] ?? '' ),
			'geo_region'    => sanitize_text_field( $data['geo']['region'] ?? '' ),
			'geo_city'      => sanitize_text_field( $data['geo']['city'] ?? '' ),
			'last_seen'     => current_time( 'mysql' ),
		);

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'id' => $existing->id ) );
		} else {
			$row['first_seen'] = current_time( 'mysql' );
			$wpdb->insert( $table, $row );
		}

		// Auto-flag if below threshold.
		$auto_flag = (int) get_option( 'redline_auto_flag', 20 );
		if ( $row['poh_score'] < $auto_flag ) {
			$wpdb->update(
				$table,
				array( 'is_flagged' => 1, 'flag_reason' => 'Auto-flagged: PoH below ' . $auto_flag ),
				array( 'device_hash' => $device_hash )
			);
		}

		return array( 'success' => true );
	}

	public static function heartbeat( array $data ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'redline_devices';

		$device_hash = sanitize_text_field( $data['device_hash'] ?? '' );
		if ( empty( $device_hash ) ) {
			return array( 'error' => 'device_hash required' );
		}

		$wpdb->update(
			$table,
			array(
				'poh_score' => absint( $data['poh_score'] ?? 0 ),
				'last_seen' => current_time( 'mysql' ),
			),
			array( 'device_hash' => $device_hash )
		);

		return array( 'success' => true );
	}

	public static function get_stats(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'redline_devices';
		$verified_min = (int) get_option( 'redline_min_poh_verified', 80 );

		$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$active_7d = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
		);
		$verified  = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE poh_score >= %d", $verified_min )
		);
		$avg_poh   = (float) $wpdb->get_var( "SELECT AVG(poh_score) FROM {$table}" );
		$flagged   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_flagged = 1" );

		return array(
			'total_installs'  => $total,
			'active_7d'       => $active_7d,
			'verified'        => $verified,
			'verified_pct'    => $total > 0 ? round( $verified / $total * 100, 1 ) : 0,
			'avg_poh'         => round( $avg_poh, 0 ),
			'flagged'         => $flagged,
		);
	}

	public static function get_poh_distribution(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'redline_devices';

		return array(
			'verified'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE poh_score >= 80" ),
			'likely'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE poh_score >= 50 AND poh_score < 80" ),
			'suspicious' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE poh_score >= 20 AND poh_score < 50" ),
			'bot'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE poh_score < 20" ),
		);
	}

	public static function get_daily_active( int $days = 30 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'redline_devices';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(last_seen) AS date, COUNT(*) AS value
				 FROM {$table}
				 WHERE last_seen >= DATE_SUB(NOW(), INTERVAL %d DAY)
				 GROUP BY DATE(last_seen)
				 ORDER BY date ASC",
				$days
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	public static function get_geo_distribution(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'redline_devices';

		$results = $wpdb->get_results(
			"SELECT geo_region AS state, COUNT(*) AS count
			 FROM {$table}
			 WHERE geo_country = 'US' AND geo_region != ''
			 GROUP BY geo_region
			 ORDER BY count DESC",
			ARRAY_A
		);

		$data = array();
		foreach ( $results as $row ) {
			$data[ $row['state'] ] = (int) $row['count'];
		}
		return $data;
	}
}
