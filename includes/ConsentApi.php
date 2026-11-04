<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

/**
 * Bridge to the WP Consent API, so other plugins can read the visitor's choice.
 */
final class ConsentApi {

	public const CATEGORIES = array( 'functional', 'preferences', 'statistics', 'statistics-anonymous', 'marketing' );

	public static function register(): void {
		add_filter( 'wp_consent_api_registered_' . plugin_basename( SILVERTRAIL_FILE ), '__return_true' );
		add_filter( 'wp_get_consent_type', array( self::class, 'type' ) );
	}

	public static function type(): string {
		return 'optin';
	}

	/**
	 * A category is allowed when the visitor consented to all of its services. A category
	 * without services is allowed only when the visitor accepted everything.
	 *
	 * @return array<string, list<string>> Category => services that need consent.
	 */
	public static function map(): array {
		$config  = Config::get();
		$consent = Config::consent_ids();
		$map     = array(
			'preferences'          => array(),
			'statistics'           => array(),
			'statistics-anonymous' => array(),
			'marketing'            => array(),
		);
		foreach ( $consent as $id ) {
			$category = $config['groups'][ $config['services'][ $id ]['group'] ]['consent_api'];
			if ( isset( $map[ $category ] ) ) {
				$map[ $category ][] = $id;
			}
			if ( 'statistics' === $category ) {
				$map['statistics-anonymous'][] = $id;
			}
		}
		return $map;
	}
}
