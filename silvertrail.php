<?php
/**
 * Plugin Name:       Silvertrail Cookie Banner
 * Plugin URI:        https://github.com/mawiswiss/silvertrail
 * Description:       A small cookie consent banner, and the backbone behind it for everything the site loads from other servers. Nothing loads before yes, accept and reject weigh the same, and developers configure it in code.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Marc Wieland
 * Author URI:        https://marcwieland.name
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       silvertrail
 *
 * @package Silvertrail
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const SILVERTRAIL_VERSION     = '1.0.0';
const SILVERTRAIL_FILE        = __FILE__;
const SILVERTRAIL_LICENSE_URL = 'https://marcwieland.name/support/';

spl_autoload_register(
	static function ( string $class_name ): void {
		if ( ! str_starts_with( $class_name, 'Silvertrail\\' ) ) {
			return;
		}
		$path = __DIR__ . '/includes/' . str_replace( '\\', '/', substr( $class_name, 12 ) ) . '.php';
		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

require __DIR__ . '/includes/functions.php';

Silvertrail\Plugin::boot();
register_activation_hook( __FILE__, array( Silvertrail\Records::class, 'sync' ) );
register_activation_hook( __FILE__, array( Silvertrail\Plugin::class, 'activated' ) );
