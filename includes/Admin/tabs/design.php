<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Abilities;
use Silvertrail\Admin\SettingsPage;
use Silvertrail\Admin\StylingPrompt;
use Silvertrail\Design;
use Silvertrail\Frontend;
use Silvertrail\Settings;

defined( 'ABSPATH' ) || exit;

$silvertrail_settings      = Settings::all();
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
	<?php echo wp_kses_post( SettingsPage::docs_link( 'styling/#from-the-theme' ) ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Source', 'silvertrail' ); ?></th>
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
			<th scope="row"><?php esc_html_e( 'Layout', 'silvertrail' ); ?><?php echo wp_kses_post( SettingsPage::in_code_label( 'layout' ) ); ?></th>
			<td>
				<fieldset class="silvertrail-cards">
					<legend class="screen-reader-text"><?php esc_html_e( 'Layout', 'silvertrail' ); ?></legend>
					<label class="silvertrail-card">
						<svg class="silvertrail-card__preview silvertrail-sketch" viewBox="0 0 120 80" aria-hidden="true" focusable="false">
							<rect class="silvertrail-sketch__page" width="120" height="80"/>
							<rect class="silvertrail-sketch__content" x="10" y="10" width="50" height="5" rx="2"/>
							<rect class="silvertrail-sketch__content" x="10" y="21" width="100" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="28" width="90" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__banner" x="30" y="16" width="60" height="48" rx="3"/>
							<rect class="silvertrail-sketch__text" x="36" y="23" width="30" height="4" rx="2"/>
							<rect class="silvertrail-sketch__text" x="36" y="31" width="46" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__button" x="36" y="50" width="22" height="8" rx="2"/>
							<rect class="silvertrail-sketch__button" x="62" y="50" width="22" height="8" rx="2"/>
						</svg>
						<input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'layout' ) ); ?>" value="modal" <?php checked( $silvertrail_settings['layout'], 'modal' ); ?> <?php disabled( Settings::in_code( 'layout' ) ); ?>>
						<span class="silvertrail-card__name"><?php esc_html_e( 'Dialog', 'silvertrail' ); ?></span>
						<span class="silvertrail-card__hint"><?php esc_html_e( 'In the middle of the page', 'silvertrail' ); ?></span>
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
						<span class="silvertrail-card__name"><?php esc_html_e( 'Box', 'silvertrail' ); ?></span>
						<span class="silvertrail-card__hint"><?php esc_html_e( 'In the bottom right corner', 'silvertrail' ); ?></span>
					</label>
					<label class="silvertrail-card">
						<svg class="silvertrail-card__preview silvertrail-sketch" viewBox="0 0 120 80" aria-hidden="true" focusable="false">
							<rect class="silvertrail-sketch__page" width="120" height="80"/>
							<rect class="silvertrail-sketch__content" x="10" y="10" width="50" height="5" rx="2"/>
							<rect class="silvertrail-sketch__content" x="10" y="21" width="100" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="28" width="90" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__content" x="10" y="35" width="40" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__banner" y="60" width="120" height="20"/>
							<rect class="silvertrail-sketch__text" x="8" y="66" width="26" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__text" x="8" y="72" width="40" height="3" rx="1.5"/>
							<rect class="silvertrail-sketch__button" x="70" y="66" width="19" height="8" rx="2"/>
							<rect class="silvertrail-sketch__button" x="93" y="66" width="19" height="8" rx="2"/>
						</svg>
						<input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'layout' ) ); ?>" value="bar" <?php checked( $silvertrail_settings['layout'], 'bar' ); ?> <?php disabled( Settings::in_code( 'layout' ) ); ?>>
						<span class="silvertrail-card__name"><?php esc_html_e( 'Bar', 'silvertrail' ); ?></span>
						<span class="silvertrail-card__hint"><?php esc_html_e( 'Across the top or bottom', 'silvertrail' ); ?></span>
					</label>
				</fieldset>
				<fieldset class="silvertrail-suboption">
					<legend><?php esc_html_e( 'Bar position', 'silvertrail' ); ?><?php echo wp_kses_post( SettingsPage::in_code_label( 'bar_position' ) ); ?></legend>
					<?php
					foreach ( array(
						'top'    => __( 'Top', 'silvertrail' ),
						'bottom' => __( 'Bottom', 'silvertrail' ),
					) as $silvertrail_edge => $silvertrail_edge_label ) :
						?>
						<label><input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'bar_position' ) ); ?>" value="<?php echo esc_attr( $silvertrail_edge ); ?>" <?php checked( $silvertrail_settings['bar_position'], $silvertrail_edge ); ?> <?php disabled( Settings::in_code( 'bar_position' ) ); ?>> <?php echo esc_html( $silvertrail_edge_label ); ?></label>
					<?php endforeach; ?>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Closing', 'silvertrail' ); ?></th>
			<td>
				<?php
				SettingsPage::checkbox(
					'require_choice',
					__( 'Require a choice before the banner closes', 'silvertrail' ),
					__( 'On by default for the dialog, off for the box and the bar. Turned on, the page dims until the visitor chooses. Turned off, the page stays usable, and a click outside or Escape closes the banner without storing anything. Only services that need no consent run, and it asks again on the next page.', 'silvertrail' )
				);
				?>
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
					<?php esc_html_e( 'A small round button in a bottom corner that reopens the privacy settings, so visitors can change their choice. It appears after their first choice and stays hidden on pages that already link to the settings.', 'silvertrail' ); ?><br>
					<?php
					printf(
						/* translators: %s: the silvertrail_settings filter */
						esc_html__( 'The %s filter shows it on every page.', 'silvertrail' ),
						'<code>silvertrail_settings</code>'
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
	<?php echo wp_kses_post( SettingsPage::docs_link( 'styling/#your-own-stylesheet' ) ); ?>
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
		<?php if ( Abilities::ai_available() ) : ?>
			<tr class="hide-if-no-js">
				<th scope="row"><label for="silvertrail-style-want"><?php esc_html_e( 'With this site’s AI', 'silvertrail' ); ?></label></th>
				<td>
					<textarea id="silvertrail-style-want" class="large-text" rows="3" aria-describedby="silvertrail-style-want-description"></textarea>
					<p class="description" id="silvertrail-style-want-description">
						<?php
						printf(
							/* translators: %s: link to Settings › Connectors */
							esc_html__( 'Describe the look you want. Write with AI sends it with the prompt to the AI provider set up under %s.', 'silvertrail' ),
							'<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">' . esc_html__( 'Settings › Connectors', 'silvertrail' ) . '</a>'
						);
						?>
					</p>
					<p>
						<button
							type="button"
							class="button"
							id="silvertrail-write-styles"
							data-busy="<?php esc_attr_e( 'Writing the styles. This can take a minute.', 'silvertrail' ); ?>"
							data-done="<?php esc_attr_e( 'The styles are ready below the button.', 'silvertrail' ); ?>"
							data-empty="<?php esc_attr_e( 'Describe the look you want first.', 'silvertrail' ); ?>"
						><?php esc_html_e( 'Write with AI', 'silvertrail' ); ?></button>
						<span class="spinner silvertrail-spinner"></span>
					</p>
					<div class="notice notice-error inline" id="silvertrail-style-error" hidden><p></p></div>
					<div id="silvertrail-style-output" hidden>
						<label for="silvertrail-style-answer" class="screen-reader-text"><?php esc_html_e( 'Stylesheet written by AI', 'silvertrail' ); ?></label>
						<textarea id="silvertrail-style-answer" class="large-text code" rows="16" readonly></textarea>
						<p>
							<?php
							printf(
								/* translators: %s: silvertrail/silvertrail.css */
								esc_html__( 'Nothing is saved yet. Check it, then save it in the theme as %s.', 'silvertrail' ),
								'<code>' . esc_html( Frontend::THEME_CSS ) . '</code>'
							);
							?>
							<span class="copy-to-clipboard-container">
								<button type="button" class="button" data-silvertrail-copy="silvertrail-style-answer"><?php esc_html_e( 'Copy stylesheet', 'silvertrail' ); ?></button>
								<span class="success hidden" aria-hidden="true"><?php esc_html_e( 'Copied', 'silvertrail' ); ?></span>
							</span>
						</p>
					</div>
				</td>
			</tr>
		<?php endif; ?>
	</table>
</section>
