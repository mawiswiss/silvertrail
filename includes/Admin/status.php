<?php
/**
 * @package Silvertrail
 *
 * @var string $tab
 */

declare(strict_types=1);

use Silvertrail\Admin\Notices;
use Silvertrail\Admin\SettingsPage;
use Silvertrail\Config;
use Silvertrail\Plugin;
use Silvertrail\Settings;

defined( 'ABSPATH' ) || exit;

$silvertrail_config   = Config::get();
$silvertrail_in_place = array_filter( Config::consent_ids(), static fn( string $id ): bool => $silvertrail_config['groups'][ $silvertrail_config['services'][ $id ]['group'] ]['in_place'] );
$silvertrail_asking   = count( Config::consent_ids() ) - count( $silvertrail_in_place );
$silvertrail_embeds   = count( $silvertrail_in_place );
$silvertrail_on       = (bool) Settings::get( 'enabled' );
$silvertrail_overview = Notices::overview( $tab );
?>

<section class="silvertrail-status" aria-labelledby="silvertrail-status-title">
	<div class="silvertrail-status__main">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="silvertrail_toggle">
			<input type="hidden" name="enabled" value="<?php echo $silvertrail_on ? '0' : '1'; ?>">
			<?php wp_nonce_field( 'silvertrail_toggle', '_silvertrail_toggle' ); ?>
			<button type="submit" class="silvertrail-switch" aria-pressed="<?php echo $silvertrail_on ? 'true' : 'false'; ?>" aria-labelledby="silvertrail-status-title" <?php disabled( Settings::in_code( 'enabled' ) ); ?>></button>
		</form>
		<div>
			<h2 id="silvertrail-status-title">
				<?php $silvertrail_on ? esc_html_e( 'Silvertrail is on', 'silvertrail' ) : esc_html_e( 'Silvertrail is off', 'silvertrail' ); ?>
				<?php echo SettingsPage::in_code_label( 'enabled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?>
			</h2>
			<p>
				<?php
				if ( ! $silvertrail_on ) {
					esc_html_e( 'Visitors get no banner, nothing is blocked, and none of its tags load.', 'silvertrail' );
					echo ' <a href="' . esc_url( add_query_arg( Plugin::PREVIEW, '1', home_url( '/' ) ) ) . '" target="_blank">' . esc_html__( 'Preview the home page', 'silvertrail' ) . '</a>';
				} elseif ( $silvertrail_asking > 0 ) {
					/* translators: %s: number of services */
					echo esc_html( sprintf( _n( 'The banner asks about %s service.', 'The banner asks about %s services.', $silvertrail_asking, 'silvertrail' ), number_format_i18n( $silvertrail_asking ) ) );
					if ( $silvertrail_embeds > 0 ) {
						/* translators: %s: number of services */
						echo ' ' . esc_html( sprintf( _n( '%s more asks on its placeholder.', '%s more ask on their placeholder.', $silvertrail_embeds, 'silvertrail' ), number_format_i18n( $silvertrail_embeds ) ) );
					}
				} elseif ( $silvertrail_embeds > 0 ) {
					/* translators: %s: number of services */
					echo esc_html( sprintf( _n( 'No banner opens. %s service asks on its placeholder.', 'No banner opens. %s services ask on their placeholder.', $silvertrail_embeds, 'silvertrail' ), number_format_i18n( $silvertrail_embeds ) ) );
				} else {
					esc_html_e( 'Nothing needs consent yet, so no banner opens.', 'silvertrail' );
					if ( 'services' !== $tab ) {
						echo ' <a href="' . esc_url( SettingsPage::url() ) . '">' . esc_html__( 'Turn on a service', 'silvertrail' ) . '</a>';
					}
				}
				?>
			</p>
		</div>
	</div>

	<?php if ( array() !== $silvertrail_overview ) : ?>
		<ul class="silvertrail-status__todo">
			<?php foreach ( $silvertrail_overview as $silvertrail_item ) : ?>
				<li class="<?php echo esc_attr( "silvertrail-status__item silvertrail-status__item--{$silvertrail_item['type']}" ); ?>">
					<?php echo esc_html( $silvertrail_item['summary'] ); ?>
					<a href="<?php echo esc_url( $silvertrail_item['url'] ); ?>"><?php echo esc_html( $silvertrail_item['where'] ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( ! get_option( SettingsPage::LICENSE_SEEN ) ) : ?>
		<form class="silvertrail-status__license" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="silvertrail_license_seen">
			<?php wp_nonce_field( 'silvertrail_license_seen', '_silvertrail_license' ); ?>
			<?php echo esc_html( Plugin::license() ); ?>
			<a href="<?php echo esc_url( SILVERTRAIL_LICENSE_URL ); ?>"><?php esc_html_e( 'Site license', 'silvertrail' ); ?></a>
			<button type="submit" class="button-link"><?php esc_html_e( 'Dismiss', 'silvertrail' ); ?></button>
		</form>
	<?php endif; ?>
</section>
