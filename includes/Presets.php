<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

/**
 * @phpstan-import-type Service from Config
 * @phpstan-type Preset array{
 *     service: Service,
 *     tag_id?: array{label: string, pattern: string, example: string},
 *     on_by_default?: bool
 * }
 */
final class Presets {

	private const GOOGLE = 'Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Ireland';

	/**
	 * @return array<string, Preset>
	 */
	public static function all(): array {
		return array(
			'google-tag-manager' => array(
				'service' => self::service(
					'marketing',
					'Google Tag Manager',
					self::GOOGLE,
					__( 'Loads the tags configured in this site’s Google Tag Manager container.', 'silvertrail' ),
					'https://business.safety.google/privacy/',
					array( 'googletagmanager.com/gtm.js', 'googletagmanager.com/ns.html' ),
					array( 'ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage' )
				),
				'tag_id'  => array(
					'label'   => __( 'Container ID', 'silvertrail' ),
					'pattern' => '^GTM-[A-Z0-9]+$',
					'example' => 'GTM-XXXXXXX',
				),
			),
			'google-analytics'   => array(
				'service' => self::service(
					'statistics',
					'Google Analytics',
					self::GOOGLE,
					__( 'Measures how visitors use this site.', 'silvertrail' ),
					'https://business.safety.google/privacy/',
					array( 'google-analytics.com', 'googletagmanager.com/gtag' ),
					array( 'analytics_storage' )
				),
				'tag_id'  => array(
					'label'   => __( 'Measurement ID', 'silvertrail' ),
					'pattern' => '^G-[A-Z0-9]+$',
					'example' => 'G-XXXXXXXXXX',
				),
			),
			'google-ads'         => array(
				'service' => self::service(
					'marketing',
					'Google Ads',
					self::GOOGLE,
					__( 'Measures ad conversions and builds audiences for advertising.', 'silvertrail' ),
					'https://business.safety.google/privacy/',
					array( 'googleadservices.com', 'doubleclick.net', 'googlesyndication.com' ),
					array( 'ad_storage', 'ad_user_data', 'ad_personalization' )
				),
				'tag_id'  => array(
					'label'   => __( 'Conversion ID', 'silvertrail' ),
					'pattern' => '^AW-[0-9]+$',
					'example' => 'AW-123456789',
				),
			),
			'linkedin-insight'   => array(
				'service' => self::service(
					'marketing',
					'LinkedIn Insight Tag',
					'LinkedIn Ireland Unlimited Company, Wilton Place, Dublin 2, Ireland',
					__( 'Measures conversions and builds audiences for LinkedIn ads.', 'silvertrail' ),
					'https://www.linkedin.com/legal/privacy-policy',
					array( 'snap.licdn.com', 'px.ads.linkedin.com', 'dc.ads.linkedin.com' )
				),
				'tag_id'  => array(
					'label'   => __( 'Partner ID', 'silvertrail' ),
					'pattern' => '^[0-9]+$',
					'example' => '123456',
				),
			),
			'meta-pixel'         => array(
				'service' => self::service(
					'marketing',
					'Meta Pixel',
					'Meta Platforms Ireland Limited, Merrion Road, Dublin 4, D04 X2K5, Ireland',
					__( 'Measures conversions and builds audiences for Facebook and Instagram ads.', 'silvertrail' ),
					'https://www.facebook.com/privacy/policy/',
					array( 'connect.facebook.net', 'facebook.com/tr' )
				),
				'tag_id'  => array(
					'label'   => __( 'Pixel ID', 'silvertrail' ),
					'pattern' => '^[0-9]+$',
					'example' => '1234567890',
				),
			),
			'youtube'            => array(
				'service'       => self::service(
					'external-media',
					'YouTube',
					self::GOOGLE,
					__( 'Shows embedded YouTube videos.', 'silvertrail' ),
					'https://policies.google.com/privacy',
					array( 'youtube.com', 'youtube-nocookie.com', 'youtu.be', 'ytimg.com' )
				),
				'on_by_default' => true,
			),
			'vimeo'              => array(
				'service'       => self::service(
					'external-media',
					'Vimeo',
					'Vimeo.com, Inc., 330 West 34th Street, 5th Floor, New York, NY 10001, USA',
					__( 'Shows embedded Vimeo videos.', 'silvertrail' ),
					'https://vimeo.com/privacy',
					array( 'vimeo.com', 'vimeocdn.com' )
				),
				'on_by_default' => true,
			),
			'google-maps'        => array(
				'service'       => self::service(
					'external-media',
					'Google Maps',
					self::GOOGLE,
					__( 'Shows embedded maps.', 'silvertrail' ),
					'https://policies.google.com/privacy',
					array( 'maps.google.com', 'google.com/maps' )
				),
				'on_by_default' => true,
			),
		);
	}

	/**
	 * @return array<string, Service>
	 */
	public static function enabled(): array {
		$settings = Settings::stored()['presets'];
		$services = array();
		foreach ( self::all() as $id => $preset ) {
			if ( ! self::switched_on( $id ) ) {
				continue;
			}
			$service           = $preset['service'];
			$service['tag_id'] = (string) ( $settings[ $id ]['tag_id'] ?? '' );
			$services[ $id ]   = $service;
		}
		return $services;
	}

	/**
	 * The admin's choice, or the preset's default until the Services tab is saved.
	 */
	public static function switched_on( string $id ): bool {
		$enabled = Settings::stored()['presets'][ $id ]['enabled'] ?? null;
		return null === $enabled ? ! empty( self::all()[ $id ]['on_by_default'] ) : (bool) $enabled;
	}

	/**
	 * @param list<string> $hosts
	 * @param list<string> $google
	 * @return Service
	 */
	private static function service( string $group, string $name, string $provider, string $purpose, string $privacy_url, array $hosts, array $google = array() ): array {
		return array(
			'group'       => $group,
			'name'        => $name,
			'provider'    => $provider,
			'purpose'     => $purpose,
			'privacy_url' => $privacy_url,
			'cookies'     => '',
			'expiry'      => '',
			'hosts'       => $hosts,
			'handles'     => array(),
			'google'      => $google,
			'consent'     => 'required',
			'tag_id'      => '',
		);
	}
}
