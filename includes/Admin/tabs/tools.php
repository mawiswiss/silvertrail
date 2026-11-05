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
	<?php
	SettingsPage::checkbox(
		'records',
		__( 'Keep a record of each decision', 'silvertrail' ),
		/* translators: %d: number of days */
		sprintf( __( 'Stores a random consent ID, the choices and the time. No IP address, no browser details. Each record is deleted after %d days.', 'silvertrail' ), (int) Settings::get( 'retention_days' ) )
	);
	?>
</section>
