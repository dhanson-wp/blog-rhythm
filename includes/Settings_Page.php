<?php
/**
 * Settings page (spike stub).
 *
 * @package BlogRhythm
 */

namespace BlogRhythm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the settings submenu page and mount point.
 */
class Settings_Page {

	const SLUG = 'blog-rhythm';

	/**
	 * Hook registration.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the page under Settings.
	 */
	public function add_page() {
		add_submenu_page(
			'options-general.php',
			esc_html__( 'Blog rhythm', 'blog-rhythm' ),
			esc_html__( 'Blog rhythm', 'blog-rhythm' ),
			'edit_posts',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the mount point.
	 */
	public function render() {
		echo '<div id="blog-rhythm-settings-root"></div>';
	}

	/**
	 * Enqueue assets on our page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		$asset_file = BLOG_RHYTHM_DIR . 'build/settings.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;
		wp_enqueue_script(
			'blog_rhythm_settings',
			BLOG_RHYTHM_URL . 'build/settings.js',
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
