<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

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
}
