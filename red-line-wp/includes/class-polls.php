<?php
/**
 * Polls CRUD + vote handling.
 */

defined( 'ABSPATH' ) || exit;

class RedLine_Polls {

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'redline_polls';
	}

	private static function votes_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'redline_votes';
	}

	public static function get_all( string $status = '' ): array {
		global $wpdb;
		$table = self::table();

		if ( $status ) {
			$results = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC", $status ),
				ARRAY_A
			);
		} else {
			$results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
		}

		return array_map( array( __CLASS__, 'hydrate' ), $results ?: array() );
	}

	public static function get_active(): array {
		global $wpdb;
		$table = self::table();

		$results = $wpdb->get_results(
			"SELECT * FROM {$table}
			 WHERE status = 'active'
			    OR (status = 'scheduled' AND opens_at <= NOW())
			 ORDER BY created_at DESC",
			ARRAY_A
		);

		return array_map( array( __CLASS__, 'hydrate' ), $results ?: array() );
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM %i WHERE id = %d", self::table(), $id ),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	private static function hydrate( array $poll ): array {
		$poll['options'] = json_decode( $poll['options'] ?? '[]', true );
		$poll['show_results'] = (bool) ( $poll['show_results'] ?? true );
		$poll['results'] = self::get_results( (int) $poll['id'], count( $poll['options'] ) );
		return $poll;
	}

	public static function get_results( int $poll_id, int $option_count ): array {
		global $wpdb;
		$votes_table = self::votes_table();
		$verified_min = (int) get_option( 'redline_min_poh_verified', 80 );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$votes_table} WHERE poll_id = %d", $poll_id )
		);

		$verified = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$votes_table} WHERE poll_id = %d AND poh_score >= %d",
				$poll_id, $verified_min
			)
		);

		$choices = array_fill( 0, $option_count, 0 );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT choice_index, COUNT(*) AS cnt FROM {$votes_table} WHERE poll_id = %d GROUP BY choice_index",
				$poll_id
			),
			ARRAY_A
		);
		foreach ( $rows as $row ) {
			$idx = (int) $row['choice_index'];
			if ( isset( $choices[ $idx ] ) ) {
				$choices[ $idx ] = (int) $row['cnt'];
			}
		}

		$by_region = array();
		$region_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT geo_region, COUNT(*) AS cnt FROM {$votes_table} WHERE poll_id = %d AND geo_region != '' GROUP BY geo_region ORDER BY cnt DESC LIMIT 50",
				$poll_id
			),
			ARRAY_A
		);
		foreach ( $region_rows as $row ) {
			$by_region[ $row['geo_region'] ] = (int) $row['cnt'];
		}

		return array(
			'total'     => $total,
			'verified'  => $verified,
			'choices'   => $choices,
			'by_region' => $by_region,
		);
	}

	public static function create( array $data ): int {
		global $wpdb;

		$wpdb->insert( self::table(), array(
			'question'       => sanitize_text_field( $data['question'] ?? '' ),
			'options'        => wp_json_encode( $data['options'] ?? array() ),
			'show_results'   => (int) ( $data['show_results'] ?? 1 ),
			'status'         => sanitize_text_field( $data['status'] ?? 'draft' ),
			'min_poh_to_vote' => absint( $data['min_poh_to_vote'] ?? 30 ),
			'opens_at'       => $data['opens_at'] ?? null,
			'closes_at'      => $data['closes_at'] ?? null,
		) );

		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$fields = array();

		if ( isset( $data['question'] ) ) $fields['question'] = sanitize_text_field( $data['question'] );
		if ( isset( $data['options'] ) )  $fields['options'] = wp_json_encode( $data['options'] );
		if ( isset( $data['show_results'] ) ) $fields['show_results'] = (int) $data['show_results'];
		if ( isset( $data['status'] ) )   $fields['status'] = sanitize_text_field( $data['status'] );
		if ( isset( $data['min_poh_to_vote'] ) ) $fields['min_poh_to_vote'] = absint( $data['min_poh_to_vote'] );
		if ( isset( $data['opens_at'] ) ) $fields['opens_at'] = $data['opens_at'];
		if ( isset( $data['closes_at'] ) ) $fields['closes_at'] = $data['closes_at'];

		if ( empty( $fields ) ) return false;

		return (bool) $wpdb->update( self::table(), $fields, array( 'id' => $id ) );
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( self::votes_table(), array( 'poll_id' => $id ) );
		return (bool) $wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	public static function vote( int $poll_id, array $data ): array {
		global $wpdb;
		$votes_table = self::votes_table();

		$device_hash  = sanitize_text_field( $data['device_hash'] ?? '' );
		$choice_index = absint( $data['choice_index'] ?? 0 );
		$poh_score    = absint( $data['poh_score'] ?? 0 );

		if ( empty( $device_hash ) ) {
			return array( 'error' => 'device_hash required' );
		}

		// Check poll exists and is active.
		$poll = self::get( $poll_id );
		if ( ! $poll || 'active' !== $poll['status'] ) {
			return array( 'error' => 'Poll not active' );
		}

		// Check min PoH.
		if ( $poh_score < $poll['min_poh_to_vote'] ) {
			return array( 'error' => 'PoH score too low to participate' );
		}

		// Check duplicate.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$votes_table} WHERE poll_id = %d AND device_hash = %s",
				$poll_id, $device_hash
			)
		);

		if ( $existing ) {
			return array( 'error' => 'Already voted' );
		}

		$wpdb->insert( $votes_table, array(
			'poll_id'       => $poll_id,
			'device_hash'   => $device_hash,
			'choice_index'  => $choice_index,
			'poh_score'     => $poh_score,
			'poh_breakdown' => wp_json_encode( $data['poh_breakdown'] ?? new stdClass() ),
			'ip_type'       => sanitize_text_field( $data['ip_type'] ?? 'unknown' ),
			'geo_region'    => sanitize_text_field( $data['geo_region'] ?? '' ),
			'time_to_vote'  => (float) ( $data['time_to_vote'] ?? 0 ),
		) );

		return array( 'success' => true, 'message' => 'Vote recorded' );
	}

	public static function export_csv( int $poll_id ): string {
		global $wpdb;
		$votes_table = self::votes_table();
		$poll = self::get( $poll_id );

		if ( ! $poll ) return '';

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$votes_table} WHERE poll_id = %d ORDER BY created_at ASC", $poll_id ),
			ARRAY_A
		);

		$csv = "Poll: " . $poll['question'] . "\n";
		$csv .= "choice_index,choice_label,device_hash,poh_score,ip_type,geo_region,time_to_vote,created_at\n";

		foreach ( $rows as $row ) {
			$label = $poll['options'][ $row['choice_index'] ] ?? 'Unknown';
			$csv .= implode( ',', array(
				$row['choice_index'],
				'"' . $label . '"',
				$row['device_hash'],
				$row['poh_score'],
				$row['ip_type'],
				$row['geo_region'],
				$row['time_to_vote'],
				$row['created_at'],
			) ) . "\n";
		}

		return $csv;
	}
}
