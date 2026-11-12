<?php
/**
 * Sample services and a sample front page to try Silvertrail on. Loaded only in the
 * wp-env development site, and seeded once per database, so `npx wp-env clean` brings it back.
 *
 * @package Silvertrail
 */

declare(strict_types=1);

add_action(
	'silvertrail_register',
	static function (): void {
		// A made-up ID, so no Google account is needed. gtag.js still loads.
		silvertrail_register_service( 'google-analytics', array( 'tag_id' => 'G-SAMPLE0001' ) );

		silvertrail_register_service(
			'confetti',
			array(
				'group'       => 'preferences',
				'name'        => 'Confetti',
				'provider'    => 'jsDelivr',
				'purpose'     => 'Celebrates each page view with confetti.',
				'privacy_url' => 'https://www.jsdelivr.com/terms/privacy-policy-jsdelivr-net',
				'hosts'       => array( 'cdn.jsdelivr.net' ),
				'handles'     => array( 'sample-confetti' ),
			)
		);

		silvertrail_register_service(
			'openstreetmap',
			array(
				'group'       => 'external-media',
				'name'        => 'OpenStreetMap',
				'provider'    => 'OpenStreetMap Foundation',
				'purpose'     => 'Shows embedded maps.',
				'privacy_url' => 'https://osmfoundation.org/wiki/Privacy_Policy',
				'hosts'       => array( 'openstreetmap.org' ),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_script( 'sample-confetti', 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js', array(), null, true );
		wp_add_inline_script( 'sample-confetti', 'confetti({ particleCount: 120, spread: 70, origin: { y: 0.6 } });' );

		// Loads before any choice on purpose, so the Leak check has something to find.
		wp_enqueue_style( 'sample-google-fonts', 'https://fonts.googleapis.com/css2?family=Fraunces&display=swap', array(), null );
		wp_add_inline_style( 'sample-google-fonts', 'h1, h2 { font-family: Fraunces, serif; }' );
	}
);

add_action(
	'init',
	static function (): void {
		if ( get_option( 'silvertrail_samples' ) ) {
			return;
		}

		$page = get_page_by_path( 'silvertrail-samples' );
		if ( null === $page ) {
			// Without a logged-in admin, post kses strips the iframes and scripts.
			kses_remove_filters();
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => 'Silvertrail samples',
					'post_name'    => 'silvertrail-samples',
					'post_content' => wp_slash( (string) file_get_contents( __DIR__ . '/page.html' ) ),
				)
			);
			kses_init();
		} else {
			$id = $page->ID;
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );

		$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $privacy > 0 && 'draft' === get_post_status( $privacy ) ) {
			wp_publish_post( $privacy );
		}

		update_option( 'silvertrail_samples', 1 );
	}
);
