<?php
/**
 * @package Silvertrail
 *
 * @var string $tab
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;

defined( 'ABSPATH' ) || exit;

$silvertrail_help = array(
	'services' => 'silvertrail-service',
	'banner'   => 'silvertrail-settings',
	'tools'    => 'silvertrail-cli',
);
?>
<div class="wrap">
	<h1>Silvertrail</h1>
	<hr class="wp-header-end">
	<?php require __DIR__ . '/status.php'; ?>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Silvertrail settings', 'silvertrail' ); ?>">
		<?php foreach ( SettingsPage::tabs() as $silvertrail_id => $silvertrail_label ) : ?>
			<a
				href="<?php echo esc_url( SettingsPage::url( $silvertrail_id ) ); ?>"
				class="nav-tab<?php echo $tab === $silvertrail_id ? ' nav-tab-active' : ''; ?>"
				<?php echo $tab === $silvertrail_id ? 'aria-current="page"' : ''; ?>
			><?php echo esc_html( $silvertrail_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<p>
		<?php esc_html_e( 'Everything here also works in code.', 'silvertrail' ); ?>
		<?php echo SettingsPage::help_button( $silvertrail_help[ $tab ], __( 'Developer help', 'silvertrail' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?>
	</p>

	<?php if ( 'tools' === $tab ) : ?>
		<?php require __DIR__ . '/tabs/tools-check.php'; ?>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( SettingsPage::SLUG ); ?>
		<input type="hidden" name="<?php echo esc_attr( SettingsPage::field( '_tab' ) ); ?>" value="<?php echo esc_attr( $tab ); ?>">
		<?php require __DIR__ . "/tabs/{$tab}.php"; ?>
	</form>

	<?php if ( 'tools' === $tab ) : ?>
		<?php require __DIR__ . '/tabs/tools-actions.php'; ?>
	<?php endif; ?>
</div>
