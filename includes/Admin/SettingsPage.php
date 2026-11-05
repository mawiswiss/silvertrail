<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail\Admin;

use Silvertrail\Config;
use Silvertrail\Presets;
use Silvertrail\Settings;
use Silvertrail\Texts;

final class SettingsPage {

	public const SLUG = 'silvertrail';

	/**
	 * The settings each tab posts. Saving one tab keeps the others as stored.
	 */
	public const TABS = array(
		'services' => array( 'presets' ),
		'banner'   => array( 'layout', 'require_choice', 'appearance', 'load_css', 'texts', 'imprint_page', 'floating_button' ),
		'consent'  => array( 'expiry_months', 'gpc', 'url_passthrough', 'records', 'retention_days', 'trail' ),
		'tools'    => array( 'leak_check' ),
	);

	private const DRAFT = 'silvertrail_texts_draft_';

	private const FLAGS = array( 'require_choice', 'floating_button', 'load_css', 'records', 'leak_check', 'gpc', 'trail', 'url_passthrough' );

	/**
	 * @var array<string, mixed>|false
	 */
	private static array|false $editor = false;

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'setting' ) );
		add_action( 'admin_post_silvertrail_ask_again', array( self::class, 'ask_again' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SILVERTRAIL_FILE ), array( self::class, 'action_links' ) );
	}

	public static function menu(): void {
		$hook = add_options_page( 'Silvertrail', 'Silvertrail', 'manage_options', self::SLUG, array( self::class, 'render' ) );
		if ( false !== $hook ) {
			add_action( "load-{$hook}", array( self::class, 'load' ) );
		}
	}

	public static function load(): void {
		$screen = get_current_screen();
		if ( null !== $screen ) {
			Help::add( $screen );
		}
		if ( 'banner' === self::tab() ) {
			add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		}
	}

	public static function enqueue(): void {
		self::$editor = wp_enqueue_code_editor( array( 'type' => 'application/json' ) );
		$deps         = false === self::$editor ? array() : array( 'code-editor' );
		wp_enqueue_script( 'silvertrail-admin', plugins_url( 'assets/admin.js', SILVERTRAIL_FILE ), $deps, SILVERTRAIL_VERSION, true );
	}

	/**
	 * Settings for the JSON code editor, empty when the user turned syntax highlighting off.
	 */
	public static function editor(): string {
		return false === self::$editor ? '' : (string) wp_json_encode( self::$editor );
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

	public static function field( string ...$path ): string {
		return Settings::OPTION . '[' . implode( '][', $path ) . ']';
	}

	public static function checkbox( string $key, string $label, string $help = '' ): void {
		printf(
			'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label>',
			esc_attr( 'silvertrail-' . str_replace( '_', '-', $key ) ),
			esc_attr( self::field( $key ) ),
			checked( ! empty( Settings::get( $key ) ), true, false ),
			esc_html( $label )
		);
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
	}

	public static function url( string $tab = 'services' ): string {
		$url = admin_url( 'options-general.php?page=' . self::SLUG );
		return 'services' === $tab ? $url : add_query_arg( 'tab', $tab, $url );
	}

	public static function tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		return isset( self::TABS[ $tab ] ) ? $tab : 'services';
	}

	/**
	 * @param array<string, string> $links
	 * @return array<string, string>
	 */
	public static function action_links( array $links ): array {
		return array( 'settings' => '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'silvertrail' ) . '</a>' ) + $links;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = self::clean( $input );
		$tab   = is_string( $input['_tab'] ?? null ) ? $input['_tab'] : '';
		if ( ! isset( self::TABS[ $tab ] ) ) {
			return $clean;
		}
		return array_replace( Settings::all(), array_intersect_key( $clean, array_flip( self::TABS[ $tab ] ) ) );
	}

	/**
	 * @param array<mixed> $input
	 * @return array<string, mixed>
	 */
	private static function clean( array $input ): array {
		$defaults = Settings::defaults();

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
		foreach ( self::FLAGS as $key ) {
			$flags[ $key ] = ! empty( $input[ $key ] );
		}

		return array(
			'layout'         => 'box' === ( $input['layout'] ?? '' ) ? 'box' : 'modal',
			'appearance'     => in_array( $input['appearance'] ?? '', array( 'light', 'dark' ), true ) ? $input['appearance'] : 'theme',
			'texts'          => self::texts( $input ),
			'imprint_page'   => absint( $input['imprint_page'] ?? 0 ),
			'presets'        => $presets,
			'expiry_months'  => max( 1, min( 13, absint( $input['expiry_months'] ?? $defaults['expiry_months'] ) ) ),
			'retention_days' => max( 30, min( 3650, absint( $input['retention_days'] ?? $defaults['retention_days'] ) ) ),
		) + $flags;
	}

	/**
	 * The admin's text edits per language. A posted JSON replaces those of the site language.
	 *
	 * @param array<mixed> $input
	 * @return array<string, array<string, string>>
	 */
	private static function texts( array $input ): array {
		$json   = $input['texts_json'] ?? null;
		$stored = is_string( $json ) ? Settings::all()['texts'] : ( $input['texts'] ?? array() );
		$texts  = array();
		foreach ( is_array( $stored ) ? $stored : array() as $locale => $custom ) {
			if ( is_string( $locale ) && preg_match( '/^[A-Za-z0-9_]+$/', $locale ) && is_array( $custom ) ) {
				$texts[ $locale ] = array_filter( array_map( 'sanitize_text_field', Texts::validate( $custom )[0] ) );
			}
		}
		if ( ! is_string( $json ) ) {
			return array_filter( $texts );
		}

		$locale  = get_locale();
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			add_settings_error( Settings::OPTION, 'silvertrail-texts', __( 'The texts are not valid JSON, so they were not saved. The editor still holds them.', 'silvertrail' ) );
			set_transient( self::DRAFT . get_current_user_id(), $json, 5 * MINUTE_IN_SECONDS );
			return array_filter( $texts );
		}

		list( $valid, $problems ) = Texts::validate( $decoded );
		foreach ( $problems as $problem ) {
			/* translators: %s: what is wrong with a text */
			add_settings_error( Settings::OPTION, 'silvertrail-texts', sprintf( __( 'Not saved: %s', 'silvertrail' ), $problem ) );
		}
		if ( array() !== $problems ) {
			set_transient( self::DRAFT . get_current_user_id(), $json, 5 * MINUTE_IN_SECONDS );
		}
		$base             = Texts::base( $locale );
		$texts[ $locale ] = array();
		foreach ( $valid as $key => $text ) {
			$text = sanitize_text_field( $text );
			if ( '' !== $text && sanitize_text_field( $base[ $key ] ) !== $text ) {
				$texts[ $locale ][ $key ] = $text;
			}
		}
		return array_filter( $texts );
	}

	/**
	 * Texts that were not valid JSON, so the admin can fix them instead of typing them again.
	 */
	public static function draft(): ?string {
		$draft = get_transient( self::DRAFT . get_current_user_id() );
		delete_transient( self::DRAFT . get_current_user_id() );
		return is_string( $draft ) ? $draft : null;
	}

	public static function ask_again(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'silvertrail' ), 403 );
		}
		check_admin_referer( 'silvertrail_ask_again' );
		Config::ask_again();
		wp_safe_redirect( add_query_arg( 'silvertrail-asked', '1', self::url( 'tools' ) ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = self::tab();

		require __DIR__ . '/view.php';
	}
}
