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
<p <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php echo wp_kses_post( $silvertrail_link ); ?>
</p>
