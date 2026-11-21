<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\Notices;
use Silvertrail\Admin\SettingsPage;
use Silvertrail\Config;

defined( 'ABSPATH' ) || exit;
?>

<section class="card silvertrail-section" id="silvertrail-ask-again">
	<h2><?php esc_html_e( 'Ask everyone again', 'silvertrail' ); ?></h2>
	<?php echo wp_kses_post( SettingsPage::docs_link( 'cli/#ask-again' ) ); ?>
	<?php Notices::card( 'ask-again' ); ?>
	<p><?php esc_html_e( 'Silvertrail asks again by itself when services are added or removed. Use this after changing what a service does.', 'silvertrail' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="silvertrail_ask_again">
		<?php wp_nonce_field( 'silvertrail_ask_again' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Consent revision', 'silvertrail' ); ?></th>
				<td>
					<code><?php echo esc_html( (string) Config::stored_revision() ); ?></code>
					<p class="description"><?php esc_html_e( 'Goes up when services are added or removed, and with Ask everyone again. A visitor whose cookie holds a lower number is asked again.', 'silvertrail' ); ?></p>
					<p class="description"><?php esc_html_e( 'With a page cache, purge it afterwards. Cached pages carry the old version until they expire, and visitors who land on them are not asked again.', 'silvertrail' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Ask everyone again', 'silvertrail' ), 'secondary', 'submit', true, array( 'id' => 'silvertrail-ask-again-submit' ) ); ?>
	</form>
</section>
