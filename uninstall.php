<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'silvertrail' );
delete_option( 'silvertrail_version' );
