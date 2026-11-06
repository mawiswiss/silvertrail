<?php
/**
 * @package Silvertrail
 *
 * @var string $tab
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Config;
use Silvertrail\Plugin;
use Silvertrail\Presets;
use Silvertrail\Settings;

defined( 'ABSPATH' ) || exit;

$silvertrail_config   = Config::get();
$silvertrail_in_place = array_filter( Config::consent_ids(), static fn( string $id ): bool => $silvertrail_config['groups'][ $silvertrail_config['services'][ $id ]['group'] ]['in_place'] );
$silvertrail_asking   = count( Config::consent_ids() ) - count( $silvertrail_in_place );
$silvertrail_embeds   = count( $silvertrail_in_place );
$silvertrail_turn_on  = SettingsPage::turning_on();
$silvertrail_on       = (bool) Settings::get( 'enabled' );
?>

<section class="silvertrail-status" aria-labelledby="silvertrail-status-title">
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
		<?php if ( '' === get_privacy_policy_url() ) : ?>
			<p class="silvertrail-status__todo">
				<?php
				printf(
					/* translators: %s: link to the privacy settings */
					esc_html__( 'Publish a privacy policy page under %s, so the banner can link to it.', 'silvertrail' ),
					'<a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' . esc_html__( 'Settings › Privacy', 'silvertrail' ) . '</a>'
				);
				?>
			</p>
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
	</div>
</section>

<?php if ( 'services' === $tab && '' !== $silvertrail_turn_on ) : ?>
	<div class="notice notice-info inline">
		<p>
			<?php
			/* translators: %s: service name */
			echo esc_html( sprintf( __( '%s is ticked. Save Changes to turn it on.', 'silvertrail' ), Presets::all()[ $silvertrail_turn_on ]['service']['name'] ) );
			?>
		</p>
	</div>
<?php endif; ?>

<?php if ( 'tools' === $tab && isset( $_GET['silvertrail-asked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'Every visitor will be asked again on their next page view.', 'silvertrail' ); ?></p></div>
<?php endif; ?>
