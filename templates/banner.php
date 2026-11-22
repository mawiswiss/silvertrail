<?php
/**
 * The consent banner. To change the markup, copy this file to your theme as
 * silvertrail/banner.php and keep the data-silvertrail-* attributes.
 *
 * @package Silvertrail
 *
 * @var array{
 *     layout: 'modal'|'box'|'bar',
 *     position: 'top'|'bottom',
 *     appearance: string,
 *     trail: bool,
 *     groups: array<string, array{name: string, description: string, locked: bool, services: array<string, array<string, mixed>>}>,
 *     texts: array<string, string>,
 *     privacy_url: string,
 *     imprint_url: string
 * } $args
 */

defined( 'ABSPATH' ) || exit;

$silvertrail_texts = $args['texts'];
$silvertrail_links = array_filter(
	array(
		$silvertrail_texts['privacy_link'] => $args['privacy_url'],
		$silvertrail_texts['imprint_link'] => $args['imprint_url'],
	)
);
$silvertrail_info  = array( 'provider', 'purpose', 'cookies', 'expiry' );
?>
<dialog
	id="silvertrail-banner"
	class="silvertrail silvertrail--<?php echo esc_attr( $args['layout'] . ( 'bar' === $args['layout'] ? " silvertrail--{$args['position']}" : '' ) ); ?>"
	aria-labelledby="silvertrail-title"
	data-silvertrail-banner
	data-silvertrail-appearance="<?php echo esc_attr( $args['appearance'] ); ?>"
	popover
>
	<div class="silvertrail__inner">
		<h2 id="silvertrail-title" class="silvertrail__title" tabindex="-1" autofocus><?php echo esc_html( $silvertrail_texts['title'] ); ?></h2>

		<div class="silvertrail__view" data-silvertrail-view="entrance">
			<p class="silvertrail__text"><?php echo esc_html( $silvertrail_texts['intro'] ); ?></p>
		</div>

		<div class="silvertrail__view" data-silvertrail-view="preferences" hidden>
			<p class="silvertrail__text"><?php echo esc_html( $silvertrail_texts['preferences_intro'] ); ?></p>
			<p class="silvertrail__note" data-silvertrail-gpc hidden><?php echo esc_html( $silvertrail_texts['gpc'] ); ?></p>
			<ul class="silvertrail__groups">
				<?php foreach ( $args['groups'] as $silvertrail_group_id => $silvertrail_group ) : ?>
					<li class="silvertrail__group">
						<label class="silvertrail__switch">
							<input
								type="checkbox"
								switch
								data-silvertrail-group="<?php echo esc_attr( $silvertrail_group_id ); ?>"
								<?php echo $silvertrail_group['locked'] ? 'checked disabled' : ''; ?>
							>
							<span class="silvertrail__group-name"><?php echo esc_html( $silvertrail_group['name'] ); ?></span>
						</label>
						<?php if ( '' !== $silvertrail_group['description'] ) : ?>
							<p class="silvertrail__group-text"><?php echo esc_html( $silvertrail_group['description'] ); ?></p>
						<?php endif; ?>
						<details class="silvertrail__details">
							<summary>
								<?php
								$silvertrail_count = count( $silvertrail_group['services'] );
								/* translators: %d: number of services */
								echo esc_html( sprintf( _n( '%d service', '%d services', $silvertrail_count, 'silvertrail' ), $silvertrail_count ) );
								?>
							</summary>
							<ul class="silvertrail__services">
								<?php foreach ( $silvertrail_group['services'] as $silvertrail_service_id => $silvertrail_service ) : ?>
									<li class="silvertrail__service">
										<label class="silvertrail__switch">
											<input
												type="checkbox"
												switch
												value="<?php echo esc_attr( $silvertrail_service_id ); ?>"
												data-silvertrail-service
												data-group="<?php echo esc_attr( $silvertrail_group_id ); ?>"
												<?php echo $silvertrail_service['locked'] ? 'checked disabled' : ''; ?>
											>
											<span class="silvertrail__service-name"><?php echo esc_html( (string) $silvertrail_service['name'] ); ?></span>
										</label>
										<dl class="silvertrail__info">
											<?php foreach ( $silvertrail_info as $silvertrail_key ) : ?>
												<?php if ( '' !== (string) $silvertrail_service[ $silvertrail_key ] ) : ?>
													<dt><?php echo esc_html( $silvertrail_texts[ $silvertrail_key ] ); ?></dt>
													<dd><?php echo esc_html( (string) $silvertrail_service[ $silvertrail_key ] ); ?></dd>
												<?php endif; ?>
											<?php endforeach; ?>
											<?php if ( '' !== (string) $silvertrail_service['privacy_url'] ) : ?>
												<dt><?php echo esc_html( $silvertrail_texts['privacy_url'] ); ?></dt>
												<dd><a href="<?php echo esc_url( (string) $silvertrail_service['privacy_url'] ); ?>" rel="noopener" target="_blank"><?php echo esc_html( (string) wp_parse_url( (string) $silvertrail_service['privacy_url'], PHP_URL_HOST ) ); ?></a></dd>
											<?php endif; ?>
										</dl>
									</li>
								<?php endforeach; ?>
							</ul>
						</details>
					</li>
				<?php endforeach; ?>
			</ul>
			<button type="button" class="silvertrail__link silvertrail__save has-background has-text-color" data-silvertrail-action="save"><?php echo esc_html( $silvertrail_texts['save'] ); ?></button>
			<?php if ( $args['trail'] ) : ?>
				<section class="silvertrail__trail" data-silvertrail-trail hidden>
					<h3 class="silvertrail__trail-title"><?php echo esc_html( $silvertrail_texts['trail_title'] ); ?></h3>
				</section>
			<?php endif; ?>
		</div>

		<div class="silvertrail__footer">
			<div class="silvertrail__actions">
				<button type="button" class="silvertrail__button has-background has-text-color" data-silvertrail-action="accept-all"><?php echo esc_html( $silvertrail_texts['accept_all'] ); ?></button>
				<button type="button" class="silvertrail__button has-background has-text-color" data-silvertrail-action="reject-all"><?php echo esc_html( $silvertrail_texts['reject_all'] ); ?></button>
				<button type="button" class="silvertrail__link has-background has-text-color" data-silvertrail-action="preferences" data-silvertrail-view="entrance"><?php echo esc_html( $silvertrail_texts['preferences'] ); ?></button>
			</div>
		</div>

		<?php if ( array() !== $silvertrail_links ) : ?>
			<p class="silvertrail__legal">
				<?php foreach ( $silvertrail_links as $silvertrail_label => $silvertrail_url ) : ?>
					<a href="<?php echo esc_url( $silvertrail_url ); ?>"><?php echo esc_html( (string) $silvertrail_label ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>
	</div>

	<button type="button" class="silvertrail__close has-background has-text-color" data-silvertrail-action="close" aria-label="<?php echo esc_attr( $silvertrail_texts['close'] ); ?>" hidden>
		<span aria-hidden="true">&times;</span>
	</button>
</dialog>
