<?php
/**
 * The AI text display's texts, through Affibloom's API and Lasso's transients.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Conversion\AiTexts;

/**
 * Affibloom's AI texts through its extension API, Lasso's from the transient it cached them in
 * (`lasso_chatgpt_response_<format><md5(serialize(prompt))>`, F31 R15; read only), Task LM5. The key is Affibloom's
 * too, so the trimmed prompt is tried first, then the prompt as written.
 */
final class ApiAiTexts implements AiTexts {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * Affibloom's text, or null.
	 *
	 * @param string $prompt The prompt.
	 * @param string $format `html` or `text` ('' is `html`).
	 */
	public function affibloom( string $prompt, string $format ): ?string {
		return $this->api->ai_text( $prompt, $format );
	}

	/**
	 * Lasso's cached text, or null.
	 *
	 * @param string $prompt The prompt.
	 * @param string $format `html` or `text` ('' is `html`).
	 */
	public function lasso( string $prompt, string $format ): ?string {
		$format = 'text' === $format ? 'text' : 'html';
		foreach ( array_unique( array( trim( $prompt ), $prompt ) ) as $asked ) {
			$text = get_transient( 'lasso_chatgpt_response_' . $format . md5( serialize( $asked ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Lasso's key.
			if ( is_string( $text ) && '' !== trim( wp_strip_all_tags( $text ) ) ) {
				return $text;
			}
		}

		return null;
	}

	/**
	 * Keeps a text in Affibloom.
	 *
	 * @param string $prompt The prompt.
	 * @param string $format `html` or `text` ('' is `html`).
	 * @param string $text   The text.
	 */
	public function store( string $prompt, string $format, string $text ): void {
		$this->api->store_ai_text( $prompt, $format, $text );
	}
}
