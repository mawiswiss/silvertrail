<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Settings;

defined( 'ABSPATH' ) || exit;
?>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Records', 'silvertrail' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Decisions', 'silvertrail' ); ?></th>
			<td>
				<?php
				SettingsPage::checkbox(
					'records',
					__( 'Keep a record of each decision', 'silvertrail' ),
					/* translators: %d: number of days */
					sprintf( __( 'Stores a random consent ID, the choices and the time. No IP address, no browser details. Each record is deleted after %d days.', 'silvertrail' ), (int) Settings::get( 'retention_days' ) )
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Export', 'silvertrail' ); ?></th>
			<td>
				<?php if ( Settings::get( 'records' ) ) : ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=silvertrail_export' ), 'silvertrail_export' ) ); ?>"><?php esc_html_e( 'Download CSV', 'silvertrail' ); ?></a>
				<?php else : ?>
					<?php esc_html_e( 'Records are off. Turn them on above.', 'silvertrail' ); ?>
				<?php endif; ?>
			</td>
		</tr>
	</table>
	<?php SettingsPage::save( 'records' ); ?>
</section>
