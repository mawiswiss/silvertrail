<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail\Admin;

use Silvertrail\Config;
use Silvertrail\Presets;
use Silvertrail\Settings;

final class SettingsPage {

	public const SLUG = 'silvertrail';

	/**
	 * Texts an admin may change. Every other label stays with the translation.
	 */
	public const EDITABLE_TEXTS = array( 'title', 'intro', 'accept_all', 'reject_all', 'preferences', 'preferences_intro', 'save', 'reopen' );

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'setting' ) );
		add_action( 'admin_post_silvertrail_ask_again', array( self::class, 'ask_again' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SILVERTRAIL_FILE ), array( self::class, 'action_links' ) );
	}

	public static function menu(): void {
		add_options_page( 'Silvertrail', 'Silvertrail', 'manage_options', self::SLUG, array( self::class, 'render' ) );
	}

	public static function setting(): void {
		register_setting(
			self::SLUG,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * @param array<string, string> $links
	 * @return array<string, string>
	 */
	public static function action_links( array $links ): array {
		$url = admin_url( 'options-general.php?page=' . self::SLUG );
		return array( 'settings' => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'silvertrail' ) . '</a>' ) + $links;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $input ): array {
		$input    = is_array( $input ) ? wp_unslash( $input ) : array();
		$defaults = Settings::defaults();

		$texts = array();
		foreach ( self::EDITABLE_TEXTS as $key ) {
			$text = sanitize_text_field( (string) ( $input['texts'][ $key ] ?? '' ) );
			if ( '' !== $text ) {
				$texts[ $key ] = $text;
			}
		}

		$colors = array();
		foreach ( array_keys( $defaults['colors'] ) as $key ) {
			$colors[ $key ] = (string) sanitize_hex_color( (string) ( $input['colors'][ $key ] ?? '' ) );
		}

		$presets = array();
		foreach ( Presets::all() as $id => $preset ) {
			$tag_id = strtoupper( sanitize_text_field( (string) ( $input['presets'][ $id ]['tag_id'] ?? '' ) ) );
			$on     = ! empty( $input['presets'][ $id ]['enabled'] );
			if ( $on && isset( $preset['tag_id'] ) && ! preg_match( '/' . $preset['tag_id']['pattern'] . '/', $tag_id ) ) {
				add_settings_error(
					Settings::OPTION,
					"silvertrail-{$id}",
					/* translators: 1: service name, 2: example ID */
					sprintf( __( '%1$s needs an ID like %2$s. Its tag is not printed until the ID is valid.', 'silvertrail' ), $preset['service']['name'], $preset['tag_id']['example'] )
				);
			}
			$presets[ $id ] = array(
				'enabled' => $on,
				'tag_id'  => $tag_id,
			);
		}

		$flags = array();
		foreach ( array( 'floating_button', 'load_css', 'records', 'leak_check', 'gpc', 'trail', 'url_passthrough' ) as $key ) {
			$flags[ $key ] = ! empty( $input[ $key ] );
		}

		return array(
			'layout'         => 'box' === ( $input['layout'] ?? '' ) ? 'box' : 'modal',
			'appearance'     => in_array( $input['appearance'] ?? '', array( 'dark', 'auto' ), true ) ? $input['appearance'] : 'light',
			'texts'          => $texts,
			'colors'         => $colors,
			'imprint_page'   => absint( $input['imprint_page'] ?? 0 ),
			'presets'        => $presets,
			'expiry_months'  => max( 1, min( 13, absint( $input['expiry_months'] ?? $defaults['expiry_months'] ) ) ),
			'retention_days' => max( 30, min( 3650, absint( $input['retention_days'] ?? $defaults['retention_days'] ) ) ),
		) + $flags;
	}

	public static function ask_again(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'silvertrail' ), 403 );
		}
		check_admin_referer( 'silvertrail_ask_again' );
		Config::ask_again();
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::SLUG . '&silvertrail-asked=1' ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$presets    = Presets::all();
		$registered = array_diff_key( Config::get()['services'], $presets, array( 'silvertrail' => true ) );

		require __DIR__ . '/view.php';
	}
}
