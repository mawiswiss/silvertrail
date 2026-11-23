<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Config;
use Silvertrail\EmbedGate;
use Silvertrail\Frontend;
use WP_HTML_Tag_Processor;
use WP_UnitTestCase;

final class GateTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Config::reset();
		add_action(
			'silvertrail_register',
			static function (): void {
				silvertrail_register_service(
					'video',
					array(
						'group' => 'external-media',
						'hosts' => array( 'video.example.test' ),
					)
				);
				silvertrail_register_service(
					'tracker',
					array(
						'group'   => 'marketing',
						'handles' => array( 'tracker' ),
					)
				);
				silvertrail_register_service(
					'own-stats',
					array(
						'group'   => 'statistics',
						'consent' => 'exempt',
						'handles' => array( 'own-stats' ),
					)
				);
			}
		);
	}

	public function tear_down(): void {
		remove_all_actions( 'silvertrail_register' );
		wp_deregister_script( 'tracker' );
		wp_deregister_script( 'own-stats' );
		wp_deregister_script( 'silvertrail' );
		Config::reset();
		parent::tear_down();
	}

	public function test_an_oembed_from_a_known_host_waits_in_a_template(): void {
		$html = EmbedGate::oembed( '<iframe src="https://video.example.test/1"></iframe>', 'https://video.example.test/watch/1' );

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'data-silvertrail-embed="video"', $html );
		$this->assertMatchesRegularExpression( '#<template>\s*<iframe data-silvertrail-wrapped src="https://video.example.test/1"></iframe>\s*</template>#', $html );
	}

	public function test_an_oembed_with_a_script_waits_unchanged(): void {
		$embed = '<blockquote><p>Post</p></blockquote><script async src="https://video.example.test/widgets.js"></script>';
		$html  = EmbedGate::oembed( $embed, 'https://video.example.test/post/1' );

		$this->assertIsString( $html );
		$this->assertStringContainsString( "<template>{$embed}</template>", $html );
	}

	public function test_content_iframes_are_wrapped_once(): void {
		$wrapped = EmbedGate::content( '<p>Hi</p><iframe src="https://video.example.test/2"></iframe><iframe src="https://elsewhere.test/3"></iframe>' );
		$this->assertIsString( $wrapped );

		$this->assertSame( 1, substr_count( $wrapped, 'data-silvertrail-embed=' ) );
		$this->assertStringContainsString( '<iframe src="https://elsewhere.test/3"></iframe>', $wrapped );
		$this->assertSame( $wrapped, EmbedGate::content( $wrapped ) );
	}

	public function test_the_placeholder_has_no_extra_buttons_by_default(): void {
		$html = EmbedGate::wrap( '<iframe src="https://video.example.test/1"></iframe>', 'video' );

		$this->assertStringNotContainsString( 'silvertrail-embed__actions', $html );
	}

	public function test_the_placeholder_shows_the_buttons_code_adds(): void {
		$seen = '';
		add_filter(
			'silvertrail_embed_actions',
			static function ( array $actions, string $service ) use ( &$seen ): array {
				$seen = $service;
				return array(
					array(
						'label' => 'Allow <all> media',
						'grant' => 'external-media',
					),
					array(
						'label' => 'Settings',
						'open'  => true,
					),
					array(
						'label' => 'Nothing to allow',
						'grant' => array( 'own-stats', 'silvertrail', 'missing' ),
					),
					array( 'grant' => 'video' ),
					array( 'label' => 'Does nothing' ),
					'junk',
				);
			},
			10,
			2
		);
		$html  = EmbedGate::wrap( '<iframe src="https://video.example.test/1"></iframe>', 'video' );
		$media = array_keys( array_filter( silvertrail_config()['services'], static fn( array $service ): bool => 'external-media' === $service['group'] ) );

		$this->assertSame( 'video', $seen );
		$this->assertContains( 'video', $media );
		$this->assertSame( 2, substr_count( $html, 'class="silvertrail-embed__action ' ) );
		$this->assertStringContainsString( 'data-silvertrail-grant="' . implode( ' ', $media ) . '">Allow &lt;all&gt; media</button>', $html );
		$this->assertStringContainsString( 'data-silvertrail-open>Settings</button>', $html );
	}

	/**
	 * @return array<string, array{string, string|null}>
	 */
	public static function sizes(): array {
		return array(
			'pixels'         => array( 'width="560" height="315"', '--silvertrail-embed-width:560px;--silvertrail-embed-height:315px;--silvertrail-embed-ratio:auto' ),
			'full width'     => array( 'width="100%" height="352"', '--silvertrail-embed-width:100%;--silvertrail-embed-height:352px;--silvertrail-embed-ratio:auto' ),
			'inline style'   => array( 'width="560" height="315" style="border:0; WIDTH: 100%; height: auto; aspect-ratio: 4 / 3"', '--silvertrail-embed-width:100%;--silvertrail-embed-ratio:4 / 3' ),
			'no size'        => array( 'title="Video"', null ),
			'percent height' => array( 'height="100%"', null ),
			'junk'           => array( 'width="0" height="calc(1)" style="height: expression(alert(1)); aspect-ratio: 1/1}body{color:red"', null ),
		);
	}

	/**
	 * @dataProvider sizes
	 */
	public function test_the_placeholder_takes_the_size_of_its_iframe( string $attributes, ?string $style ): void {
		$tags = new WP_HTML_Tag_Processor( EmbedGate::wrap( "<iframe src=\"https://video.example.test/1\" {$attributes}></iframe>", 'video' ) );
		$tags->next_tag( array( 'class_name' => 'silvertrail-embed' ) );

		$this->assertSame( $style, $tags->get_attribute( 'style' ) );
	}

	public function test_a_theme_template_keeps_its_own_style_and_gets_the_size(): void {
		global $wp_stylesheet_path, $wp_template_path;
		$theme = array( $wp_stylesheet_path, $wp_template_path );
		$dir   = get_temp_dir() . 'silvertrail-theme-' . wp_generate_password( 6, false );
		wp_mkdir_p( $dir . '/silvertrail' );
		file_put_contents( $dir . '/silvertrail/embed.php', '<section data-silvertrail-embed="video" style="color: red;"><button data-silvertrail-load>Load</button><template></template></section>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$wp_stylesheet_path = $dir; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_template_path   = $dir; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$html = EmbedGate::wrap( '<iframe src="https://video.example.test/1" height="152"></iframe>', 'video' );

		list( $wp_stylesheet_path, $wp_template_path ) = $theme; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_delete_file( $dir . '/silvertrail/embed.php' );
		rmdir( $dir . '/silvertrail' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir

		$this->assertStringContainsString( '<section data-silvertrail-embed="video" style="color: red;--silvertrail-embed-height:152px;--silvertrail-embed-ratio:auto">', $html );
	}

	public function test_iframes_outside_the_content_are_wrapped(): void {
		$iframe = '<iframe src="https://video.example.test/4"></iframe>';

		$this->assertStringContainsString( 'data-silvertrail-embed="video"', do_blocks( "<!-- wp:html -->{$iframe}<!-- /wp:html -->" ) );
		$this->assertStringContainsString( 'data-silvertrail-embed="video"', (string) apply_filters( 'widget_text_content', $iframe ) );
		$this->assertStringContainsString( 'data-silvertrail-embed="video"', (string) apply_filters( 'widget_custom_html_content', $iframe ) );
	}

	public function test_the_inline_config_cannot_open_markup(): void {
		add_action(
			'silvertrail_register',
			static function (): void {
				silvertrail_register_service( 'odd', array( 'group' => 'marketing', 'name' => '<!--<script>' ) );
			}
		);
		Frontend::enqueue();

		$before = implode( '', (array) wp_scripts()->get_data( 'silvertrail', 'before' ) );
		$this->assertStringContainsString( '"odd"', $before );
		$this->assertStringNotContainsString( '<', $before );
	}

	public function test_a_mapped_handle_and_its_after_code_are_inert(): void {
		wp_register_script( 'tracker', 'https://tracker.example.test/t.js', array(), '1', false );
		wp_add_inline_script( 'tracker', 'track();' );
		wp_localize_script( 'tracker', 'trackerSettings', array( 'id' => '1' ) );

		$html = get_echo( 'wp_print_scripts', array( array( 'tracker' ) ) );

		$this->assertSame( array( 'text/plain', 'tracker' ), $this->script( $html, 'tracker-js' ) );
		$this->assertSame( array( 'text/plain', 'tracker' ), $this->script( $html, 'tracker-js-after' ) );
		$extra = $this->script( $html, 'tracker-js-extra' );
		$this->assertNotSame( 'text/plain', $extra[0] );
		$this->assertNull( $extra[1] );
	}

	public function test_an_exempt_handle_runs_normally(): void {
		wp_register_script( 'own-stats', 'https://stats.example.test/s.js', array(), '1', false );

		$html = get_echo( 'wp_print_scripts', array( array( 'own-stats' ) ) );

		$this->assertStringNotContainsString( 'text/plain', $html );
	}

	/**
	 * @return array{0: mixed, 1: mixed} The type and data-silvertrail of the script with this id.
	 */
	private function script( string $html, string $id ): array {
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( array( 'tag_name' => 'SCRIPT' ) ) ) {
			if ( $tags->get_attribute( 'id' ) === $id ) {
				return array( $tags->get_attribute( 'type' ), $tags->get_attribute( 'data-silvertrail' ) );
			}
		}
		$this->fail( "No script with id {$id}." );
	}
}
