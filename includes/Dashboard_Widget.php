<?php
/**
 * Dashboard widget (spike stub).
 *
 * @package BlogRhythm
 */

namespace BlogRhythm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the dashboard widget and mount point.
 */
class Dashboard_Widget {

	/**
	 * Hook registration.
	 */
	public function register() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the widget.
	 */
	public function add_widget() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'blog_rhythm_widget',
			esc_html__( 'Blog rhythm', 'blog-rhythm' ),
			array( $this, 'render' )
		);
	}

	/**
	 * Render the mount point.
	 */
	public function render() {
		echo '<div id="blog-rhythm-widget-root"></div>';
	}

	/**
	 * Enqueue assets on the dashboard.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( 'index.php' !== $hook || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$asset_file = BLOG_RHYTHM_DIR . 'build/widget.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;
		wp_enqueue_script(
			'blog_rhythm_widget',
			BLOG_RHYTHM_URL . 'build/widget.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		// Design tokens come from core; the bundle ships no token CSS.
		if ( wp_style_is( 'wp-theme', 'registered' ) ) {
			wp_enqueue_style( 'wp-theme' );
		}
	}
}
