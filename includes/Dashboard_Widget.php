<?php
/**
 * Dashboard widget.
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

	const WIDGET_ID = 'blog_rhythm_widget';

	/**
	 * Handle for the stylesheet shared by the widget and the settings page.
	 */
	const STYLE_HANDLE = 'blog_rhythm_style';

	/**
	 * Hook registration.
	 */
	public function register() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the widget for people who can write posts.
	 */
	public function add_widget() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			self::WIDGET_ID,
			esc_html__( 'Blog Rhythm', 'blog-rhythm' ),
			array( $this, 'render' ),
			null,
			null,
			'normal',
			'default'
		);

		// Append the Settings link to the box heading only, after registering
		// (core's pattern), so Screen Options keeps a plain checkbox label.
		global $wp_meta_boxes;
		if ( isset( $wp_meta_boxes['dashboard']['normal']['default'][ self::WIDGET_ID ]['title'] ) ) {
			$wp_meta_boxes['dashboard']['normal']['default'][ self::WIDGET_ID ]['title'] .= sprintf( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Core's documented way to decorate a registered box title.
				' <a class="blog-rhythm-settings-link" href="%1$s">%2$s</a>',
				esc_url( Settings_Page::url() ),
				esc_html__( 'Settings', 'blog-rhythm' )
			);
		}
	}

	/**
	 * Render the mount point.
	 */
	public function render() {
		printf(
			'<div id="blog-rhythm-widget-root" data-settings-url="%s"></div>',
			esc_url( Settings_Page::url() )
		);
	}

	/**
	 * Enqueue the shared stylesheet (with its token dependency).
	 */
	public static function enqueue_style() {
		$css_file = BLOG_RHYTHM_DIR . 'build/style-widget.css';
		if ( ! is_readable( $css_file ) ) {
			return;
		}
		// Design tokens come from core; the bundle ships no token CSS.
		$deps = wp_style_is( 'wp-theme', 'registered' ) ? array( 'wp-theme' ) : array();
		wp_enqueue_style(
			self::STYLE_HANDLE,
			BLOG_RHYTHM_URL . 'build/style-widget.css',
			$deps,
			(string) filemtime( $css_file )
		);
		wp_style_add_data( self::STYLE_HANDLE, 'rtl', 'replace' );
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
			self::WIDGET_ID,
			BLOG_RHYTHM_URL . 'build/widget.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( self::WIDGET_ID, 'blog-rhythm', BLOG_RHYTHM_DIR . 'languages' );
		self::enqueue_style();
	}
}
