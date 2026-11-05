<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

use WP_CLI;

/**
 * Inspects and manages Silvertrail.
 */
final class Cli {

	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'silvertrail', self::class );
		}
	}

	/**
	 * Lists the services.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, csv, json or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param list<string>          $args
	 * @param array<string, string> $assoc_args
	 */
	public function services( array $args, array $assoc_args ): void {
		$essential = Config::essential_ids();
		$rows      = array();
		foreach ( Config::get()['services'] as $id => $service ) {
			$rows[] = array(
				'id'      => $id,
				'group'   => $service['group'],
				'name'    => $service['name'],
				'consent' => in_array( $id, $essential, true ) ? 'not needed' : 'required',
				'hosts'   => implode( ' ', $service['hosts'] ),
				'handles' => implode( ' ', $service['handles'] ),
			);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'id', 'group', 'name', 'consent', 'hosts', 'handles' ) );
	}

	/**
	 * Builds the config and the texts of every installed language, and reports every problem in them.
	 */
	public function validate(): void {
		$problems = array();
		$collect  = static function ( string $function_name, string $message ) use ( &$problems ): void {
			$problems[] = wp_strip_all_tags( $message );
		};
		add_action( 'doing_it_wrong_run', $collect, 10, 2 );
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		Config::rebuild();
		$count = count( Config::get()['services'] );
		Texts::reset();
		foreach ( array_unique( array_merge( array( 'en_US', get_locale() ), get_available_languages() ) ) as $locale ) {
			$translation = Texts::translation( $locale );
			if ( null !== $translation ) {
				WP_CLI::log( sprintf( 'Texts for %s come from %s.', $locale, $translation['from'] ) );
			}
			Texts::base( $locale );
		}
		remove_action( 'doing_it_wrong_run', $collect );
		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		foreach ( $problems as $problem ) {
			WP_CLI::warning( $problem );
		}
		if ( array() !== $problems ) {
			WP_CLI::error( sprintf( '%d problem(s) found.', count( $problems ) ) );
		}
		WP_CLI::success( sprintf( 'The config is valid: %d services.', $count ) );
	}

	/**
	 * Asks every visitor again on their next page view.
	 *
	 * @subcommand ask-again
	 */
	public function ask_again(): void {
		WP_CLI::success( sprintf( 'Revision is now %d.', Config::ask_again() ) );
	}

	/**
	 * Exports the consent records as CSV.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : Only export is supported.
	 * ---
	 * options:
	 *   - export
	 * ---
	 *
	 * [--file=<file>]
	 * : Write to this file instead of the standard output.
	 *
	 * @param list<string>          $args
	 * @param array<string, string> $assoc_args
	 */
	public function records( array $args, array $assoc_args ): void {
		if ( ! Records::enabled() ) {
			WP_CLI::error( 'Records are turned off.' );
		}
		$file   = $assoc_args['file'] ?? 'php://stdout';
		$handle = fopen( $file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CLI output.
		if ( false === $handle ) {
			WP_CLI::error( "Cannot write to {$file}." );
			return;
		}
		$count = Records::export( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI output.
		if ( 'php://stdout' !== $file ) {
			WP_CLI::success( sprintf( 'Exported %d records to %s.', $count, $file ) );
		}
	}
}
