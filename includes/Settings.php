<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

/**
 * @phpstan-type PresetSetting array{enabled: bool, tag_id: string}
 * @phpstan-type SettingsArray array{
 *     layout: 'modal'|'box',
 *     require_choice: bool,
 *     appearance: 'theme'|'light'|'dark',
 *     texts: array<string, array<string, string>>,
 *     floating_button: bool,
 *     load_css: bool,
 *     imprint_page: int,
 *     presets: array<string, PresetSetting>,
 *     expiry_months: int,
 *     records: bool,
 *     retention_days: int,
 *     leak_check: bool,
 *     gpc: bool,
 *     trail: bool,
 *     url_passthrough: bool
 * }
 */
final class Settings {

	public const OPTION = 'silvertrail';

	/**
	 * @return SettingsArray
	 */
	public static function defaults(): array {
		return array(
			'layout'          => 'modal',
			'require_choice'  => true,
			'appearance'      => 'theme',
			'texts'           => array(),
			'floating_button' => false,
			'load_css'        => true,
			'imprint_page'    => 0,
			'presets'         => array(),
			'expiry_months'   => 6,
			'records'         => false,
			'retention_days'  => 365,
			'leak_check'      => false,
			'gpc'             => true,
			'trail'           => true,
			'url_passthrough' => false,
		);
	}

	/**
	 * @return SettingsArray
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		/** @var SettingsArray */
		return array_replace( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? null;
	}
}
