<?php
/**
 * @package Silvertrail
 *
 * @var string $tab
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Config;
use Silvertrail\Presets;

defined( 'ABSPATH' ) || exit;

$silvertrail_config   = Config::get();
$silvertrail_in_place = array_filter( Config::consent_ids(), static fn( string $id ): bool => $silvertrail_config['groups'][ $silvertrail_config['services'][ $id ]['group'] ]['in_place'] );
$silvertrail_asking   = count( Config::consent_ids() ) - count( $silvertrail_in_place );
$silvertrail_embeds   = count( $silvertrail_in_place );
$silvertrail_turn_on  = SettingsPage::turning_on();
?>

<?php if ( 'services' === $tab ) : ?>
	<?php if ( '' !== $silvertrail_turn_on ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				/* translators: %s: service name */
				echo esc_html( sprintf( __( '%s is ticked. Save Changes to turn it on.', 'silvertrail' ), Presets::all()[ $silvertrail_turn_on ]['service']['name'] ) );
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $silvertrail_asking > 0 ) : ?>
		<div class="notice notice-success inline">
			<p>
				<?php
				/* translators: %s: number of services */
				echo esc_html( sprintf( _n( 'The banner is on. It asks about %s service.', 'The banner is on. It asks about %s services.', $silvertrail_asking, 'silvertrail' ), number_format_i18n( $silvertrail_asking ) ) );
				if ( $silvertrail_embeds > 0 ) {
					/* translators: %s: number of services */
					echo ' ' . esc_html( sprintf( _n( '%s more asks on its placeholder.', '%s more ask on their placeholder.', $silvertrail_embeds, 'silvertrail' ), number_format_i18n( $silvertrail_embeds ) ) );
				}
				?>
			</p>
		</div>
	<?php elseif ( $silvertrail_embeds > 0 ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				/* translators: %s: number of services */
				echo esc_html( sprintf( _n( 'No banner opens. %s service asks on its placeholder, so only pages with such an embed ask anything.', 'No banner opens. %s services ask on their placeholder, so only pages with such an embed ask anything.', $silvertrail_embeds, 'silvertrail' ), number_format_i18n( $silvertrail_embeds ) ) );
				?>
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'No service needs consent yet, so no banner opens. Turn on a service below or register one in code.', 'silvertrail' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '' === get_privacy_policy_url() ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				printf(
					/* translators: %s: link to the privacy settings */
					esc_html__( 'No privacy policy page is published, so the banner cannot link to one. Choose it under %s.', 'silvertrail' ),
					'<a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' . esc_html__( 'Settings › Privacy', 'silvertrail' ) . '</a>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>
<?php endif; ?>

<?php if ( 'tools' === $tab && isset( $_GET['silvertrail-asked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'Every visitor will be asked again on their next page view.', 'silvertrail' ); ?></p></div>
<?php endif; ?>
