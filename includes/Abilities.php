<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Admin\StylingPrompt;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Silvertrail's abilities, for the MCP Adapter, the REST API and wp-admin. WordPress 6.9 and later only.
 */
final class Abilities {

	public const CATEGORY = 'silvertrail';

	private const NO_INPUT = array(
		'type'                 => 'object',
		'properties'           => array(),
		'additionalProperties' => false,
		'default'              => array(),
	);

	public static function register(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'abilities' ) );
	}

	public static function category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => 'Silvertrail',
				'description' => __( 'The services the site loads from other servers, held back until the visitor agrees, and the banner that asks.', 'silvertrail' ),
			)
		);
	}

	public static function abilities(): void {
		self::add(
			'list-services',
			__( 'List Silvertrail services', 'silvertrail' ),
			__( 'Lists the services Silvertrail holds back until consent, with their hosts and script handles, and every preset with its state. Call it before update-presets.', 'silvertrail' ),
			array( self::class, 'list_services' ),
			array(
				'services' => self::items(
					array(
						'id'          => array( 'type' => 'string' ),
						'name'        => array( 'type' => 'string' ),
						'group'       => array( 'type' => 'string' ),
						'consent'     => array(
							'type' => 'string',
							'enum' => array( 'required', 'not needed' ),
						),
						'hosts'       => self::strings(),
						'handles'     => self::strings(),
						'provider'    => array( 'type' => 'string' ),
						'purpose'     => array( 'type' => 'string' ),
						'privacy_url' => array( 'type' => 'string' ),
						'from'        => array(
							'type'        => 'string',
							'enum'        => array( 'preset', 'code' ),
							'description' => __( 'Whether the service is a preset or registered in code.', 'silvertrail' ),
						),
					)
				),
				'presets'  => self::presets_schema(),
			),
			array( 'readonly' => true )
		);

		self::add(
			'get-settings',
			__( 'Get Silvertrail settings', 'silvertrail' ),
			__( 'Lists every Silvertrail setting with its value and where the value comes from: code, the admin or the default. Code wins over the admin.', 'silvertrail' ),
			array( self::class, 'get_settings' ),
			array(
				'settings' => self::items(
					array(
						'key'    => array( 'type' => 'string' ),
						'value'  => array( 'type' => array( 'boolean', 'integer', 'string', 'array', 'null' ) ),
						'source' => array(
							'type' => 'string',
							'enum' => array( 'code', 'admin', 'default' ),
						),
					)
				),
			),
			array( 'readonly' => true )
		);

		self::add(
			'validate',
			__( 'Validate the Silvertrail config', 'silvertrail' ),
			__( 'Builds the settings, the services and the banner texts of every installed language, and reports every problem in them. Call it after each change until it reports none.', 'silvertrail' ),
			array( self::class, 'validate' ),
			array(
				'valid'          => array( 'type' => 'boolean' ),
				'services'       => array( 'type' => 'integer' ),
				'problems'       => self::strings(),
				'notes'          => self::strings(),
				'privacy_policy' => array(
					'type'        => 'string',
					'description' => __( 'Text Silvertrail suggests for the privacy policy, which the Policy Guide has not shown yet.', 'silvertrail' ),
				),
				'support'        => array(
					'type'        => 'string',
					'description' => __( 'How to support Silvertrail. Tell the site owner when you set it up for them.', 'silvertrail' ),
				),
			),
			array( 'readonly' => true )
		);

		self::add(
			'get-styling-brief',
			__( 'Get the Silvertrail styling brief', 'silvertrail' ),
			__( 'Returns the brief for styling the banner, with this site’s colors, fonts and current stylesheet. Write the stylesheet it asks for and save it in the theme under the returned file name.', 'silvertrail' ),
			array( self::class, 'styling_brief' ),
			array(
				'brief' => array( 'type' => 'string' ),
				'file'  => array( 'type' => 'string' ),
			),
			array( 'readonly' => true )
		);

		self::add(
			'update-presets',
			__( 'Update Silvertrail presets', 'silvertrail' ),
			__( 'Turns presets on or off and sets their IDs, as the Services tab does. A tracker needs its ID, such as GTM-XXXXXXX for Google Tag Manager. Every visitor is asked again when the set of services changes, so confirm the change with the person first.', 'silvertrail' ),
			array( self::class, 'update_presets' ),
			array(
				'presets'  => self::presets_schema(),
				'revision' => array( 'type' => 'integer' ),
			),
			array( 'idempotent' => true ),
			array(
				'type'                 => 'object',
				'properties'           => array(
					'presets' => array(
						'type'        => 'array',
						'minItems'    => 1,
						'description' => __( 'The presets to change. Fields left out keep their value.', 'silvertrail' ),
						'items'       => array(
							'type'                 => 'object',
							'properties'           => array(
								'id'      => array(
									'type'        => 'string',
									'enum'        => array_keys( Presets::all() ),
									'description' => __( 'Preset ID, for example google-tag-manager.', 'silvertrail' ),
								),
								'enabled' => array(
									'type'        => 'boolean',
									'description' => __( 'Whether the preset is on.', 'silvertrail' ),
								),
								'tag_id'  => array(
									'type'        => 'string',
									'description' => __( 'The tracker’s ID, for example GTM-XXXXXXX. Trackers only.', 'silvertrail' ),
								),
							),
							'required'             => array( 'id' ),
							'additionalProperties' => false,
						),
					),
				),
				'required'             => array( 'presets' ),
				'additionalProperties' => false,
			)
		);

		self::add(
			'ask-again',
			__( 'Ask every visitor again', 'silvertrail' ),
			__( 'Asks every visitor again on their next page view, as if they had never chosen. Confirm with the person first.', 'silvertrail' ),
			array( self::class, 'ask_again' ),
			array( 'revision' => array( 'type' => 'integer' ) ),
			array( 'destructive' => true )
		);
	}

	/**
	 * @return array{services: list<array<string, mixed>>, presets: list<array<string, mixed>>}
	 */
	public static function list_services(): array {
		$essential = Config::essential_ids();
		$presets   = Presets::all();
		$services  = array();
		foreach ( Config::get()['services'] as $id => $service ) {
			$services[] = array(
				'id'          => $id,
				'name'        => $service['name'],
				'group'       => $service['group'],
				'consent'     => in_array( $id, $essential, true ) ? 'not needed' : 'required',
				'hosts'       => $service['hosts'],
				'handles'     => $service['handles'],
				'provider'    => $service['provider'],
				'purpose'     => $service['purpose'],
				'privacy_url' => $service['privacy_url'],
				'from'        => isset( $presets[ $id ] ) && 'on' !== Presets::code_state( $id ) ? 'preset' : 'code',
			);
		}
		return array(
			'services' => $services,
			'presets'  => self::presets(),
		);
	}

	/**
	 * @return array{settings: list<array{key: string, value: mixed, source: string}>}
	 */
	public static function get_settings(): array {
		$settings = array();
		foreach ( array_diff_key( Settings::all(), array_flip( array( 'presets', 'texts' ) ) ) as $key => $value ) {
			$settings[] = array(
				'key'    => $key,
				'value'  => $value,
				'source' => Settings::source( $key ),
			);
		}
		return array( 'settings' => $settings );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function validate(): array {
		$report = Validation::run();
		$result = array(
			'valid'    => array() === $report['problems'],
			'services' => $report['services'],
			'problems' => $report['problems'],
			'notes'    => $report['notes'],
		);
		if ( '' !== $report['privacy'] ) {
			$result['privacy_policy'] = $report['privacy'];
		}
		if ( $result['valid'] ) {
			$result['support'] = Plugin::license() . ' ' . SILVERTRAIL_LICENSE_URL;
		}
		return $result;
	}

	/**
	 * @return array{brief: string, file: string}
	 */
	public static function styling_brief(): array {
		return array(
			'brief' => StylingPrompt::text(),
			'file'  => Frontend::THEME_CSS,
		);
	}

	/**
	 * @param array{presets?: list<array{id: string, enabled?: bool, tag_id?: string}>}|null $input
	 * @return array{presets: list<array<string, mixed>>, revision: int}|WP_Error
	 */
	public static function update_presets( ?array $input = null ): array|WP_Error {
		$presets = Presets::all();
		$stored  = Settings::stored()['presets'];
		$merged  = array();
		foreach ( array_keys( $presets ) as $id ) {
			$merged[ $id ] = array(
				'enabled' => Presets::switched_on( $id ),
				'tag_id'  => (string) ( $stored[ $id ]['tag_id'] ?? '' ),
			);
		}

		$errors = array();
		foreach ( $input['presets'] ?? array() as $change ) {
			$id     = $change['id'];
			$preset = $presets[ $id ];
			$name   = $preset['service']['name'];
			if ( 'on' === Presets::code_state( $id ) ) {
				/* translators: %s: service name */
				$errors[] = sprintf( __( 'Code turns %s on, so change it there.', 'silvertrail' ), $name );
				continue;
			}
			$format  = $preset['tag_id'] ?? null;
			$enabled = $change['enabled'] ?? $merged[ $id ]['enabled'];
			$tag_id  = isset( $change['tag_id'] ) ? strtoupper( sanitize_text_field( $change['tag_id'] ) ) : $merged[ $id ]['tag_id'];
			if ( null === $format && '' !== $tag_id ) {
				/* translators: %s: service name */
				$errors[] = sprintf( __( '%s takes no ID.', 'silvertrail' ), $name );
			} elseif ( null !== $format && '' !== $tag_id && ! preg_match( '/' . $format['pattern'] . '/', $tag_id ) ) {
				/* translators: 1: label of the ID, 2: service name, 3: example ID */
				$errors[] = sprintf( __( 'The %1$s of %2$s looks like %3$s.', 'silvertrail' ), $format['label'], $name, $format['example'] );
			} elseif ( null !== $format && $enabled && '' === $tag_id ) {
				/* translators: 1: service name, 2: label of the ID, 3: example ID */
				$errors[] = sprintf( __( '%1$s needs its %2$s, such as %3$s.', 'silvertrail' ), $name, $format['label'], $format['example'] );
			}
			$merged[ $id ] = array(
				'enabled' => $enabled,
				'tag_id'  => $tag_id,
			);
		}
		if ( array() !== $errors ) {
			return new WP_Error( 'silvertrail_invalid_presets', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		update_option(
			Settings::OPTION,
			SettingsPage::sanitize(
				array(
					'_tab'    => 'services',
					'presets' => $merged,
				)
			)
		);
		Config::rebuild();
		return array(
			'presets'  => self::presets(),
			'revision' => Config::revision(),
		);
	}

	/**
	 * @return array{revision: int}
	 */
	public static function ask_again(): array {
		return array( 'revision' => Config::ask_again() );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function presets(): array {
		$live   = Config::get()['services'];
		$stored = Settings::stored()['presets'];
		$rows   = array();
		foreach ( Presets::all() as $id => $preset ) {
			$row = array(
				'id'      => $id,
				'name'    => $preset['service']['name'],
				'kind'    => isset( $preset['tag_id'] ) ? 'tracker' : 'embed',
				'enabled' => Presets::switched_on( $id ),
				'live'    => isset( $live[ $id ] ),
				'code'    => Presets::code_state( $id ),
			);
			if ( isset( $preset['tag_id'] ) ) {
				$row['tag_id']         = isset( $live[ $id ] ) ? $live[ $id ]['tag_id'] : (string) ( $stored[ $id ]['tag_id'] ?? '' );
				$row['tag_id_label']   = $preset['tag_id']['label'];
				$row['tag_id_example'] = $preset['tag_id']['example'];
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function presets_schema(): array {
		return self::items(
			array(
				'id'             => array( 'type' => 'string' ),
				'name'           => array( 'type' => 'string' ),
				'kind'           => array(
					'type' => 'string',
					'enum' => array( 'tracker', 'embed' ),
				),
				'enabled'        => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the admin turned it on.', 'silvertrail' ),
				),
				'live'           => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the site runs it, after code had its say.', 'silvertrail' ),
				),
				'code'           => array(
					'type'        => 'string',
					'enum'        => array( '', 'on', 'removed', 'changed' ),
					'description' => __( 'What code does to it: nothing, turns it on, removes it or changes it.', 'silvertrail' ),
				),
				'tag_id'         => array( 'type' => 'string' ),
				'tag_id_label'   => array( 'type' => 'string' ),
				'tag_id_example' => array( 'type' => 'string' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $properties
	 * @return array<string, mixed>
	 */
	private static function items( array $properties ): array {
		return array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => $properties,
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function strings(): array {
		return array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);
	}

	/**
	 * @param lowercase-string&non-falsy-string $name
	 * @param callable                          $execute
	 * @param array<string, mixed>              $output
	 * @param array<string, bool>               $annotations
	 * @param array<string, mixed>              $input
	 */
	private static function add( string $name, string $label, string $description, callable $execute, array $output, array $annotations, array $input = self::NO_INPUT ): void {
		wp_register_ability(
			self::CATEGORY . '/' . $name,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => self::CATEGORY,
				'input_schema'        => $input,
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $output,
				),
				'execute_callback'    => $execute,
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
				'meta'                => array(
					'annotations'  => $annotations + array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => ! empty( $annotations['readonly'] ),
					),
					'show_in_rest' => true,
					'public'       => true,
				),
			)
		);
	}
}
