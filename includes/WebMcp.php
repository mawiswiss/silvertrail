<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

defined( 'ABSPATH' ) || exit;

/**
 * WebMCP origin trial tokens. The tools themselves register in assets/silvertrail.js.
 *
 * @phpstan-type TokenInfo array{token: string, origin: string, feature: string, expiry: int, problem: string}
 */
final class WebMcp {

	public const WARN_DAYS = 14;

	public static function register(): void {
		add_action( 'wp_head', array( self::class, 'head' ), 1 );
	}

	public static function head(): void {
		if ( ! Settings::get( 'webmcp' ) ) {
			return;
		}
		foreach ( self::tokens() as $token ) {
			if ( 0 === $token['expiry'] || $token['expiry'] > time() ) {
				printf( '<meta http-equiv="origin-trial" content="%s">' . "\n", esc_attr( $token['token'] ) );
			}
		}
	}

	/**
	 * @return list<TokenInfo>
	 */
	public static function tokens(): array {
		$tokens = array();
		foreach ( (array) Settings::get( 'webmcp_tokens' ) as $token ) {
			$read     = self::read( (string) $token );
			$tokens[] = array(
				'token'   => (string) $token,
				'origin'  => $read['origin'] ?? '',
				'feature' => $read['feature'] ?? '',
				'expiry'  => $read['expiry'] ?? 0,
				'problem' => self::problem( $read ),
			);
		}
		return $tokens;
	}

	/**
	 * Reads the payload without checking the signature, which only the browser can.
	 *
	 * @return array{origin: string, feature: string, expiry: int, subdomains: bool}|null
	 */
	public static function read( string $token ): ?array {
		$raw = base64_decode( $token, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- origin trial tokens are base64.
		if ( false === $raw || strlen( $raw ) < 69 ) {
			return null;
		}
		$length = unpack( 'N', substr( $raw, 65, 4 ) );
		if ( false === $length || strlen( $raw ) < 69 + $length[1] ) {
			return null;
		}
		$payload = json_decode( substr( $raw, 69, $length[1] ), true );
		if ( ! is_array( $payload ) || ! is_string( $payload['origin'] ?? null ) || ! is_string( $payload['feature'] ?? null ) || ! is_int( $payload['expiry'] ?? null ) ) {
			return null;
		}
		return array(
			'origin'     => $payload['origin'],
			'feature'    => $payload['feature'],
			'expiry'     => $payload['expiry'],
			'subdomains' => ! empty( $payload['isSubdomain'] ),
		);
	}

	/**
	 * @param array{origin: string, feature: string, expiry: int, subdomains: bool}|null $read
	 */
	private static function problem( ?array $read ): string {
		if ( null === $read ) {
			return __( 'Silvertrail cannot read a WebMCP trial token, so it cannot tell when the token expires.', 'silvertrail' );
		}
		if ( $read['expiry'] <= time() ) {
			/* translators: 1: origin of the token, 2: date */
			return sprintf( __( 'The WebMCP trial token for %1$s expired on %2$s. Renew it or remove it.', 'silvertrail' ), $read['origin'], wp_date( 'Y-m-d', $read['expiry'] ) );
		}
		if ( ! self::fits( $read['origin'], $read['subdomains'] ) ) {
			/* translators: 1: origin of the token, 2: origin of this site */
			return sprintf( __( 'The WebMCP trial token is for %1$s, not for this site at %2$s.', 'silvertrail' ), $read['origin'], self::origin( home_url() ) );
		}
		return '';
	}

	private static function fits( string $origin, bool $subdomains ): bool {
		$token = wp_parse_url( self::origin( $origin ) );
		$site  = wp_parse_url( self::origin( home_url() ) );
		if ( ! is_array( $token ) || ! is_array( $site ) || ( $token['scheme'] ?? '' ) !== ( $site['scheme'] ?? '' ) || ( $token['port'] ?? 0 ) !== ( $site['port'] ?? 0 ) ) {
			return false;
		}
		$host = $token['host'] ?? '';
		$here = $site['host'] ?? '';
		return $host === $here || ( $subdomains && str_ends_with( $here, '.' . $host ) );
	}

	/**
	 * Scheme, host and port, so https://a.test and https://a.test:443 compare equal.
	 */
	private static function origin( string $url ): string {
		$parts  = wp_parse_url( $url );
		$scheme = is_array( $parts ) ? strtolower( $parts['scheme'] ?? '' ) : '';
		$host   = is_array( $parts ) ? strtolower( $parts['host'] ?? '' ) : '';
		$port   = is_array( $parts ) && isset( $parts['port'] ) ? (int) $parts['port'] : ( 'http' === $scheme ? 80 : 443 );
		return "{$scheme}://{$host}:{$port}";
	}
}
