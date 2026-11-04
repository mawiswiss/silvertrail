<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

final class Texts {

	/**
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return array(
			'title'             => __( 'Privacy settings', 'silvertrail' ),
			'intro'             => __( 'We use cookies and similar technologies. Some are needed for this site to work. Everything else only runs if you agree.', 'silvertrail' ),
			'accept_all'        => __( 'Accept all', 'silvertrail' ),
			'reject_all'        => __( 'Only essential', 'silvertrail' ),
			'preferences'       => __( 'Choose', 'silvertrail' ),
			'preferences_intro' => __( 'Choose what may run on this site. You can change it at any time.', 'silvertrail' ),
			'save'              => __( 'Save choices', 'silvertrail' ),
			'close'             => __( 'Close', 'silvertrail' ),
			'provider'          => __( 'Provider', 'silvertrail' ),
			'purpose'           => __( 'Purpose', 'silvertrail' ),
			'cookies'           => __( 'Cookies', 'silvertrail' ),
			'expiry'            => __( 'Lifetime', 'silvertrail' ),
			'privacy_url'       => __( 'Privacy policy', 'silvertrail' ),
			'privacy_link'      => __( 'Privacy policy', 'silvertrail' ),
			'imprint_link'      => __( 'Imprint', 'silvertrail' ),
			/* translators: %s: service name */
			'embed_text'        => __( 'This content comes from %s. Loading it tells them about your visit.', 'silvertrail' ),
			'embed_load'        => __( 'Load content', 'silvertrail' ),
			/* translators: %s: service name */
			'embed_remember'    => __( 'Always allow %s', 'silvertrail' ),
			'reopen'            => __( 'Privacy settings', 'silvertrail' ),
			'gpc'               => __( 'Your browser sends Global Privacy Control, so only essential services run. You can still allow more here.', 'silvertrail' ),
			'trail_title'       => __( 'On this page', 'silvertrail' ),
			'trail_held'        => __( 'Held back', 'silvertrail' ),
			'trail_loaded'      => __( 'Loaded from', 'silvertrail' ),
			'trail_unknown'     => __( 'not listed here', 'silvertrail' ),
			'trail_none'        => __( 'Nothing from other sites.', 'silvertrail' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function all(): array {
		$defaults = self::defaults();

		$custom = Settings::get( 'texts' );
		if ( is_array( $custom ) ) {
			foreach ( $custom as $key => $text ) {
				if ( isset( $defaults[ $key ] ) && is_string( $text ) && '' !== trim( $text ) ) {
					$defaults[ $key ] = $text;
				}
			}
		}

		/**
		 * @param array<string, string> $texts
		 */
		$texts = apply_filters( 'silvertrail_texts', $defaults );
		return is_array( $texts ) ? array_map( 'strval', $texts + $defaults ) : $defaults;
	}
}
