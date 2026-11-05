<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

/**
 * Shows a page as a first-time visitor sees it, for the scan on the Services tab.
 */
final class Scan {

	public const PARAM = 'silvertrail-scan';

	public static function register(): void {
		add_filter( 'determine_current_user', array( self::class, 'visitor' ), PHP_INT_MAX );
		add_action( 'send_headers', array( self::class, 'headers' ) );
		if ( self::active() ) {
			self::forget_choices();
		}
	}

	private static function forget_choices(): void {
		foreach ( array_keys( $_COOKIE ) as $name ) {
			if ( Config::cookie() === $name || str_starts_with( (string) $name, 'wp_consent_' ) ) {
				unset( $_COOKIE[ $name ] );
			}
		}
	}

	public static function active(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- it only drops privileges.
		return isset( $_GET[ self::PARAM ] ) && ! is_admin();
	}

	public static function visitor( mixed $user ): mixed {
		return self::active() ? 0 : $user;
	}

	public static function headers(): void {
		if ( self::active() ) {
			nocache_headers();
		}
	}

	public static function enqueue(): void {
		wp_enqueue_script( 'silvertrail-scan', plugins_url( 'assets/scan.js', SILVERTRAIL_FILE ), array(), SILVERTRAIL_VERSION, true );
		wp_add_inline_script( 'silvertrail-scan', 'window.silvertrailScan = ' . wp_json_encode( self::data(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function data(): array {
		$config    = Config::get();
		$essential = Config::essential_ids();
		$services  = array();
		foreach ( $config['services'] as $id => $service ) {
			$services[ $id ] = array(
				'name'       => $service['name'],
				'group'      => $config['groups'][ $service['group'] ]['name'] ?? $service['group'],
				'provider'   => $service['provider'],
				'purpose'    => $service['purpose'],
				'privacyUrl' => $service['privacy_url'],
				'cookies'    => $service['cookies'],
				'expiry'     => $service['expiry'],
				'essential'  => in_array( $id, $essential, true ),
			);
		}
		$presets = array();
		foreach ( Presets::all() as $id => $preset ) {
			$presets[ $id ] = array(
				'name'    => $preset['service']['name'],
				'hosts'   => $preset['service']['hosts'],
				'tracker' => isset( $preset['tag_id'] ),
			);
		}

		return array(
			'home'     => home_url( '/' ),
			'param'    => self::PARAM,
			'site'     => get_bloginfo( 'name' ),
			'language' => get_bloginfo( 'language' ),
			'services' => $services,
			'presets'  => $presets,
			'texts'    => self::texts(),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function texts(): array {
		return array(
			'scanning'          => __( 'Opening the page as a first-time visitor…', 'silvertrail' ),
			'foreign'           => __( 'Only pages of this site can be scanned.', 'silvertrail' ),
			'failed'            => __( 'Could not read that page. It may refuse to be shown in a frame, or Silvertrail does not run on it.', 'silvertrail' ),
			'clean'             => __( 'Clean. Before choosing, the visitor’s browser contacts no other server that needs consent.', 'silvertrail' ),
			'attention'         => __( 'Before choosing, the visitor’s browser already contacts these servers. Each line says what to do.', 'silvertrail' ),
			'early'             => __( 'Loads before the visitor chooses', 'silvertrail' ),
			'held'              => __( 'Waits until the visitor agrees', 'silvertrail' ),
			'fine'              => __( 'Loads early, which is fine', 'silvertrail' ),
			'stored'            => __( 'Cookies and storage the page set before a choice', 'silvertrail' ),
			'none'              => __( 'None', 'silvertrail' ),
			/* translators: %s: service name */
			'essential'         => __( '%s is marked as essential, so it needs no consent.', 'silvertrail' ),
			/* translators: %s: service name */
			'cookieless'        => __( '%s runs in Consent Mode Advanced. It sends only cookieless pings until the visitor agrees.', 'silvertrail' ),
			/* translators: %s: service name */
			'registered'        => __( 'This is %s, but it still loads before the visitor chooses. The theme or another plugin adds it directly.', 'silvertrail' ),
			/* translators: %s: service name */
			'presetEmbed'       => __( 'Looks like %s. Turn it on below, and a placeholder shows until the visitor agrees.', 'silvertrail' ),
			/* translators: %s: service name */
			'presetTracker'     => __( 'Looks like %s, added by the theme or another plugin. Remove that code, then turn it on below with your ID, so Silvertrail holds it back.', 'silvertrail' ),
			/* translators: %s: service name */
			'turnOn'            => __( 'Turn on %s', 'silvertrail' ),
			'turnedOn'          => __( 'Ticked below. Save Changes to apply.', 'silvertrail' ),
			/* translators: %s: script handle */
			'script'            => __( 'A script that WordPress loads as “%s”. Register a service in code with this script handle, and it waits for consent.', 'silvertrail' ),
			'scriptRaw'         => __( 'A script written straight into the page. Mark it with data-silvertrail, as Help at the top right shows, and it waits for consent.', 'silvertrail' ),
			'frame'             => __( 'An embedded frame. Register its host as a service in code, and a placeholder shows until the visitor agrees.', 'silvertrail' ),
			'font'              => __( 'Fonts or styles. They cannot wait for consent, so host them on this site instead.', 'silvertrail' ),
			'image'             => __( 'Images. They cannot wait for consent, so host them on this site instead.', 'silvertrail' ),
			'other'             => __( 'Requested by another script on the page. Hold back that script, and this waits too.', 'silvertrail' ),
			'kindScript'        => __( 'script', 'silvertrail' ),
			'kindFrame'         => __( 'embedded frame', 'silvertrail' ),
			'kindFont'          => __( 'fonts or styles', 'silvertrail' ),
			'kindImage'         => __( 'images', 'silvertrail' ),
			'kindOther'         => __( 'requested by another script', 'silvertrail' ),
			'report'            => __( 'Prompt for an AI assistant', 'silvertrail' ),
			'reportHelp'        => __( 'Paste it into ChatGPT or another assistant to get the provider, purpose and privacy policy of each service for the banner.', 'silvertrail' ),
			'copy'              => __( 'Copy prompt', 'silvertrail' ),
			'copied'            => __( 'Copied', 'silvertrail' ),
			/* translators: 1: site name, 2: site address, 3: page address */
			'promptIntro'       => __( 'My website %1$s (%2$s) has a cookie banner that lists each service with its provider, purpose and privacy policy. Opening %3$s as a first-time visitor found the following.', 'silvertrail' ),
			'promptConnections' => __( 'Connections to other servers', 'silvertrail' ),
			'promptHeld'        => __( 'Services that wait for consent and still lack details', 'silvertrail' ),
			'promptCookies'     => __( 'Cookies and storage the page set', 'silvertrail' ),
			/* translators: %s: language code such as de-CH */
			'promptAsk'         => __( "For each service behind these entries, give me these three lines. Group entries that belong to the same service.\nProvider: the company's name and postal address\nPurpose: one short sentence a visitor understands\nPrivacy policy: the address of the provider's privacy policy\n\nExample:\nGoogle Tag Manager\nProvider: Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Ireland\nPurpose: Loads the tags configured in this site's Google Tag Manager container.\nPrivacy policy: https://business.safety.google/privacy/\n\nIf you cannot tell what an entry is, say so instead of guessing. Write the purpose in the language %s.", 'silvertrail' ),
		);
	}
}
