<?php
/**
 * What a Lasso item becomes.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * What a Lasso item becomes (Tasks LM2, LM3): Affibloom's Product box block with its attributes, `[affibloom …]`, or that
 * shortcode in a Shortcode block for a Lasso block Affibloom's block cannot hold whole (deviation 159).
 */
final class AffibloomTarget {

	/** Affibloom's block. */
	public const BLOCK = 'affibloom/product-box';

	/** WordPress's Shortcode block holding `[affibloom …]`. */
	public const SHORTCODE_BLOCK = 'core/shortcode';

	/**
	 * Builds the target.
	 *
	 * @param string               $kind       BLOCK, or 'shortcode'.
	 * @param array<string, mixed> $attributes The block's attributes, or the shortcode's.
	 */
	public function __construct( public readonly string $kind, public readonly array $attributes ) {}

	/**
	 * The block's attributes.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 */
	public static function block( array $attributes ): self {
		return new self( self::BLOCK, $attributes );
	}

	/**
	 * `[affibloom …]` with these attributes.
	 *
	 * @param array<string, string> $attributes The shortcode's attributes.
	 */
	public static function shortcode( array $attributes ): self {
		return new self( 'shortcode', $attributes );
	}

	/**
	 * `[affibloom …]` in WordPress's Shortcode block (core/shortcode), for a Lasso block Affibloom's block cannot hold
	 * whole (deviation 159).
	 *
	 * @param array<string, string> $attributes The shortcode's attributes.
	 */
	public static function shortcode_block( array $attributes ): self {
		return new self( self::SHORTCODE_BLOCK, $attributes );
	}

	/**
	 * As it would be written: the block's comment (its attributes as JSON), the shortcode's text, or the shortcode in
	 * a Shortcode block as WordPress saves one.
	 */
	public function text(): string {
		if ( self::SHORTCODE_BLOCK === $this->kind ) {
			return "<!-- wp:shortcode -->\n" . self::shortcode( $this->attributes )->text() . "\n<!-- /wp:shortcode -->";
		}
		if ( self::BLOCK === $this->kind ) {
			return '<!-- wp:' . self::BLOCK . ' ' . (string) json_encode( $this->attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' /-->'; // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Domain code does not call WordPress.
		}
		$text = '';
		foreach ( $this->attributes as $name => $value ) {
			$text .= ' ' . $name . '="' . str_replace( '"', '&quot;', (string) $value ) . '"';
		}

		return '[affibloom' . $text . ']';
	}
}
