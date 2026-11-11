<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>

<section id="silvertrail-scan" class="card silvertrail-section">
	<h2><?php esc_html_e( 'Leak check', 'silvertrail' ); ?></h2>
	<p><?php esc_html_e( 'Opens each page as a first-time visitor, declines, agrees to each service on its own and loads the page again. Lists what runs without consent, what starts only after a reload, and what to do about each.', 'silvertrail' ); ?></p>
	<p class="hide-if-js"><?php esc_html_e( 'The check runs in this browser and needs JavaScript.', 'silvertrail' ); ?></p>
	<table class="form-table hide-if-no-js" role="presentation">
		<tr>
			<th scope="row"><label for="silvertrail-scan-urls"><?php esc_html_e( 'Pages', 'silvertrail' ); ?></label></th>
			<td>
				<textarea id="silvertrail-scan-urls" class="large-text code" rows="3" spellcheck="false" aria-describedby="silvertrail-scan-urls-description"><?php echo esc_textarea( home_url( '/' ) ); ?></textarea>
				<p class="description" id="silvertrail-scan-urls-description"><?php esc_html_e( 'One address of this site per line. Agreeing counts as one visit in the site’s trackers. Turn off ad blockers in this browser first. They hide requests that real visitors make.', 'silvertrail' ); ?></p>
				<p>
					<button type="button" class="button" data-silvertrail-scan-run><?php esc_html_e( 'Scan', 'silvertrail' ); ?></button>
					<button type="button" class="button" data-silvertrail-scan-cancel hidden><?php esc_html_e( 'Cancel', 'silvertrail' ); ?></button>
				</p>
				<div class="silvertrail-scan-progress" data-silvertrail-scan-progress hidden>
					<p id="silvertrail-scan-step"></p>
					<progress aria-labelledby="silvertrail-scan-step"></progress>
				</div>
				<div data-silvertrail-scan-result></div>
			</td>
		</tr>
	</table>
</section>
