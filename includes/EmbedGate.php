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
		$size = null;
		while ( $tags->next_tag( array( 'tag_name' => 'IFRAME' ) ) ) {
			$size ??= self::size( $tags );
			$tags->set_attribute( self::MARK, true );
		}

		$placeholder = self::sized(
			Templates::capture(
				'embed',
				array(
					'service' => $service,
					'name'    => $config['services'][ $service ]['name'],
					'texts'   => Texts::all(),
					'theme'   => Frontend::appearance(),
					'actions' => self::actions( $service ),
				)
			),
			(string) $size
		);
		$end         = strripos( $placeholder, '</template>' );
		return false === $end ? $placeholder : substr_replace( $placeholder, $tags->get_updated_html(), $end, 0 );
	}

	/**
	 * The iframe's size as custom properties. Its inline style beats its attributes, as in CSS.
	 */
	private static function size( WP_HTML_Tag_Processor $tags ): string {
		$width  = self::attribute( $tags->get_attribute( 'width' ), true );
		$height = self::attribute( $tags->get_attribute( 'height' ), false );
		$ratio  = '';
		$style  = $tags->get_attribute( 'style' );
		foreach ( is_string( $style ) ? explode( ';', $style ) : array() as $declaration ) {
			$pair = explode( ':', $declaration, 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			$property = strtolower( trim( $pair[0] ) );
			$value    = strtolower( trim( $pair[1] ) );
			if ( 'width' === $property ) {
				$width = self::length( $value, true );
			} elseif ( 'height' === $property ) {
				$height = self::length( $value, false );
			} elseif ( 'aspect-ratio' === $property ) {
				$ratio = 1 === preg_match( '#^\d+(\.\d+)?(\s*/\s*\d+(\.\d+)?)?$#', $value ) ? $value : '';
			}
		}

		$properties = '' === $width ? array() : array( "--silvertrail-embed-width:{$width}" );
		if ( '' !== $height ) {
			$properties[] = "--silvertrail-embed-height:{$height}";
			$properties[] = '--silvertrail-embed-ratio:auto';
		} elseif ( '' !== $ratio ) {
			$properties[] = "--silvertrail-embed-ratio:{$ratio}";
		}
		return implode( ';', $properties );
	}

	private static function attribute( mixed $value, bool $percent ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\s*(\d+(?:\.\d+)?)(%?)/', $value, $match ) || (float) $match[1] <= 0 ) {
			return '';
		}
		if ( '%' === $match[2] ) {
			return $percent ? "{$match[1]}%" : '';
		}
		return "{$match[1]}px";
	}

	private static function length( string $value, bool $percent ): string {
		if ( 1 !== preg_match( '/^(\d+(?:\.\d+)?)(px|%|r?em|vw|vh|svh|dvh|lvh)$/', $value, $match ) || (float) $match[1] <= 0 ) {
			return '';
		}
		return '%' !== $match[2] || $percent ? $value : '';
	}

	private static function sized( string $placeholder, string $size ): string {
		if ( '' === $size ) {
			return $placeholder;
		}
		$tags = new WP_HTML_Tag_Processor( $placeholder );
		while ( $tags->next_tag() ) {
			if ( null === $tags->get_attribute( 'data-silvertrail-embed' ) ) {
				continue;
			}
			$style = $tags->get_attribute( 'style' );
			$style = is_string( $style ) ? rtrim( trim( $style ), ';' ) : '';
			$tags->set_attribute( 'style', '' === $style ? $size : "{$style};{$size}" );
			break;
		}
		return $tags->get_updated_html();
	}

	/**
	 * @return list<array{label: string, grant: list<string>}>
	 */
	private static function actions( string $service ): array {
		$actions = array();
		foreach ( (array) apply_filters( 'silvertrail_embed_actions', array(), $service ) as $action ) {
			if ( ! is_array( $action ) || ! is_string( $action['label'] ?? null ) || '' === trim( $action['label'] ) ) {
				continue;
			}
			if ( isset( $action['grant'] ) ) {
				$grant = self::services( (array) $action['grant'] );
				if ( array() !== $grant ) {
					$actions[] = array(
						'label' => $action['label'],
						'grant' => $grant,
					);
				}
			} elseif ( ! empty( $action['open'] ) ) {
				$actions[] = array(
					'label' => $action['label'],
					'grant' => array(),
				);
			}
		}
		return $actions;
	}

	/**
	 * A group ID stands for all its services.
	 *
	 * @param array<mixed> $ids
	 * @return list<string>
	 */
	private static function services( array $ids ): array {
		$services = Config::get()['services'];
		$found    = array();
		foreach ( $ids as $id ) {
			if ( ! is_string( $id ) ) {
				continue;
			}
			if ( isset( $services[ $id ] ) ) {
				$found[] = $id;
				continue;
			}
			foreach ( $services as $service_id => $service ) {
				if ( $service['group'] === $id ) {
					$found[] = $service_id;
				}
			}
		}
		return array_values( array_diff( array_unique( $found ), Config::essential_ids() ) );
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
