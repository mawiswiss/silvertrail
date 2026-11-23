<?php
/**
 * Public PHP API.
 *
 * @package Silvertrail
 */

declare(strict_types=1);

use Silvertrail\Config;
use Silvertrail\Consent;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the visitor consented to a service, or to every service of a group. Reads
 * the cookie, so it only holds on requests a page cache never serves, such as form
 * posts or REST calls. Pages gate their output in the browser instead.
 */
function silvertrail_has( string $service ): bool {
	return Consent::has( $service );
}

/**
 * Call on the silvertrail_register action.
 *
 * @param array<string, mixed> $args name, description, locked, consent_api.
 */
function silvertrail_register_group( string $id, array $args ): void {
	Config::register_group( $id, $args );
}

/**
 * Call on the silvertrail_register action. For an ID that exists or names a preset, only the given fields change.
 *
 * @param array<string, mixed> $args group, name, provider, purpose, privacy_url, cookies, expiry, hosts, handles, google, consent, tag_id.
 */
function silvertrail_register_service( string $id, array $args = array() ): void {
	Config::register_service( $id, $args );
}

/**
 * @return array<string, mixed>
 */
function silvertrail_config(): array {
	return Config::get();
}
