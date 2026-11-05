<?php
/**
 * @package Silvertrail
 */

declare(strict_types=1);

namespace Silvertrail;

/**
 * Google Consent Mode v2. The default must reach the dataLayer before any Google tag.
 */
final class ConsentMode {

	public static function register(): void {
		add_action( 'wp_head', array( self::class, 'print' ), -10 );
	}

	/**
	 * @return array<string, list<string>> Consent type => services that grant it.
	 */
	public static function map(): array {
		$map = array();
		foreach ( Config::get()['services'] as $id => $service ) {
			foreach ( $service['google'] as $type ) {
				$map[ $type ][] = $id;
			}
		}
		return $map;
	}

	public static function print(): void {
		$map = self::map();
		if ( array() === $map ) {
			return;
		}

		$defaults                     = array_fill_keys( Config::GOOGLE_TYPES, 'denied' );
		$defaults['security_storage'] = 'granted';
		$defaults['wait_for_update']  = 500;

		$code  = 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}';
		$code .= 'gtag("consent","default",' . wp_json_encode( $defaults ) . ');';
		$code .= 'gtag("set","ads_data_redaction",true);';
		if ( Config::get()['google']['url_passthrough'] ) {
			$code .= 'gtag("set","url_passthrough",true);';
		}
		$code .= sprintf(
			'(function(c,n,m,e){var x=document.cookie.match(new RegExp("(?:^|; )"+c+"=([^;]*)"));if(!x)return;try{var d=JSON.parse(decodeURIComponent(x[1]));if(!(d.n>=n))return;var u={};for(var t in m){u[t]=m[t].some(function(s){return d.c.indexOf(s)>-1||e.indexOf(s)>-1})?"granted":"denied"}gtag("consent","update",u)}catch(z){}})(%s,%d,%s,%s);',
			wp_json_encode( Config::cookie(), JSON_HEX_TAG | JSON_HEX_AMP ),
			Config::revision(),
			wp_json_encode( $map, JSON_HEX_TAG | JSON_HEX_AMP ),
			wp_json_encode( Config::essential_ids(), JSON_HEX_TAG | JSON_HEX_AMP )
		);

		wp_print_inline_script_tag( $code, array( 'id' => 'silvertrail-consent-mode' ) );
	}
}
