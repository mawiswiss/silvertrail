<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail\Admin;

use Silvertrail\Config;
use Silvertrail\Plugin;
use Silvertrail\Presets;
use Silvertrail\Settings;
use Silvertrail\WebMcp;

defined( 'ABSPATH' ) || exit;

final class Notices {

	/**
	 * @var list<array{setting: string, code: string, message: string, type: string}>
	 */
	private static array $errors = array();

	/**
	 * Runs on all_admin_notices, because core prints the settings errors right after it.
	 */
	public static function take_errors(): void {
		global $wp_settings_errors;
		// Also loads the errors options.php kept from the save into the global.
		get_settings_errors();
		foreach ( (array) $wp_settings_errors as $key => $error ) {
			if ( Settings::OPTION === $error['setting'] && 'silvertrail-texts' === $error['code'] ) {
				self::$errors[] = $error;
				unset( $wp_settings_errors[ $key ] );
			}
		}
	}

	/**
	 * Errors and warnings also go to the overview, with a summary.
	 *
	 * @return list<array{card: string, type: string, message: string, summary?: string}>
	 */
	public static function all(): array {
		$notices = array();

		foreach ( self::$errors as $error ) {
			$notices[] = array(
				'card'    => 'texts',
				'type'    => 'error',
				'message' => esc_html( $error['message'] ),
				'summary' => __( 'Some texts were not saved.', 'silvertrail' ),
			);
		}

		$services = Config::get()['services'];
		foreach ( Presets::all() as $id => $preset ) {
			if ( ! isset( $services[ $id ], $preset['tag_id'] ) || preg_match( '/' . $preset['tag_id']['pattern'] . '/', $services[ $id ]['tag_id'] ) ) {
				continue;
			}
			$notices[] = array(
				'card'    => 'trackers',
				'type'    => 'error',
				/* translators: 1: service name, 2: example ID */
				'message' => esc_html( sprintf( __( '%1$s needs an ID like %2$s. Its tag is not printed until the ID is valid.', 'silvertrail' ), $preset['service']['name'], $preset['tag_id']['example'] ) ),
				/* translators: %s: service name */
				'summary' => sprintf( __( '%s needs a valid ID.', 'silvertrail' ), $preset['service']['name'] ),
			);
		}

		if ( '' === get_privacy_policy_url() ) {
			$notices[] = array(
				'card'    => 'links',
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %s: link to the privacy settings */
					esc_html__( 'Publish a privacy policy page under %s, so the banner can link to it.', 'silvertrail' ),
					'<a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' . esc_html__( 'Settings › Privacy', 'silvertrail' ) . '</a>'
				),
				'summary' => __( 'No privacy policy page is published.', 'silvertrail' ),
			);
		} elseif ( Plugin::privacy_pending() ) {
			$notices[] = array(
				'card'    => 'links',
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %s: link to the Privacy Policy Guide */
					esc_html__( 'Silvertrail suggests a paragraph for your privacy policy. Copy it from the %s.', 'silvertrail' ),
					'<a href="' . esc_url( admin_url( 'options-privacy.php?tab=policyguide' ) ) . '">' . esc_html__( 'Privacy Policy Guide', 'silvertrail' ) . '</a>'
				),
				'summary' => __( 'Silvertrail suggests text for the privacy policy.', 'silvertrail' ),
			);
		}

		foreach ( WebMcp::tokens() as $token ) {
			$message = $token['problem'];
			if ( '' === $message && $token['expiry'] - time() < WebMcp::WARN_DAYS * DAY_IN_SECONDS ) {
				/* translators: 1: origin of the token, 2: date */
				$message = sprintf( __( 'The WebMCP trial token for %1$s expires on %2$s. Renew it in the browser’s origin trial console.', 'silvertrail' ), $token['origin'], wp_date( 'Y-m-d', $token['expiry'] ) );
			}
			if ( '' !== $message ) {
				$notices[] = array(
					'card'    => 'webmcp',
					'type'    => 'warning',
					'message' => esc_html( $message ),
					'summary' => __( 'A WebMCP trial token needs attention.', 'silvertrail' ),
				);
			}
		}

		$turn_on = SettingsPage::turning_on();
		if ( '' !== $turn_on ) {
			$preset    = Presets::all()[ $turn_on ];
			$notices[] = array(
				'card'    => isset( $preset['tag_id'] ) ? 'trackers' : 'embeds',
				'type'    => 'info',
				/* translators: %s: service name */
				'message' => esc_html( sprintf( __( '%s is ticked. Save Changes to turn it on.', 'silvertrail' ), $preset['service']['name'] ) ),
			);
		}

		if ( isset( $_GET['silvertrail-asked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			$notices[] = array(
				'card'    => 'ask-again',
				'type'    => 'success',
				'message' => esc_html__( 'Every visitor will be asked again on their next page view.', 'silvertrail' ),
			);
		}

		return $notices;
	}

	/**
	 * @return list<array{type: string, summary: string, url: string, where: string}>
	 */
	public static function overview( string $tab ): array {
		$cards = array(
			'trackers' => array( 'services', __( 'Trackers', 'silvertrail' ) ),
			'texts'    => array( 'banner', __( 'Texts', 'silvertrail' ) ),
			'links'    => array( 'banner', __( 'Links', 'silvertrail' ) ),
			'webmcp'   => array( 'agents', __( 'Visiting agents', 'silvertrail' ) ),
		);
		$items = array();
		foreach ( self::all() as $notice ) {
			if ( ! isset( $notice['summary'], $cards[ $notice['card'] ] ) ) {
				continue;
			}
			list( $card_tab, $heading )  = $cards[ $notice['card'] ];
			$anchor                      = '#silvertrail-' . $notice['card'];
			$items[ $notice['summary'] ] = array(
				'type'    => $notice['type'],
				'summary' => $notice['summary'],
				'url'     => $tab === $card_tab ? $anchor : SettingsPage::url( $card_tab ) . $anchor,
				'where'   => SettingsPage::tabs()[ $card_tab ] . ' › ' . $heading,
			);
		}
		return array_values( $items );
	}

	public static function reset(): void {
		self::$errors = array();
	}

	public static function card( string $card ): void {
		foreach ( self::all() as $notice ) {
			if ( $card === $notice['card'] ) {
				printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( $notice['type'] ), wp_kses_post( $notice['message'] ) );
			}
		}
	}
}
