<?php

declare(strict_types=1);

namespace Silvertrail\Tests;

use Silvertrail\Admin\AgentPrompts;
use Silvertrail\Admin\Notices;
use Silvertrail\Admin\SettingsPage;
use Silvertrail\Settings;
use Silvertrail\WebMcp;
use WP_UnitTestCase;

final class WebMcpTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		remove_all_filters( 'silvertrail_settings' );
		remove_all_filters( 'home_url' );
		Settings::reset();
		Notices::reset();
		parent::tear_down();
	}

	public function test_a_token_is_read_without_its_signature(): void {
		$expiry = time() + 30 * DAY_IN_SECONDS;

		$this->assertSame(
			array(
				'origin'     => 'http://example.org:80',
				'feature'    => 'WebMCP',
				'expiry'     => $expiry,
				'subdomains' => false,
			),
			WebMcp::read( $this->token( 'http://example.org:80', $expiry ) )
		);
		$this->assertNull( WebMcp::read( 'not a token' ) );
		$this->assertNull( WebMcp::read( base64_encode( str_repeat( "\0", 80 ) ) ) );
	}

	public function test_a_valid_token_is_printed_and_has_no_problem(): void {
		$token = $this->token( home_url(), time() + 30 * DAY_IN_SECONDS );
		$this->tokens( array( $token ) );

		$this->assertSame( '', WebMcp::tokens()[0]['problem'] );
		$this->assertStringContainsString( '<meta http-equiv="origin-trial" content="' . $token . '">', $this->head() );
		$this->assertSame( array(), $this->webmcp_notices() );
	}

	public function test_an_expired_token_is_left_out_and_reported(): void {
		$this->tokens( array( $this->token( home_url(), time() - DAY_IN_SECONDS ) ) );

		$this->assertSame( '', $this->head() );
		$this->assertStringContainsString( 'expired on ' . wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ), WebMcp::tokens()[0]['problem'] );
	}

	public function test_an_unreadable_token_is_printed_and_reported(): void {
		$this->tokens( array( 'NewTokenFormat' ) );

		$this->assertStringContainsString( 'content="NewTokenFormat"', $this->head() );
		$this->assertStringContainsString( 'cannot read', WebMcp::tokens()[0]['problem'] );
	}

	public function test_a_token_for_another_address_is_reported_unless_it_covers_subdomains(): void {
		add_filter( 'home_url', static fn(): string => 'http://shop.example.org/' );
		$expiry = time() + 30 * DAY_IN_SECONDS;
		$this->tokens(
			array(
				$this->token( 'http://example.org', $expiry, true ),
				$this->token( 'http://example.org', $expiry ),
				$this->token( 'https://shop.example.org', $expiry ),
			)
		);

		$problems = array_column( WebMcp::tokens(), 'problem' );

		$this->assertSame( '', $problems[0] );
		$this->assertStringContainsString( 'not for this site at http://shop.example.org:80', $problems[1] );
		$this->assertNotSame( '', $problems[2] );
	}

	public function test_nothing_is_printed_with_the_tools_off(): void {
		$this->tokens( array( $this->token( home_url(), time() + 30 * DAY_IN_SECONDS ) ), false );

		$this->assertSame( '', $this->head() );
	}

	public function test_two_weeks_before_expiry_the_overview_points_to_the_agents_tab(): void {
		$this->tokens( array( $this->token( home_url(), time() + 3 * DAY_IN_SECONDS ) ) );

		$this->assertCount( 1, $this->webmcp_notices() );
		$this->assertContains(
			array(
				'type'    => 'warning',
				'summary' => 'A WebMCP trial token needs attention.',
				'url'     => SettingsPage::url( 'agents' ) . '#silvertrail-webmcp',
				'where'   => 'Agents › Visiting agents',
			),
			Notices::overview( 'services' )
		);
	}

	public function test_the_check_prompt_holds_the_brief_and_the_services(): void {
		$prompt = AgentPrompts::check();

		$this->assertStringStartsWith( '## Brief: check the site', $prompt );
		$this->assertStringNotContainsString( '## Brief: set up the site', $prompt );
		$this->assertStringContainsString( '`youtube` YouTube (external-media): needs consent; hosts: youtube.com, youtube-nocookie.com, youtu.be, ytimg.com', $prompt );
		$this->assertStringContainsString( '## Pages to check', $prompt );
		$this->assertStringStartsWith( '## Brief: set up the site', AgentPrompts::setup() );
	}

	public function test_the_agents_tab_renders_both_prompts_and_the_tokens(): void {
		$this->tokens( array( $this->token( home_url(), time() + 30 * DAY_IN_SECONDS ) ) );

		ob_start();
		require dirname( SILVERTRAIL_FILE ) . '/includes/Admin/tabs/agents.php';
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="silvertrail-check-prompt"', $html );
		$this->assertStringContainsString( 'id="silvertrail-setup-prompt"', $html );
		$this->assertStringContainsString( 'WebMCP for ' . home_url() . ', valid until', $html );
	}

	/**
	 * @param list<string> $tokens
	 */
	private function tokens( array $tokens, bool $on = true ): void {
		add_filter(
			'silvertrail_settings',
			static function ( array $settings ) use ( $tokens, $on ): array {
				$settings['webmcp']        = $on;
				$settings['webmcp_tokens'] = $tokens;
				return $settings;
			}
		);
	}

	private function token( string $origin, int $expiry, bool $subdomains = false ): string {
		$payload = array(
			'origin'  => $origin,
			'feature' => 'WebMCP',
			'expiry'  => $expiry,
		);
		if ( $subdomains ) {
			$payload['isSubdomain'] = true;
		}
		$json = (string) wp_json_encode( $payload );
		return base64_encode( chr( 3 ) . str_repeat( "\0", 64 ) . pack( 'N', strlen( $json ) ) . $json ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- a test token.
	}

	private function head(): string {
		ob_start();
		WebMcp::head();
		return (string) ob_get_clean();
	}

	/**
	 * @return list<array<string, string>>
	 */
	private function webmcp_notices(): array {
		return array_values( array_filter( Notices::all(), static fn( array $notice ): bool => 'webmcp' === $notice['card'] ) );
	}
}
