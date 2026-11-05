<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Settings;

defined( 'ABSPATH' ) || exit;
?>

<h2><?php esc_html_e( 'Leak check', 'silvertrail' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Admin panel', 'silvertrail' ); ?></th>
		<td>
			<?php SettingsPage::checkbox( 'leak_check', __( 'Show admins what a page loads before consent', 'silvertrail' ), __( 'Open any page with ?silvertrail-check to see the panel. Visitors never see it.', 'silvertrail' ) ); ?>
			<?php if ( Settings::get( 'leak_check' ) ) : ?>
				<p><a class="button" href="<?php echo esc_url( add_query_arg( 'silvertrail-check', '1', home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Check the home page', 'silvertrail' ); ?></a></p>
			<?php endif; ?>
		</td>
	</tr>
</table>
