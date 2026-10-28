<?php
/**
 * Smoke test.
 *
 * @package BlogRhythm
 */

/**
 * Confirms the plugin loads.
 */
class Test_Bootstrap extends WP_UnitTestCase {

	public function test_plugin_constants_are_defined() {
		$this->assertTrue( defined( 'BLOG_RHYTHM_VERSION' ) );
		$this->assertTrue( class_exists( 'BlogRhythm\Dashboard_Widget' ) );
	}
}
