<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

use WP_HTML_Tag_Processor;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps embeds from known hosts in a placeholder. The embed waits inert in a template.
 */
final class EmbedGate {

	private const MARK = 'data-silvertrail-wrapped';

	public static function register(): void {
		add_filter( 'embed_oembed_html', array( self::class, 'oembed' ), 20, 2 );
		add_filter( 'the_content', array( self::class, 'content' ), 999 );
		// Templates, template parts, patterns and block widgets render outside the_content.
		add_filter( 'render_block_core/html', array( self::class, 'content' ), 20 );
		add_filter( 'widget_text_content', array( self::class, 'content' ), 999 );
		add_filter( 'widget_custom_html_content', array( self::class, 'content' ), 999 );
	}

	public static function oembed( mixed $html, mixed $url ): mixed {
		if ( ! is_string( $html ) || ! is_string( $url ) ) {
			return $html;
		}
		return self::wrap( $html, Config::service_for_url( $url ) ?? self::service_in( $html ) );
	}

	public static function content( mixed $content ): mixed {
		if ( ! is_string( $content ) || false === stripos( $content, '<iframe' ) ) {
			return $content;
		}
		return preg_replace_callback(
			'#<iframe\b[^>]*>.*?</iframe>#is',
			static function ( array $found ): string {
				if ( str_contains( $found[0], self::MARK ) ) {
					return $found[0];
				}
				return self::wrap( $found[0], self::service_in( $found[0] ) );
			},
			$content
		) ?? $content;
	}

	public static function wrap( string $html, ?string $service ): string {
		$config = Config::get();
		if ( null === $service || ! isset( $config['services'][ $service ] ) || in_array( $service, Config::essential_ids(), true ) ) {
			return $html;
		}

		$tags = new WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( array( 'tag_name' => 'IFRAME' ) ) ) {
			$tags->set_attribute( self::MARK, true );
		}

		$placeholder = Templates::capture(
			'embed',
			array(
				'service' => $service,
				'name'    => $config['services'][ $service ]['name'],
				'texts'   => Texts::all(),
				'theme'   => Frontend::appearance(),
			)
		);
		$end         = strripos( $placeholder, '</template>' );
		return false === $end ? $placeholder : substr_replace( $placeholder, $tags->get_updated_html(), $end, 0 );
	}

	private static function service_in( string $html ): ?string {
		$tags = new WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( array( 'tag_name' => 'IFRAME' ) ) ) {
			$src = $tags->get_attribute( 'src' );
			if ( is_string( $src ) ) {
				$service = Config::service_for_url( $src );
				if ( null !== $service ) {
					return $service;
				}
			}
		}
		return null;
	}
}
