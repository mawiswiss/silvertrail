<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;
use Silvertrail\Config;
use Silvertrail\Presets;
use Silvertrail\Settings;

defined( 'ABSPATH' ) || exit;

$silvertrail_config    = Config::get();
$silvertrail_presets   = Presets::all();
$silvertrail_stored    = Settings::all()['presets'];
$silvertrail_code      = array_diff_key( $silvertrail_config['services'], $silvertrail_presets, array( 'silvertrail' => true ) );
$silvertrail_essential = Config::essential_ids();
$silvertrail_asking    = count( Config::consent_ids() );
$silvertrail_group     = static fn( string $id ): string => $silvertrail_config['groups'][ $id ]['name'] ?? $id;
$silvertrail_codes     = static fn( array $items ): string => implode( ' ', array_map( static fn( $item ): string => '<code>' . esc_html( (string) $item ) . '</code>', $items ) );
$silvertrail_trackers  = array_filter( $silvertrail_presets, static fn( array $preset ): bool => isset( $preset['tag_id'] ) );
$silvertrail_embeds    = array_diff_key( $silvertrail_presets, $silvertrail_trackers );
?>

<?php if ( $silvertrail_asking > 0 ) : ?>
	<div class="notice notice-success inline">
		<p>
			<?php
			/* translators: %s: number of services */
			echo esc_html( sprintf( _n( 'The banner is on. It asks about %s service.', 'The banner is on. It asks about %s services.', $silvertrail_asking, 'silvertrail' ), number_format_i18n( $silvertrail_asking ) ) );
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

<h2><?php esc_html_e( 'Trackers', 'silvertrail' ); ?></h2>
<p><?php esc_html_e( 'Silvertrail prints the official tag and holds it back until the visitor agrees.', 'silvertrail' ); ?></p>
<table class="widefat striped">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Service', 'silvertrail' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Group', 'silvertrail' ); ?></th>
			<th scope="col"><?php esc_html_e( 'ID', 'silvertrail' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $silvertrail_trackers as $silvertrail_id => $silvertrail_preset ) : ?>
			<?php $silvertrail_setting = $silvertrail_stored[ $silvertrail_id ] ?? array(); ?>
			<tr>
				<td>
					<label for="<?php echo esc_attr( "silvertrail-preset-{$silvertrail_id}" ); ?>">
						<input type="checkbox" id="<?php echo esc_attr( "silvertrail-preset-{$silvertrail_id}" ); ?>" name="<?php echo esc_attr( SettingsPage::field( 'presets', $silvertrail_id, 'enabled' ) ); ?>" value="1" <?php checked( ! empty( $silvertrail_setting['enabled'] ) ); ?>>
						<strong><?php echo esc_html( $silvertrail_preset['service']['name'] ); ?></strong>
					</label>
					<p class="description"><?php echo esc_html( $silvertrail_preset['service']['purpose'] ); ?></p>
				</td>
				<td><?php echo esc_html( $silvertrail_group( $silvertrail_preset['service']['group'] ) ); ?></td>
				<td>
					<?php if ( isset( $silvertrail_preset['tag_id'] ) ) : ?>
						<label class="screen-reader-text" for="<?php echo esc_attr( "silvertrail-{$silvertrail_id}-id" ); ?>"><?php echo esc_html( $silvertrail_preset['service']['name'] . ' ' . $silvertrail_preset['tag_id']['label'] ); ?></label>
						<input
							type="text"
							class="regular-text code"
							id="<?php echo esc_attr( "silvertrail-{$silvertrail_id}-id" ); ?>"
							name="<?php echo esc_attr( SettingsPage::field( 'presets', $silvertrail_id, 'tag_id' ) ); ?>"
							value="<?php echo esc_attr( (string) ( $silvertrail_setting['tag_id'] ?? '' ) ); ?>"
							placeholder="<?php echo esc_attr( $silvertrail_preset['tag_id']['example'] ); ?>"
							pattern="<?php echo esc_attr( $silvertrail_preset['tag_id']['pattern'] ); ?>"
						>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<h2><?php esc_html_e( 'Embeds', 'silvertrail' ); ?></h2>
<p><?php esc_html_e( 'Videos and maps from these providers show a placeholder until the visitor agrees.', 'silvertrail' ); ?></p>
<table class="widefat striped">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Service', 'silvertrail' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Hosts', 'silvertrail' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $silvertrail_embeds as $silvertrail_id => $silvertrail_preset ) : ?>
			<?php $silvertrail_setting = $silvertrail_stored[ $silvertrail_id ] ?? array(); ?>
			<tr>
				<td>
					<label for="<?php echo esc_attr( "silvertrail-preset-{$silvertrail_id}" ); ?>">
						<input type="checkbox" id="<?php echo esc_attr( "silvertrail-preset-{$silvertrail_id}" ); ?>" name="<?php echo esc_attr( SettingsPage::field( 'presets', $silvertrail_id, 'enabled' ) ); ?>" value="1" <?php checked( ! empty( $silvertrail_setting['enabled'] ) ); ?>>
						<strong><?php echo esc_html( $silvertrail_preset['service']['name'] ); ?></strong>
					</label>
					<p class="description"><?php echo esc_html( $silvertrail_preset['service']['purpose'] ); ?></p>
				</td>
				<td><?php echo wp_kses_post( $silvertrail_codes( $silvertrail_preset['service']['hosts'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<h2><?php esc_html_e( 'Registered in code', 'silvertrail' ); ?></h2>
<?php if ( array() === $silvertrail_code ) : ?>
	<p>
		<?php
		printf(
			/* translators: %s: the silvertrail_register action */
			esc_html__( 'Nothing yet. Register your own services on the %s action. Help, at the top right, shows how.', 'silvertrail' ),
			'<code>silvertrail_register</code>'
		);
		?>
	</p>
<?php else : ?>
	<p><?php esc_html_e( 'These come from the theme or a plugin and change only there.', 'silvertrail' ); ?></p>
	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Service', 'silvertrail' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Group', 'silvertrail' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Consent', 'silvertrail' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Blocks', 'silvertrail' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $silvertrail_code as $silvertrail_id => $silvertrail_service ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $silvertrail_service['name'] ); ?></strong>
						<br><code><?php echo esc_html( $silvertrail_id ); ?></code>
					</td>
					<td><?php echo esc_html( $silvertrail_group( $silvertrail_service['group'] ) ); ?></td>
					<td><?php echo in_array( $silvertrail_id, $silvertrail_essential, true ) ? esc_html__( 'Not needed', 'silvertrail' ) : esc_html__( 'Required', 'silvertrail' ); ?></td>
					<td>
						<?php if ( array() !== $silvertrail_service['hosts'] ) : ?>
							<?php echo wp_kses_post( $silvertrail_codes( $silvertrail_service['hosts'] ) ); ?><br>
						<?php endif; ?>
						<?php if ( array() !== $silvertrail_service['handles'] ) : ?>
							<?php
							printf(
								/* translators: %s: script handles */
								esc_html__( 'Script handles: %s', 'silvertrail' ),
								wp_kses_post( $silvertrail_codes( $silvertrail_service['handles'] ) )
							);
							?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
