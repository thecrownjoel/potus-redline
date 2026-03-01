<?php
/**
 * Alerts CRUD operations.
 */

defined( 'ABSPATH' ) || exit;

class RedLine_Alerts {

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'redline_alerts';
	}

	public static function get_all( string $status = '', int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		$table = self::table();

		$where = '1=1';
		$params = array();

		if ( $status ) {
			$where .= ' AND status = %s';
			$params[] = $status;
		}

		$params[] = $limit;
		$params[] = $offset;

		$query = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		return $wpdb->get_results( $wpdb->prepare( $query, ...$params ), ARRAY_A ) ?: array();
	}

	public static function get_published( int $limit = 20 ): array {
		global $wpdb;
		$table = self::table();

		// Include scheduled alerts whose time has come.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE status = 'published'
				    OR (status = 'scheduled' AND scheduled_at <= NOW())
				 ORDER BY created_at DESC
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM %i WHERE id = %d", self::table(), $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function create( array $data ): int {
		global $wpdb;

		$wpdb->insert( self::table(), array(
			'headline'     => sanitize_text_field( $data['headline'] ?? '' ),
			'body'         => sanitize_textarea_field( $data['body'] ?? '' ),
			'category'     => sanitize_text_field( $data['category'] ?? 'Statement' ),
			'link_url'     => esc_url_raw( $data['link_url'] ?? '' ),
			'priority'     => sanitize_text_field( $data['priority'] ?? 'normal' ),
			'status'       => sanitize_text_field( $data['status'] ?? 'draft' ),
			'scheduled_at' => $data['scheduled_at'] ?? null,
		) );

		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$fields = array();
		$allowed = array( 'headline', 'body', 'category', 'link_url', 'priority', 'status', 'scheduled_at' );

		foreach ( $allowed as $key ) {
			if ( isset( $data[ $key ] ) ) {
				if ( 'link_url' === $key ) {
					$fields[ $key ] = esc_url_raw( $data[ $key ] );
				} elseif ( 'body' === $key ) {
					$fields[ $key ] = sanitize_textarea_field( $data[ $key ] );
				} else {
					$fields[ $key ] = sanitize_text_field( $data[ $key ] );
				}
			}
		}

		if ( empty( $fields ) ) {
			return false;
		}

		return (bool) $wpdb->update( self::table(), $fields, array( 'id' => $id ) );
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	public static function increment_impressions( int $id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare( "UPDATE %i SET impressions = impressions + 1 WHERE id = %d", self::table(), $id )
		);
	}

	public static function increment_clicks( int $id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare( "UPDATE %i SET clicks = clicks + 1 WHERE id = %d", self::table(), $id )
		);
	}

	public static function count_by_status(): array {
		global $wpdb;
		$table = self::table();

		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) AS count FROM {$table} GROUP BY status",
			ARRAY_A
		);

		$counts = array( 'published' => 0, 'draft' => 0, 'scheduled' => 0 );
		foreach ( $results as $row ) {
			$counts[ $row['status'] ] = (int) $row['count'];
		}
		return $counts;
	}
}
