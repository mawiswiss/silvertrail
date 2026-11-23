<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Frontend;
use Silvertrail\Settings;
use WP_UnitTestCase;

final class FrontendTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	private static function banner(): string {
		ob_start();
		Frontend::render();
		return (string) ob_get_clean();
	}

	public function test_the_banner_links_the_imprint_only_once_it_is_published(): void {
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Imprint',
			)
		);
		update_option( Settings::OPTION, array( 'imprint_page' => $page ) );

		$this->assertStringNotContainsString( '>Imprint</a>', self::banner() );

		wp_publish_post( $page );
		$this->assertStringContainsString( 'href="' . esc_url( (string) get_permalink( $page ) ) . '">Imprint</a>', self::banner() );
	}

	public function test_the_banner_links_no_imprint_when_none_is_set(): void {
		// A published page as the global post, as on any singular view.
		$GLOBALS['post'] = get_post( self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		update_option( Settings::OPTION, array( 'imprint_page' => 0 ) );

		$this->assertStringNotContainsString( '>Imprint</a>', self::banner() );
	}
}
