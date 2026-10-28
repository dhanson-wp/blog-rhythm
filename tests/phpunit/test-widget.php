<?php
/**
 * Dashboard widget tests.
 *
 * @package BlogRhythm
 */

use BlogRhythm\Dashboard_Widget;
use BlogRhythm\Settings_Page;

/**
 * Tests for BlogRhythm\Dashboard_Widget.
 */
class Test_Widget extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		set_current_screen( 'dashboard' );
		$GLOBALS['wp_meta_boxes'] = array();
		wp_dequeue_script( 'blog_rhythm_widget' );
		wp_dequeue_style( 'blog_rhythm_style' );
	}

	public function tear_down() {
		unset( $GLOBALS['wp_meta_boxes'] );
		parent::tear_down();
	}

	public function test_widget_registered_for_authors_not_subscribers() {
		$widget = new Dashboard_Widget();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$widget->add_widget();
		$this->assertArrayNotHasKey( 'dashboard', $GLOBALS['wp_meta_boxes'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$widget->add_widget();
		$box = $GLOBALS['wp_meta_boxes']['dashboard']['normal']['default']['blog_rhythm_widget'];
		$this->assertSame( 'blog_rhythm_widget', $box['id'] );
		$this->assertStringContainsString( 'Blog Rhythm', $box['title'] );
		$this->assertStringContainsString( esc_url( Settings_Page::url() ), $box['title'] );
		$this->assertStringContainsString( '>Settings</a>', $box['title'] );
	}

	public function test_render_outputs_mount_with_escaped_settings_url() {
		$widget = new Dashboard_Widget();
		ob_start();
		$widget->render();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'id="blog-rhythm-widget-root"', $html );
		$this->assertStringContainsString( 'data-settings-url="' . esc_url( Settings_Page::url() ) . '"', $html );
	}

	public function test_script_only_enqueued_on_dashboard() {
		$widget = new Dashboard_Widget();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );

		$widget->enqueue( 'edit.php' );
		$this->assertFalse( wp_script_is( 'blog_rhythm_widget', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'blog_rhythm_style', 'enqueued' ) );

		$widget->enqueue( 'index.php' );
		$this->assertTrue( wp_script_is( 'blog_rhythm_widget', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'blog_rhythm_style', 'enqueued' ) );
	}

	public function test_script_not_enqueued_for_subscribers() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		( new Dashboard_Widget() )->enqueue( 'index.php' );
		$this->assertFalse( wp_script_is( 'blog_rhythm_widget', 'enqueued' ) );
	}

	public function test_settings_page_url_points_at_posts_menu_page() {
		$this->assertSame( admin_url( 'edit.php?page=blog-rhythm' ), Settings_Page::url() );
	}
}
