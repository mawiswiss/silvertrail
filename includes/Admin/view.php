<?php
/**
 * @package Silvertrail
 *
 * @var array<string, array<string, mixed>> $presets
 * @var array<string, array<string, mixed>> $registered
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Config;
use Silvertrail\Settings;
use Silvertrail\Texts;

defined( 'ABSPATH' ) || exit;

$silvertrail_settings = Settings::all();
$silvertrail_groups   = Config::get()['groups'];
$silvertrail_defaults = Texts::defaults();
$silvertrail_name     = Settings::OPTION;
$silvertrail_flag     = static function ( string $key, string $label, string $help = '' ) use ( $silvertrail_settings, $silvertrail_name ): void {
	printf(
		'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>%5$s',
		esc_attr( $silvertrail_name ),
		esc_attr( $key ),
		checked( ! empty( $silvertrail_settings[ $key ] ), true, false ),
		esc_html( $label ),
		'' !== $help ? '<p class="description">' . esc_html( $help ) . '</p>' : ''
	);
};
?>
<div class="wrap">
	<h1>Silvertrail</h1>

	<?php if ( isset( $_GET['silvertrail-asked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Every visitor will be asked again on their next page view.', 'silvertrail' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( SettingsPage::SLUG ); ?>

		<h2><?php esc_html_e( 'Services', 'silvertrail' ); ?></h2>
		<p><?php esc_html_e( 'Turn on what this site uses. Trackers with an ID get their official tag printed, blocked until the visitor agrees.', 'silvertrail' ); ?></p>
		<table class="widefat striped" style="max-width: 50rem">
			<thead><tr><th><?php esc_html_e( 'Service', 'silvertrail' ); ?></th><th><?php esc_html_e( 'Group', 'silvertrail' ); ?></th><th><?php esc_html_e( 'ID', 'silvertrail' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $presets as $silvertrail_id => $silvertrail_preset ) : ?>
					<?php $silvertrail_preset_setting = $silvertrail_settings['presets'][ $silvertrail_id ] ?? array(); ?>
					<tr>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( "{$silvertrail_name}[presets][{$silvertrail_id}][enabled]" ); ?>" value="1" <?php checked( ! empty( $silvertrail_preset_setting['enabled'] ) ); ?>>
								<?php echo esc_html( $silvertrail_preset['service']['name'] ); ?>
							</label>
						</td>
						<td><?php echo esc_html( $silvertrail_groups[ $silvertrail_preset['service']['group'] ]['name'] ?? $silvertrail_preset['service']['group'] ); ?></td>
						<td>
							<?php if ( isset( $silvertrail_preset['tag_id'] ) ) : ?>
								<label class="screen-reader-text" for="<?php echo esc_attr( "silvertrail-{$silvertrail_id}-id" ); ?>"><?php echo esc_html( $silvertrail_preset['tag_id']['label'] ); ?></label>
								<input
									type="text"
									class="regular-text code"
									id="<?php echo esc_attr( "silvertrail-{$silvertrail_id}-id" ); ?>"
									name="<?php echo esc_attr( "{$silvertrail_name}[presets][{$silvertrail_id}][tag_id]" ); ?>"
									value="<?php echo esc_attr( (string) ( $silvertrail_preset_setting['tag_id'] ?? '' ) ); ?>"
									placeholder="<?php echo esc_attr( $silvertrail_preset['tag_id']['example'] ); ?>"
									pattern="<?php echo esc_attr( $silvertrail_preset['tag_id']['pattern'] ); ?>"
								>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Embeds are blocked automatically.', 'silvertrail' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( array() !== $registered ) : ?>
			<h3><?php esc_html_e( 'Registered in code', 'silvertrail' ); ?></h3>
			<ul class="ul-disc">
				<?php foreach ( $registered as $silvertrail_service ) : ?>
					<li><?php echo esc_html( sprintf( '%s (%s)', $silvertrail_service['name'], $silvertrail_groups[ $silvertrail_service['group'] ]['name'] ?? $silvertrail_service['group'] ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Banner', 'silvertrail' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Layout', 'silvertrail' ); ?></th>
				<td>
					<fieldset>
						<label><input type="radio" name="<?php echo esc_attr( $silvertrail_name ); ?>[layout]" value="modal" <?php checked( $silvertrail_settings['layout'], 'modal' ); ?>> <?php esc_html_e( 'Centered, the page waits for a choice', 'silvertrail' ); ?></label><br>
						<label><input type="radio" name="<?php echo esc_attr( $silvertrail_name ); ?>[layout]" value="box" <?php checked( $silvertrail_settings['layout'], 'box' ); ?>> <?php esc_html_e( 'Box in the corner, the page stays usable', 'silvertrail' ); ?></label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="silvertrail-appearance"><?php esc_html_e( 'Appearance', 'silvertrail' ); ?></label></th>
				<td>
					<select id="silvertrail-appearance" name="<?php echo esc_attr( $silvertrail_name ); ?>[appearance]">
						<option value="light" <?php selected( $silvertrail_settings['appearance'], 'light' ); ?>><?php esc_html_e( 'Light', 'silvertrail' ); ?></option>
						<option value="dark" <?php selected( $silvertrail_settings['appearance'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'silvertrail' ); ?></option>
						<option value="auto" <?php selected( $silvertrail_settings['appearance'], 'auto' ); ?>><?php esc_html_e( 'Follow the visitor’s system', 'silvertrail' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Colors', 'silvertrail' ); ?></th>
				<td>
					<?php
					foreach (
						array(
							'accent'     => __( 'Buttons', 'silvertrail' ),
							'background' => __( 'Background', 'silvertrail' ),
							'text'       => __( 'Text', 'silvertrail' ),
						) as $silvertrail_key => $silvertrail_label
					) :
						?>
						<label style="display: inline-block; margin-right: 1rem">
							<?php echo esc_html( $silvertrail_label ); ?>
							<input type="text" class="small-text code" style="width: 6rem" name="<?php echo esc_attr( "{$silvertrail_name}[colors][{$silvertrail_key}]" ); ?>" value="<?php echo esc_attr( $silvertrail_settings['colors'][ $silvertrail_key ] ?? '' ); ?>" placeholder="#2f4858" pattern="#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?">
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Leave empty for the defaults. For full control, turn off the default styles and use the --silvertrail-* custom properties.', 'silvertrail' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Texts', 'silvertrail' ); ?></th>
				<td>
					<?php foreach ( SettingsPage::EDITABLE_TEXTS as $silvertrail_key ) : ?>
						<p>
							<label for="<?php echo esc_attr( "silvertrail-text-{$silvertrail_key}" ); ?>" class="screen-reader-text"><?php echo esc_html( $silvertrail_defaults[ $silvertrail_key ] ); ?></label>
							<?php if ( in_array( $silvertrail_key, array( 'intro', 'preferences_intro' ), true ) ) : ?>
								<textarea id="<?php echo esc_attr( "silvertrail-text-{$silvertrail_key}" ); ?>" class="large-text" rows="2" name="<?php echo esc_attr( "{$silvertrail_name}[texts][{$silvertrail_key}]" ); ?>" placeholder="<?php echo esc_attr( $silvertrail_defaults[ $silvertrail_key ] ); ?>"><?php echo esc_textarea( $silvertrail_settings['texts'][ $silvertrail_key ] ?? '' ); ?></textarea>
							<?php else : ?>
								<input type="text" id="<?php echo esc_attr( "silvertrail-text-{$silvertrail_key}" ); ?>" class="regular-text" name="<?php echo esc_attr( "{$silvertrail_name}[texts][{$silvertrail_key}]" ); ?>" value="<?php echo esc_attr( $silvertrail_settings['texts'][ $silvertrail_key ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $silvertrail_defaults[ $silvertrail_key ] ); ?>">
							<?php endif; ?>
						</p>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Empty fields use the translated default shown as placeholder.', 'silvertrail' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="silvertrail-imprint"><?php esc_html_e( 'Imprint page', 'silvertrail' ); ?></label></th>
				<td>
					<?php
					wp_dropdown_pages(
						array(
							'name'              => esc_attr( "{$silvertrail_name}[imprint_page]" ),
							'id'                => 'silvertrail-imprint',
							'selected'          => (int) $silvertrail_settings['imprint_page'],
							'show_option_none'  => esc_html__( 'None', 'silvertrail' ),
							'option_none_value' => '0',
						)
					);
					?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to the privacy settings */
							esc_html__( 'The privacy policy link comes from %s.', 'silvertrail' ),
							'<a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' . esc_html__( 'Settings › Privacy', 'silvertrail' ) . '</a>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Reopen', 'silvertrail' ); ?></th>
				<td>
					<?php $silvertrail_flag( 'floating_button', __( 'Show a small button that reopens the settings', 'silvertrail' ), __( 'Without it, link to #silvertrail in a menu or footer, or use the shortcode [silvertrail_link] or the Privacy settings link block.', 'silvertrail' ) ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Styles', 'silvertrail' ); ?></th>
				<td><?php $silvertrail_flag( 'load_css', __( 'Load the default styles', 'silvertrail' ) ); ?></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Consent', 'silvertrail' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="silvertrail-expiry"><?php esc_html_e( 'Keep a choice for', 'silvertrail' ); ?></label></th>
				<td><input type="number" min="1" max="13" class="small-text" id="silvertrail-expiry" name="<?php echo esc_attr( $silvertrail_name ); ?>[expiry_months]" value="<?php echo esc_attr( (string) $silvertrail_settings['expiry_months'] ); ?>"> <?php esc_html_e( 'months', 'silvertrail' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Signals', 'silvertrail' ); ?></th>
				<td>
					<?php $silvertrail_flag( 'gpc', __( 'Treat Global Privacy Control as “only essential” and show no banner', 'silvertrail' ) ); ?><br>
					<?php $silvertrail_flag( 'url_passthrough', __( 'Pass Google ad click IDs in links while ads cookies are denied', 'silvertrail' ) ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Records', 'silvertrail' ); ?></th>
				<td>
					<?php $silvertrail_flag( 'records', __( 'Keep a record of each decision', 'silvertrail' ), __( 'Stores a random consent ID, the choices and the time. No IP address, no browser details.', 'silvertrail' ) ); ?>
					<p>
						<label><?php esc_html_e( 'Delete records after', 'silvertrail' ); ?>
						<input type="number" min="30" max="3650" class="small-text" name="<?php echo esc_attr( $silvertrail_name ); ?>[retention_days]" value="<?php echo esc_attr( (string) $silvertrail_settings['retention_days'] ); ?>">
						<?php esc_html_e( 'days', 'silvertrail' ); ?></label>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Transparency', 'silvertrail' ); ?></th>
				<td>
					<?php $silvertrail_flag( 'trail', __( 'Show visitors what each page held back and loaded', 'silvertrail' ) ); ?><br>
					<?php $silvertrail_flag( 'leak_check', __( 'Leak check for admins', 'silvertrail' ), __( 'Open any page with ?silvertrail-check to see what it loads before consent.', 'silvertrail' ) ); ?>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

	<h2><?php esc_html_e( 'Ask everyone again', 'silvertrail' ); ?></h2>
	<p><?php esc_html_e( 'Silvertrail asks again by itself when services are added or removed. Use this after changing what a service does.', 'silvertrail' ); ?></p>
	<p class="description"><?php esc_html_e( 'With a page cache, purge it afterwards. Cached pages carry the old version until they expire, and visitors who land on them are not asked again.', 'silvertrail' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="silvertrail_ask_again">
		<?php wp_nonce_field( 'silvertrail_ask_again' ); ?>
		<?php submit_button( __( 'Ask everyone again', 'silvertrail' ), 'secondary', 'submit', false ); ?>
	</form>

	<?php if ( ! empty( $silvertrail_settings['records'] ) ) : ?>
		<h2><?php esc_html_e( 'Export records', 'silvertrail' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="silvertrail_export">
			<?php wp_nonce_field( 'silvertrail_export' ); ?>
			<?php submit_button( __( 'Download CSV', 'silvertrail' ), 'secondary', 'submit', false ); ?>
		</form>
	<?php endif; ?>
</div>
