<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

final class Frontend {

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_footer', array( self::class, 'render' ) );
	}

	public static function enqueue(): void {
		$base = plugins_url( 'assets/', SILVERTRAIL_FILE );

		wp_enqueue_script(
			'silvertrail',
			$base . 'silvertrail.js',
			array(),
			self::version( 'silvertrail.js' ),
			array(
				'strategy'  => 'defer',
				'in_footer' => false,
			)
		);
		wp_add_inline_script( 'silvertrail', 'window.silvertrailConfig = ' . wp_json_encode( self::script_data(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );

		if ( Settings::get( 'load_css' ) ) {
			wp_enqueue_style( 'silvertrail', $base . 'silvertrail.css', array(), self::version( 'silvertrail.css' ) );
			$colors = self::color_css();
			if ( '' !== $colors ) {
				wp_add_inline_style( 'silvertrail', $colors );
			}
		}
	}

	public static function render(): void {
		$config = Config::get();
		$groups = array();
		foreach ( $config['groups'] as $id => $group ) {
			$services = array();
			foreach ( $config['services'] as $service_id => $service ) {
				if ( $service['group'] === $id ) {
					$services[ $service_id ] = $service + array(
						'locked' => $group['locked'] || 'exempt' === $service['consent'],
					);
				}
			}
			if ( array() === $services ) {
				continue;
			}
			$groups[ $id ] = $group + array(
				'services' => $services,
				'locked'   => ! in_array( false, array_column( $services, 'locked' ), true ),
			);
		}

		$imprint = (int) Settings::get( 'imprint_page' );

		Templates::render(
			'banner',
			array(
				'layout'      => 'box' === Settings::get( 'layout' ) ? 'box' : 'modal',
				'appearance'  => self::appearance(),
				'trail'       => (bool) Settings::get( 'trail' ),
				'groups'      => $groups,
				'texts'       => Texts::all(),
				'privacy_url' => (string) get_privacy_policy_url(),
				'imprint_url' => $imprint ? (string) get_permalink( $imprint ) : '',
			)
		);
	}

	private static function color_css(): string {
		$colors = Settings::get( 'colors' );
		$colors = is_array( $colors ) ? array_map( static fn ( $color ): string => (string) sanitize_hex_color( (string) $color ), $colors ) : array();
		$rules  = array();
		if ( '' !== ( $colors['accent'] ?? '' ) ) {
			$rules[] = '--silvertrail-accent:' . $colors['accent'];
			$rules[] = '--silvertrail-on-accent:' . self::readable_on( $colors['accent'] );
		}
		if ( '' !== ( $colors['background'] ?? '' ) ) {
			$rules[] = '--silvertrail-background:' . $colors['background'];
		}
		if ( '' !== ( $colors['text'] ?? '' ) ) {
			$rules[] = '--silvertrail-text:' . $colors['text'];
			$rules[] = '--silvertrail-muted:color-mix(in srgb,' . $colors['text'] . ' 75%,transparent)';
		}
		return array() === $rules ? '' : '.silvertrail,.silvertrail-embed,.silvertrail-reopen{' . implode( ';', $rules ) . '}';
	}

	private static function readable_on( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$channels  = array_map(
			static function ( string $pair ): float {
				$value = hexdec( $pair ) / 255;
				return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
			},
			str_split( $hex, 2 )
		);
		$luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
		return $luminance > 0.179 ? '#111111' : '#ffffff';
	}

	private static function version( string $asset ): string {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return (string) filemtime( dirname( SILVERTRAIL_FILE ) . '/assets/' . $asset );
		}
		return SILVERTRAIL_VERSION;
	}

	/**
	 * @return array<string, string>
	 */
	private static function trail_texts(): array {
		$texts = Texts::all();
		return array(
			'held'    => $texts['trail_held'],
			'loaded'  => $texts['trail_loaded'],
			'unknown' => $texts['trail_unknown'],
			'none'    => $texts['trail_none'],
		);
	}

	public static function appearance(): string {
		$appearance = Settings::get( 'appearance' );
		return in_array( $appearance, array( 'dark', 'auto' ), true ) ? $appearance : 'light';
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function script_data(): array {
		$services = array();
		foreach ( Config::get()['services'] as $id => $service ) {
			$services[ $id ] = array(
				'name'   => $service['name'],
				'group'  => $service['group'],
				'hosts'  => $service['hosts'],
				'google' => $service['google'],
			);
		}

		return array(
			'n'           => Config::revision(),
			'cookie'      => Config::cookie(),
			'path'        => Config::cookie_path(),
			'maxAge'      => max( 1, (int) Settings::get( 'expiry_months' ) ) * MONTH_IN_SECONDS,
			'essential'   => Config::essential_ids(),
			'ungated'     => array_values( array_filter( array_keys( Config::get()['services'] ), array( Config::class, 'ungated' ) ) ),
			'services'    => $services,
			'needsBanner' => Config::needs_banner(),
			'google'      => (object) ConsentMode::map(),
			'consentApi'  => ConsentApi::map(),
			'gpc'         => (bool) Settings::get( 'gpc' ),
			'require'     => (bool) Settings::get( 'require_choice' ),
			'check'       => LeakCheck::active(),
			'record'      => Records::listening() ? rest_url( 'silvertrail/v1/record' ) : '',
			'recordIds'   => Records::enabled(),
			'texts'       => self::trail_texts(),
		);
	}
}
