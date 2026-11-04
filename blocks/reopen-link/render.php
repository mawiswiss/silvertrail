<?php
/**
 * @package Silvertrail
 *
 * @var array{text?: string} $attributes
 */

defined( 'ABSPATH' ) || exit;

?>
<p <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php echo Silvertrail\Reopen::link( wp_strip_all_tags( $attributes['text'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the link escapes its text. ?>
</p>
