<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

/**
 * @phpstan-type Group array{name: string, description: string, locked: bool, consent_api: string}
 * @phpstan-type Service array{
 *     group: string,
 *     name: string,
 *     provider: string,
 *     purpose: string,
 *     privacy_url: string,
 *     cookies: string,
 *     expiry: string,
 *     hosts: list<string>,
 *     handles: list<string>,
 *     google: list<string>,
 *     consent: 'required'|'exempt',
 *     tag_id: string
 * }
 * @phpstan-type ConfigArray array{
 *     groups: array<string, Group>,
 *     services: array<string, Service>,
 *     google: array{mode: 'basic'|'advanced'}
 * }
 */
final class Config {

	public const COOKIE         = 'silvertrail';
	public const VERSION_OPTION = 'silvertrail_version';

	public const GOOGLE_TYPES = array(
		'ad_storage',
		'ad_user_data',
		'ad_personalization',
		'analytics_storage',
		'functionality_storage',
		'personalization_storage',
		'security_storage',
	);

	private const ID_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

	/**
	 * @var ConfigArray|null
	 */
	private static ?array $config = null;

	private static ?int $revision = null;

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private static array $registered_groups = array();

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private static array $registered_services = array();

	/**
	 * @return ConfigArray
	 */
	public static function get(): array {
		if ( null === self::$config ) {
			self::$config = self::build();
		}
		return self::$config;
	}

	/**
	 * Builds again on the next read, keeping what was registered.
	 */
	public static function rebuild(): void {
		self::$config   = null;
		self::$revision = null;
	}

	public static function reset(): void {
		self::$config              = null;
		self::$revision            = null;
		self::$registered_groups   = array();
		self::$registered_services = array();
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function register_group( string $id, array $args ): void {
		self::$registered_groups[ $id ] = $args;
		self::$config                   = null;
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function register_service( string $id, array $args ): void {
		self::$registered_services[ $id ] = $args;
		self::$config                     = null;
	}

	/**
	 * Services that never need a decision: the locked groups and services marked exempt.
	 *
	 * @return list<string>
	 */
	public static function essential_ids(): array {
		$config = self::get();
		$ids    = array();
		foreach ( $config['services'] as $id => $service ) {
			if ( 'exempt' === $service['consent'] || $config['groups'][ $service['group'] ]['locked'] ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * @return list<string>
	 */
	public static function consent_ids(): array {
		return array_values( array_diff( array_keys( self::get()['services'] ), self::essential_ids() ) );
	}

	/**
	 * In Consent Mode Advanced, Google tags load before consent and send only cookieless pings.
	 */
	public static function ungated( string $id ): bool {
		$config = self::get();
		return 'advanced' === $config['google']['mode'] && array() !== ( $config['services'][ $id ]['google'] ?? array() );
	}

	/**
	 * Sites of a network share the host, so each one keeps its own cookie.
	 */
	public static function cookie(): string {
		return is_multisite() && ! is_main_site() ? self::COOKIE . '_' . get_current_blog_id() : self::COOKIE;
	}

	public static function cookie_path(): string {
		$path = trim( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ), '/' );
		return '/' . $path;
	}

	public static function needs_banner(): bool {
		return array() !== self::consent_ids();
	}

	/**
	 * The consent counter. A cookie with a lower number is outdated.
	 */
	public static function revision(): int {
		if ( null !== self::$revision ) {
			return self::$revision;
		}

		$ids = self::consent_ids();
		sort( $ids );
		$hash   = substr( md5( implode( ',', $ids ) ), 0, 12 );
		$stored = self::stored_version();

		if ( $stored['hash'] !== $hash ) {
			if ( $stored['previous'] === $hash && time() - $stored['changed'] < MINUTE_IN_SECONDS ) {
				_doing_it_wrong(
					__METHOD__,
					esc_html__( 'The registered services flipped back and forth within a minute. They must not depend on the current request, or visitors are asked again on every page.', 'silvertrail' ),
					'0.1.0'
				);
			}
			$stored = array(
				'hash'     => $hash,
				'previous' => $stored['hash'],
				'n'        => '' === $stored['hash'] ? 1 : $stored['n'] + 1,
				'changed'  => time(),
			);
			update_option( self::VERSION_OPTION, $stored, true );
		}

		self::$revision = $stored['n'];
		return self::$revision;
	}

	public static function stored_revision(): int {
		return self::stored_version()['n'];
	}

	public static function ask_again(): int {
		self::revision();
		$stored       = self::stored_version();
		$stored['n'] += 1;
		update_option( self::VERSION_OPTION, $stored, true );
		self::$revision = $stored['n'];
		return $stored['n'];
	}

	public static function service_for_url( string $url ): ?string {
		$target = self::host_and_path( $url );
		if ( '' === $target ) {
			return null;
		}
		foreach ( self::get()['services'] as $id => $service ) {
			foreach ( $service['hosts'] as $pattern ) {
				if ( self::matches( $target, $pattern ) ) {
					return $id;
				}
			}
		}
		return null;
	}

	/**
	 * @return ConfigArray
	 */
	private static function build(): array {
		if ( ! did_action( 'init' ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Read the Silvertrail config on init or later.', 'silvertrail' ), '0.1.0' );
		}

		$config = array(
			'groups'   => self::default_groups(),
			'services' => array( 'silvertrail' => self::own_service() ) + Presets::enabled(),
			'google'   => array( 'mode' => 'basic' ),
		);

		do_action( 'silvertrail_register' );

		foreach ( self::$registered_groups as $id => $args ) {
			$config['groups'][ $id ] = array_replace( $config['groups'][ $id ] ?? array(), $args );
		}
		foreach ( self::$registered_services as $id => $args ) {
			$config['services'][ $id ] = array_replace( $config['services'][ $id ] ?? array(), $args );
		}

		/**
		 * Edits the final groups and services.
		 *
		 * @param array<string, mixed> $config
		 */
		$config = apply_filters( 'silvertrail_config', $config );

		return self::validate( is_array( $config ) ? $config : array() );
	}

	/**
	 * @param array<mixed> $config
	 * @return ConfigArray
	 */
	private static function validate( array $config ): array {
		$groups = array();
		foreach ( (array) ( $config['groups'] ?? array() ) as $id => $group ) {
			if ( ! is_string( $id ) || ! preg_match( self::ID_PATTERN, $id ) || ! is_array( $group ) ) {
				self::wrong( __( 'Skipped a group with an invalid ID.', 'silvertrail' ) );
				continue;
			}
			$groups[ $id ] = array(
				'name'        => (string) ( $group['name'] ?? $id ),
				'description' => (string) ( $group['description'] ?? '' ),
				'locked'      => 'essential' === $id || ! empty( $group['locked'] ),
				'consent_api' => in_array( $group['consent_api'] ?? null, ConsentApi::CATEGORIES, true ) ? (string) $group['consent_api'] : 'marketing',
			);
		}
		$groups['essential'] ??= self::default_groups()['essential'];

		$services = array();
		foreach ( (array) ( $config['services'] ?? array() ) as $id => $service ) {
			if ( ! is_string( $id ) || ! preg_match( self::ID_PATTERN, $id ) || ! is_array( $service ) ) {
				self::wrong( __( 'Skipped a service with an invalid ID.', 'silvertrail' ) );
				continue;
			}
			$group = (string) ( $service['group'] ?? '' );
			if ( ! isset( $groups[ $group ] ) ) {
				/* translators: 1: service ID, 2: group ID */
				self::wrong( sprintf( __( 'Skipped the service "%1$s" because its group "%2$s" does not exist.', 'silvertrail' ), $id, $group ) );
				continue;
			}
			$google  = self::strings( $service['google'] ?? array() );
			$unknown = array_diff( $google, self::GOOGLE_TYPES );
			if ( array() !== $unknown ) {
				/* translators: 1: service ID, 2: list of consent types */
				self::wrong( sprintf( __( 'The service "%1$s" names unknown Consent Mode types: %2$s.', 'silvertrail' ), $id, implode( ', ', $unknown ) ) );
			}
			$services[ $id ] = array(
				'group'       => $group,
				'name'        => (string) ( $service['name'] ?? $id ),
				'provider'    => (string) ( $service['provider'] ?? '' ),
				'purpose'     => (string) ( $service['purpose'] ?? '' ),
				'privacy_url' => (string) ( $service['privacy_url'] ?? '' ),
				'cookies'     => (string) ( $service['cookies'] ?? '' ),
				'expiry'      => (string) ( $service['expiry'] ?? '' ),
				'hosts'       => array_map( array( self::class, 'normalize_pattern' ), self::strings( $service['hosts'] ?? array() ) ),
				'handles'     => self::strings( $service['handles'] ?? array() ),
				'google'      => array_values( array_intersect( $google, self::GOOGLE_TYPES ) ),
				'consent'     => 'exempt' === ( $service['consent'] ?? 'required' ) ? 'exempt' : 'required',
				'tag_id'      => (string) ( $service['tag_id'] ?? '' ),
			);
		}
		$services['silvertrail'] = self::own_service();

		$mode = $config['google']['mode'] ?? 'basic';

		return array(
			'groups'   => $groups,
			'services' => $services,
			'google'   => array( 'mode' => 'advanced' === $mode ? 'advanced' : 'basic' ),
		);
	}

	/**
	 * @return array<string, Group>
	 */
	private static function default_groups(): array {
		return array(
			'essential'      => array(
				'name'        => __( 'Essential', 'silvertrail' ),
				'description' => __( 'Needed for the site to work. They are always on.', 'silvertrail' ),
				'locked'      => true,
				'consent_api' => 'functional',
			),
			'preferences'    => array(
				'name'        => __( 'Preferences', 'silvertrail' ),
				'description' => __( 'Remember your settings, such as a language or a filled-in form.', 'silvertrail' ),
				'locked'      => false,
				'consent_api' => 'preferences',
			),
			'statistics'     => array(
				'name'        => __( 'Statistics', 'silvertrail' ),
				'description' => __( 'Show us how visitors use this site, so we can improve it.', 'silvertrail' ),
				'locked'      => false,
				'consent_api' => 'statistics',
			),
			'marketing'      => array(
				'name'        => __( 'Marketing', 'silvertrail' ),
				'description' => __( 'Measure ads and show you ads that fit your interests, also on other sites.', 'silvertrail' ),
				'locked'      => false,
				'consent_api' => 'marketing',
			),
			'external-media' => array(
				'name'        => __( 'External media', 'silvertrail' ),
				'description' => __( 'Videos, maps and posts embedded from other platforms. The platform can see your visit when they load.', 'silvertrail' ),
				'locked'      => false,
				'consent_api' => 'marketing',
			),
		);
	}

	/**
	 * @return Service
	 */
	private static function own_service(): array {
		$months = (int) Settings::get( 'expiry_months' );
		return array(
			'group'       => 'essential',
			'name'        => 'Silvertrail',
			'provider'    => __( 'The owner of this site', 'silvertrail' ),
			'purpose'     => __( 'Stores your consent choices.', 'silvertrail' ),
			'privacy_url' => (string) get_privacy_policy_url(),
			'cookies'     => self::cookie(),
			/* translators: %d: number of months */
			'expiry'      => sprintf( _n( '%d month', '%d months', $months, 'silvertrail' ), $months ),
			'hosts'       => array(),
			'handles'     => array(),
			'google'      => array(),
			'consent'     => 'required',
			'tag_id'      => '',
		);
	}

	/**
	 * @return array{hash: string, previous: string, n: int, changed: int}
	 */
	private static function stored_version(): array {
		$stored = get_option( self::VERSION_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array(
			'hash'     => (string) ( $stored['hash'] ?? '' ),
			'previous' => (string) ( $stored['previous'] ?? '' ),
			'n'        => (int) ( $stored['n'] ?? 0 ),
			'changed'  => (int) ( $stored['changed'] ?? 0 ),
		);
	}

	/**
	 * @return list<string>
	 */
	private static function strings( mixed $value ): array {
		return array_values( array_filter( (array) $value, static fn ( $item ): bool => is_string( $item ) && '' !== $item ) );
	}

	private static function normalize_pattern( string $pattern ): string {
		$pattern = strtolower( trim( $pattern ) );
		$pattern = (string) preg_replace( '#^(https?:)?//#', '', $pattern );
		return (string) preg_replace( '#^www\.#', '', $pattern );
	}

	private static function host_and_path( string $url ): string {
		if ( str_starts_with( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return strtolower( $host ) . ( is_string( $path ) ? $path : '' );
	}

	private static function matches( string $target, string $pattern ): bool {
		[ $target_host, $target_path ]   = array_pad( explode( '/', $target, 2 ), 2, '' );
		[ $pattern_host, $pattern_path ] = array_pad( explode( '/', $pattern, 2 ), 2, '' );

		if ( $target_host !== $pattern_host && ! str_ends_with( $target_host, '.' . $pattern_host ) ) {
			return false;
		}
		return '' === $pattern_path || str_starts_with( $target_path, $pattern_path );
	}

	private static function wrong( string $message ): void {
		_doing_it_wrong( 'silvertrail_config', esc_html( $message ), '0.1.0' );
	}
}
