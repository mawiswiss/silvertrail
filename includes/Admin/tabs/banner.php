<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Settings;
use Silvertrail\Texts;

defined( 'ABSPATH' ) || exit;

$silvertrail_settings = Settings::all();
$silvertrail_defaults = Texts::defaults();
$silvertrail_privacy  = (int) get_option( 'wp_page_for_privacy_policy' );
$silvertrail_colors   = array(
	'accent'     => __( 'Button color', 'silvertrail' ),
	'background' => __( 'Background color', 'silvertrail' ),
	'text'       => __( 'Text color', 'silvertrail' ),
);
$silvertrail_texts    = array(
	'title'             => __( 'Title', 'silvertrail' ),
	'intro'             => __( 'Intro', 'silvertrail' ),
	'accept_all'        => __( 'Accept button', 'silvertrail' ),
	'reject_all'        => __( 'Reject button', 'silvertrail' ),
	'preferences'       => __( 'Preferences button', 'silvertrail' ),
	'preferences_intro' => __( 'Preferences intro', 'silvertrail' ),
	'save'              => __( 'Save button', 'silvertrail' ),
	'reopen'            => __( 'Reopen link', 'silvertrail' ),
);
?>

<h2><?php esc_html_e( 'Layout', 'silvertrail' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Position', 'silvertrail' ); ?></th>
		<td>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Position', 'silvertrail' ); ?></legend>
				<label><input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'layout' ) ); ?>" value="modal" <?php checked( $silvertrail_settings['layout'], 'modal' ); ?>> <?php esc_html_e( 'Centered, the page waits for a choice', 'silvertrail' ); ?></label><br>
				<label><input type="radio" name="<?php echo esc_attr( SettingsPage::field( 'layout' ) ); ?>" value="box" <?php checked( $silvertrail_settings['layout'], 'box' ); ?>> <?php esc_html_e( 'Box in the corner, the page stays usable', 'silvertrail' ); ?></label>
			</fieldset>
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
<p>
	<?php
	printf(
		/* translators: %s: the silvertrail_texts filter */
		esc_html__( 'Empty fields use the translated default shown in grey. The %s filter changes every text, these and all others.', 'silvertrail' ),
		'<code>silvertrail_texts</code>'
	);
	?>
</p>
<table class="form-table" role="presentation">
	<?php foreach ( SettingsPage::EDITABLE_TEXTS as $silvertrail_key ) : ?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( "silvertrail-text-{$silvertrail_key}" ); ?>"><?php echo esc_html( $silvertrail_texts[ $silvertrail_key ] ?? $silvertrail_key ); ?></label></th>
			<td>
				<?php if ( in_array( $silvertrail_key, array( 'intro', 'preferences_intro' ), true ) ) : ?>
					<textarea id="<?php echo esc_attr( "silvertrail-text-{$silvertrail_key}" ); ?>" class="large-text" rows="2" name="<?php echo esc_attr( SettingsPage::field( 'texts', $silvertrail_key ) ); ?>" placeholder="<?php echo esc_attr( $silvertrail_defaults[ $silvertrail_key ] ); ?>"><?php echo esc_textarea( $silvertrail_settings['texts'][ $silvertrail_key ] ?? '' ); ?></textarea>
				<?php else : ?>
					<input type="text" id="<?php echo esc_attr( "silvertrail-text-{$silvertrail_key}" ); ?>" class="regular-text" name="<?php echo esc_attr( SettingsPage::field( 'texts', $silvertrail_key ) ); ?>" value="<?php echo esc_attr( $silvertrail_settings['texts'][ $silvertrail_key ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $silvertrail_defaults[ $silvertrail_key ] ); ?>">
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
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
