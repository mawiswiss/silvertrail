<?php

declare(strict_types=1);

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// wp-env's own WP_TESTS_DIR config shares the e2e site's tables, and the installer would drop them.
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

$silvertrail_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $silvertrail_tests_dir || ! is_file( $silvertrail_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library not found. Run composer install, then: npm run test:php\n" );
	exit( 1 );
}

require $silvertrail_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__, 2 ) . '/silvertrail.php';
	}
);

require $silvertrail_tests_dir . '/includes/bootstrap.php';
