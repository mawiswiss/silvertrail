<?php
/**
 * @package Silvertrail
 *
 * @var array{text?: string} $attributes
 */

defined( 'ABSPATH' ) || exit;

$silvertrail_link = Silvertrail\Reopen::link( wp_strip_all_tags( $attributes['text'] ?? '' ), ' class="wp-block-navigation-item__content"' );
if ( '' === $silvertrail_link ) {
	return;
}
?>
<li <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'wp-block-navigation-item' ) ) ); ?>><?php echo wp_kses_post( $silvertrail_link ); ?></li>
