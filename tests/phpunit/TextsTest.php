<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Settings;
use Silvertrail\Texts;
use WP_UnitTestCase;

final class TextsTest extends WP_UnitTestCase {

	private string $file = '';

	public function set_up(): void {
		parent::set_up();
		Texts::reset();
	}

	public function tear_down(): void {
		remove_all_filters( 'silvertrail_translation' );
		remove_all_filters( 'silvertrail_texts' );
		delete_option( Settings::OPTION );
		if ( '' !== $this->file ) {
			wp_delete_file( $this->file );
		}
		parent::tear_down();
	}

	public function test_the_layers_stack_up_to_the_filter(): void {
		$this->translate(
			array(
				'title'  => 'From the developer',
				'intro'  => 'Developer intro',
				'reopen' => 'Developer reopen',
			)
		);
		$this->edit(
			'en_US',
			array(
				'title' => 'From the admin',
				'intro' => 'Admin intro',
			)
		);
		add_filter(
			'silvertrail_texts',
			static function ( array $texts ): array {
				$texts['title'] = 'From the filter';
				return $texts;
			}
		);

		$texts = Texts::all();

		$this->assertSame( 'From the filter', $texts['title'] );
		$this->assertSame( 'Admin intro', $texts['intro'] );
		$this->assertSame( 'Developer reopen', $texts['reopen'] );
		$this->assertSame( 'Accept all', $texts['accept_all'] );
	}

	public function test_a_developer_translation_can_be_a_json_file(): void {
		$this->file = get_temp_dir() . 'silvertrail-texts-test.json';
		file_put_contents( $this->file, wp_json_encode( array( 'accept_all' => 'Alle akzeptieren' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$file = $this->file;
		add_filter(
			'silvertrail_translation',
			static fn( $translation, string $locale ) => 'en_US' === $locale ? $file : $translation,
			10,
			2
		);

		$this->assertSame( 'Alle akzeptieren', Texts::all()['accept_all'] );
	}

	public function test_the_theme_folder_holds_a_translation_per_language(): void {
		global $wp_stylesheet_path, $wp_template_path;
		$theme = array( $wp_stylesheet_path, $wp_template_path );
		$dir   = get_temp_dir() . 'silvertrail-theme-' . wp_generate_password( 6, false );
		wp_mkdir_p( $dir . '/silvertrail' );
		file_put_contents( $dir . '/silvertrail/texts.json', '{ "title": "Any language", "save": "Save" }' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $dir . '/silvertrail/texts-en_US.json', '{ "title": "English" }' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$wp_stylesheet_path = $dir; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_template_path   = $dir; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$english = Texts::base( 'en_US' )['title'];
		$other   = Texts::base( 'fr_FR' );
		$from    = Texts::translation( 'en_US' )['from'] ?? '';

		list( $wp_stylesheet_path, $wp_template_path ) = $theme; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		array_map( 'wp_delete_file', glob( $dir . '/silvertrail/*.json' ) ?: array() );
		rmdir( $dir . '/silvertrail' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir

		$this->assertSame( 'English', $english );
		$this->assertSame( 'Any language', $other['title'] );
		$this->assertSame( 'Save', $other['save'] );
		$this->assertStringEndsWith( 'silvertrail/texts-en_US.json', $from );
	}

	public function test_a_missing_file_is_skipped(): void {
		$this->translate( '/nowhere/texts.json' );

		$this->assertSame( Texts::defaults(), Texts::base( 'en_US' ) );
	}

	public function test_broken_entries_are_dropped_and_reported(): void {
		$this->setExpectedIncorrectUsage( Texts::class );
		$this->translate(
			array(
				'title'      => 'Kept',
				'unknown'    => 'Dropped',
				'embed_text' => '50% of %s',
				'intro'      => array( 'not text' ),
			)
		);

		$texts = Texts::base( 'en_US' );

		$this->assertSame( 'Kept', $texts['title'] );
		$this->assertSame( Texts::defaults()['embed_text'], $texts['embed_text'] );
		$this->assertSame( Texts::defaults()['intro'], $texts['intro'] );
		$this->assertArrayNotHasKey( 'unknown', $texts );
	}

	public function test_admin_edits_apply_only_in_their_language(): void {
		$this->edit( 'de_CH', array( 'title' => 'Datenschutz' ) );

		$this->assertSame( 'Privacy settings', Texts::all()['title'] );
		$this->assertSame( array( 'title' => 'Datenschutz' ), Texts::custom( 'de_CH' ) );
	}

	public function test_placeholders_must_survive(): void {
		$this->assertSame( array( 'embed_remember' => 'Immer %s zulassen' ), Texts::validate( array( 'embed_remember' => 'Immer %s zulassen' ) )[0] );
		$this->assertSame( array( 'embed_remember' => '%1$s immer zulassen' ), Texts::validate( array( 'embed_remember' => '%1$s immer zulassen' ) )[0] );
		$this->assertSame( array(), Texts::validate( array( 'embed_remember' => 'Immer zulassen' ) )[0] );
		$this->assertSame( array(), Texts::validate( array( 'embed_remember' => '100% %s' ) )[0] );
		$this->assertSame( array( 'title' => '100% private' ), Texts::validate( array( 'title' => '100% private' ) )[0] );
	}

	/**
	 * @param string|array<string, mixed> $translation
	 */
	private function translate( string|array $translation ): void {
		add_filter( 'silvertrail_translation', static fn() => $translation );
	}

	/**
	 * @param array<string, string> $texts
	 */
	private function edit( string $locale, array $texts ): void {
		update_option( Settings::OPTION, array_replace( Settings::defaults(), array( 'texts' => array( $locale => $texts ) ) ) );
	}
}
