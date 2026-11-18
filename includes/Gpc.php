<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

defined( 'ABSPATH' ) || exit;

/**
 * Global Privacy Control on the server. The browser side lives in silvertrail.js.
 */
final class Gpc {

	public const PATH   = '/.well-known/gpc.json';
	public const OPTION = 'silvertrail_gpc_since';

	public static function register(): void {
		add_action( 'parse_request', array( self::class, 'serve' ) );
	}

	public static function header(): bool {
		return Settings::get( 'gpc' ) && isset( $_SERVER['HTTP_SEC_GPC'] ) && '1' === $_SERVER['HTTP_SEC_GPC'];
	}

	public static function serve(): void {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( self::PATH !== wp_parse_url( $uri, PHP_URL_PATH ) || ! Settings::get( 'gpc' ) ) {
			return;
		}
		wp_send_json( self::resource(), 200 );
	}

	/**
	 * @return array{gpc: true, lastUpdate: string}
	 */
	public static function resource(): array {
		$since = get_option( self::OPTION );
		if ( ! is_string( $since ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $since ) ) {
			$since = gmdate( 'Y-m-d' );
			update_option( self::OPTION, $since, false );
		}
		return array(
			'gpc'        => true,
			'lastUpdate' => $since,
		);
	}

	public static function privacy_text(): string {
		if ( ! Settings::get( 'enabled' ) || ! Settings::get( 'gpc' ) ) {
			return '';
		}
		return '<p>' . __( 'If your browser sends Global Privacy Control, we treat it as a refusal of every service that needs consent and load only essential ones, even if you allowed more before. You can still allow more in the privacy settings, and we keep that choice.', 'silvertrail' ) . '</p>';
	}
}
