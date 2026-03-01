<?php
/**
 * Mock data seeder for demo purposes.
 */

defined( 'ABSPATH' ) || exit;

class RedLine_Mock_Data {

	public static function seed(): void {
		self::seed_alerts();
		self::seed_polls();
		self::seed_desk();
		self::seed_devices();
	}

	private static function seed_alerts(): void {
		$alerts = array(
			array(
				'headline'  => 'Executive Order on Securing American Borders',
				'body'      => 'The President has signed a comprehensive executive order strengthening border security measures and allocating additional resources to enforcement agencies along the southern border.',
				'category'  => 'Executive Order',
				'link_url'  => 'https://www.whitehouse.gov/presidential-actions/executive-order-securing-borders/',
				'priority'  => 'urgent',
				'status'    => 'published',
			),
			array(
				'headline'  => 'President Delivers Remarks on Economic Growth',
				'body'      => 'In a statement from the East Room, the President highlighted 3.2% GDP growth and record low unemployment across all demographics.',
				'category'  => 'Statement',
				'link_url'  => 'https://www.whitehouse.gov/briefing-room/statements/economic-growth/',
				'priority'  => 'normal',
				'status'    => 'published',
			),
			array(
				'headline'  => 'Press Briefing by Press Secretary',
				'body'      => "The Press Secretary addressed questions on the administration's infrastructure plan, upcoming diplomatic meetings, and domestic policy priorities.",
				'category'  => 'Press Briefing',
				'link_url'  => 'https://www.whitehouse.gov/briefing-room/press-briefings/',
				'priority'  => 'normal',
				'status'    => 'published',
			),
			array(
				'headline'  => 'State Dinner with Prime Minister Announced',
				'body'      => 'The White House announces a State Dinner honoring the Prime Minister, celebrating the strong alliance between our two nations.',
				'category'  => 'Event',
				'link_url'  => 'https://www.whitehouse.gov/briefing-room/statements/state-dinner/',
				'priority'  => 'normal',
				'status'    => 'published',
			),
		);

		foreach ( $alerts as $alert ) {
			$id = RedLine_Alerts::create( $alert );
			if ( $id ) {
				RedLine_Alerts::update( $id, array( 'status' => 'published' ) );
			}
		}
	}

	private static function seed_polls(): void {
		$polls = array(
			array(
				'question'       => 'Do you support the new infrastructure investment plan?',
				'options'        => array( 'Yes', 'No', 'Need More Info' ),
				'show_results'   => 1,
				'status'         => 'active',
				'min_poh_to_vote' => 30,
			),
			array(
				'question'       => 'Which issue matters most to your family?',
				'options'        => array( 'Economy', 'Border Security', 'Healthcare', 'Education', 'Energy' ),
				'show_results'   => 0,
				'status'         => 'active',
				'min_poh_to_vote' => 30,
			),
			array(
				'question'       => 'Should the President hold a town hall in your state?',
				'options'        => array( 'Yes', 'No' ),
				'show_results'   => 1,
				'status'         => 'closed',
				'min_poh_to_vote' => 30,
			),
		);

		foreach ( $polls as $poll ) {
			RedLine_Polls::create( $poll );
		}

		// Seed some mock votes.
		self::seed_mock_votes();
	}

	private static function seed_mock_votes(): void {
		global $wpdb;
		$votes_table = $wpdb->prefix . 'redline_votes';
		$states = array( 'CA', 'TX', 'FL', 'NY', 'PA', 'OH', 'IL', 'GA', 'NC', 'MI', 'VA', 'WA', 'AZ', 'MA', 'CO' );

		// Poll 1: ~12,847 votes, weighted toward Yes.
		$poll_1_weights = array( 0.614, 0.203, 0.183 );
		self::generate_votes( 1, 3, $poll_1_weights, 12847, $states );

		// Poll 2: ~8,234 votes across 5 options.
		$poll_2_weights = array( 0.351, 0.240, 0.187, 0.134, 0.088 );
		self::generate_votes( 2, 5, $poll_2_weights, 8234, $states );

		// Poll 3: ~31,247 votes, heavily Yes.
		$poll_3_weights = array( 0.891, 0.109 );
		self::generate_votes( 3, 2, $poll_3_weights, 31247, $states );
	}

	private static function generate_votes( int $poll_id, int $option_count, array $weights, int $total, array $states ): void {
		global $wpdb;
		$votes_table = $wpdb->prefix . 'redline_votes';

		// Insert summary votes in batches for performance.
		$batch_size = min( $total, 500 );
		$values = array();

		for ( $i = 0; $i < $batch_size; $i++ ) {
			$rand = mt_rand( 0, 10000 ) / 10000;
			$cumulative = 0;
			$choice = 0;
			for ( $j = 0; $j < $option_count; $j++ ) {
				$cumulative += $weights[ $j ];
				if ( $rand <= $cumulative ) {
					$choice = $j;
					break;
				}
			}

			$hash     = substr( md5( "mock_{$poll_id}_{$i}" ), 0, 16 );
			$poh      = mt_rand( 50, 98 );
			$state    = $states[ array_rand( $states ) ];
			$ttv      = round( mt_rand( 3000, 45000 ) / 1000, 1 );
			$days_ago = mt_rand( 0, 14 );
			$date     = gmdate( 'Y-m-d H:i:s', time() - $days_ago * 86400 - mt_rand( 0, 86400 ) );

			$values[] = $wpdb->prepare(
				'(%d, %s, %d, %d, %s, %s, %f, %s)',
				$poll_id, $hash, $choice, $poh, 'residential', $state, $ttv, $date
			);
		}

		if ( ! empty( $values ) ) {
			$chunks = array_chunk( $values, 100 );
			foreach ( $chunks as $chunk ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Values are already prepared above.
				$wpdb->query(
					"INSERT IGNORE INTO {$votes_table} (poll_id, device_hash, choice_index, poh_score, ip_type, geo_region, time_to_vote, created_at) VALUES " . implode( ',', $chunk )
				);
			}
		}
	}

	private static function seed_desk(): void {
		$messages = array(
			array(
				'title'  => 'A Message on American Energy Independence',
				'body'   => "My fellow Americans,\n\nEnergy independence is not just an economic issue — it is a matter of national security. Today, I am proud to announce that the United States has reached a historic milestone in domestic energy production.\n\nWe are no longer dependent on foreign nations for our energy needs. American workers, American innovation, and American resources are powering our future.\n\nThis is what happens when government gets out of the way and lets the American people do what they do best — build, create, and lead.\n\nGod bless you, and God bless the United States of America.",
				'status' => 'published',
			),
			array(
				'title'  => 'Thank You, America',
				'body'   => "To every American who believes in this country — thank you.\n\nThank you for your service, your sacrifice, and your faith in the American Dream. Every day I wake up in the White House, I think about you. The teacher staying late to help a student. The truck driver keeping our supply chains moving. The soldier standing watch so we can sleep in peace.\n\nThis administration works for YOU. Not the lobbyists, not the special interests — YOU.\n\nKeep believing. Keep fighting. Our best days are ahead.\n\nWith gratitude,\nThe President",
				'status' => 'published',
			),
		);

		foreach ( $messages as $msg ) {
			RedLine_Desk::create( $msg );
		}
	}

	private static function seed_devices(): void {
		global $wpdb;
		$table  = $wpdb->prefix . 'redline_devices';
		$states = array( 'CA', 'TX', 'FL', 'NY', 'PA', 'OH', 'IL', 'GA', 'NC', 'MI', 'VA', 'WA', 'AZ', 'MA', 'CO', 'TN', 'MO', 'IN', 'WI', 'MN' );

		$values = array();
		$count  = 200; // Seed 200 devices for demo.

		for ( $i = 0; $i < $count; $i++ ) {
			$hash     = substr( md5( "device_mock_{$i}" ), 0, 16 );
			$poh      = mt_rand( 10, 98 );
			$state    = $states[ array_rand( $states ) ];
			$days_ago = mt_rand( 0, 60 );
			$first    = gmdate( 'Y-m-d H:i:s', time() - $days_ago * 86400 );
			$last     = gmdate( 'Y-m-d H:i:s', time() - mt_rand( 0, 7 ) * 86400 );

			$flagged = $poh < 20 ? 1 : 0;
			$reason  = $flagged ? 'Auto-flagged: PoH below 20' : '';

			$values[] = $wpdb->prepare(
				'(%s, %d, %s, %s, %s, %s, %s, %d, %s, %s, %s)',
				$hash, $poh, '{}', 'residential', 'US', $state, '',
				$flagged, $reason, $first, $last
			);
		}

		if ( ! empty( $values ) ) {
			$chunks = array_chunk( $values, 100 );
			foreach ( $chunks as $chunk ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query(
					"INSERT IGNORE INTO {$table} (device_hash, poh_score, poh_breakdown, ip_type, geo_country, geo_region, geo_city, is_flagged, flag_reason, first_seen, last_seen) VALUES " . implode( ',', $chunk )
				);
			}
		}
	}
}
