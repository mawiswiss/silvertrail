<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Admin\StylingPrompt;
use Silvertrail\Design;
use Silvertrail\Frontend;
use Silvertrail\Settings;
use Silvertrail\Texts;

defined( 'ABSPATH' ) || exit;

$silvertrail_settings      = Settings::all();
$silvertrail_privacy       = (int) get_option( 'wp_page_for_privacy_policy' );
$silvertrail_locale        = get_locale();
$silvertrail_base          = Texts::base( $silvertrail_locale );
$silvertrail_current       = array_replace( $silvertrail_base, Texts::custom( $silvertrail_locale ) );
$silvertrail_changed       = array_keys( array_diff_assoc( $silvertrail_current, $silvertrail_base ) );
$silvertrail_translation   = Texts::translation( $silvertrail_locale );
$silvertrail_editor        = SettingsPage::editor( 'json' );
$silvertrail_prompt_editor = SettingsPage::editor( 'markdown' );
$silvertrail_theme_css     = is_file( get_theme_file_path( Frontend::THEME_CSS ) );
$silvertrail_appearances   = array(
	'theme' => array( __( 'Like the theme', 'silvertrail' ), __( 'As the theme sets it', 'silvertrail' ) ),
	'light' => array( __( 'Light', 'silvertrail' ), __( 'Its lightest color behind', 'silvertrail' ) ),
	'dark'  => array( __( 'Dark', 'silvertrail' ), __( 'Its darkest color behind', 'silvertrail' ) ),
);
$silvertrail_swatch        = static function ( string $mode ): string {
	$tokens = array_intersect_key( Design::tokens( $mode ), array_flip( array( 'background', 'text', 'accent', 'on-accent', 'radius' ) ) );
	return implode( ';', array_map( static fn( string $key, string $value ): string => "--silvertrail-{$key}:{$value}", array_keys( $tokens ), $tokens ) );
};
?>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Look', 'silvertrail' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Position', 'silvertrail' ); ?><?php echo SettingsPage::in_code_label( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?></th>
			<td>
				<fieldset class="silvertrail-cards">
					<legend class="screen-reader-text"><?php esc_html_e( 'Position', 'silvertrail' ); ?></legend>
					<label class="silvertrail-card">
						<svg class="silvertrail-card__preview silvertrail-sketch" viewBox="0 0 120 80" aria-hidden="true" focusable="false">
							<rect class="silvertrail-sketch__page" width="120" height="80"/>
							<rect class="silvertrail-sketch__content" x="10" y="10" width="50" height="5" rx="2"/>
							<rect class="silvertrail-sketch__content" x="10" y="21" width="100" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="28" width="90" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__backdrop" width="120" height="80"/>
							<rect class="silvertrail-sketch__banner" x="30" y="16" width="60" height="48" rx="3"/>
							<rect class="silvertrail-sketch__text" x="36" y="23" width="30" height="4" rx="2"/>
							<rect class="silvertrail-sketch__text" x="36" y="31" width="46" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__button" x="36" y="50" width="22" height="8" rx="2"/>
							<rect class="silvertrail-sketch__button" x="62" y="50" width="22" height="8" rx="2"/>
						</svg>
						<input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'layout' ) ); ?>" value="modal" <?php checked( $silvertrail_settings['layout'], 'modal' ); ?> <?php disabled( Settings::in_code( 'layout' ) ); ?>>
						<span class="silvertrail-card__name"><?php esc_html_e( 'Centered', 'silvertrail' ); ?></span>
						<span class="silvertrail-card__hint"><?php esc_html_e( 'Dims the page', 'silvertrail' ); ?></span>
					</label>
					<label class="silvertrail-card">
						<svg class="silvertrail-card__preview silvertrail-sketch" viewBox="0 0 120 80" aria-hidden="true" focusable="false">
							<rect class="silvertrail-sketch__page" width="120" height="80"/>
							<rect class="silvertrail-sketch__content" x="10" y="10" width="50" height="5" rx="2"/>
							<rect class="silvertrail-sketch__content" x="10" y="21" width="100" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="28" width="90" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="35" width="40" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__banner" x="62" y="38" width="52" height="36" rx="3"/>
							<rect class="silvertrail-sketch__text" x="67" y="44" width="26" height="4" rx="2"/>
							<rect class="silvertrail-sketch__text" x="67" y="51" width="40" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__button" x="67" y="61" width="19" height="7" rx="2"/>
							<rect class="silvertrail-sketch__button" x="89" y="61" width="19" height="7" rx="2"/>
						</svg>
						<input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'layout' ) ); ?>" value="box" <?php checked( $silvertrail_settings['layout'], 'box' ); ?> <?php disabled( Settings::in_code( 'layout' ) ); ?>>
						<span class="silvertrail-card__name"><?php esc_html_e( 'Corner', 'silvertrail' ); ?></span>
						<span class="silvertrail-card__hint"><?php esc_html_e( 'The page stays usable', 'silvertrail' ); ?></span>
					</label>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Appearance', 'silvertrail' ); ?><?php echo SettingsPage::in_code_label( 'appearance' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?></th>
			<td>
				<fieldset class="silvertrail-cards">
					<legend class="screen-reader-text"><?php esc_html_e( 'Appearance', 'silvertrail' ); ?></legend>
					<?php foreach ( $silvertrail_appearances as $silvertrail_mode => list( $silvertrail_label, $silvertrail_hint ) ) : ?>
						<label class="silvertrail-card">
							<span class="<?php echo esc_attr( "silvertrail-card__preview silvertrail-swatch silvertrail-swatch--{$silvertrail_mode}" ); ?>" style="<?php echo esc_attr( $silvertrail_swatch( $silvertrail_mode ) ); ?>" aria-hidden="true">
								<span class="silvertrail-swatch__text"></span>
								<span class="silvertrail-swatch__text silvertrail-swatch__text--short"></span>
								<span class="silvertrail-swatch__buttons"><span></span><span></span></span>
							</span>
							<input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'appearance' ) ); ?>" value="<?php echo esc_attr( $silvertrail_mode ); ?>" <?php checked( $silvertrail_settings['appearance'], $silvertrail_mode ); ?> <?php disabled( Settings::in_code( 'appearance' ) ); ?>>
							<span class="silvertrail-card__name"><?php echo esc_html( $silvertrail_label ); ?></span>
							<span class="silvertrail-card__hint"><?php echo esc_html( $silvertrail_hint ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'All three take colors, corners and buttons from the theme.', 'silvertrail' ); ?></p>
			</td>
		</tr>
	</table>
</section>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Custom styles', 'silvertrail' ); ?></h2>
	<p><?php esc_html_e( 'For anything beyond the theme’s colors and buttons, the theme adds its own stylesheet. An AI assistant can write it.', 'silvertrail' ); ?></p>
	<div class="<?php echo esc_attr( $silvertrail_theme_css ? 'notice notice-success notice-alt inline' : 'notice notice-info notice-alt inline' ); ?>">
		<p>
			<?php
			printf(
				/* translators: %s: silvertrail/silvertrail.css */
				$silvertrail_theme_css ? esc_html__( 'The theme adds %s. It loads after the default styles.', 'silvertrail' ) : esc_html__( 'The theme has no %s yet.', 'silvertrail' ),
				'<code>' . esc_html( Frontend::THEME_CSS ) . '</code>'
			);
			?>
		</p>
	</div>
	<ol class="silvertrail-steps">
		<li>
			<?php esc_html_e( 'Copy the prompt. It holds the brief and this site’s colors, fonts and current stylesheet.', 'silvertrail' ); ?>
			<span class="copy-to-clipboard-container hide-if-no-js">
				<button type="button" class="button" data-silvertrail-copy="silvertrail-styling-prompt"><?php esc_html_e( 'Copy prompt', 'silvertrail' ); ?></button>
				<span class="success hidden" aria-hidden="true"><?php esc_html_e( 'Copied', 'silvertrail' ); ?></span>
			</span>
		</li>
		<li><?php esc_html_e( 'Paste it into an AI assistant and describe the look you want. A screenshot of the site helps.', 'silvertrail' ); ?></li>
		<li>
			<?php
			printf(
				/* translators: %s: silvertrail/silvertrail.css */
				esc_html__( 'Save its answer in the theme as %s.', 'silvertrail' ),
				'<code>' . esc_html( Frontend::THEME_CSS ) . '</code>'
			);
			?>
		</li>
	</ol>
	<details class="silvertrail-disclosure">
		<summary><?php esc_html_e( 'Show the prompt', 'silvertrail' ); ?></summary>
		<label for="silvertrail-styling-prompt" class="screen-reader-text"><?php esc_html_e( 'Prompt for an AI assistant', 'silvertrail' ); ?></label>
		<textarea
			id="silvertrail-styling-prompt"
			class="large-text code"
			rows="16"
			readonly
			<?php if ( '' !== $silvertrail_prompt_editor ) : ?>
				data-silvertrail-editor="<?php echo esc_attr( $silvertrail_prompt_editor ); ?>"
			<?php endif; ?>
		><?php echo esc_textarea( StylingPrompt::text() ); ?></textarea>
	</details>
</section>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Behavior', 'silvertrail' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Closing', 'silvertrail' ); ?></th>
			<td>
				<?php
				SettingsPage::checkbox(
					'require_choice',
					__( 'Require a choice before the banner closes', 'silvertrail' ),
					__( 'Turned off, a click outside or Escape closes it without storing anything, and it asks again on the next page.', 'silvertrail' )
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Reopen', 'silvertrail' ); ?></th>
			<td>
				<p><?php esc_html_e( 'A small button reopens the settings on every page without a link to #silvertrail. Add such a link to the footer with the [silvertrail_link] shortcode or the Privacy settings link block.', 'silvertrail' ); ?></p>
			</td>
		</tr>
	</table>
</section>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Texts', 'silvertrail' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="silvertrail-texts">
					<?php
					/* translators: %s: locale, for example de_CH */
					echo esc_html( sprintf( __( 'Texts in %s', 'silvertrail' ), $silvertrail_locale ) );
					?>
				</label>
			</th>
			<td>
				<textarea
					id="silvertrail-texts"
					class="large-text code"
					rows="16"
					spellcheck="false"
					name="<?php echo esc_attr( SettingsPage::field( 'texts_json' ) ); ?>"
					<?php if ( '' !== $silvertrail_editor ) : ?>
						data-silvertrail-editor="<?php echo esc_attr( $silvertrail_editor ); ?>"
					<?php endif; ?>
				><?php echo esc_textarea( SettingsPage::draft() ?? (string) wp_json_encode( $silvertrail_current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
				<p class="description">
					<?php
					printf(
						/* translators: 1: embed_text, 2: embed_remember */
						esc_html__( 'Change a value and save. Remove a key to follow the translation again. Keep %%s in %1$s and %2$s, where the service name goes.', 'silvertrail' ),
						'<code>embed_text</code>',
						'<code>embed_remember</code>'
					);
					?>
				</p>
				<?php if ( array() !== $silvertrail_changed ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: list of text keys */
							esc_html__( 'Changed here: %s', 'silvertrail' ),
							wp_kses_post( implode( ', ', array_map( static fn( string $key ): string => '<code>' . esc_html( $key ) . '</code>', $silvertrail_changed ) ) )
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( null !== $silvertrail_translation ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: file path or filter name */
							esc_html__( 'The defaults come from the developer translation in %s.', 'silvertrail' ),
							'<code>' . esc_html( str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $silvertrail_translation['from'] ) ) ) . '</code>'
						);
						?>
					</p>
				<?php else : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: file name */
							esc_html__( 'Developers can ship this JSON in the theme as %s.', 'silvertrail' ),
							'<code>' . esc_html( "silvertrail/texts-{$silvertrail_locale}.json" ) . '</code>'
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( has_filter( 'silvertrail_texts' ) ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: the silvertrail_texts filter */
							esc_html__( 'Code changes some texts after these, through the %s filter.', 'silvertrail' ),
							'<code>silvertrail_texts</code>'
						);
						?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
	</table>
</section>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Links', 'silvertrail' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Privacy policy', 'silvertrail' ); ?></th>
			<td>
				<?php if ( '' !== get_privacy_policy_url() ) : ?>
					<a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php echo esc_html( get_the_title( $silvertrail_privacy ) ); ?></a>
				<?php else : ?>
					<?php esc_html_e( 'None published', 'silvertrail' ); ?>
				<?php endif; ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: link to the privacy settings */
						esc_html__( 'Choose the page under %s.', 'silvertrail' ),
						'<a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' . esc_html__( 'Settings › Privacy', 'silvertrail' ) . '</a>'
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="silvertrail-imprint"><?php esc_html_e( 'Imprint page', 'silvertrail' ); ?></label><?php echo SettingsPage::in_code_label( 'imprint_page' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?></th>
			<td>
				<?php
				$silvertrail_pages = wp_dropdown_pages(
					array(
						'name'              => esc_attr( SettingsPage::field( 'imprint_page' ) ),
						'id'                => 'silvertrail-imprint',
						'selected'          => (int) $silvertrail_settings['imprint_page'],
						'show_option_none'  => esc_html__( 'None', 'silvertrail' ),
						'option_none_value' => '0',
						'echo'              => false,
					)
				);
				echo Settings::in_code( 'imprint_page' ) ? str_replace( '<select ', '<select disabled ', $silvertrail_pages ) : $silvertrail_pages; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from wp_dropdown_pages().
				?>
			</td>
		</tr>
	</table>
</section>
