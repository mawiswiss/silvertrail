<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Admin\Notices;
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
$silvertrail_group     = static fn( string $id ): string => $silvertrail_config['groups'][ $id ]['name'] ?? $id;
$silvertrail_codes     = static fn( array $items ): string => implode( ' ', array_map( static fn( $item ): string => '<code>' . esc_html( (string) $item ) . '</code>', $items ) );
$silvertrail_trackers  = array_filter( $silvertrail_presets, static fn( array $preset ): bool => isset( $preset['tag_id'] ) );
$silvertrail_embeds    = array_diff_key( $silvertrail_presets, $silvertrail_trackers );
$silvertrail_in_code   = static fn( string $id ): bool => SettingsPage::in_code( $id );
$silvertrail_turn_on   = SettingsPage::turning_on();
$silvertrail_state     = static fn( string $id ): string => match ( Presets::code_state( $id ) ) {
	'on'      => __( 'On in code', 'silvertrail' ),
	'removed' => __( 'Removed in code', 'silvertrail' ),
	'changed' => __( 'Changed in code', 'silvertrail' ),
	default   => '',
};
$silvertrail_toggle    = static function ( string $id, string $name, string $purpose, bool $focus = false ) use ( $silvertrail_state, $silvertrail_in_code, $silvertrail_turn_on ): void {
	$state   = $silvertrail_state( $id );
	$in_code = $silvertrail_in_code( $id );
	printf(
		'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s %4$s%9$s> <strong>%5$s</strong> <code>%6$s</code></label>%7$s<p class="description">%8$s</p>',
		esc_attr( "silvertrail-preset-{$id}" ),
		esc_attr( SettingsPage::field( 'presets', $id, 'enabled' ) ),
		checked( $in_code || Presets::switched_on( $id ) || $silvertrail_turn_on === $id, true, false ),
		disabled( $in_code, true, false ),
		esc_html( $name ),
		esc_html( $id ),
		'' === $state ? '' : ' ' . wp_kses_post( SettingsPage::in_code_chip( $state, $in_code ) ),
		esc_html( $purpose ),
		$focus ? ' autofocus' : ''
	);
};
?>

<section class="card silvertrail-section" id="silvertrail-trackers">
	<h2><?php esc_html_e( 'Trackers', 'silvertrail' ); ?></h2>
	<?php echo wp_kses_post( SettingsPage::docs_link( 'services/#presets' ) ); ?>
	<?php Notices::card( 'trackers' ); ?>
	<p><?php esc_html_e( 'Silvertrail prints the official tag and holds it back until the visitor agrees.', 'silvertrail' ); ?></p>
	<table class="silvertrail-list">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Service', 'silvertrail' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Group', 'silvertrail' ); ?></th>
				<th scope="col"><?php esc_html_e( 'ID', 'silvertrail' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $silvertrail_trackers as $silvertrail_id => $silvertrail_preset ) : ?>
				<?php
				$silvertrail_live = $silvertrail_config['services'][ $silvertrail_id ] ?? null;
				$silvertrail_tag  = $silvertrail_in_code( $silvertrail_id ) ? (string) ( $silvertrail_live['tag_id'] ?? '' ) : (string) ( $silvertrail_stored[ $silvertrail_id ]['tag_id'] ?? '' );
				?>
				<tr>
					<td><?php $silvertrail_toggle( $silvertrail_id, $silvertrail_preset['service']['name'], $silvertrail_preset['service']['purpose'] ); ?></td>
					<td><?php echo esc_html( $silvertrail_group( $silvertrail_live['group'] ?? $silvertrail_preset['service']['group'] ) ); ?></td>
					<td>
						<?php if ( isset( $silvertrail_preset['tag_id'] ) ) : ?>
							<label class="screen-reader-text" for="<?php echo esc_attr( "silvertrail-{$silvertrail_id}-id" ); ?>"><?php echo esc_html( $silvertrail_preset['service']['name'] . ' ' . $silvertrail_preset['tag_id']['label'] ); ?></label>
							<input
								type="text"
								class="regular-text code"
								id="<?php echo esc_attr( "silvertrail-{$silvertrail_id}-id" ); ?>"
								name="<?php echo esc_attr( SettingsPage::field( 'presets', $silvertrail_id, 'tag_id' ) ); ?>"
								value="<?php echo esc_attr( $silvertrail_tag ); ?>"
								placeholder="<?php echo esc_attr( $silvertrail_preset['tag_id']['example'] ); ?>"
								pattern="<?php echo esc_attr( $silvertrail_preset['tag_id']['pattern'] ); ?>"
								<?php disabled( $silvertrail_in_code( $silvertrail_id ) ); ?>
								<?php echo $silvertrail_turn_on === $silvertrail_id ? 'autofocus' : ''; ?>
							>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php SettingsPage::save( 'trackers' ); ?>
</section>

<section class="card silvertrail-section" id="silvertrail-embeds">
	<h2><?php esc_html_e( 'Embeds', 'silvertrail' ); ?></h2>
	<?php echo wp_kses_post( SettingsPage::docs_link( 'services/#embeds' ) ); ?>
	<?php Notices::card( 'embeds' ); ?>
	<p><?php esc_html_e( 'Videos and maps from these providers show a placeholder and ask there, without opening the banner.', 'silvertrail' ); ?></p>
	<table class="silvertrail-list">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Service', 'silvertrail' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Hosts', 'silvertrail' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $silvertrail_embeds as $silvertrail_id => $silvertrail_preset ) : ?>
				<tr>
					<td><?php $silvertrail_toggle( $silvertrail_id, $silvertrail_preset['service']['name'], $silvertrail_preset['service']['purpose'], $silvertrail_turn_on === $silvertrail_id ); ?></td>
					<td class="silvertrail-list__hosts"><?php echo wp_kses_post( $silvertrail_codes( $silvertrail_config['services'][ $silvertrail_id ]['hosts'] ?? $silvertrail_preset['service']['hosts'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php SettingsPage::save( 'embeds' ); ?>
</section>

<section class="card silvertrail-section">
	<h2><?php esc_html_e( 'Registered in code', 'silvertrail' ); ?></h2>
	<?php echo wp_kses_post( SettingsPage::docs_link( 'services/#register-a-service' ) ); ?>
	<p>
		<?php
		printf(
			/* translators: 1: silvertrail_register_service(), 2: the silvertrail_config filter */
			esc_html__( 'Change any service in code by its ID: %1$s with only the fields to change, or the %2$s filter to edit or remove anything at the end.', 'silvertrail' ),
			'<code>silvertrail_register_service()</code>',
			'<code>silvertrail_config</code>'
		);
		echo ' ' . wp_kses_post( SettingsPage::help_button( 'silvertrail-adjust', __( 'Show the example', 'silvertrail' ) ) );
		?>
	</p>
	<?php if ( array() === $silvertrail_code ) : ?>
		<p>
			<?php
			printf(
				/* translators: %s: the silvertrail_register action */
				esc_html__( 'Nothing yet. Register your own services on the %s action.', 'silvertrail' ),
				'<code>silvertrail_register</code>'
			);
			echo ' ' . wp_kses_post( SettingsPage::help_button( 'silvertrail-service', __( 'Show the example', 'silvertrail' ) ) );
			?>
		</p>
	<?php else : ?>
		<table class="silvertrail-list">
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
							<code><?php echo esc_html( $silvertrail_id ); ?></code>
							<?php if ( '' !== $silvertrail_service['purpose'] ) : ?>
								<p class="description"><?php echo esc_html( $silvertrail_service['purpose'] ); ?></p>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $silvertrail_group( $silvertrail_service['group'] ) ); ?></td>
						<td><?php echo in_array( $silvertrail_id, $silvertrail_essential, true ) ? esc_html__( 'Not needed', 'silvertrail' ) : esc_html__( 'Required', 'silvertrail' ); ?></td>
						<td class="silvertrail-list__hosts">
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
</section>
