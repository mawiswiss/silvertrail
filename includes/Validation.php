<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the settings, the config and the texts of every installed language, and collects every problem in them.
 *
 * @phpstan-type Report array{services: int, problems: list<string>, notes: list<string>, privacy: string}
 */
final class Validation {

	/**
	 * @return Report
	 */
	public static function run(): array {
		$problems = array();
		$notes    = array();
		$collect  = static function ( string $function_name, string $message ) use ( &$problems ): void {
			$problems[] = html_entity_decode( wp_strip_all_tags( $message ), ENT_QUOTES );
		};
		add_action( 'doing_it_wrong_run', $collect, 10, 2 );
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		Settings::reset();
		Settings::all();
		Config::rebuild();
		$count = count( Config::get()['services'] );
		Texts::reset();
		foreach ( array_unique( array_merge( array( 'en_US', get_locale() ), get_available_languages() ) ) as $locale ) {
			$translation = Texts::translation( $locale );
			if ( null !== $translation ) {
				$notes[] = sprintf( 'Texts for %s come from %s.', $locale, $translation['from'] );
			}
			Texts::base( $locale );
		}
		remove_action( 'doing_it_wrong_run', $collect );
		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		foreach ( WebMcp::tokens() as $token ) {
			if ( '' !== $token['problem'] ) {
				$problems[] = $token['problem'];
			} else {
				$notes[] = sprintf( 'WebMCP token for %s on %s, valid until %s.', $token['feature'], $token['origin'], wp_date( 'Y-m-d', $token['expiry'] ) );
			}
		}

		return array(
			'services' => $count,
			'problems' => $problems,
			'notes'    => $notes,
			'privacy'  => Plugin::privacy_pending() ? trim( html_entity_decode( wp_strip_all_tags( str_replace( '</p>', "</p>\n", Plugin::privacy_suggestion() ) ), ENT_QUOTES ) ) : '',
		);
	}

	public static function privacy_warning(): string {
		return 'Silvertrail suggests this text for the privacy policy, and the Policy Guide has not shown it yet: ' . admin_url( 'options-privacy.php?tab=policyguide' );
	}
}
