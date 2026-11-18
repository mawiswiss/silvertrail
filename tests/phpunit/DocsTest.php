<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use ReflectionClass;
use ReflectionMethod;
use Silvertrail\Cli;
use Silvertrail\Presets;
use Silvertrail\Settings;
use Silvertrail\Texts;
use WP_UnitTestCase;

final class DocsTest extends WP_UnitTestCase {

	private static function root(): string {
		return dirname( SILVERTRAIL_FILE );
	}

	private static function docs(): string {
		return implode( "\n", array_map( 'file_get_contents', glob( self::root() . '/docs/*.md' ) ?: array() ) );
	}

	/**
	 * @return list<string>
	 */
	private static function found( string $pattern, string $subject ): array {
		preg_match_all( $pattern, $subject, $found );
		return array_values( array_unique( $found[1] ) );
	}

	private function assertDocumented( string $needle, string $what ): void {
		$this->assertStringContainsString( $needle, self::docs(), "docs/ names {$what} {$needle}." );
	}

	public function test_the_index_lists_every_page(): void {
		$index = (string) file_get_contents( self::root() . '/docs/README.md' );
		$pages = substr( $index, (int) strpos( $index, "\n## Pages\n" ) );
		foreach ( glob( self::root() . '/docs/*.md' ) ?: array() as $file ) {
			if ( 'README.md' !== basename( $file ) ) {
				$this->assertStringContainsString( '](' . basename( $file ) . ')', $pages, 'docs/README.md lists ' . basename( $file ) . ' under Pages.' );
			}
		}
	}

	public function test_the_docs_name_every_setting_text_and_preset(): void {
		foreach ( array_diff( array_keys( Settings::defaults() ), array( 'presets', 'texts' ) ) as $key ) {
			$this->assertDocumented( "`{$key}`", 'the setting' );
		}
		foreach ( array_keys( Texts::defaults() ) as $key ) {
			$this->assertDocumented( "`{$key}`", 'the text' );
		}
		foreach ( array_keys( Presets::all() ) as $id ) {
			$this->assertDocumented( "`{$id}`", 'the preset' );
		}
	}

	public function test_the_docs_name_every_config_key(): void {
		$config = silvertrail_config();
		$keys   = array_merge(
			array_keys( $config ),
			array_keys( $config['services']['silvertrail'] ),
			array_keys( $config['groups']['essential'] ),
			array_keys( $config['google'] )
		);
		foreach ( array_unique( $keys ) as $key ) {
			$this->assertDocumented( "`{$key}`", 'the config key' );
		}
	}

	public function test_the_docs_name_every_hook(): void {
		$source = '';
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::root() . '/includes' ) ) as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$source .= file_get_contents( $file->getPathname() );
			}
		}
		$hooks = self::found( "/(?:apply_filters|do_action)\(\s*'(silvertrail_[a-z_]+)'/", $source );
		$this->assertNotEmpty( $hooks );
		foreach ( $hooks as $hook ) {
			$this->assertDocumented( "`{$hook}`", 'the hook' );
		}
	}

	public function test_the_docs_name_every_command(): void {
		foreach ( ( new ReflectionClass( Cli::class ) )->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
			if ( $method->isStatic() || Cli::class !== $method->getDeclaringClass()->getName() ) {
				continue;
			}
			preg_match( '/@subcommand ([a-z-]+)/', (string) $method->getDocComment(), $subcommand );
			$this->assertDocumented( 'wp silvertrail ' . ( $subcommand[1] ?? $method->getName() ), 'the command' );
		}
	}

	public function test_the_docs_name_every_ability_tool_and_browser_call(): void {
		$abilities = self::found( "/self::add\(\s*'([a-z-]+)'/", (string) file_get_contents( self::root() . '/includes/Abilities.php' ) );
		$this->assertNotEmpty( $abilities );
		foreach ( $abilities as $ability ) {
			$this->assertDocumented( "`silvertrail/{$ability}`", 'the ability' );
		}

		$tools = self::found( "/name: '([a-z_]+)'/", (string) file_get_contents( self::root() . '/assets/webmcp.js' ) );
		$this->assertNotEmpty( $tools );
		foreach ( $tools as $tool ) {
			$this->assertDocumented( "`{$tool}`", 'the WebMCP tool' );
		}

		preg_match( '/window\.silvertrail = \{([^}]+)\}/', (string) file_get_contents( self::root() . '/assets/silvertrail.js' ), $api );
		$this->assertNotEmpty( $api );
		foreach ( array_map( 'trim', explode( ',', $api[1] ) ) as $call ) {
			$this->assertDocumented( "`{$call}(", 'the browser call' );
		}
	}

	public function test_the_docs_name_every_template(): void {
		foreach ( glob( self::root() . '/templates/*.php' ) ?: array() as $file ) {
			$this->assertDocumented( '`' . basename( $file ) . '`', 'the template' );
		}
	}
}
