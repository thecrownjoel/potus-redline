<?php
/**
 * Desk messages CRUD.
 */

defined( 'ABSPATH' ) || exit;

class RedLine_Desk {

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'redline_desk';
	}

	public static function get_all( string $status = '' ): array {
		global $wpdb;
		$table = self::table();

		if ( $status ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC", $status ),
				ARRAY_A
			) ?: array();
		}

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A ) ?: array();
	}

	public static function get_published(): array {
		global $wpdb;
		$table = self::table();

		return $wpdb->get_results(
			"SELECT * FROM {$table}
			 WHERE status = 'published'
			    OR (status = 'scheduled' AND scheduled_at <= NOW())
			 ORDER BY created_at DESC",
			ARRAY_A
		) ?: array();
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
			'title'        => sanitize_text_field( $data['title'] ?? '' ),
			'body'         => wp_kses_post( $data['body'] ?? '' ),
			'image_url'    => esc_url_raw( $data['image_url'] ?? '' ),
			'status'       => sanitize_text_field( $data['status'] ?? 'draft' ),
			'scheduled_at' => $data['scheduled_at'] ?? null,
		) );

		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$fields = array();

		if ( isset( $data['title'] ) )     $fields['title'] = sanitize_text_field( $data['title'] );
		if ( isset( $data['body'] ) )      $fields['body'] = wp_kses_post( $data['body'] );
		if ( isset( $data['image_url'] ) ) $fields['image_url'] = esc_url_raw( $data['image_url'] );
		if ( isset( $data['status'] ) )    $fields['status'] = sanitize_text_field( $data['status'] );
		if ( isset( $data['scheduled_at'] ) ) $fields['scheduled_at'] = $data['scheduled_at'];

		if ( empty( $fields ) ) return false;

		return (bool) $wpdb->update( self::table(), $fields, array( 'id' => $id ) );
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	public static function increment_views( int $id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare( "UPDATE %i SET views = views + 1 WHERE id = %d", self::table(), $id )
		);
	}

	public static function increment_read_throughs( int $id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare( "UPDATE %i SET read_throughs = read_throughs + 1 WHERE id = %d", self::table(), $id )
		);
	}
}
