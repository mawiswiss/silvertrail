<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

final class Templates {

	/**
	 * A theme overrides a template by copying it to silvertrail/{name}.php.
	 *
	 * @param array<string, mixed> $args Passed to the template as $args.
	 */
	public static function render( string $name, array $args ): void {
		$file = locate_template( "silvertrail/{$name}.php" );
		if ( '' === $file ) {
			$file = dirname( SILVERTRAIL_FILE ) . "/templates/{$name}.php";
		}
		load_template( $file, false, $args );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function capture( string $name, array $args ): string {
		ob_start();
		self::render( $name, $args );
		return (string) ob_get_clean();
	}
}
