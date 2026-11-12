<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail\Admin;

use Silvertrail\Config;
use Silvertrail\Design;
use Silvertrail\Plugin;
use Silvertrail\Presets;
use Silvertrail\Scan;
use Silvertrail\Settings;
use Silvertrail\Texts;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {

	public const SLUG = 'silvertrail';

	public const LICENSE_SEEN = 'silvertrail_license_seen';

	/**
	 * The settings each tab posts. Saving one tab keeps the others as stored.
	 */
	public const TABS = array(
		'services' => array( 'presets' ),
		'banner'   => array( 'layout', 'require_choice', 'appearance', 'texts', 'imprint_page', 'floating_button', 'floating_position' ),
		'tools'    => array( 'records' ),
	);

	private const DRAFT = 'silvertrail_texts_draft_';

	private const FLAGS = array( 'require_choice', 'records' );

	/**
	 * @var array<string, array<string, mixed>|false>
	 */
	private static array $editors = array();

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'setting' ) );
		add_action( 'admin_post_silvertrail_ask_again', array( self::class, 'ask_again' ) );
		add_action( 'admin_post_silvertrail_license_seen', array( self::class, 'license_seen' ) );
		add_action( 'admin_post_silvertrail_toggle', array( self::class, 'toggle' ) );
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
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'all_admin_notices', array( Notices::class, 'take_errors' ) );
		add_filter( 'admin_footer_text', array( self::class, 'footer' ) );
		if ( 'tools' === self::tab() ) {
			add_action( 'admin_enqueue_scripts', array( Scan::class, 'enqueue' ) );
		}
	}

	public static function enqueue(): void {
		$deps = array( 'clipboard', 'wp-a11y' );
		wp_enqueue_style( 'silvertrail-admin', plugins_url( 'assets/admin.css', SILVERTRAIL_FILE ), array(), SILVERTRAIL_VERSION );
		if ( 'banner' === self::tab() ) {
			self::$editors = array(
				'json'     => wp_enqueue_code_editor( array( 'type' => 'application/json' ) ),
				'markdown' => wp_enqueue_code_editor(
					array(
						'type'       => 'text/x-markdown',
						'codemirror' => array(
							'readOnly'     => true,
							'lineWrapping' => true,
						),
					)
				),
			);
			$deps          = array_filter( self::$editors ) === array() ? $deps : array( ...$deps, 'code-editor' );
			wp_add_inline_style( 'silvertrail-admin', wp_get_global_stylesheet( array( 'variables' ) ) );
		}
		wp_enqueue_script( 'silvertrail-admin', plugins_url( 'assets/admin.js', SILVERTRAIL_FILE ), $deps, SILVERTRAIL_VERSION, true );
	}

	/**
	 * Settings for a code editor, empty when the user turned syntax highlighting off.
	 */
	public static function editor( string $type ): string {
		$settings = self::$editors[ $type ] ?? false;
		return false === $settings ? '' : (string) wp_json_encode( $settings );
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
			'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s %4$s> %5$s</label>%6$s',
			esc_attr( 'silvertrail-' . str_replace( '_', '-', $key ) ),
			esc_attr( self::field( $key ) ),
			checked( ! empty( Settings::get( $key ) ), true, false ),
			disabled( Settings::in_code( $key ), true, false ),
			esc_html( $label ),
			wp_kses_post( self::in_code_label( $key ) )
		);
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
	}

	/**
	 * Each card's button saves the whole tab, because the tab is one form.
	 */
	public static function save( string $card ): void {
		submit_button( '', 'primary', 'submit', true, array( 'id' => "silvertrail-save-{$card}" ) );
	}

	/**
	 * A button that opens a tab of the Help panel, which needs JavaScript like the panel itself.
	 */
	public static function help_button( string $tab, string $label ): string {
		return sprintf(
			'<button type="button" class="button-link hide-if-no-js" data-silvertrail-help="%1$s">%2$s</button>',
			esc_attr( $tab ),
			esc_html( $label )
		);
	}

	public static function in_code_label( string $key ): string {
		return Settings::in_code( $key ) ? ' <strong class="post-state">' . esc_html__( 'Set in code', 'silvertrail' ) . '</strong>' : '';
	}

	public static function url( string $tab = 'services' ): string {
		$url = admin_url( 'options-general.php?page=' . self::SLUG );
		return 'services' === $tab ? $url : add_query_arg( 'tab', $tab, $url );
	}

	public static function in_code( string $id ): bool {
		return isset( Config::get()['services'][ $id ] ) && ! Presets::switched_on( $id );
	}

	/**
	 * The preset the leak check sent the admin here to tick. Nothing is stored until they save.
	 */
	public static function turning_on(): string {
		$id = isset( $_GET['silvertrail-turn-on'] ) ? sanitize_key( wp_unslash( $_GET['silvertrail-turn-on'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- it only ticks a box in the form.
		return isset( Presets::all()[ $id ] ) && ! self::in_code( $id ) ? $id : '';
	}

	/**
	 * @return array<string, string>
	 */
	public static function tabs(): array {
		return array(
			'services' => __( 'Services', 'silvertrail' ),
			'banner'   => __( 'Banner', 'silvertrail' ),
			'tools'    => __( 'Tools', 'silvertrail' ),
		);
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
		return array(
			'settings' => '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'silvertrail' ) . '</a>',
			'license'  => '<a href="' . esc_url( SILVERTRAIL_LICENSE_URL ) . '">' . esc_html__( 'Support Silvertrail', 'silvertrail' ) . '</a>',
		) + $links;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$tab   = is_string( $input['_tab'] ?? null ) ? $input['_tab'] : '';
		$keys  = 'switch' === $tab ? array( 'enabled' ) : ( self::TABS[ $tab ] ?? null );
		if ( null === $keys ) {
			// A new option runs through here twice: add_option() sanitizes the saved value again, with _tab and the defaults gone.
			return self::changed( self::clean( $input + Settings::defaults(), false ) );
		}
		$posted = array_intersect_key( self::clean( $input ), array_flip( $keys ) );
		$posted = array_filter( $posted, static fn( string $key ): bool => ! Settings::in_code( $key ), ARRAY_FILTER_USE_KEY );
		return self::changed( array_replace( Settings::saved(), $posted ) );
	}

	/**
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	private static function changed( array $values ): array {
		$defaults = Settings::defaults();
		return array_filter( $values, static fn( mixed $value, string $key ): bool => ( $defaults[ $key ] ?? null ) !== $value, ARRAY_FILTER_USE_BOTH );
	}

	/**
	 * A form leaves unticked presets out. A saved option without presets leaves them at their defaults.
	 *
	 * @param array<mixed> $input
	 * @return array<string, mixed>
	 */
	private static function clean( array $input, bool $form = true ): array {
		$defaults = Settings::defaults();

		$presets = array();
		foreach ( $form || array() !== $input['presets'] ? array_keys( Presets::all() ) : array() as $id ) {
			$presets[ $id ] = array(
				'enabled' => ! empty( $input['presets'][ $id ]['enabled'] ),
				'tag_id'  => strtoupper( sanitize_text_field( (string) ( $input['presets'][ $id ]['tag_id'] ?? '' ) ) ),
			);
		}

		$values = array_diff_key( $defaults, array_flip( array( 'texts', 'presets' ) ) );
		$values = array_replace( $values, array_intersect_key( $input, $values ) );
		foreach ( self::FLAGS as $key ) {
			$values[ $key ] = ! empty( $input[ $key ] );
		}
		if ( isset( $input['floating'] ) ) {
			$values['floating_button']   = 'never' === $input['floating'] ? 'never' : 'auto';
			$values['floating_position'] = $input['floating'];
		}

		$values = Settings::normalize( $values );
		// The theme's own scheme stays 'theme', so the banner follows a later theme switch.
		if ( Design::scheme() === $values['appearance'] ) {
			$values['appearance'] = 'theme';
		}

		return array(
			'texts'   => self::texts( $input ),
			'presets' => $presets,
		) + $values;
	}

	/**
	 * The admin's text edits per language. A posted JSON replaces those of the site language.
	 *
	 * @param array<mixed> $input
	 * @return array<string, array<string, string>>
	 */
	private static function texts( array $input ): array {
		$json   = $input['texts_json'] ?? null;
		$stored = is_string( $json ) ? Settings::stored()['texts'] : ( $input['texts'] ?? array() );
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
		wp_safe_redirect( add_query_arg( 'silvertrail-asked', '1', self::url( 'tools' ) . '#silvertrail-ask-again' ) );
		exit;
	}

	public static function license_seen(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'silvertrail' ), 403 );
		}
		check_admin_referer( 'silvertrail_license_seen', '_silvertrail_license' );
		update_option( self::LICENSE_SEEN, true, false );
		self::back();
	}

	public static function toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'silvertrail' ), 403 );
		}
		check_admin_referer( 'silvertrail_toggle', '_silvertrail_toggle' );
		update_option(
			Settings::OPTION,
			array(
				'_tab'    => 'switch',
				'enabled' => ! empty( $_POST['enabled'] ),
			)
		);
		self::back();
	}

	private static function back(): never {
		$referer = wp_get_referer();
		wp_safe_redirect( false === $referer ? self::url() : $referer );
		exit;
	}

	public static function footer( string $text ): string {
		if ( ! get_option( self::LICENSE_SEEN ) ) {
			return $text;
		}
		return esc_html( Plugin::license() ) . ' <a href="' . esc_url( SILVERTRAIL_LICENSE_URL ) . '">' . esc_html__( 'Support Silvertrail', 'silvertrail' ) . '</a>';
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = self::tab();

		require __DIR__ . '/view.php';
	}
}
