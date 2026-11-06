<?php
/**
 * @package Silvertrail
 *
 * @var array{text?: string} $attributes
 */

defined( 'ABSPATH' ) || exit;

$silvertrail_link = Silvertrail\Reopen::link( wp_strip_all_tags( $attributes['text'] ?? '' ) );
if ( '' === $silvertrail_link ) {
	return;
}
?>
<p <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php echo $silvertrail_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the link escapes its text. ?>
</p>
