<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Settings;
use Silvertrail\Texts;

defined( 'ABSPATH' ) || exit;

$silvertrail_settings    = Settings::all();
$silvertrail_privacy     = (int) get_option( 'wp_page_for_privacy_policy' );
$silvertrail_locale      = get_locale();
$silvertrail_base        = Texts::base( $silvertrail_locale );
$silvertrail_current     = array_replace( $silvertrail_base, Texts::custom( $silvertrail_locale ) );
$silvertrail_changed     = array_keys( array_diff_assoc( $silvertrail_current, $silvertrail_base ) );
$silvertrail_translation = Texts::translation( $silvertrail_locale );
$silvertrail_editor      = SettingsPage::editor();
$silvertrail_colors      = array(
	'accent'     => __( 'Button color', 'silvertrail' ),
	'background' => __( 'Background color', 'silvertrail' ),
	'text'       => __( 'Text color', 'silvertrail' ),
);
?>

<h2><?php esc_html_e( 'Layout', 'silvertrail' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Position', 'silvertrail' ); ?></th>
		<td>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Position', 'silvertrail' ); ?></legend>
				<label><input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'layout' ) ); ?>" value="modal" <?php checked( $silvertrail_settings['layout'], 'modal' ); ?>> <?php esc_html_e( 'Centered over the page', 'silvertrail' ); ?></label><br>
				<label><input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'layout' ) ); ?>" value="box" <?php checked( $silvertrail_settings['layout'], 'box' ); ?>> <?php esc_html_e( 'Box in the corner, the page stays usable', 'silvertrail' ); ?></label>
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
				__( 'Centered, the page stays blocked until the visitor chooses. Turn it off to let a click outside, Escape or the close button hide the banner. Nothing is stored then, and the banner shows again on the next page.', 'silvertrail' )
			);
			?>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="silvertrail-appearance"><?php esc_html_e( 'Appearance', 'silvertrail' ); ?></label></th>
		<td>
			<select id="silvertrail-appearance" name="<?php echo esc_attr( SettingsPage::field( 'appearance' ) ); ?>">
				<option value="light" <?php selected( $silvertrail_settings['appearance'], 'light' ); ?>><?php esc_html_e( 'Light', 'silvertrail' ); ?></option>
				<option value="dark" <?php selected( $silvertrail_settings['appearance'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'silvertrail' ); ?></option>
				<option value="auto" <?php selected( $silvertrail_settings['appearance'], 'auto' ); ?>><?php esc_html_e( 'Follow the visitor’s system', 'silvertrail' ); ?></option>
			</select>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'Styles', 'silvertrail' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Default styles', 'silvertrail' ); ?></th>
		<td>
			<?php
			SettingsPage::checkbox(
				'load_css',
				__( 'Load the default styles', 'silvertrail' ),
				__( 'Turn them off to style the banner yourself with the --silvertrail-* custom properties.', 'silvertrail' )
			);
			?>
		</td>
	</tr>
	<?php foreach ( $silvertrail_colors as $silvertrail_key => $silvertrail_label ) : ?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( "silvertrail-color-{$silvertrail_key}" ); ?>"><?php echo esc_html( $silvertrail_label ); ?></label></th>
			<td>
				<input
					type="text"
					class="silvertrail-color"
					id="<?php echo esc_attr( "silvertrail-color-{$silvertrail_key}" ); ?>"
					name="<?php echo esc_attr( SettingsPage::field( 'colors', $silvertrail_key ) ); ?>"
					value="<?php echo esc_attr( $silvertrail_settings['colors'][ $silvertrail_key ] ?? '' ); ?>"
				>
			</td>
		</tr>
	<?php endforeach; ?>
</table>
<p class="description"><?php esc_html_e( 'Leave a color empty for the default.', 'silvertrail' ); ?></p>

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
			<p class="description"><?php esc_html_e( 'Every banner text as JSON, ready to copy and paste. Change a value and save. Remove a key, or put its default back, to follow the translation again.', 'silvertrail' ); ?></p>
			<p class="description">
				<?php
				printf(
					/* translators: 1: embed_text, 2: embed_remember */
					esc_html__( 'Keep %%s in %1$s and %2$s, where the service name goes.', 'silvertrail' ),
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
		<th scope="row"><label for="silvertrail-imprint"><?php esc_html_e( 'Imprint page', 'silvertrail' ); ?></label></th>
		<td>
			<?php
			wp_dropdown_pages(
				array(
					'name'              => esc_attr( SettingsPage::field( 'imprint_page' ) ),
					'id'                => 'silvertrail-imprint',
					'selected'          => (int) $silvertrail_settings['imprint_page'],
					'show_option_none'  => esc_html__( 'None', 'silvertrail' ),
					'option_none_value' => '0',
				)
			);
			?>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Reopen', 'silvertrail' ); ?></th>
		<td>
			<?php
			SettingsPage::checkbox(
				'floating_button',
				__( 'Show a small button that reopens the settings', 'silvertrail' ),
				__( 'Without it, link to #silvertrail in a menu or footer, or use the shortcode [silvertrail_link] or the Privacy settings link block.', 'silvertrail' )
			);
			?>
		</td>
	</tr>
</table>
