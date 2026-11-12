<?php
/**
 * The floating button that reopens the preferences. Copy it to your theme as
 * silvertrail/reopen-button.php to change it, and keep the data-silvertrail-* attributes.
 * In auto mode it stays hidden on pages with another link to #silvertrail.
 *
 * @package Silvertrail
 *
 * @var array{label: string, appearance: string, mode: string, position: string, icon: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<button
	type="button"
	class="<?php echo esc_attr( 'silvertrail-reopen silvertrail-reopen--' . $args['position'] ); ?>"
	data-silvertrail-open
	data-silvertrail-reopen="<?php echo esc_attr( $args['mode'] ); ?>"
	data-silvertrail-appearance="<?php echo esc_attr( $args['appearance'] ); ?>"
	aria-label="<?php echo esc_attr( $args['label'] ); ?>"
	hidden
>
	<?php echo wp_kses( $args['icon'], Silvertrail\Reopen::icon_html() ); ?>
</button>
