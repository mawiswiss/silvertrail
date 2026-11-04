<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

use WP_Admin_Bar;

/**
 * Shows an admin what a page loads before consent. Everything stays in the browser.
 */
final class LeakCheck {

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 20 );
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar' ), 100 );
	}

	public static function enabled(): bool {
		return (bool) Settings::get( 'leak_check' ) && current_user_can( 'manage_options' );
	}

	public static function active(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch for admins.
		return isset( $_GET['silvertrail-check'] ) && self::enabled();
	}

	public static function enqueue(): void {
		if ( ! self::active() ) {
			return;
		}
		wp_enqueue_script(
			'silvertrail-leak-check',
			plugins_url( 'assets/leak-check.js', SILVERTRAIL_FILE ),
			array( 'silvertrail' ),
			SILVERTRAIL_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		wp_add_inline_script(
			'silvertrail-leak-check',
			'window.silvertrailLeakTexts = ' . wp_json_encode(
				array(
					'title'    => __( 'Silvertrail leak check', 'silvertrail' ),
					'intro'    => __( 'This view acts as if the visitor had not decided yet.', 'silvertrail' ),
					'clean'    => __( 'No requests to other hosts before consent.', 'silvertrail' ),
					'ok'       => __( 'essential, fine', 'silvertrail' ),
					'early'    => __( 'loaded before consent', 'silvertrail' ),
					'unknown'  => __( 'not listed, gate it or register a service', 'silvertrail' ),
					'cookies'  => __( 'Cookies this page can read', 'silvertrail' ),
					'storage'  => __( 'Browser storage keys', 'silvertrail' ),
					'none'     => __( 'none', 'silvertrail' ),
					'limits'   => __( 'Cookies of other sites and HttpOnly cookies are not visible from here.', 'silvertrail' ),
					'close'    => __( 'Close', 'silvertrail' ),
					'requests' => __( 'Requests to other hosts', 'silvertrail' ),
				),
				JSON_HEX_TAG | JSON_HEX_AMP
			) . ';',
			'before'
		);
	}

	public static function admin_bar( WP_Admin_Bar $bar ): void {
		if ( is_admin() || ! self::enabled() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'silvertrail-check',
				'title' => __( 'Leak check', 'silvertrail' ),
				'href'  => add_query_arg( 'silvertrail-check', '1' ),
			)
		);
	}
}
