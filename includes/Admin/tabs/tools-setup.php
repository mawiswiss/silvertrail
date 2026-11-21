<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Config;

defined( 'ABSPATH' ) || exit;
?>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Setup', 'silvertrail' ); ?></h2>
	<?php echo wp_kses_post( SettingsPage::docs_link( 'consent-signals/' ) ); ?>
	<table class="form-table silvertrail-facts" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Version', 'silvertrail' ); ?></th>
			<td><code><?php echo esc_html( SILVERTRAIL_VERSION ); ?></code></td>
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
