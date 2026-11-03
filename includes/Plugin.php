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
	}
}
