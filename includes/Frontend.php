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
				'groups'      => $groups,
				'texts'       => Texts::all(),
				'privacy_url' => (string) get_privacy_policy_url(),
				'imprint_url' => $imprint ? (string) get_permalink( $imprint ) : '',
			)
		);
	}

	private static function version( string $asset ): string {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return (string) filemtime( dirname( SILVERTRAIL_FILE ) . '/assets/' . $asset );
		}
		return SILVERTRAIL_VERSION;
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
			'services'    => $services,
			'needsBanner' => Config::needs_banner(),
			'gpc'         => (bool) Settings::get( 'gpc' ),
		);
	}
}
