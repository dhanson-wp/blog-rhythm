<?php
/**
 * Activity derivation tests.
 *
 * @package BlogRhythm
 */

use BlogRhythm\Activity;
use BlogRhythm\Log;

/**
 * Tests for BlogRhythm\Activity.
 */
class Test_Activity extends WP_UnitTestCase {

	/**
	 * The writer.
	 *
	 * @var int
	 */
	private $me;

	/**
	 * Another author.
	 *
	 * @var int
	 */
	private $other;

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'America/New_York' );
		$this->me    = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->other = self::factory()->user->create( array( 'role' => 'editor' ) );
	}

	private function post( array $args = array() ): int {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_author' => $this->me,
					'post_status' => 'publish',
					'post_date'   => '2026-05-10 10:00:00',
				),
				$args
			)
		);
	}

	private function revision( int $parent, int $author, string $when ): int {
		return wp_insert_post(
			array(
				'post_type'    => 'revision',
				'post_status'  => 'inherit',
				'post_parent'  => $parent,
				'post_author'  => $author,
				'post_title'   => 'rev',
				'post_content' => 'rev',
				'post_name'    => $parent . '-revision-v1-' . wp_generate_password( 6, false ),
				'post_date'    => $when,
			)
		);
	}

	private function range(): array {
		return Activity::for_range( $this->me, '2026-01-01', '2026-12-31' );
	}

	public function test_published_post_counts_on_site_date() {
		$this->post();
		$this->assertSame( array( '2026-05-10' => array( 'published' ) ), $this->range() );
	}

	public function test_late_evening_post_uses_site_timezone_not_utc() {
		$this->post( array( 'post_date' => '2026-05-10 23:30:00' ) );
		$days = $this->range();
		$this->assertSame( array( '2026-05-10' ), array_keys( $days ) );
		$this->assertSame( array( 'published' ), $days['2026-05-10'] );
	}

	public function test_dst_change_day_has_one_entry() {
		// Spring forward (03:30 is 07:30 UTC) and fall back (23:30 is 04:30 UTC next day).
		$this->post( array( 'post_date' => '2025-03-09 03:30:00' ) );
		$this->post( array( 'post_date' => '2025-11-02 23:30:00' ) );
		$days = Activity::for_range( $this->me, '2025-01-01', '2025-12-31' );
		$this->assertSame( array( '2025-03-09', '2025-11-02' ), array_keys( $days ) );
		$this->assertSame( array( 'published' ), $days['2025-03-09'] );
		$this->assertSame( array( 'published' ), $days['2025-11-02'] );
	}

	public function test_draft_pending_future_are_drafted() {
		$this->post( array( 'post_status' => 'draft', 'post_date' => '2026-05-01 09:00:00' ) );
		$this->post( array( 'post_status' => 'pending', 'post_date' => '2026-05-02 09:00:00' ) );
		$future = $this->post( array( 'post_status' => 'future', 'post_date' => '2099-01-01 09:00:00' ) );
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_modified' => '2026-05-03 09:00:00', 'post_modified_gmt' => '2026-05-03 13:00:00' ), array( 'ID' => $future ) );
		clean_post_cache( $future );
		$days = $this->range();
		$this->assertSame( array( 'drafted' ), $days['2026-05-01'] );
		$this->assertSame( array( 'drafted' ), $days['2026-05-02'] );
		$this->assertSame( array( 'drafted' ), $days['2026-05-03'] );
		// The scheduled date itself is not activity.
		$all = Activity::for_range( $this->me, '2026-01-01', '2099-12-31' );
		$this->assertArrayNotHasKey( '2099-01-01', $all );
		$this->assertCount( 3, $all );
	}

	public function test_private_counts_as_published() {
		$this->post( array( 'post_status' => 'private' ) );
		$this->assertSame( array( 'published' ), $this->range()['2026-05-10'] );
	}

	public function test_trashed_posts_ignored() {
		$id = $this->post();
		wp_trash_post( $id );
		$this->post( array( 'post_status' => 'auto-draft' ) );
		$this->assertSame( array(), $this->range() );
	}

	public function test_other_authors_posts_never_appear() {
		$this->post( array( 'post_author' => $this->other ) );
		$this->assertSame( array(), $this->range() );
		$this->assertSame( array(), Activity::for_range( $this->me, '2026-01-01', '2026-12-31' ) );
	}

	public function test_editor_revision_on_my_post_does_not_count_for_me() {
		$id = $this->post();
		$this->revision( $id, $this->other, '2026-06-01 12:00:00' );
		$this->assertSame( array( '2026-05-10' => array( 'published' ) ), $this->range() );
	}

	public function test_editor_edit_of_my_draft_does_not_count_via_post_modified() {
		$id = $this->post( array( 'post_status' => 'draft' ) );
		$this->revision( $id, $this->other, '2026-06-01 12:00:00' );
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_modified' => '2026-06-01 12:00:00', 'post_modified_gmt' => '2026-06-01 16:00:00' ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		$this->assertSame( array( '2026-05-10' => array( 'drafted' ) ), $this->range() );
	}

	public function test_my_revision_counts_as_drafted() {
		$id = $this->post( array( 'post_status' => 'draft', 'post_date' => '2026-05-01 09:00:00' ) );
		$this->revision( $id, $this->me, '2026-05-03 09:00:00' );
		$days = $this->range();
		$this->assertSame( array( 'drafted' ), $days['2026-05-03'] );
		$this->assertSame( array( 'drafted' ), $days['2026-05-01'] );
	}

	public function test_my_revision_on_publish_day_is_drafted() {
		$id = $this->post();
		$this->revision( $id, $this->me, '2026-05-10 08:00:00' );
		$this->assertSame( array( '2026-05-10' => array( 'published', 'drafted' ) ), $this->range() );
	}

	public function test_edit_of_older_published_post_is_updated() {
		$id = $this->post();
		$this->revision( $id, $this->me, '2026-05-20 09:00:00' );
		$days = $this->range();
		$this->assertSame( array( 'published' ), $days['2026-05-10'] );
		$this->assertSame( array( 'updated' ), $days['2026-05-20'] );
	}

	public function test_published_post_modified_alone_is_not_updated() {
		$id = $this->post();
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_modified' => '2026-07-01 12:00:00', 'post_modified_gmt' => '2026-07-01 16:00:00' ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		$this->assertSame( array( '2026-05-10' => array( 'published' ) ), $this->range() );
	}

	public function test_pages_ignored() {
		$this->post( array( 'post_type' => 'page' ) );
		$this->assertSame( array(), $this->range() );
	}

	public function test_user_with_no_posts_returns_empty_array() {
		$this->assertSame( array(), $this->range() );
	}

	public function test_range_is_inclusive() {
		$this->post( array( 'post_date' => '2026-05-10 10:00:00' ) );
		$this->post( array( 'post_date' => '2026-05-12 10:00:00' ) );
		$this->post( array( 'post_date' => '2026-05-13 10:00:00' ) );
		$days = Activity::for_range( $this->me, '2026-05-10', '2026-05-12' );
		$this->assertSame( array( '2026-05-10', '2026-05-12' ), array_keys( $days ) );
	}

	public function test_manual_entries_merge_as_idea_in_stable_order() {
		$this->post();
		update_user_meta(
			$this->me,
			Log::META_KEY,
			array(
				'entries'        => array(
					'2026-05-10' => array( 'idea' ),
					'2026-05-11' => array( 'idea' ),
				),
				'cleared_before' => '',
			)
		);
		$this->assertSame(
			array(
				'2026-05-10' => array( 'published', 'idea' ),
				'2026-05-11' => array( 'idea' ),
			),
			$this->range()
		);
	}

	public function test_range_filters_before_cleared_before() {
		$this->post( array( 'post_date' => '2026-05-10 10:00:00' ) );
		$this->post( array( 'post_date' => '2026-05-20 10:00:00' ) );
		update_user_meta(
			$this->me,
			Log::META_KEY,
			array(
				'entries'        => array( '2026-05-05' => array( 'idea' ), '2026-05-21' => array( 'idea' ) ),
				'cleared_before' => '2026-05-20',
			)
		);
		$this->assertSame( array( '2026-05-20', '2026-05-21' ), array_keys( $this->range() ) );
	}
}
