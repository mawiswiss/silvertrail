<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

use WP_CLI;

final class Plugin {

	public static function boot(): void {
		Frontend::register();
		ScriptGate::register();
		EmbedGate::register();
		Snippets::register();
		ConsentMode::register();
		ConsentApi::register();
		Reopen::register();
		Scan::register();
		Records::register();
		Cli::register();
		if ( is_admin() ) {
			Admin\SettingsPage::register();
		}
	}

	public static function activated(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( self::license() . ' ' . SILVERTRAIL_LICENSE_URL );
		}
	}

	public static function license(): string {
		return __( 'Silvertrail is free on your own site. When you are paid to put it on a client’s site, buy a site license.', 'silvertrail' );
	}
}
