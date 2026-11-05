<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Settings;

defined( 'ABSPATH' ) || exit;

$silvertrail_settings = Settings::all();
?>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Choices', 'silvertrail' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="silvertrail-expiry"><?php esc_html_e( 'Keep a choice for', 'silvertrail' ); ?></label></th>
			<td>
				<input type="number" min="1" max="13" class="small-text" id="silvertrail-expiry" name="<?php echo esc_attr( SettingsPage::field( 'expiry_months' ) ); ?>" value="<?php echo esc_attr( (string) $silvertrail_settings['expiry_months'] ); ?>">
				<?php esc_html_e( 'months', 'silvertrail' ); ?>
				<p class="description"><?php esc_html_e( 'Visitors are asked again after this time, and whenever services are added or removed.', 'silvertrail' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Signals', 'silvertrail' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Signals', 'silvertrail' ); ?></legend>
					<?php SettingsPage::checkbox( 'gpc', __( 'Treat Global Privacy Control as “only essential” and show no banner', 'silvertrail' ) ); ?>
				</fieldset>
			</td>
		</tr>
	</table>
</section>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Records', 'silvertrail' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Keep records', 'silvertrail' ); ?></th>
			<td>
				<?php SettingsPage::checkbox( 'records', __( 'Keep a record of each decision', 'silvertrail' ), __( 'Stores a random consent ID, the choices and the time. No IP address, no browser details. Export them under Tools.', 'silvertrail' ) ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="silvertrail-retention"><?php esc_html_e( 'Delete records after', 'silvertrail' ); ?></label></th>
			<td>
				<input type="number" min="30" max="3650" class="small-text" id="silvertrail-retention" name="<?php echo esc_attr( SettingsPage::field( 'retention_days' ) ); ?>" value="<?php echo esc_attr( (string) $silvertrail_settings['retention_days'] ); ?>">
				<?php esc_html_e( 'days', 'silvertrail' ); ?>
			</td>
		</tr>
	</table>
</section>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Transparency', 'silvertrail' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'On this page', 'silvertrail' ); ?></th>
			<td>
				<?php SettingsPage::checkbox( 'trail', __( 'Show visitors what each page held back and loaded', 'silvertrail' ) ); ?>
			</td>
		</tr>
	</table>
</section>
