<?php
/**
 * @package Silvertrail
 *
 * @var string $tab
 */

declare(strict_types=1);

use Silvertrail\Admin\SettingsPage;

defined( 'ABSPATH' ) || exit;

$silvertrail_tabs = array(
	'services' => __( 'Services', 'silvertrail' ),
	'banner'   => __( 'Banner', 'silvertrail' ),
	'tools'    => __( 'Tools', 'silvertrail' ),
);
?>
<div class="wrap">
	<h1>Silvertrail</h1>
	<hr class="wp-header-end">
	<?php require __DIR__ . '/notices.php'; ?>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Silvertrail settings', 'silvertrail' ); ?>">
		<?php foreach ( $silvertrail_tabs as $silvertrail_id => $silvertrail_label ) : ?>
			<a
				href="<?php echo esc_url( SettingsPage::url( $silvertrail_id ) ); ?>"
				class="nav-tab<?php echo $tab === $silvertrail_id ? ' nav-tab-active' : ''; ?>"
				<?php echo $tab === $silvertrail_id ? 'aria-current="page"' : ''; ?>
			><?php echo esc_html( $silvertrail_label ); ?></a>
		<?php endforeach; ?>
	</nav>

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
