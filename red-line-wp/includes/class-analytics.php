<?php
/**
 * Analytics ingestion & queries.
 */

defined( 'ABSPATH' ) || exit;

class RedLine_Analytics {

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'redline_events';
	}

	public static function ingest( array $payload ): array {
		global $wpdb;
		$table = self::table();

		$device_hash = sanitize_text_field( $payload['device_hash'] ?? '' );
		$poh_score   = absint( $payload['poh_score'] ?? 0 );
		$geo_region  = sanitize_text_field( $payload['geo']['region'] ?? '' );
		$events      = $payload['events'] ?? array();

		$count = 0;
		foreach ( $events as $event ) {
			$event_type = sanitize_text_field( $event['type'] ?? 'unknown' );
			unset( $event['type'] );

			$wpdb->insert( $table, array(
				'device_hash' => $device_hash,
				'event_type'  => $event_type,
				'event_data'  => wp_json_encode( $event ),
				'poh_score'   => $poh_score,
				'geo_region'  => $geo_region,
			) );
			++$count;
		}

		// Update device heartbeat.
		if ( $device_hash ) {
			RedLine_Devices::heartbeat( array(
				'device_hash' => $device_hash,
				'poh_score'   => $poh_score,
			) );
		}

		return array( 'success' => true, 'events_ingested' => $count );
	}

	public static function get_event_counts( int $days = 30 ): array {
		global $wpdb;
		$table = self::table();

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, COUNT(*) AS count
				 FROM {$table}
				 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				 GROUP BY event_type
				 ORDER BY count DESC",
				$days
			),
			ARRAY_A
		);

		$counts = array();
		foreach ( $results as $row ) {
			$counts[ $row['event_type'] ] = (int) $row['count'];
		}
		return $counts;
	}

	public static function get_daily_events( int $days = 30, string $event_type = '' ): array {
		global $wpdb;
		$table = self::table();

		$where = $wpdb->prepare( 'created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)', $days );
		if ( $event_type ) {
			$where .= $wpdb->prepare( ' AND event_type = %s', $event_type );
		}

		return $wpdb->get_results(
			"SELECT DATE(created_at) AS date, COUNT(*) AS value
			 FROM {$table}
			 WHERE {$where}
			 GROUP BY DATE(created_at)
			 ORDER BY date ASC",
			ARRAY_A
		) ?: array();
	}

	public static function get_recent_events( int $limit = 20 ): array {
		global $wpdb;
		$table = self::table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT device_hash, event_type, poh_score, geo_region, created_at
				 FROM {$table}
				 ORDER BY created_at DESC
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		) ?: array();
	}

	public static function get_top_pages( int $limit = 10, int $days = 30 ): array {
		global $wpdb;
		$table = self::table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.url')) AS url,
					COUNT(*) AS views,
					AVG(CAST(JSON_EXTRACT(event_data, '$.duration') AS UNSIGNED)) AS avg_time,
					AVG(CAST(JSON_EXTRACT(event_data, '$.scroll_depth') AS DECIMAL(3,2))) AS avg_scroll
				 FROM {$table}
				 WHERE event_type = 'page_visit'
				   AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				 GROUP BY url
				 ORDER BY views DESC
				 LIMIT %d",
				$days, $limit
			),
			ARRAY_A
		) ?: array();
	}

	public static function purge_old_data( int $days ): int {
		global $wpdb;
		$table = self::table();

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}
}
