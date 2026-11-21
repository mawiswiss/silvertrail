<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\AgentPrompts;
use Silvertrail\Admin\Notices;
use Silvertrail\Admin\SettingsPage;
use Silvertrail\Settings;
use Silvertrail\WebMcp;

defined( 'ABSPATH' ) || exit;

$silvertrail_prompt_editor = SettingsPage::editor( 'markdown' );
$silvertrail_tokens        = WebMcp::tokens();

$silvertrail_prompt = static function ( string $id, string $text ) use ( $silvertrail_prompt_editor ): void {
	?>
	<details class="health-check-accordion silvertrail-accordion">
		<summary class="health-check-accordion-trigger">
			<span class="title"><?php esc_html_e( 'Show the prompt', 'silvertrail' ); ?></span>
			<span class="icon"></span>
		</summary>
		<div class="health-check-accordion-panel">
			<label for="<?php echo esc_attr( $id ); ?>" class="screen-reader-text"><?php esc_html_e( 'Prompt for an AI agent', 'silvertrail' ); ?></label>
			<textarea
				id="<?php echo esc_attr( $id ); ?>"
				class="large-text code"
				rows="16"
				readonly
				<?php if ( '' !== $silvertrail_prompt_editor ) : ?>
					data-silvertrail-editor="<?php echo esc_attr( $silvertrail_prompt_editor ); ?>"
				<?php endif; ?>
			><?php echo esc_textarea( $text ); ?></textarea>
		</div>
	</details>
	<?php
};

$silvertrail_copy = static function ( string $id ): void {
	?>
	<span class="copy-to-clipboard-container hide-if-no-js">
		<button type="button" class="button silvertrail-copy" data-silvertrail-copy="<?php echo esc_attr( $id ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><?php esc_html_e( 'Copy prompt', 'silvertrail' ); ?></button>
		<span class="success hidden" aria-hidden="true"><?php esc_html_e( 'Copied', 'silvertrail' ); ?></span>
	</span>
	<?php
};
?>

<section class="card silvertrail-section" id="silvertrail-agent-check">
	<h2><?php esc_html_e( 'Check the site with an agent', 'silvertrail' ); ?></h2>
	<?php echo wp_kses_post( SettingsPage::docs_link( 'agents/#check-a-site' ) ); ?>
	<p><?php esc_html_e( 'An agent that controls a browser goes through every consent state of a page, watches what loads and reports what slipped through before consent. Silvertrail hands it a WebMCP tool for each step, so it does not have to click the banner.', 'silvertrail' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Ask an agent', 'silvertrail' ); ?></th>
			<td>
				<ol class="silvertrail-steps">
					<li>
						<?php esc_html_e( 'Copy the prompt. It holds the brief and this site’s services with their hosts.', 'silvertrail' ); ?>
						<?php $silvertrail_copy( 'silvertrail-check-prompt' ); ?>
					</li>
					<li><?php esc_html_e( 'Add the pages to check at its end, and give it to an agent that controls a browser.', 'silvertrail' ); ?></li>
					<li><?php esc_html_e( 'Fix what it reports, or register the hosts it found as services.', 'silvertrail' ); ?></li>
				</ol>
				<?php $silvertrail_prompt( 'silvertrail-check-prompt', AgentPrompts::check() ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Connect a browser', 'silvertrail' ); ?></th>
			<td>
				<p><?php esc_html_e( 'WebMCP is an experiment in Chrome and may still change. Chrome DevTools MCP starts a Chrome with WebMCP on, for Claude Code like this:', 'silvertrail' ); ?></p>
				<p><code>claude mcp add chrome-devtools -- npx -y chrome-devtools-mcp@latest --categoryExperimentalWebmcp --categoryExperimentalThirdParty --chromeArg=--enable-features=WebMCP</code></p>
				<p class="description">
					<?php
					printf(
						/* translators: 1: chrome://flags address, 2: window.silvertrail */
						esc_html__( 'To try the tools by hand, turn on %1$s in Chrome and open DevTools › Application › WebMCP. An agent without WebMCP clicks the banner and calls %2$s instead.', 'silvertrail' ),
						'<code>chrome://flags/#enable-webmcp-testing</code>',
						'<code>window.silvertrail</code>'
					);
					?>
				</p>
			</td>
		</tr>
	</table>
</section>

<section class="card silvertrail-section" id="silvertrail-agent-setup">
	<h2><?php esc_html_e( 'Set up with an agent', 'silvertrail' ); ?></h2>
	<?php echo wp_kses_post( SettingsPage::docs_link( 'agents/#set-up-a-site' ) ); ?>
	<p><?php esc_html_e( 'A coding agent sets Silvertrail up in code and checks its own work on the command line.', 'silvertrail' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Ask an agent', 'silvertrail' ); ?></th>
			<td>
				<ol class="silvertrail-steps">
					<li>
						<?php esc_html_e( 'Copy the prompt. It holds the brief and the services this site has now.', 'silvertrail' ); ?>
						<?php $silvertrail_copy( 'silvertrail-setup-prompt' ); ?>
					</li>
					<li><?php esc_html_e( 'Add what the site loads from other services, and give it to a coding agent that works on the site.', 'silvertrail' ); ?></li>
					<li><?php esc_html_e( 'Check the result with the prompt above.', 'silvertrail' ); ?></li>
				</ol>
				<?php $silvertrail_prompt( 'silvertrail-setup-prompt', AgentPrompts::setup() ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Skill', 'silvertrail' ); ?></th>
			<td>
				<p><?php esc_html_e( 'Coding agents that read skills can install the Silvertrail skill:', 'silvertrail' ); ?></p>
				<p><code>npx skills add mawiswiss/silvertrail</code></p>
				<p class="description">
					<?php
					printf(
						/* translators: 1: skills folder, 2: docs/agents.md */
						esc_html__( 'It also ships in the plugin folder under %1$s. The guide to agents is %2$s in the same folder.', 'silvertrail' ),
						'<code>skills/silvertrail/</code>',
						'<code>docs/agents.md</code>'
					);
					?>
				</p>
			</td>
		</tr>
	</table>
</section>

<section class="card silvertrail-section" id="silvertrail-webmcp">
	<h2><?php esc_html_e( 'Visiting agents', 'silvertrail' ); ?></h2>
	<?php echo wp_kses_post( SettingsPage::docs_link( 'agents/#visiting-agents' ) ); ?>
	<p><?php esc_html_e( 'Agents that browse for a person meet the banner too. A browser that sends Global Privacy Control gets “only essential” without a banner. An agent that speaks WebMCP reads the services and answers through tools. It may allow only essential on its own, and more only with its person’s answer.', 'silvertrail' ); ?></p>
	<?php Notices::card( 'webmcp' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'WebMCP tools', 'silvertrail' ); ?><?php echo wp_kses_post( SettingsPage::in_code_label( 'webmcp' ) ); ?></th>
			<td>
				<?php
				echo Settings::get( 'webmcp' ) ? esc_html__( 'On, in every browser that supports WebMCP.', 'silvertrail' ) : esc_html__( 'Off.', 'silvertrail' );
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Trial tokens', 'silvertrail' ); ?></th>
			<td>
				<?php if ( array() === $silvertrail_tokens ) : ?>
					<p><?php esc_html_e( 'None. Until WebMCP ships, visitors’ browsers offer it only on a site with a trial token.', 'silvertrail' ); ?></p>
				<?php else : ?>
					<ul class="silvertrail-tokens">
						<?php foreach ( $silvertrail_tokens as $silvertrail_token ) : ?>
							<li>
								<?php
								if ( '' !== $silvertrail_token['problem'] ) {
									echo esc_html( $silvertrail_token['problem'] );
								} else {
									/* translators: 1: trial feature, 2: origin, 3: date */
									echo esc_html( sprintf( __( '%1$s for %2$s, valid until %3$s.', 'silvertrail' ), $silvertrail_token['feature'], $silvertrail_token['origin'], wp_date( 'Y-m-d', $silvertrail_token['expiry'] ) ) );
								}
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p class="description">
					<?php
					printf(
						/* translators: 1: webmcp_tokens, 2: silvertrail_settings */
						esc_html__( 'Register this site’s address for the WebMCP origin trial in Chrome, and add the token to %1$s in the %2$s filter. Silvertrail prints it and warns here two weeks before it runs out.', 'silvertrail' ),
						'<code>webmcp_tokens</code>',
						'<code>silvertrail_settings</code>'
					);
					echo ' ' . wp_kses_post( SettingsPage::help_button( 'silvertrail-agents', __( 'Show how', 'silvertrail' ) ) );
					?>
				</p>
			</td>
		</tr>
	</table>
</section>

<section class="card silvertrail-section" id="silvertrail-agent-more">
	<h2><?php esc_html_e( 'More prompts', 'silvertrail' ); ?></h2>
	<?php echo wp_kses_post( SettingsPage::docs_link( 'agents/' ) ); ?>
	<ul>
		<li>
			<a href="<?php echo esc_url( SettingsPage::url( 'design' ) . '#silvertrail-custom-styles' ); ?>"><?php esc_html_e( 'Design › Custom styles', 'silvertrail' ); ?></a>:
			<?php esc_html_e( 'a prompt that styles the banner to fit the theme.', 'silvertrail' ); ?>
		</li>
		<li>
			<a href="<?php echo esc_url( SettingsPage::url( 'tools' ) . '#silvertrail-scan' ); ?>"><?php esc_html_e( 'Tools › Leak check', 'silvertrail' ); ?></a>:
			<?php esc_html_e( 'after a scan, a prompt that asks about each unknown service it found.', 'silvertrail' ); ?>
		</li>
	</ul>
</section>
