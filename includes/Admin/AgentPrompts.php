<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail\Admin;

use Silvertrail\Config;
use Silvertrail\Plugin;
use Silvertrail\Settings;

defined( 'ABSPATH' ) || exit;

final class AgentPrompts {

	public static function check(): string {
		return self::brief( '## Brief: check the site' ) . "\n\n" . self::site() . "\n\n## Pages to check\n\n- " . self::address( home_url( '/' ) ) . "\n";
	}

	public static function setup(): string {
		return self::brief( '## Brief: set up the site' ) . "\n\n" . self::site( true ) . "\n\n## What the site needs\n\n";
	}

	private static function site( bool $pages = false ): string {
		$config    = Config::get();
		$essential = Config::essential_ids();
		$lines     = array(
			'## This site',
			'',
			'- Address: ' . home_url( '/' ),
			'- Plugin folder: `' . str_replace( ABSPATH, '', dirname( SILVERTRAIL_FILE ) ) . '`, version ' . SILVERTRAIL_VERSION,
		);
		if ( ! Settings::get( 'enabled' ) ) {
			$lines[] = '- Silvertrail is off. Add `?' . Plugin::PREVIEW . '` to an address to see the page as visitors would get it with Silvertrail on.';
		}
		$lines[] = '- Global Privacy Control counts as "only essential": ' . ( Settings::get( 'gpc' ) ? 'yes' : 'no' );
		$lines[] = '- WebMCP tools: ' . ( Settings::get( 'webmcp' ) ? 'on, in browsers that support WebMCP' : 'off, so use `window.silvertrail`' );
		if ( $pages ) {
			$lines[] = '- Privacy page: ' . self::page( (int) get_option( 'wp_page_for_privacy_policy' ) );
			$lines[] = '- Imprint page: ' . self::page( (int) Settings::get( 'imprint_page' ) );
		}
		$lines[] = '- Services:';
		foreach ( $config['services'] as $id => $service ) {
			$facts = array( in_array( $id, $essential, true ) ? 'always runs' : 'needs consent' );
			if ( array() !== $service['hosts'] ) {
				$facts[] = 'hosts: ' . implode( ', ', $service['hosts'] );
			}
			if ( array() !== $service['handles'] ) {
				$facts[] = 'script handles: ' . implode( ', ', $service['handles'] );
			}
			$lines[] = "  - `{$id}` {$service['name']} ({$service['group']}): " . implode( '; ', $facts );
		}
		return implode( "\n", $lines );
	}

	private static function page( int $id ): string {
		$status = $id ? get_post_status( $id ) : false;
		if ( false === $status ) {
			return 'none';
		}
		$page = "`{$id}` " . get_the_title( $id );
		return 'publish' === $status ? "{$page}, published and linked under the banner" : "{$page}, {$status}, not linked under the banner until it is published";
	}

	private static function address( string $url ): string {
		return Settings::get( 'enabled' ) ? $url : add_query_arg( Plugin::PREVIEW, '1', $url );
	}

	private static function brief( string $heading ): string {
		$text  = (string) file_get_contents( dirname( SILVERTRAIL_FILE ) . '/docs/agents.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$start = strpos( $text, $heading );
		if ( false === $start ) {
			return '';
		}
		$end = strpos( $text, "\n## ", $start + strlen( $heading ) );
		return trim( false === $end ? substr( $text, $start ) : substr( $text, $start, $end - $start ) );
	}
}
