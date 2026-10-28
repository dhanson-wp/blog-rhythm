<?php
/**
 * Settings page tests.
 *
 * @package BlogRhythm
 */

use BlogRhythm\Settings_Page;

/**
 * Tests for BlogRhythm\Settings_Page.
 */
class Test_Settings_Page extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		global $submenu, $_wp_submenu_nopriv, $_registered_pages;
		$submenu            = array();
		$_wp_submenu_nopriv = array();
		$_registered_pages  = array();
		wp_dequeue_script( 'blog_rhythm_settings' );
		wp_deregister_script( 'blog_rhythm_settings' );
		wp_dequeue_style( 'blog_rhythm_settings' );
		wp_dequeue_style( 'blog_rhythm_style' );
	}

	private function slugs_under_posts(): array {
		global $submenu;
		return isset( $submenu['edit.php'] ) ? wp_list_pluck( $submenu['edit.php'], 2 ) : array();
	}

	public function test_menu_visible_to_authors() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$page = new Settings_Page();
		$page->add_page();

		global $submenu;
		$this->assertContains( 'blog-rhythm', $this->slugs_under_posts() );
		$entry = null;
		foreach ( $submenu['edit.php'] as $item ) {
			if ( 'blog-rhythm' === $item[2] ) {
				$entry = $item;
			}
		}
		$this->assertSame( 'Blog Rhythm', $entry[0] );
		$this->assertSame( 'edit_posts', $entry[1] );
		$this->assertSame( 'Blog Rhythm', $entry[3] );
		$this->assertNotEmpty( $page->hook );
	}

	public function test_menu_hidden_from_subscribers() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		( new Settings_Page() )->add_page();
		$this->assertNotContains( 'blog-rhythm', $this->slugs_under_posts() );
	}

	public function test_url_points_at_posts_menu_page() {
		$this->assertSame( admin_url( 'edit.php?page=blog-rhythm' ), Settings_Page::url() );
	}

	public function test_assets_only_enqueued_on_this_page() {
		if ( ! is_readable( BLOG_RHYTHM_DIR . 'build/settings.asset.php' ) ) {
			$this->markTestSkipped( 'Build the assets first.' );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$page = new Settings_Page();
		$page->add_page();

		$page->enqueue( 'index.php' );
		$this->assertFalse( wp_script_is( 'blog_rhythm_settings', 'enqueued' ) );

		$page->enqueue( $page->hook );
		$this->assertTrue( wp_script_is( 'blog_rhythm_settings', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'blog_rhythm_style', 'enqueued' ) );

		$data = wp_scripts()->get_data( 'blog_rhythm_settings', 'before' );
		$this->assertIsArray( $data );
		$this->assertStringContainsString( 'blogRhythmSettings', implode( "\n", $data ) );
		$this->assertStringContainsString( 'timezone', implode( "\n", $data ) );
	}

	public function test_assets_not_enqueued_for_subscribers() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$page       = new Settings_Page();
		$page->hook = 'posts_page_blog-rhythm';
		$page->enqueue( 'posts_page_blog-rhythm' );
		$this->assertFalse( wp_script_is( 'blog_rhythm_settings', 'enqueued' ) );
	}

	public function test_render_outputs_mount_point() {
		ob_start();
		( new Settings_Page() )->render();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'id="blog-rhythm-settings-root"', $html );
	}

	public function test_timezone_label_names_site_zone() {
		update_option( 'timezone_string', 'America/New_York' );
		$this->assertSame( 'America/New_York', Settings_Page::timezone_label() );

		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', -5 );
		$this->assertSame( 'UTC-05:00', Settings_Page::timezone_label() );
	}
}
