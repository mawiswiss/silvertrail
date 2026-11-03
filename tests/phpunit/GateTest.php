<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Config;
use Silvertrail\EmbedGate;
use Silvertrail\Frontend;
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

	public function test_content_iframes_are_wrapped_once(): void {
		$wrapped = EmbedGate::content( '<p>Hi</p><iframe src="https://video.example.test/2"></iframe><iframe src="https://elsewhere.test/3"></iframe>' );
		$this->assertIsString( $wrapped );

		$this->assertSame( 1, substr_count( $wrapped, 'data-silvertrail-embed=' ) );
		$this->assertStringContainsString( '<iframe src="https://elsewhere.test/3"></iframe>', $wrapped );
		$this->assertSame( $wrapped, EmbedGate::content( $wrapped ) );
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
		$this->assertSame( array( null, null ), $this->script( $html, 'tracker-js-extra' ) );
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
