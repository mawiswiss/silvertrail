<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

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
	 * Lists the settings, their values and where each comes from: code, admin or default.
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
	public function settings( array $args, array $assoc_args ): void {
		$format = $assoc_args['format'] ?? 'table';
		$rows   = array();
		foreach ( array_diff_key( Settings::all(), array_flip( array( 'presets', 'texts' ) ) ) as $key => $value ) {
			$rows[] = array(
				'key'    => $key,
				'value'  => in_array( $format, array( 'json', 'yaml' ), true ) ? $value : (string) wp_json_encode( $value ),
				'source' => Settings::source( $key ),
			);
		}
		WP_CLI\Utils\format_items( $format, $rows, array( 'key', 'value', 'source' ) );
	}

	/**
	 * Builds the settings, the config and the texts of every installed language, and reports every problem in them.
	 */
	public function validate(): void {
		$report = Validation::run();
		foreach ( $report['notes'] as $note ) {
			WP_CLI::log( $note );
		}
		if ( '' !== $report['privacy'] ) {
			WP_CLI::warning( Validation::privacy_warning() );
			WP_CLI::log( $report['privacy'] );
		}
		foreach ( $report['problems'] as $problem ) {
			WP_CLI::warning( $problem );
		}
		if ( array() !== $report['problems'] ) {
			WP_CLI::error( sprintf( '%d problem(s) found.', count( $report['problems'] ) ) );
		}
		WP_CLI::success( sprintf( 'The config is valid: %d services.', $report['services'] ) );
		WP_CLI::log( Plugin::license() . ' ' . SILVERTRAIL_LICENSE_URL );
	}

	/**
	 * Writes the banner's custom styles with the AI provider set up under Settings › Connectors, and prints them. Needs WordPress 7.0.
	 *
	 * ## OPTIONS
	 *
	 * <want>
	 * : The look you want.
	 *
	 * ## EXAMPLES
	 *
	 *     wp silvertrail write-styles "A dark bar with rounded buttons" > wp-content/themes/my-theme/silvertrail/silvertrail.css
	 *
	 * @subcommand write-styles
	 *
	 * @param list<string> $args
	 */
	public function write_styles( array $args ): void {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			WP_CLI::error( 'Writing styles with AI needs WordPress 7.0 or later.' );
		}
		$result = Abilities::write_styles( array( 'want' => $args[0] ) );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			return;
		}
		WP_CLI::line( $result['css'] );
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
