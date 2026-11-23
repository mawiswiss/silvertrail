<?php
/**
 * Placeholder for a blocked embed. To change the markup, copy this file to your theme
 * as silvertrail/embed.php and keep the data-silvertrail-* attributes and the empty
 * template. Silvertrail puts the embed into the template and its size into the style
 * attribute of the element with data-silvertrail-embed.
 *
 * @package Silvertrail
 *
 * @var array{service: string, name: string, texts: array<string, string>, theme: string, actions: list<array{label: string, grant: list<string>}>} $args
 */

defined( 'ABSPATH' ) || exit;

$silvertrail_texts = $args['texts'];
?>
<div class="silvertrail-embed" data-silvertrail-embed="<?php echo esc_attr( $args['service'] ); ?>" data-silvertrail-appearance="<?php echo esc_attr( $args['theme'] ); ?>">
	<div class="silvertrail-embed__inner">
		<p class="silvertrail-embed__text"><?php echo esc_html( sprintf( $silvertrail_texts['embed_text'], $args['name'] ) ); ?></p>
		<button type="button" class="silvertrail-embed__button has-background has-text-color" data-silvertrail-load><?php echo esc_html( $silvertrail_texts['embed_load'] ); ?></button>
		<label class="silvertrail-embed__remember">
			<input type="checkbox" data-silvertrail-remember>
			<?php echo esc_html( sprintf( $silvertrail_texts['embed_remember'], $args['name'] ) ); ?>
		</label>
		<?php if ( array() !== $args['actions'] ) : ?>
			<p class="silvertrail-embed__actions">
				<?php foreach ( $args['actions'] as $silvertrail_action ) : ?>
					<?php if ( array() === $silvertrail_action['grant'] ) : ?>
						<button type="button" class="silvertrail-embed__action has-background has-text-color" data-silvertrail-open><?php echo esc_html( $silvertrail_action['label'] ); ?></button>
					<?php else : ?>
						<button type="button" class="silvertrail-embed__action has-background has-text-color" data-silvertrail-grant="<?php echo esc_attr( implode( ' ', $silvertrail_action['grant'] ) ); ?>"><?php echo esc_html( $silvertrail_action['label'] ); ?></button>
					<?php endif; ?>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>
	</div>
	<template></template>
</div>
