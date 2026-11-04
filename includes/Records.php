<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Opt-in records of each decision: a random consent ID, the revision, the choices and
 * the time. No IP address and no browser details.
 */
final class Records {

	public const TABLE   = 'silvertrail_records';
	public const CRON    = 'silvertrail_prune_records';
	public const SOURCES = array( 'banner', 'embed', 'gpc', 'hook' );

	private const MAX_BODY = 4096;

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'route' ) );
		add_action( self::CRON, array( self::class, 'prune' ) );
		add_action( 'admin_post_silvertrail_export', array( self::class, 'download' ) );
		add_action( 'add_option_' . Settings::OPTION, array( self::class, 'sync' ) );
		add_action( 'update_option_' . Settings::OPTION, array( self::class, 'sync' ) );
		add_action( 'admin_init', array( self::class, 'privacy_text' ) );
		add_action( 'admin_init', array( self::class, 'heal' ) );
	}

	public static function enabled(): bool {
		return (bool) Settings::get( 'records' );
	}

	/**
	 * The endpoint exists only when something will use what it receives.
	 */
	public static function listening(): bool {
		return self::enabled() || has_action( 'silvertrail_recorded' );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			'CREATE TABLE ' . self::table() . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				consent_id char(32) NOT NULL,
				revision int(10) unsigned NOT NULL,
				choices text NOT NULL,
				source varchar(20) NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY consent_id (consent_id),
				KEY created_at (created_at)
			) {$charset};"
		);
	}

	public static function sync(): void {
		if ( self::enabled() ) {
			self::install();
		}
		if ( self::enabled() || self::has_rows() ) {
			if ( ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
			}
		} else {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/**
	 * Settings written past the option hooks, by a filter or a database import, still get the table and the job.
	 */
	public static function heal(): void {
		if ( self::enabled() && ! wp_next_scheduled( self::CRON ) ) {
			self::sync();
		}
	}

	private static function has_rows(): bool {
		global $wpdb;
		$suppress = $wpdb->suppress_errors();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a custom table that may not exist.
		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i LIMIT 1', self::table() ) );
		$wpdb->suppress_errors( $suppress );
		return null !== $id;
	}

	public static function route(): void {
		if ( ! self::listening() ) {
			return;
		}
		register_rest_route(
			'silvertrail/v1',
			'/record',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'record' ),
				// Cached pages carry no usable nonce, so anyone may post. Listeners must treat the data as unverified.
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function record( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY ) {
			return new WP_Error( 'silvertrail_too_large', 'Too large.', array( 'status' => 413 ) );
		}
		$body       = $request->get_json_params();
		$consent_id = is_array( $body ) && is_string( $body['i'] ?? null ) ? $body['i'] : '';
		$revision   = is_array( $body ) && is_int( $body['n'] ?? null ) ? $body['n'] : 0;
		$source     = is_array( $body ) && is_string( $body['source'] ?? null ) ? $body['source'] : '';
		$choices    = is_array( $body ) && is_array( $body['c'] ?? null ) ? array_filter( $body['c'], 'is_string' ) : null;

		$valid = null !== $choices
			&& $revision >= 1 && $revision <= Config::revision()
			&& in_array( $source, self::SOURCES, true )
			&& ( preg_match( '/^[a-f0-9]{32}$/', $consent_id ) || ( '' === $consent_id && ! self::enabled() ) );
		if ( ! $valid ) {
			return new WP_Error( 'silvertrail_invalid', 'Invalid record.', array( 'status' => 400 ) );
		}

		$record = array(
			'consent_id' => $consent_id,
			'revision'   => $revision,
			'choices'    => array_values( array_intersect( Config::consent_ids(), $choices ) ),
			'source'     => $source,
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		);

		if ( self::enabled() ) {
			self::heal();
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- a custom table.
			$wpdb->insert( self::table(), array( 'choices' => (string) wp_json_encode( $record['choices'] ) ) + $record );
		}

		/**
		 * Fires for every decision a visitor makes. The data comes from the browser unverified.
		 *
		 * @param array{consent_id: string, revision: int, choices: list<string>, source: string, created_at: string} $record
		 */
		do_action( 'silvertrail_recorded', $record );

		return new WP_REST_Response( null, 204 );
	}

	public static function prune(): void {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - max( 30, (int) Settings::get( 'retention_days' ) ) * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a custom table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', self::table(), $cutoff ) );
		if ( ! self::enabled() && ! self::has_rows() ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/**
	 * @param resource $handle
	 */
	public static function export( $handle ): int {
		global $wpdb;
		fputcsv( $handle, array( 'consent_id', 'revision', 'choices', 'source', 'created_at' ), ',', '"', '' );
		$count = 0;
		$last  = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a custom table, read in pages.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, consent_id, revision, choices, source, created_at FROM %i WHERE id > %d ORDER BY id LIMIT 1000', self::table(), $last ), ARRAY_A );
			$rows = is_array( $rows ) ? $rows : array();
			foreach ( $rows as $row ) {
				$last = (int) $row['id'];
				unset( $row['id'] );
				fputcsv( $handle, $row, ',', '"', '' );
				++$count;
			}
			$fetched = count( $rows );
		} while ( 1000 === $fetched );
		return $count;
	}

	public static function download(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'silvertrail' ), 403 );
		}
		check_admin_referer( 'silvertrail_export' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="silvertrail-records-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$output = fopen( 'php://output', 'w' );
		if ( false !== $output ) {
			self::export( $output );
			fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output.
		}
		exit;
	}

	public static function privacy_text(): void {
		if ( ! self::enabled() || ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'Silvertrail',
			wp_kses_post(
				'<p>' . __( 'When you make a choice in the privacy settings, we store a random consent ID, the choices, the version of the settings and the time. We store no IP address and no browser details. The record lets us show that you consented, and we delete it after the retention period.', 'silvertrail' ) . '</p>'
			)
		);
	}
}
