<?php
/**
 * Settings page.
 *
 * @package BlogRhythm
 */

namespace BlogRhythm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Blog Rhythm page under Posts, its mount point, and assets.
 */
class Settings_Page {

	const SLUG = 'blog-rhythm';

	const SCRIPT_HANDLE = 'blog_rhythm_settings';

	/**
	 * Hook suffix returned by add_submenu_page(). Core builds it from the
	 * translated "Posts" menu title, so it is stored rather than guessed.
	 *
	 * @var string
	 */
	public $hook = '';

	/**
	 * URL of the settings page.
	 *
	 * @return string
	 */
	public static function url(): string {
		return admin_url( 'edit.php?page=' . self::SLUG );
	}

	/**
	 * Hook registration.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the page under Posts for everyone who can write posts.
	 */
	public function add_page() {
		$hook       = add_submenu_page(
			'edit.php',
			__( 'Blog Rhythm', 'blog-rhythm' ),
			__( 'Blog Rhythm', 'blog-rhythm' ),
			'edit_posts',
			self::SLUG,
			array( $this, 'render' )
		);
		$this->hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Render the mount point.
	 */
	public function render() {
		echo '<div id="blog-rhythm-settings-root" class="blog-rhythm-settings-root"></div>';
	}

	/**
	 * The site time zone as shown in the reminders help text.
	 *
	 * @return string A zone name such as America/New_York, or UTC+02:00.
	 */
	public static function timezone_label(): string {
		$zone = wp_timezone_string();
		if ( '' !== $zone && ( '+' === $zone[0] || '-' === $zone[0] ) ) {
			return 'UTC' . $zone;
		}
		return $zone;
	}

	/**
	 * Enqueue assets on this page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( '' === $this->hook || $this->hook !== $hook || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$asset_file = BLOG_RHYTHM_DIR . 'build/settings.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			BLOG_RHYTHM_URL . 'build/settings.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( self::SCRIPT_HANDLE, 'blog-rhythm', BLOG_RHYTHM_DIR . 'languages' );

		$user = wp_get_current_user();
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.blogRhythmSettings = ' . wp_json_encode(
				array(
					'timezone' => self::timezone_label(),
					'email'    => $user->user_email,
				)
			) . ';',
			'before'
		);

		// Shared widget and visual styles, with the core token dependency.
		Dashboard_Widget::enqueue_style();

		$deps = array( 'wp-components' );
		if ( wp_style_is( Dashboard_Widget::STYLE_HANDLE, 'registered' ) ) {
			$deps[] = Dashboard_Widget::STYLE_HANDLE;
		} elseif ( wp_style_is( 'wp-theme', 'registered' ) ) {
			$deps[] = 'wp-theme';
		}
		$css_file = BLOG_RHYTHM_DIR . 'build/style-settings.css';
		if ( is_readable( $css_file ) ) {
			wp_enqueue_style(
				self::SCRIPT_HANDLE,
				BLOG_RHYTHM_URL . 'build/style-settings.css',
				$deps,
				(string) filemtime( $css_file )
			);
			wp_style_add_data( self::SCRIPT_HANDLE, 'rtl', 'replace' );
		} else {
			foreach ( $deps as $dep ) {
				wp_enqueue_style( $dep );
			}
		}
	}
}
