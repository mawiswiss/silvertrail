<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

defined( 'ABSPATH' ) || exit;

/**
 * Shows a page as a first-time visitor sees it, for the leak check on the Tools tab.
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

	/**
	 * Once the scan agreed, the WP Consent API cookies are the scan's own.
	 */
	private static function forget_choices(): void {
		$agreed = isset( $_COOKIE[ Config::cookie( true ) ] );
		foreach ( array_keys( $_COOKIE ) as $name ) {
			if ( Config::cookie( false ) === $name || ( ! $agreed && str_starts_with( (string) $name, 'wp_consent_' ) ) ) {
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
		wp_register_script( 'silvertrail-scan-findings', plugins_url( 'assets/scan-findings.js', SILVERTRAIL_FILE ), array(), SILVERTRAIL_VERSION, true );
		wp_enqueue_script( 'silvertrail-scan', plugins_url( 'assets/scan.js', SILVERTRAIL_FILE ), array( 'silvertrail-scan-findings', 'wp-a11y' ), SILVERTRAIL_VERSION, true );
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
				'source'     => self::source( $id ),
			);
		}
		$presets = array();
		foreach ( Presets::all() as $id => $preset ) {
			$presets[ $id ] = array(
				'name'    => $preset['service']['name'],
				'hosts'   => $preset['service']['hosts'],
				'tracker' => isset( $preset['tag_id'] ),
				'locked'  => Admin\SettingsPage::in_code( $id ),
			);
		}

		return array(
			'home'     => home_url( '/' ),
			'settings' => Admin\SettingsPage::url(),
			'param'    => self::PARAM,
			'cookie'   => Config::cookie( true ),
			'path'     => Config::cookie_path(),
			'license'  => SILVERTRAIL_LICENSE_URL,
			'site'     => get_bloginfo( 'name' ),
			'language' => get_bloginfo( 'language' ),
			'services' => $services,
			'presets'  => $presets,
			'texts'    => self::texts(),
		);
	}

	private static function source( string $id ): string {
		if ( 'silvertrail' === $id ) {
			return 'own';
		}
		return isset( Presets::all()[ $id ] ) && Presets::switched_on( $id ) ? 'settings' : 'code';
	}

	/**
	 * @return array<string, string>
	 */
	private static function texts(): array {
		return array(
			'license'           => Plugin::license(),
			'scanning'          => __( 'Scanning. Each page takes a few seconds, and each service a few more.', 'silvertrail' ),
			/* translators: 1: page number, 2: number of pages, 3: page address */
			'stepFirst'         => __( 'Page %1$s of %2$s: opening %3$s as a first-time visitor', 'silvertrail' ),
			/* translators: 1: page number, 2: number of pages */
			'stepDeclined'      => __( 'Page %1$s of %2$s: declining everything', 'silvertrail' ),
			/* translators: 1: page number, 2: number of pages */
			'stepAgreed'        => __( 'Page %1$s of %2$s: agreeing to everything', 'silvertrail' ),
			/* translators: 1: page number, 2: number of pages */
			'stepReload'        => __( 'Page %1$s of %2$s: loading it again after agreeing', 'silvertrail' ),
			/* translators: 1: page number, 2: number of pages, 3: service name */
			'stepService'       => __( 'Page %1$s of %2$s: agreeing to %3$s alone', 'silvertrail' ),
			'cancelled'         => __( 'Scan cancelled. The cookies of this browser are as they were before.', 'silvertrail' ),
			'foreign'           => __( 'Only pages of this site can be scanned.', 'silvertrail' ),
			'failed'            => __( 'Could not read that page. It may refuse to be shown in a frame, or Silvertrail does not run on it.', 'silvertrail' ),
			'clean'             => __( 'Clean. Nothing contacts another server before the visitor agrees, and nothing waits for a reload once they do.', 'silvertrail' ),
			'attention'         => __( 'Something runs without consent. Each line below says what to do.', 'silvertrail' ),
			'reloadOnly'        => __( 'Nothing runs without consent, but some services start only after the page loads again. Each line below says what to do.', 'silvertrail' ),
			'guard'             => __( 'Needs guarding', 'silvertrail' ),
			'services'          => __( 'Services', 'silvertrail' ),
			'todo'              => __( 'What to do', 'silvertrail' ),
			'belongs'           => __( 'Contacted after agreeing, listed by no service', 'silvertrail' ),
			'stored'            => __( 'Cookies and storage the page set before a choice', 'silvertrail' ),
			'none'              => __( 'None', 'silvertrail' ),
			/* translators: %s: page addresses */
			'onPages'           => __( 'On %s.', 'silvertrail' ),
			'colService'        => __( 'Service', 'silvertrail' ),
			'colSource'         => __( 'Set in', 'silvertrail' ),
			'colBefore'         => __( 'Before a choice', 'silvertrail' ),
			'colDeclined'       => __( 'After declining', 'silvertrail' ),
			'colAgreed'         => __( 'After agreeing', 'silvertrail' ),
			'sourceSettings'    => __( 'Services tab', 'silvertrail' ),
			'sourceCode'        => __( 'Code', 'silvertrail' ),
			'quiet'             => __( 'Quiet', 'silvertrail' ),
			'contacts'          => __( 'Contacts its servers', 'silvertrail' ),
			'cookieless'        => __( 'Cookieless pings', 'silvertrail' ),
			'essential'         => __( 'Always on', 'silvertrail' ),
			'starts'            => __( 'Starts at once', 'silvertrail' ),
			'reload'            => __( 'Only after a reload', 'silvertrail' ),
			'unseen'            => __( 'No request to its hosts', 'silvertrail' ),
			'absent'            => __( 'Not on these pages', 'silvertrail' ),
			'any'               => __( 'Starts with any choice', 'silvertrail' ),
			/* translators: %s: service names */
			'alsoStarts'        => __( 'Also starts %s', 'silvertrail' ),
			/* translators: %s: service name */
			'registered'        => __( '%s loads before the visitor chooses. The theme or another plugin adds it directly, so Silvertrail cannot hold it back.', 'silvertrail' ),
			/* translators: %s: service name */
			'todoDeclined'      => __( '%s is contacted after the visitor declines. Its code reacts to the choice without checking it.', 'silvertrail' ),
			/* translators: %s: service name */
			'todoAny'           => __( '%s starts when the visitor agrees to any service. Its code reacts to the choice without checking it.', 'silvertrail' ),
			/* translators: 1: service name, 2: other service name, 3: Consent Mode types */
			'todoAlsoShared'    => __( 'Agreeing to %1$s also starts %2$s, because both grant the Consent Mode type %3$s. Narrow the google types of %1$s in code, or ask for the consent in the container.', 'silvertrail' ),
			/* translators: 1: service name, 2: other service name */
			'todoAlso'          => __( 'Agreeing to %1$s also starts %2$s. Its code or container loads %2$s without checking the consent for it.', 'silvertrail' ),
			/* translators: %s: service name */
			'todoReload'        => __( '%s starts only after the page loads again. Gate it in the browser with data-silvertrail or its script handle instead of silvertrail_has(), and start it without waiting for the page to load.', 'silvertrail' ),
			/* translators: %s: host */
			'todoLate'          => __( '%s is contacted only after the page loads again. Something on the server checks the consent, so a visitor who agrees misses it until the next page.', 'silvertrail' ),
			'guardDeclined'     => __( 'Contacted after the visitor declines. A script reacts to the choice without checking it.', 'silvertrail' ),
			/* translators: %s: service names */
			'guardAny'          => __( 'Contacted when the visitor agrees to any of %s, whatever they agreed to. Tie it to one service.', 'silvertrail' ),
			/* translators: 1: service name, 2: host */
			'belongsTo'         => __( 'Seems to belong to %1$s. Add %2$s to its hosts, so the banner and the trail name it.', 'silvertrail' ),
			'afterAll'          => __( 'Contacted once the visitor agrees to everything. The check could not tie it to one service.', 'silvertrail' ),
			/* translators: %s: service name */
			'presetEmbed'       => __( 'Looks like %s. Turn it on under Services, and a placeholder shows until the visitor agrees.', 'silvertrail' ),
			/* translators: %s: service name */
			'presetTracker'     => __( 'Looks like %s, added by the theme or another plugin. Remove that code, then turn it on under Services with your ID, so Silvertrail holds it back.', 'silvertrail' ),
			/* translators: %s: service name */
			'turnOn'            => __( 'Turn on %s', 'silvertrail' ),
			/* translators: %s: script handle */
			'script'            => __( 'A script that WordPress loads as “%s”. Register a service in code with this script handle, and it waits for consent.', 'silvertrail' ),
			'scriptRaw'         => __( 'A script written straight into the page. Mark it with data-silvertrail, as Developer help › Gate a script shows, and it waits for consent.', 'silvertrail' ),
			'frame'             => __( 'An embedded frame. Register its host as a service in code, and a placeholder shows until the visitor agrees.', 'silvertrail' ),
			'font'              => __( 'Fonts or styles. They cannot wait for consent, so host them on this site instead.', 'silvertrail' ),
			'image'             => __( 'Images. They cannot wait for consent, so host them on this site instead.', 'silvertrail' ),
			'other'             => __( 'Requested by another script on the page. Hold back that script, and this waits too.', 'silvertrail' ),
			'kindScript'        => __( 'script', 'silvertrail' ),
			'kindFrame'         => __( 'embedded frame', 'silvertrail' ),
			'kindFont'          => __( 'fonts or styles', 'silvertrail' ),
			'kindImage'         => __( 'images', 'silvertrail' ),
			'kindOther'         => __( 'requested by another script', 'silvertrail' ),
			'kindAfter'         => __( 'contacted after agreeing', 'silvertrail' ),
			'report'            => __( 'Prompt for an AI assistant', 'silvertrail' ),
			'reportHelp'        => __( 'Paste it into ChatGPT or another assistant to get the provider, purpose and privacy policy of each service for the banner.', 'silvertrail' ),
			'copy'              => __( 'Copy prompt', 'silvertrail' ),
			'copied'            => __( 'Copied', 'silvertrail' ),
			/* translators: 1: site name, 2: site address, 3: page addresses */
			'promptIntro'       => __( 'My website %1$s (%2$s) has a cookie banner that lists each service with its provider, purpose and privacy policy. Opening %3$s as a first-time visitor found the following.', 'silvertrail' ),
			'promptConnections' => __( 'Connections to other servers', 'silvertrail' ),
			'promptHeld'        => __( 'Services that wait for consent and still lack details', 'silvertrail' ),
			'promptCookies'     => __( 'Cookies and storage the page set', 'silvertrail' ),
			/* translators: %s: language code such as de-CH */
			'promptAsk'         => __( "For each service behind these entries, give me these three lines. Group entries that belong to the same service.\nProvider: the company's name and postal address\nPurpose: one short sentence a visitor understands\nPrivacy policy: the address of the provider's privacy policy\n\nExample:\nGoogle Tag Manager\nProvider: Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Ireland\nPurpose: Loads the tags configured in this site's Google Tag Manager container.\nPrivacy policy: https://business.safety.google/privacy/\n\nIf you cannot tell what an entry is, say so instead of guessing. Write the purpose in the language %s.", 'silvertrail' ),
		);
	}
}
