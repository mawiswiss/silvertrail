<?php
/**
 * The floating button that reopens the preferences. Copy it to your theme as
 * silvertrail/reopen-button.php to change it, and keep the data-silvertrail-* attributes.
 * In auto mode it stays hidden on pages with another link to #silvertrail.
 *
 * @package Silvertrail
 *
 * @var array{label: string, appearance: string, mode: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<button
	type="button"
	class="silvertrail-reopen"
	data-silvertrail-open
	data-silvertrail-reopen="<?php echo esc_attr( $args['mode'] ); ?>"
	data-silvertrail-appearance="<?php echo esc_attr( $args['appearance'] ); ?>"
	aria-label="<?php echo esc_attr( $args['label'] ); ?>"
	hidden
>
	<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
		<path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-4.6-4.2A4 4 0 0 1 12 2Zm-3.5 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm7 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm-6 2a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/>
	</svg>
</button>
