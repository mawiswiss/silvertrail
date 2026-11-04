<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

/**
 * Renders the script handles a service lists as inert tags.
 */
final class ScriptGate {

	public static function register(): void {
		add_filter( 'wp_script_attributes', array( self::class, 'script' ) );
		add_filter( 'wp_inline_script_attributes', array( self::class, 'inline' ) );
	}

	/**
	 * @param array<string, string|bool> $attributes
	 * @return array<string, string|bool>
	 */
	public static function script( array $attributes ): array {
		$id = is_string( $attributes['id'] ?? null ) ? $attributes['id'] : '';
		return self::gate( $attributes, (string) preg_replace( '/-js$/', '', $id ) );
	}

	/**
	 * Gates the before and after code of a handle, not its localized data.
	 *
	 * @param array<string, string|bool> $attributes
	 * @return array<string, string|bool>
	 */
	public static function inline( array $attributes ): array {
		$id = is_string( $attributes['id'] ?? null ) ? $attributes['id'] : '';
		if ( ! preg_match( '/^(.+)-js-(before|after)$/', $id, $match ) ) {
			return $attributes;
		}
		return self::gate( $attributes, $match[1] );
	}

	/**
	 * @param array<string, string|bool> $attributes
	 * @return array<string, string|bool>
	 */
	private static function gate( array $attributes, string $handle ): array {
		$service = self::service_for_handle( $handle );
		if ( null === $service ) {
			return $attributes;
		}
		if ( isset( $attributes['type'] ) && is_string( $attributes['type'] ) && 'text/javascript' !== $attributes['type'] ) {
			$attributes['data-silvertrail-type'] = $attributes['type'];
		}
		$attributes['type']             = 'text/plain';
		$attributes['data-silvertrail'] = $service;
		return $attributes;
	}

	private static function service_for_handle( string $handle ): ?string {
		if ( '' === $handle ) {
			return null;
		}
		$essential = Config::essential_ids();
		foreach ( Config::get()['services'] as $id => $service ) {
			if ( in_array( $handle, $service['handles'], true ) && ! in_array( $id, $essential, true ) && ! Config::ungated( $id ) ) {
				return $id;
			}
		}
		return null;
	}
}
