<?php
/**
 * The parts of the WordPress 7.0 AI Client that Silvertrail uses. php-stubs/wp-cli-stubs still holds the WordPress stubs at 6.x.
 */

final class WP_AI_Client_Prompt_Builder {

	public function is_supported_for_text_generation(): bool {}

	/**
	 * @return string|WP_Error
	 */
	public function generate_text() {}
}

function wp_supports_ai(): bool {}

/**
 * @param string|null $prompt
 */
function wp_ai_client_prompt( $prompt = null ): WP_AI_Client_Prompt_Builder {}
