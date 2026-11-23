<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the consent cookie. Only meaningful on uncached requests such as form posts.
 */
final class Consent {

	/**
	 * @return array{n: int, c: list<string>, t: int, g: bool}|null
	 */
	public static function cookie(): ?array {
		$name = Config::cookie();
		if ( ! isset( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) ) {
			return null;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, every field is validated below.
		$data = json_decode( wp_unslash( $_COOKIE[ $name ] ), true );
		if ( ! is_array( $data ) || ! isset( $data['n'], $data['c'] ) || ! is_array( $data['c'] ) ) {
			return null;
		}
		return array(
			'n' => (int) $data['n'],
			'c' => array_values( array_filter( $data['c'], 'is_string' ) ),
			't' => (int) ( $data['t'] ?? 0 ),
			'g' => ! empty( $data['g'] ),
		);
	}

	/**
	 * @return list<string>
	 */
	public static function consented_ids(): array {
		$cookie = self::cookie();
		if ( null === $cookie || $cookie['n'] < Config::revision() || ( ! $cookie['g'] && Gpc::header() ) ) {
			return array();
		}
		return array_values( array_intersect( $cookie['c'], array_keys( Config::get()['services'] ) ) );
	}

	public static function has( string $id ): bool {
		return 'granted' === self::state( $id )['status'];
	}

	/**
	 * @return array{status: 'granted'|'mixed'|'denied', granted: list<string>, denied: list<string>}
	 */
	public static function state( string $id ): array {
		$services = Config::get()['services'];
		$ids      = isset( $services[ $id ] ) ? array( $id ) : array_keys( array_filter( $services, static fn( array $service ): bool => $service['group'] === $id ) );
		$ids      = array() === $ids ? array( $id ) : $ids;
		$granted  = array_values( array_intersect( $ids, array_merge( Config::essential_ids(), self::consented_ids() ) ) );
		$denied   = array_values( array_diff( $ids, $granted ) );
		if ( array() === $denied ) {
			$status = 'granted';
		} elseif ( array() === $granted ) {
			$status = 'denied';
		} else {
			$status = 'mixed';
		}
		return array(
			'status'  => $status,
			'granted' => $granted,
			'denied'  => $denied,
		);
	}
}
