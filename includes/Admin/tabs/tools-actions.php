<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Config;
use Silvertrail\Settings;

defined( 'ABSPATH' ) || exit;
?>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Ask everyone again', 'silvertrail' ); ?></h2>
	<p><?php esc_html_e( 'Silvertrail asks again by itself when services are added or removed. Use this after changing what a service does.', 'silvertrail' ); ?></p>
	<p class="description"><?php esc_html_e( 'With a page cache, purge it afterwards. Cached pages carry the old version until they expire, and visitors who land on them are not asked again.', 'silvertrail' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="silvertrail_ask_again">
		<?php wp_nonce_field( 'silvertrail_ask_again' ); ?>
		<?php submit_button( __( 'Ask everyone again', 'silvertrail' ), 'secondary' ); ?>
	</form>
</section>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Export records', 'silvertrail' ); ?></h2>
	<?php if ( Settings::get( 'records' ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="silvertrail_export">
			<?php wp_nonce_field( 'silvertrail_export' ); ?>
			<?php submit_button( __( 'Download CSV', 'silvertrail' ), 'secondary' ); ?>
		</form>
	<?php else : ?>
		<p><?php esc_html_e( 'Records are off. Turn them on above.', 'silvertrail' ); ?></p>
	<?php endif; ?>
</section>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Setup', 'silvertrail' ); ?></h2>
	<table class="form-table silvertrail-facts" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Version', 'silvertrail' ); ?></th>
			<td><code><?php echo esc_html( SILVERTRAIL_VERSION ); ?></code></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Consent revision', 'silvertrail' ); ?></th>
			<td>
				<code><?php echo esc_html( (string) Config::stored_revision() ); ?></code>
				<p class="description"><?php esc_html_e( 'Goes up when services are added or removed, and with Ask everyone again. A visitor whose cookie holds a lower number is asked again.', 'silvertrail' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Cookie', 'silvertrail' ); ?></th>
			<td><code><?php echo esc_html( Config::cookie() ); ?></code></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Google Consent Mode', 'silvertrail' ); ?></th>
			<td>
				<code><?php echo esc_html( Config::get()['google']['mode'] ); ?></code>
				<p class="description">
					<?php
					printf(
						/* translators: %s: the silvertrail_config filter */
						esc_html__( 'Change it with the %s filter.', 'silvertrail' ),
						'<code>silvertrail_config</code>'
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'WP Consent API', 'silvertrail' ); ?></th>
			<td><?php echo function_exists( 'wp_has_consent' ) ? esc_html__( 'Active, Silvertrail reports every choice to it', 'silvertrail' ) : esc_html__( 'Not installed', 'silvertrail' ); ?></td>
		</tr>
	</table>
</section>
