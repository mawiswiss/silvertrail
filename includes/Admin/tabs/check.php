<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>

<section id="silvertrail-scan" class="card silvertrail-section">
	<h2><?php esc_html_e( 'What a visitor’s browser loads', 'silvertrail' ); ?></h2>
	<p><?php esc_html_e( 'Opens a page as a first-time visitor who has not chosen yet. Lists every other server the page contacts and what to do about each.', 'silvertrail' ); ?></p>
	<p class="hide-if-js"><?php esc_html_e( 'The check runs in this browser and needs JavaScript.', 'silvertrail' ); ?></p>
	<div class="hide-if-no-js">
		<p class="description"><?php esc_html_e( 'Turn off ad blockers in this browser first. They hide requests that real visitors make.', 'silvertrail' ); ?></p>
		<p>
			<label for="silvertrail-scan-url"><?php esc_html_e( 'Page', 'silvertrail' ); ?></label>
			<input type="text" id="silvertrail-scan-url" class="regular-text code" value="<?php echo esc_attr( home_url( '/' ) ); ?>" spellcheck="false">
			<button type="button" class="button" data-silvertrail-scan-run><?php esc_html_e( 'Scan', 'silvertrail' ); ?></button>
		</p>
		<div data-silvertrail-scan-result aria-live="polite"></div>
	</div>
</section>
