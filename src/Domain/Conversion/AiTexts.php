<?php
/**
 * The AI text display's texts, in Lasso and in Affibloom.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * The texts of AI text boxes (`[lasso prompt]`; F31 A9, R15; Task LM5): Affibloom's stored text, Lasso's cached one
 * (Lasso kept it in a transient), and where Affibloom keeps one. Affibloom never asks a service for a text (F30 D14).
 */
interface AiTexts {

	/**
	 * Affibloom's text for a prompt and format, or null.
	 *
	 * @param string $prompt The prompt.
	 * @param string $format `html` or `text` ('' is `html`).
	 */
	public function affibloom( string $prompt, string $format ): ?string;

	/**
	 * Lasso's cached text for a prompt and format, or null (it expired, or was never made).
	 *
	 * @param string $prompt The prompt.
	 * @param string $format `html` or `text` ('' is `html`).
	 */
	public function lasso( string $prompt, string $format ): ?string;

	/**
	 * Keeps a text in Affibloom.
	 *
	 * @param string $prompt The prompt.
	 * @param string $format `html` or `text` ('' is `html`).
	 * @param string $text   The text.
	 */
	public function store( string $prompt, string $format, string $text ): void;
}
