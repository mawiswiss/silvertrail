<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\Notices;
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
$silvertrail_theme_json    = wp_theme_has_theme_json();
$silvertrail_as_theme      = __( 'As the theme sets it', 'silvertrail' );
$silvertrail_appearances   = $silvertrail_theme_json ? array(
	'light' => array( __( 'Light', 'silvertrail' ), 'light' === Design::scheme() ? $silvertrail_as_theme : __( 'Its lightest color behind', 'silvertrail' ) ),
	'dark'  => array( __( 'Dark', 'silvertrail' ), 'dark' === Design::scheme() ? $silvertrail_as_theme : __( 'Its darkest color behind', 'silvertrail' ) ),
) : array(
	'light' => array( __( 'Light', 'silvertrail' ), __( 'Dark text on white', 'silvertrail' ) ),
	'dark'  => array( __( 'Dark', 'silvertrail' ), __( 'Light text on dark gray', 'silvertrail' ) ),
);
$silvertrail_floating      = 'never' === $silvertrail_settings['floating_button'] ? 'never' : $silvertrail_settings['floating_position'];
$silvertrail_one_look      = has_filter( 'silvertrail_design' ) && Design::tokens( 'light' ) === Design::tokens( 'dark' );
$silvertrail_now           = Frontend::appearance();
$silvertrail_tokens        = Design::tokens( $silvertrail_now );
$silvertrail_theme_tokens  = Design::from_theme( $silvertrail_now );
$silvertrail_coded         = array_keys( array_diff_assoc( $silvertrail_tokens, $silvertrail_theme_tokens ) + array_diff_assoc( $silvertrail_theme_tokens, $silvertrail_tokens ) );
$silvertrail_theme_colors  = $silvertrail_theme_json && array() === array_intersect( $silvertrail_coded, array( 'background', 'text', 'accent', 'on-accent' ) );
$silvertrail_palette       = array_filter(
	array_column( (array) wp_get_global_settings( array( 'color', 'palette', 'theme' ) ), 'color' ),
	static fn( mixed $color ): bool => is_string( $color ) && 1 === preg_match( '/^[#a-zA-Z0-9(),.%\s-]+$/', $color )
);
$silvertrail_palette       = array_slice( array_intersect_key( $silvertrail_palette, array_unique( array_map( 'strtolower', $silvertrail_palette ) ) ), 0, 5 );
$silvertrail_theme_name    = (string) wp_get_theme()->get( 'Name' );
$silvertrail_layers        = array_filter(
	array(
		$silvertrail_theme_json ? 'theme.json' : __( 'default colors', 'silvertrail' ),
		array() !== $silvertrail_coded ? 'silvertrail_design' : '',
		$silvertrail_theme_css ? Frontend::THEME_CSS : '',
	)
);
$silvertrail_in_code       = SettingsPage::help_button( 'silvertrail-design', __( 'in code', 'silvertrail' ) );
$silvertrail_swatch        = static function ( string $mode ): string {
	$tokens = array_intersect_key( Design::tokens( $mode ), array_flip( array( 'background', 'text', 'accent', 'on-accent', 'radius' ) ) );
	return implode( ';', array_map( static fn( string $key, string $value ): string => "--silvertrail-{$key}:{$value}", array_keys( $tokens ), $tokens ) );
};
?>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Look', 'silvertrail' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Position', 'silvertrail' ); ?><?php echo wp_kses_post( SettingsPage::in_code_label( 'layout' ) ); ?></th>
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
			<th scope="row"><?php esc_html_e( 'Design', 'silvertrail' ); ?></th>
			<td>
				<div class="silvertrail-source">
					<span class="<?php echo esc_attr( "silvertrail-source__preview silvertrail-swatch silvertrail-swatch--{$silvertrail_now} silvertrail-swatch--palette" ); ?>" style="<?php echo esc_attr( $silvertrail_swatch( $silvertrail_now ) ); ?>" aria-hidden="true">
						<span class="silvertrail-swatch__type">Aa</span>
						<span class="silvertrail-swatch__palette">
							<?php if ( $silvertrail_theme_colors ) : ?>
								<?php foreach ( $silvertrail_palette as $silvertrail_color ) : ?>
									<span style="<?php echo esc_attr( "background:{$silvertrail_color}" ); ?>"></span>
								<?php endforeach; ?>
							<?php else : ?>
								<span class="silvertrail-swatch__dot--text"></span>
								<span class="silvertrail-swatch__dot--accent"></span>
								<span class="silvertrail-swatch__dot--on-accent"></span>
							<?php endif; ?>
						</span>
					</span>
					<div>
						<p>
							<strong><?php echo esc_html( $silvertrail_theme_json ? $silvertrail_theme_name : 'Silvertrail' ); ?></strong>
							<span class="silvertrail-source__layers"><?php echo esc_html( implode( ' + ', $silvertrail_layers ) ); ?></span>
						</p>
						<p>
							<?php
							if ( $silvertrail_theme_json && wp_is_block_theme() && current_user_can( 'edit_theme_options' ) ) {
								$silvertrail_source = sprintf(
									/* translators: 1: link to the Styles editor, 2: button that opens the help */
									esc_html__( 'Change it in the %1$s or %2$s.', 'silvertrail' ),
									'<a href="' . esc_url( admin_url( 'site-editor.php?path=/wp_global_styles' ) ) . '">' . esc_html__( 'Styles editor', 'silvertrail' ) . '</a>',
									$silvertrail_in_code
								);
							} elseif ( $silvertrail_theme_json ) {
								/* translators: 1: theme.json, 2: button that opens the help */
								$silvertrail_source = sprintf( esc_html__( 'Change it in its %1$s or %2$s.', 'silvertrail' ), '<code>theme.json</code>', $silvertrail_in_code );
							} else {
								/* translators: 1: theme name, 2: theme.json, 3: button that opens the help */
								$silvertrail_source = sprintf( esc_html__( '%1$s has no %2$s. Change it %3$s.', 'silvertrail' ), esc_html( $silvertrail_theme_name ), '<code>theme.json</code>', $silvertrail_in_code );
							}
							echo wp_kses_post( $silvertrail_source );
							?>
						</p>
					</div>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Appearance', 'silvertrail' ); ?><?php echo wp_kses_post( SettingsPage::in_code_label( 'appearance' ) ); ?></th>
			<td>
				<?php if ( $silvertrail_one_look ) : ?>
					<p>
						<?php
						/* translators: %s: silvertrail_design */
						printf( esc_html__( 'The %s filter gives light and dark the same colors, so there is nothing to choose.', 'silvertrail' ), '<code>silvertrail_design</code>' );
						?>
					</p>
				<?php else : ?>
					<fieldset class="silvertrail-cards">
						<legend class="screen-reader-text"><?php esc_html_e( 'Appearance', 'silvertrail' ); ?></legend>
						<?php foreach ( $silvertrail_appearances as $silvertrail_mode => list( $silvertrail_label, $silvertrail_hint ) ) : ?>
							<label class="silvertrail-card">
								<span class="<?php echo esc_attr( "silvertrail-card__preview silvertrail-swatch silvertrail-swatch--{$silvertrail_mode}" ); ?>" style="<?php echo esc_attr( $silvertrail_swatch( $silvertrail_mode ) ); ?>" aria-hidden="true">
									<span class="silvertrail-swatch__text"></span>
									<span class="silvertrail-swatch__text silvertrail-swatch__text--short"></span>
									<span class="silvertrail-swatch__buttons"><span></span><span></span></span>
								</span>
								<input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'appearance' ) ); ?>" value="<?php echo esc_attr( $silvertrail_mode ); ?>" <?php checked( Frontend::appearance(), $silvertrail_mode ); ?> <?php disabled( Settings::in_code( 'appearance' ) ); ?>>
								<span class="silvertrail-card__name"><?php echo esc_html( $silvertrail_label ); ?></span>
								<span class="silvertrail-card__hint"><?php echo esc_html( $silvertrail_hint ); ?></span>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Floating button', 'silvertrail' ); ?><?php echo wp_kses_post( SettingsPage::in_code_label( Settings::in_code( 'floating_button' ) ? 'floating_button' : 'floating_position' ) ); ?></th>
			<td>
				<fieldset class="silvertrail-cards">
					<legend class="screen-reader-text"><?php esc_html_e( 'Floating button', 'silvertrail' ); ?></legend>
					<label class="silvertrail-card">
						<svg class="silvertrail-card__preview silvertrail-sketch" viewBox="0 0 120 80" aria-hidden="true" focusable="false">
							<rect class="silvertrail-sketch__page" width="120" height="80"/>
							<rect class="silvertrail-sketch__content" x="10" y="10" width="50" height="5" rx="2"/>
							<rect class="silvertrail-sketch__content" x="10" y="21" width="100" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="28" width="90" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="35" width="40" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="66" width="24" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__button" x="38" y="66" width="26" height="3" rx="1.5"/>
						</svg>
						<input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'floating' ) ); ?>" value="never" <?php checked( $silvertrail_floating, 'never' ); ?> <?php disabled( Settings::in_code( 'floating_button' ) ); ?>>
						<span class="silvertrail-card__name"><?php esc_html_e( 'Off', 'silvertrail' ); ?></span>
						<span class="silvertrail-card__hint"><?php esc_html_e( 'Only links open the settings', 'silvertrail' ); ?></span>
					</label>
					<label class="silvertrail-card">
						<svg class="silvertrail-card__preview silvertrail-sketch" viewBox="0 0 120 80" aria-hidden="true" focusable="false">
							<rect class="silvertrail-sketch__page" width="120" height="80"/>
							<rect class="silvertrail-sketch__content" x="10" y="10" width="50" height="5" rx="2"/>
							<rect class="silvertrail-sketch__content" x="10" y="21" width="100" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="28" width="90" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="35" width="40" height="3" rx="1.5"/>
							<circle class="silvertrail-sketch__reopen" cx="15" cy="66" r="7"/>
						</svg>
						<input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'floating' ) ); ?>" value="left" <?php checked( $silvertrail_floating, 'left' ); ?> <?php disabled( Settings::in_code( 'floating_button' ) || Settings::in_code( 'floating_position' ) ); ?>>
						<span class="silvertrail-card__name"><?php esc_html_e( 'Bottom left', 'silvertrail' ); ?></span>
						<span class="silvertrail-card__hint"><?php esc_html_e( 'On pages without a link', 'silvertrail' ); ?></span>
					</label>
					<label class="silvertrail-card">
						<svg class="silvertrail-card__preview silvertrail-sketch" viewBox="0 0 120 80" aria-hidden="true" focusable="false">
							<rect class="silvertrail-sketch__page" width="120" height="80"/>
							<rect class="silvertrail-sketch__content" x="10" y="10" width="50" height="5" rx="2"/>
							<rect class="silvertrail-sketch__content" x="10" y="21" width="100" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="28" width="90" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="35" width="40" height="3" rx="1.5"/>
							<circle class="silvertrail-sketch__reopen" cx="105" cy="66" r="7"/>
						</svg>
						<input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'floating' ) ); ?>" value="right" <?php checked( $silvertrail_floating, 'right' ); ?> <?php disabled( Settings::in_code( 'floating_button' ) || Settings::in_code( 'floating_position' ) ); ?>>
						<span class="silvertrail-card__name"><?php esc_html_e( 'Bottom right', 'silvertrail' ); ?></span>
						<span class="silvertrail-card__hint"><?php esc_html_e( 'On pages without a link', 'silvertrail' ); ?></span>
					</label>
				</fieldset>
				<p class="description">
					<?php
					printf(
						/* translators: 1: .silvertrail-reopen, 2: the silvertrail_reopen_icon filter */
						esc_html__( 'Style it through %1$s and swap its icon with the %2$s filter.', 'silvertrail' ),
						'<code>.silvertrail-reopen</code>',
						'<code>silvertrail_reopen_icon</code>'
					);
					echo ' ' . wp_kses_post( SettingsPage::help_button( 'silvertrail-floating', __( 'Show how', 'silvertrail' ) ) );
					?>
				</p>
			</td>
		</tr>
	</table>
	<?php SettingsPage::save( 'look' ); ?>
</section>

<section class="card silvertrail-section" id="silvertrail-custom-styles">
	<h2><?php esc_html_e( 'Custom styles', 'silvertrail' ); ?></h2>
	<p><?php esc_html_e( 'For anything beyond the theme’s colors and buttons, the theme adds its own stylesheet. An AI assistant can write it.', 'silvertrail' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Write it with AI', 'silvertrail' ); ?></th>
			<td>
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
				<details class="health-check-accordion silvertrail-accordion">
					<summary class="health-check-accordion-trigger">
						<span class="title"><?php esc_html_e( 'Show the prompt', 'silvertrail' ); ?></span>
						<span class="icon"></span>
					</summary>
					<div class="health-check-accordion-panel">
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
					</div>
				</details>
			</td>
		</tr>
	</table>
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
	</table>
	<?php SettingsPage::save( 'behavior' ); ?>
</section>

<section class="card silvertrail-section" id="silvertrail-texts">
	<h2><?php esc_html_e( 'Texts', 'silvertrail' ); ?></h2>
	<?php Notices::card( 'texts' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="silvertrail-texts-json">
					<?php
					/* translators: %s: locale, for example de_CH */
					echo esc_html( sprintf( __( 'Texts in %s', 'silvertrail' ), $silvertrail_locale ) );
					?>
				</label>
			</th>
			<td>
				<ul class="ul-disc silvertrail-howto">
					<li>
						<?php
						printf(
							/* translators: %s: one line of the texts, for example "accept_all": "Accept all" */
							esc_html__( 'Change only the text in quotes after the colon, as in %s, then save.', 'silvertrail' ),
							'<code>' . esc_html( '"accept_all": ' . wp_json_encode( $silvertrail_current['accept_all'], JSON_UNESCAPED_UNICODE ) ) . '</code>'
						);
						?>
					</li>
					<li><?php esc_html_e( 'Delete a line to bring back its default text.', 'silvertrail' ); ?></li>
					<li>
						<?php
						printf(
							/* translators: 1: %s, 2: embed_text, 3: embed_remember */
							esc_html__( 'Keep %1$s in %2$s and %3$s. The service name goes there.', 'silvertrail' ),
							'<code>%s</code>',
							'<code>embed_text</code>',
							'<code>embed_remember</code>'
						);
						?>
					</li>
				</ul>
				<textarea
					id="silvertrail-texts-json"
					class="large-text code"
					rows="16"
					spellcheck="false"
					name="<?php echo esc_attr( SettingsPage::field( 'texts_json' ) ); ?>"
					<?php if ( '' !== $silvertrail_editor ) : ?>
						data-silvertrail-editor="<?php echo esc_attr( $silvertrail_editor ); ?>"
					<?php endif; ?>
				><?php echo esc_textarea( SettingsPage::draft() ?? (string) wp_json_encode( $silvertrail_current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
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
						echo ' ' . wp_kses_post( SettingsPage::help_button( 'silvertrail-texts', __( 'Show how', 'silvertrail' ) ) );
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
	<?php SettingsPage::save( 'texts' ); ?>
</section>

<section class="card silvertrail-section" id="silvertrail-links">
	<h2><?php esc_html_e( 'Links', 'silvertrail' ); ?></h2>
	<?php Notices::card( 'links' ); ?>
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
			<th scope="row"><label for="silvertrail-imprint"><?php esc_html_e( 'Imprint page', 'silvertrail' ); ?></label><?php echo wp_kses_post( SettingsPage::in_code_label( 'imprint_page' ) ); ?></th>
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
				echo wp_kses(
					Settings::in_code( 'imprint_page' ) ? str_replace( '<select ', '<select disabled ', $silvertrail_pages ) : $silvertrail_pages,
					array(
						'select' => array(
							'name'     => true,
							'id'       => true,
							'class'    => true,
							'disabled' => true,
						),
						'option' => array(
							'value'    => true,
							'selected' => true,
							'class'    => true,
						),
					)
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Privacy settings link', 'silvertrail' ); ?></th>
			<td>
				<?php
				printf(
					/* translators: 1: #silvertrail, 2: [silvertrail_link] */
					esc_html__( 'Visitors change their choice through a link to %1$s, usually in the footer. Add one with the %2$s shortcode or the Privacy settings link block.', 'silvertrail' ),
					'<code>#silvertrail</code>',
					'<code>[silvertrail_link]</code>'
				);
				?>
				<p class="description"><?php esc_html_e( 'Every page needs one, unless the floating button is on.', 'silvertrail' ); ?></p>
			</td>
		</tr>
	</table>
	<?php SettingsPage::save( 'links' ); ?>
</section>
