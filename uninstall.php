<?php
/**
 * Removes Blog Rhythm data on uninstall.
 *
 * @package BlogRhythm
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Runs without the plugin loaded, so the key list is repeated here.
// Keep in sync with BlogRhythm\Privacy::META_KEYS.
foreach ( array(
	'blog_rhythm_settings',
	'blog_rhythm_log',
	'blog_rhythm_reminders_on',
	'blog_rhythm_reminder_secret',
	'blog_rhythm_reminder_last',
) as $blog_rhythm_key ) {
	delete_metadata( 'user', 0, $blog_rhythm_key, '', true );
}
unset( $blog_rhythm_key );

wp_clear_scheduled_hook( 'blog_rhythm_send_reminders' );
