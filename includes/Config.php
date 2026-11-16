<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type Group array{name: string, description: string, locked: bool, consent_api: string, in_place: bool}
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
 *     google: array{mode: 'basic'|'advanced', url_passthrough: bool}
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

	private const KEYS = array(
		'config'  => array( 'groups', 'services', 'google' ),
		'group'   => array( 'name', 'description', 'locked', 'consent_api', 'in_place' ),
		'service' => array( 'group', 'name', 'provider', 'purpose', 'privacy_url', 'cookies', 'expiry', 'hosts', 'handles', 'google', 'consent', 'tag_id' ),
		'google'  => array( 'mode', 'url_passthrough' ),
	);

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
	 * Sites of a network share the host, so each one keeps its own cookie. A scan keeps its own too.
	 */
	public static function cookie( ?bool $scan = null ): string {
		$name = ( $scan ?? Scan::active() ) ? self::COOKIE . '_scan' : self::COOKIE;
		return is_multisite() && ! is_main_site() ? $name . '_' . get_current_blog_id() : $name;
	}

	public static function cookie_path(): string {
		$path = trim( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ), '/' );
		return '/' . $path;
	}

	/**
	 * Services in a group asked in place, such as embeds, ask on their placeholder instead.
	 */
	public static function needs_banner(): bool {
		$config = self::get();
		foreach ( self::consent_ids() as $id ) {
			if ( ! $config['groups'][ $config['services'][ $id ]['group'] ]['in_place'] ) {
				return true;
			}
		}
		return false;
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
					'1.0.0'
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
			_doing_it_wrong( __METHOD__, esc_html__( 'Read the Silvertrail config on init or later.', 'silvertrail' ), '1.0.0' );
		}

		$config = array(
			'groups'   => self::default_groups(),
			'services' => array( 'silvertrail' => self::own_service() ) + Presets::enabled(),
			'google'   => array(
				'mode'            => 'basic',
				'url_passthrough' => false,
			),
		);

		do_action( 'silvertrail_register' );

		foreach ( self::$registered_groups as $id => $args ) {
			$config['groups'][ $id ] = array_replace( $config['groups'][ $id ] ?? array(), $args );
		}
		$presets = Presets::all();
		foreach ( self::$registered_services as $id => $args ) {
			$config['services'][ $id ] = array_replace( $config['services'][ $id ] ?? $presets[ $id ]['service'] ?? array(), $args );
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
		$consent_mode = is_array( $config['google'] ?? null ) ? $config['google'] : array();
		$checks       = array(
			'config' => $config,
			'google' => $consent_mode,
		);
		foreach ( $checks as $type => $values ) {
			$keys = self::unknown( $values, $type );
			if ( '' !== $keys ) {
				/* translators: 1: config or google, 2: list of keys */
				self::wrong( sprintf( __( 'The %1$s array has unknown keys: %2$s.', 'silvertrail' ), $type, $keys ) );
			}
		}
		$mode = $consent_mode['mode'] ?? 'basic';
		if ( ! in_array( $mode, array( 'basic', 'advanced' ), true ) ) {
			self::wrong( __( 'The Consent Mode must be basic or advanced, so Silvertrail uses basic.', 'silvertrail' ) );
		}

		$groups = array();
		foreach ( (array) ( $config['groups'] ?? array() ) as $id => $group ) {
			if ( ! is_string( $id ) || ! preg_match( self::ID_PATTERN, $id ) || ! is_array( $group ) ) {
				self::wrong( __( 'Skipped a group with an invalid ID.', 'silvertrail' ) );
				continue;
			}
			$keys = self::unknown( $group, 'group' );
			if ( '' !== $keys ) {
				/* translators: 1: group ID, 2: list of keys */
				self::wrong( sprintf( __( 'The group "%1$s" has unknown keys: %2$s.', 'silvertrail' ), $id, $keys ) );
			}
			$groups[ $id ] = array(
				'name'        => (string) ( $group['name'] ?? $id ),
				'description' => (string) ( $group['description'] ?? '' ),
				'locked'      => 'essential' === $id || ! empty( $group['locked'] ),
				'consent_api' => in_array( $group['consent_api'] ?? null, ConsentApi::CATEGORIES, true ) ? (string) $group['consent_api'] : 'marketing',
				'in_place'    => ! empty( $group['in_place'] ),
			);
		}
		$groups['essential'] ??= self::default_groups()['essential'];

		$services = array();
		foreach ( (array) ( $config['services'] ?? array() ) as $id => $service ) {
			if ( ! is_string( $id ) || ! preg_match( self::ID_PATTERN, $id ) || ! is_array( $service ) ) {
				self::wrong( __( 'Skipped a service with an invalid ID.', 'silvertrail' ) );
				continue;
			}
			$keys = self::unknown( $service, 'service' );
			if ( '' !== $keys ) {
				/* translators: 1: service ID, 2: list of keys */
				self::wrong( sprintf( __( 'The service "%1$s" has unknown keys: %2$s.', 'silvertrail' ), $id, $keys ) );
			}
			$group = (string) ( $service['group'] ?? '' );
			if ( ! isset( $groups[ $group ] ) ) {
				/* translators: 1: service ID, 2: group ID */
				self::wrong( sprintf( __( 'Skipped the service "%1$s" because its group "%2$s" does not exist.', 'silvertrail' ), $id, $group ) );
				continue;
			}
			if ( $groups[ $group ]['in_place'] && ( array() !== self::strings( $service['handles'] ?? array() ) || '' !== (string) ( $service['tag_id'] ?? '' ) ) ) {
				/* translators: 1: service ID, 2: group ID */
				self::wrong( sprintf( __( 'The service "%1$s" loads scripts, but its group "%2$s" asks in place, so no banner asks for it. Move it to another group.', 'silvertrail' ), $id, $group ) );
			}
			if ( ! in_array( $service['consent'] ?? 'required', array( 'required', 'exempt' ), true ) ) {
				/* translators: %s: service ID */
				self::wrong( sprintf( __( 'The service "%s" sets consent to neither required nor exempt, so it needs consent.', 'silvertrail' ), $id ) );
			}
			$pattern = Presets::all()[ $id ]['tag_id']['pattern'] ?? null;
			$tag_id  = (string) ( $service['tag_id'] ?? '' );
			if ( null !== $pattern && ! Presets::switched_on( $id ) && ! preg_match( '/' . $pattern . '/', $tag_id ) ) {
				/* translators: 1: service ID, 2: tag ID, 3: example ID */
				self::wrong( sprintf( __( 'The service "%1$s" has the tag ID "%2$s", which does not look like %3$s, so its tag is not printed.', 'silvertrail' ), $id, $tag_id, Presets::all()[ $id ]['tag_id']['example'] ?? '' ) );
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

		return array(
			'groups'   => $groups,
			'services' => $services,
			'google'   => array(
				'mode'            => 'advanced' === $mode ? 'advanced' : 'basic',
				'url_passthrough' => ! empty( $consent_mode['url_passthrough'] ),
			),
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
				'in_place'    => false,
			),
			'preferences'    => array(
				'name'        => __( 'Preferences', 'silvertrail' ),
				'description' => __( 'Remember your settings, such as a language or a filled-in form.', 'silvertrail' ),
				'locked'      => false,
				'consent_api' => 'preferences',
				'in_place'    => false,
			),
			'statistics'     => array(
				'name'        => __( 'Statistics', 'silvertrail' ),
				'description' => __( 'Show us how visitors use this site, so we can improve it.', 'silvertrail' ),
				'locked'      => false,
				'consent_api' => 'statistics',
				'in_place'    => false,
			),
			'marketing'      => array(
				'name'        => __( 'Marketing', 'silvertrail' ),
				'description' => __( 'Measure ads and show you ads that fit your interests, also on other sites.', 'silvertrail' ),
				'locked'      => false,
				'consent_api' => 'marketing',
				'in_place'    => false,
			),
			'external-media' => array(
				'name'        => __( 'External media', 'silvertrail' ),
				'description' => __( 'Videos, maps and posts embedded from other platforms. The platform can see your visit when they load.', 'silvertrail' ),
				'locked'      => false,
				'consent_api' => 'marketing',
				'in_place'    => true,
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

	/**
	 * @param array<mixed> $values
	 * @param 'config'|'group'|'service'|'google' $type
	 */
	private static function unknown( array $values, string $type ): string {
		return implode( ', ', array_diff( array_map( 'strval', array_keys( $values ) ), self::KEYS[ $type ] ) );
	}

	private static function wrong( string $message ): void {
		_doing_it_wrong( 'silvertrail_config', esc_html( $message ), '1.0.0' );
	}
}
