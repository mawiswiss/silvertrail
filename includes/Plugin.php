<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

use WP_CLI;

final class Plugin {

	public const PREVIEW = 'silvertrail-preview';

	public static function boot(): void {
		Reopen::register();
		Scan::register();
		Records::register();
		Cli::register();
		// Waits until a silvertrail_settings filter added on init counts too.
		add_action( 'wp_loaded', array( self::class, 'start' ) );
		if ( is_admin() ) {
			Admin\SettingsPage::register();
		}
	}

	public static function start(): void {
		if ( ! self::live() ) {
			return;
		}
		Frontend::register();
		ScriptGate::register();
		EmbedGate::register();
		Snippets::register();
		ConsentMode::register();
		ConsentApi::register();
		add_action( 'wp_footer', array( Reopen::class, 'button' ) );
	}

	/**
	 * Whether Silvertrail works on this request: switched on, or previewed through the URL.
	 */
	public static function live(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a preview only shows what visitors would get.
		return (bool) Settings::get( 'enabled' ) || isset( $_GET[ self::PREVIEW ] ) || Scan::active();
	}

	public static function activated(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( self::license() . ' ' . SILVERTRAIL_LICENSE_URL );
		}
	}

	public static function license(): string {
		return __( 'Silvertrail is free. When you are paid to put it on a client’s site, consider supporting its development with a site license.', 'silvertrail' );
	}
}
