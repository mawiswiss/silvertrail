<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\Notices;
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
$silvertrail_editor      = SettingsPage::editor( 'json' );
?>

<section class="card silvertrail-section" id="silvertrail-texts">
	<h2><?php esc_html_e( 'Texts', 'silvertrail' ); ?></h2>
	<?php echo wp_kses_post( SettingsPage::docs_link( 'texts/' ) ); ?>
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
	<?php echo wp_kses_post( SettingsPage::docs_link( 'reopen/#links' ) ); ?>
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
