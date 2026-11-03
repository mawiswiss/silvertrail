<?php
/**
 * Placeholder for a blocked embed. To change the markup, copy this file to your theme
 * as silvertrail/embed.php and keep the data-silvertrail-* attributes and the template.
 *
 * @package Silvertrail
 *
 * @var array{service: string, name: string, embed: string, texts: array<string, string>, theme: string} $args
 */

defined( 'ABSPATH' ) || exit;

$silvertrail_texts = $args['texts'];
?>
<div class="silvertrail-embed" data-silvertrail-embed="<?php echo esc_attr( $args['service'] ); ?>" data-silvertrail-appearance="<?php echo esc_attr( $args['theme'] ); ?>">
	<div class="silvertrail-embed__inner">
		<p class="silvertrail-embed__text"><?php echo esc_html( sprintf( $silvertrail_texts['embed_text'], $args['name'] ) ); ?></p>
		<button type="button" class="silvertrail-embed__button" data-silvertrail-load><?php echo esc_html( $silvertrail_texts['embed_load'] ); ?></button>
		<label class="silvertrail-embed__remember">
			<input type="checkbox" data-silvertrail-remember>
			<?php echo esc_html( sprintf( $silvertrail_texts['embed_remember'], $args['name'] ) ); ?>
		</label>
	</div>
	<template>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the embed HTML WordPress or the post already produced, unchanged.
		echo $args['embed'];
		?>
	</template>
</div>
