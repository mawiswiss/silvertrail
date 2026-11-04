<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

/**
 * Prints the official tag of every preset that has a valid ID, inert until consent.
 * The noscript fallbacks are left out because nothing could gate them.
 */
final class Snippets {

	public static function register(): void {
		add_action( 'wp_head', array( self::class, 'print' ), 20 );
	}

	public static function print(): void {
		$config  = Config::get();
		$presets = Presets::all();
		foreach ( $config['services'] as $id => $service ) {
			$pattern = $presets[ $id ]['tag_id']['pattern'] ?? null;
			if ( null === $pattern || ! preg_match( '/' . $pattern . '/', $service['tag_id'] ) ) {
				continue;
			}
			$attributes = Config::ungated( $id ) ? array() : array(
				'type'             => 'text/plain',
				'data-silvertrail' => $id,
			);
			foreach ( self::tags( $id, $service['tag_id'] ) as $index => $tag ) {
				$tag_attributes = $attributes + array( 'id' => "silvertrail-{$id}-{$index}" );
				if ( isset( $tag['src'] ) ) {
					wp_print_script_tag( $tag_attributes + array( 'src' => $tag['src'] ) );
				} else {
					wp_print_inline_script_tag( $tag['code'], $tag_attributes );
				}
			}
		}
	}

	/**
	 * @return list<array{src: string}|array{code: string}>
	 */
	public static function tags( string $preset, string $tag_id ): array {
		$json = (string) wp_json_encode( $tag_id );

		switch ( $preset ) {
			case 'google-tag-manager':
				return array(
					array( 'code' => "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',{$json});" ),
				);
			case 'google-analytics':
			case 'google-ads':
				return array(
					array( 'src' => 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $tag_id ) ),
					array( 'code' => "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',{$json});" ),
				);
			case 'linkedin-insight':
				return array(
					array( 'code' => "window._linkedin_partner_id={$json};window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];window._linkedin_data_partner_ids.push(window._linkedin_partner_id);(function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}var s=document.getElementsByTagName('script')[0];var b=document.createElement('script');b.type='text/javascript';b.async=true;b.src='https://snap.licdn.com/li.lms-analytics/insight.min.js';s.parentNode.insertBefore(b,s);})(window.lintrk);" ),
				);
			case 'meta-pixel':
				return array(
					array( 'code' => "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',{$json});fbq('track','PageView');" ),
				);
		}
		return array();
	}
}
