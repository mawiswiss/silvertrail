<?php

declare(strict_types=1);

define( 'DB_NAME', getenv( 'WORDPRESS_DB_NAME' ) ?: 'tests-wordpress' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ?: 'password' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ?: 'tests-mysql' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

if ( 'wordpress' === DB_NAME ) {
	fwrite( STDERR, "Refusing to run the tests against the dev database.\n" );
	exit( 1 );
}

// The installer drops every table with this prefix. The e2e site in the same database uses wp_.
$table_prefix = 'wptests_';

define( 'ABSPATH', '/var/www/html/' );
define( 'WP_TESTS_DOMAIN', 'localhost' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Silvertrail Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WP_DEBUG', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'WP_ACCESSIBLE_HOSTS', '' );
