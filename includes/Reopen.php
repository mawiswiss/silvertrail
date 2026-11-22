<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

defined( 'ABSPATH' ) || exit;

/**
 * Ways back to the preferences: the shortcode, the block, the menu item and the floating button.
 */
final class Reopen {

	public const HASH = '#silvertrail';

	public const ICON = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-4.6-4.2A4 4 0 0 1 12 2Zm-3.5 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm7 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm-6 2a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/></svg>';

	private const SVG_TAGS = array( 'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon' );

	private const SVG_ATTRIBUTES = array( 'xmlns', 'viewbox', 'width', 'height', 'class', 'aria-hidden', 'focusable', 'fill', 'fill-rule', 'clip-rule', 'opacity', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'transform', 'd', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'points' );

	public static function register(): void {
		add_shortcode( 'silvertrail_link', array( self::class, 'shortcode' ) );
		add_action( 'init', array( self::class, 'block' ) );
		// Fires after core hides new users' extra boxes, so this one starts visible.
		add_action( 'admin_head-nav-menus.php', array( self::class, 'menu_box' ) );
		add_filter( 'wp_nav_menu_objects', array( self::class, 'menu_items' ) );
	}

	public static function menu_box(): void {
		add_meta_box( 'silvertrail-menu-item', 'Silvertrail', array( self::class, 'render_menu_box' ), 'nav-menus', 'side' );
	}

	public static function render_menu_box(): void {
		$title = Texts::all( get_locale() )['reopen'];
		?>
		<div id="silvertrail-menu-item" class="posttypediv">
			<div class="tabs-panel tabs-panel-active">
				<ul class="categorychecklist form-no-clear">
					<li>
						<label class="menu-item-title">
							<input type="checkbox" class="menu-item-checkbox" name="menu-item[-1][menu-item-object-id]" value="-1"> <?php echo esc_html( $title ); ?>
						</label>
						<input type="hidden" class="menu-item-type" name="menu-item[-1][menu-item-type]" value="custom">
						<input type="hidden" class="menu-item-title" name="menu-item[-1][menu-item-title]" value="<?php echo esc_attr( $title ); ?>">
						<input type="hidden" class="menu-item-url" name="menu-item[-1][menu-item-url]" value="<?php echo esc_attr( self::HASH ); ?>">
					</li>
				</ul>
			</div>
			<p class="button-controls wp-clearfix">
				<span class="add-to-menu">
					<input type="submit" class="button submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu', 'silvertrail' ); ?>" name="add-silvertrail-menu-item" id="submit-silvertrail-menu-item">
					<span class="spinner"></span>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Drops links to the preferences while Silvertrail is off, as the shortcode and the block do.
	 *
	 * @param array<int, object> $items
	 * @return array<int, object>
	 */
	public static function menu_items( array $items ): array {
		if ( Plugin::live() ) {
			return $items;
		}
		return array_filter( $items, static fn( object $item ): bool => ! str_ends_with( (string) ( $item->url ?? '' ), self::HASH ) );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public static function shortcode( array|string $atts, ?string $content = null ): string {
		return self::link( (string) $content );
	}

	public static function link( string $text, string $attributes = '' ): string {
		if ( ! Plugin::live() ) {
			return '';
		}
		$text = '' !== trim( $text ) ? $text : Texts::all()['reopen'];
		return sprintf( '<a href="#silvertrail" data-silvertrail-open%s>%s</a>', $attributes, esc_html( $text ) );
	}

	public static function block(): void {
		register_block_type( dirname( SILVERTRAIL_FILE ) . '/blocks/reopen-link' );
	}

	public static function button(): void {
		$mode = Settings::get( 'floating_button' );
		if ( 'never' === $mode || array() === Config::consent_ids() ) {
			return;
		}
		Templates::render(
			'reopen-button',
			array(
				'label'      => Texts::all()['reopen'],
				'appearance' => Frontend::appearance(),
				'mode'       => (string) $mode,
				'position'   => (string) Settings::get( 'floating_position' ),
				'icon'       => self::icon(),
			)
		);
	}

	/**
	 * The icon inside the floating button, as inline SVG or an image. Other markup is stripped.
	 */
	public static function icon(): string {
		/**
		 * Replaces the icon of the floating button. The button keeps its label for screen readers.
		 *
		 * @param string $icon Inline SVG or an <img>.
		 */
		$icon = (string) apply_filters( 'silvertrail_reopen_icon', self::ICON );
		return wp_kses( $icon, self::icon_html() );
	}

	/**
	 * @return array<string, array<string, bool>>
	 */
	public static function icon_html(): array {
		return array_fill_keys( self::SVG_TAGS, array_fill_keys( self::SVG_ATTRIBUTES, true ) )
			+ array( 'img' => array_fill_keys( array( 'src', 'alt', 'width', 'height', 'class' ), true ) );
	}
}
